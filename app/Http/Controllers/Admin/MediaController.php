<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\News;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use RuntimeException;

class MediaController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        $media = $this->search($term)
            ->with('uploader')
            ->paginate(24)
            ->withQueryString();

        // কোন ছবি কয়টি সংবাদে ব্যবহৃত (মূল ছবি বা লেখার ভেতরে; ট্র্যাশসহ)
        $usage = $media->getCollection()
            ->mapWithKeys(fn (Media $m) => [$m->filename => $this->usageCount($m->filename)]);

        return view('admin.media.index', [
            'media' => $media,
            'usage' => $usage,
            'term' => $term,
            'total' => Media::count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // আপলোডের সীমা ২০ MB; ছবি সংরক্ষণের সময় নিজে থেকে ১২০০×৮০০ ও ১ MB-এর নিচে নামানো হয়।
        $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
        ], [
            'files.required' => 'অন্তত একটি ছবি বেছে নিন।',
            'files.max' => 'একবারে সর্বোচ্চ ১০টি ছবি আপলোড করা যাবে।',
            'files.*.image' => 'শুধু ছবি আপলোড করা যাবে।',
            'files.*.mimes' => 'শুধু jpg, png বা webp ছবি আপলোড করা যাবে।',
            'files.*.max' => 'প্রতিটি ছবি সর্বোচ্চ ২০ MB হতে পারবে।',
            'files.*.uploaded' => 'ছবি আপলোড ব্যর্থ হয়েছে (ফাইল খুব বড় হতে পারে)।',
        ]);

        $files = $request->file('files');
        $saved = 0;

        try {
            foreach ($files as $file) {
                Media::storeUpload($file, $request->user());
                $saved++;
            }
        } catch (RuntimeException $e) {
            $redirect = back()->withErrors(['files' => $e->getMessage()]);

            return $saved > 0
                ? $redirect->with('success', $saved.'টি ছবি গ্যালারিতে যোগ হয়েছে।')
                : $redirect;
        }

        return back()->with('success', $saved.'টি ছবি গ্যালারিতে যোগ হয়েছে।');
    }

    /** সংবাদের ফর্মের "গ্যালারি থেকে বাছুন" পপআপের জন্য JSON। */
    public function list(Request $request): JsonResponse
    {
        $page = $this->search(trim((string) $request->query('q', '')))->paginate(18);

        return response()->json([
            'items' => $page->getCollection()->map(fn (Media $m) => [
                'filename' => $m->filename,
                'url' => $m->url,
                'path' => '/'.Media::DIR.'/'.$m->filename,
                'name' => $m->original_name ?: $m->filename,
            ])->values(),
            'has_more' => $page->hasMorePages(),
        ]);
    }

    /**
     * এডিটরের (CKEditor) আপলোড: বোতাম, পেস্ট ও ড্র্যাগ করা ছবি। ফাইল গ্যালারিতেও জমা হয়।
     * উত্তরের ধরন CKEditor এর আপলোড অ্যাডাপ্টার যেভাবে চায়: uploaded + url অথবা uploaded=0 + error.
     */
    public function editorUpload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'upload' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
        ], [
            'upload.required' => 'ছবি পাওয়া যায়নি।',
            'upload.image' => 'শুধু ছবি আপলোড করা যাবে।',
            'upload.mimes' => 'শুধু jpg, png বা webp ছবি আপলোড করা যাবে।',
            'upload.max' => 'ছবি সর্বোচ্চ ২০ MB হতে পারবে।',
            'upload.uploaded' => 'ছবি আপলোড ব্যর্থ হয়েছে (ফাইল খুব বড় হতে পারে)।',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'uploaded' => 0,
                'error' => ['message' => $validator->errors()->first()],
            ]);
        }

        try {
            $media = Media::storeUpload($request->file('upload'), $request->user());
        } catch (RuntimeException $e) {
            return response()->json([
                'uploaded' => 0,
                'error' => ['message' => $e->getMessage()],
            ]);
        }

        return response()->json([
            'uploaded' => 1,
            'fileName' => $media->filename,
            'url' => '/'.Media::DIR.'/'.$media->filename,
        ]);
    }

    /** একটি ছবি মোছে। ছবি সংবাদে ব্যবহৃত থাকলেও মোছা যায়। */
    public function destroy(Request $request, Media $media): RedirectResponse
    {
        abort_unless($request->user()->isEditorOrAdmin(), 403);

        $cleared = $this->deleteMedia($media);

        $message = 'ছবিটি গ্যালারি থেকে মোছা হয়েছে।';

        if ($cleared > 0) {
            $message .= " {$cleared}টি সংবাদের প্রধান ছবি ফাঁকা হয়েছে।";
        }

        return back()->with('success', $message);
    }

    /** একসাথে একাধিক ছবি মোছে। ব্যবহৃত ছবিও মোছা যায়। */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isEditorOrAdmin(), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
        ], [
            'ids.required' => 'মুছে ফেলার জন্য অন্তত একটি ছবি বাছুন।',
            'ids.min' => 'মুছে ফেলার জন্য অন্তত একটি ছবি বাছুন।',
            'ids.max' => 'একবারে সর্বোচ্চ ১০০টি ছবি মোছা যাবে।',
            'ids.*.integer' => 'ছবির তথ্য সঠিক নয়।',
        ]);

        $deleted = 0;
        $cleared = 0;

        foreach (Media::whereIn('id', $data['ids'])->get() as $media) {
            $cleared += $this->deleteMedia($media);
            $deleted++;
        }

        $message = "{$deleted}টি ছবি গ্যালারি থেকে মোছা হয়েছে।";

        if ($cleared > 0) {
            $message .= " {$cleared}টি সংবাদের প্রধান ছবি ফাঁকা হয়েছে।";
        }

        return back()->with('success', $message);
    }

    /**
     * ছবির ফাইল ও গ্যালারির রেকর্ড মোছে।
     * যেসব সংবাদের প্রধান ছবি এটি, সেগুলোর ছবির ঘর ফাঁকা করা হয় (ভাঙা ছবি এড়াতে)।
     * সংবাদের লেখার ভেতরের ছবি বদলানো হয় না।
     *
     * @return int কয়টি সংবাদের প্রধান ছবি ফাঁকা হলো
     */
    private function deleteMedia(Media $media): int
    {
        $filename = $media->filename;

        $cleared = News::withTrashed()
            ->where('image', $filename)
            ->update(['image' => null]);

        File::delete(public_path(Media::DIR.'/'.basename($filename)));
        $media->delete();

        return $cleared;
    }

    /** ছবিটি কয়টি সংবাদে আছে: মূল ছবি হিসেবে অথবা লেখার ভেতরে। */
    private function usageCount(string $filename): int
    {
        return News::withTrashed()
            ->where(function ($q) use ($filename) {
                $q->where('image', $filename)
                    ->orWhere('description', 'like', '%'.addcslashes($filename, '%_\\').'%');
            })
            ->count();
    }

    private function search(string $term): Builder
    {
        return Media::query()
            ->when($term !== '', fn ($q) => $q->where('original_name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->latest()
            ->orderByDesc('id');
    }
}