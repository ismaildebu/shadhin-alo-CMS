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
        $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'files.required' => 'অন্তত একটি ছবি বেছে নিন।',
            'files.max' => 'একবারে সর্বোচ্চ ১০টি ছবি আপলোড করা যাবে।',
            'files.*.image' => 'শুধু ছবি আপলোড করা যাবে।',
            'files.*.mimes' => 'শুধু jpg, png বা webp ছবি আপলোড করা যাবে।',
            'files.*.max' => 'প্রতিটি ছবি সর্বোচ্চ ২ MB হতে পারবে।',
            'files.*.uploaded' => 'ছবি আপলোড ব্যর্থ হয়েছে (ফাইল খুব বড় হতে পারে)।',
        ]);

        $files = $request->file('files');

        foreach ($files as $file) {
            Media::storeUpload($file, $request->user());
        }

        return back()->with('success', count($files).'টি ছবি গ্যালারিতে যোগ হয়েছে।');
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
            'upload' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'upload.required' => 'ছবি পাওয়া যায়নি।',
            'upload.image' => 'শুধু ছবি আপলোড করা যাবে।',
            'upload.mimes' => 'শুধু jpg, png বা webp ছবি আপলোড করা যাবে।',
            'upload.max' => 'ছবি সর্বোচ্চ ২ MB হতে পারবে।',
            'upload.uploaded' => 'ছবি আপলোড ব্যর্থ হয়েছে (ফাইল খুব বড় হতে পারে)।',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'uploaded' => 0,
                'error' => ['message' => $validator->errors()->first()],
            ]);
        }

        $media = Media::storeUpload($request->file('upload'), $request->user());

        return response()->json([
            'uploaded' => 1,
            'fileName' => $media->filename,
            'url' => '/'.Media::DIR.'/'.$media->filename,
        ]);
    }

    public function destroy(Request $request, Media $media): RedirectResponse
    {
        abort_unless($request->user()->isEditorOrAdmin(), 403);

        // ব্যবহৃত ছবি মুছলে ওই সংবাদগুলোর ছবি ভেঙে যেত।
        $uses = $this->usageCount($media->filename);

        if ($uses > 0) {
            return back()->withErrors(['media' => "ছবিটি {$uses}টি সংবাদে ব্যবহৃত হচ্ছে, তাই মোছা যাবে না।"]);
        }

        File::delete(public_path(Media::DIR.'/'.basename($media->filename)));
        $media->delete();

        return back()->with('success', 'ছবিটি গ্যালারি থেকে মোছা হয়েছে।');
    }

    /** ছবিটি কয়টি সংবাদে আছে: মূল ছবি হিসেবে অথবা লেখার ভেতরে (ফাইলের নাম UUID, তাই খোঁজা নিরাপদ)। */
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