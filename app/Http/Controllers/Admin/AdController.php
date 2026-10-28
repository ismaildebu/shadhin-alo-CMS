<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdController extends Controller
{
    public function index(): View
    {
        $ads = Ad::with('creator')->orderBy('slot')->latest('id')->paginate(20);

        // কোন স্লটে এখন কয়টি বিজ্ঞাপন চলছে
        $liveBySlot = Ad::where('status', true)->get()->filter->isLive()->groupBy('slot')->map->count();

        return view('admin.ads.index', compact('ads', 'liveBySlot'));
    }

    public function create(): View
    {
        return view('admin.ads.create', [
            'ad' => new Ad(['status' => true, 'type' => 'image', 'slot' => 'sidebar_top']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->payload($request);

        Ad::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('ads.index')->with('success', 'বিজ্ঞাপন যোগ হয়েছে।');
    }

    public function edit(Request $request, Ad $ad): View
    {
        $this->authorizeAd($request, $ad);

        return view('admin.ads.edit', compact('ad'));
    }

    public function update(Request $request, Ad $ad): RedirectResponse
    {
        $this->authorizeAd($request, $ad);

        $oldImage = $ad->image;
        $ad->update($this->payload($request, $ad));

        if ($oldImage && $oldImage !== $ad->image) {
            $this->deleteImage($oldImage);
        }

        return redirect()->route('ads.index')->with('success', 'বিজ্ঞাপন আপডেট হয়েছে।');
    }

    public function destroy(Request $request, Ad $ad): RedirectResponse
    {
        $this->authorizeAd($request, $ad);

        $image = $ad->image;
        $ad->delete();
        $this->deleteImage($image);

        return redirect()->route('ads.index')->with('success', 'বিজ্ঞাপন মোছা হয়েছে।');
    }

    /** কোডের বিজ্ঞাপনে যেকোনো স্ক্রিপ্ট বসানো যায়, তাই সেটি শুধু অ্যাডমিন বদলাতে পারবেন। */
    private function authorizeAd(Request $request, Ad $ad): void
    {
        abort_unless($request->user()->isAdmin() || $ad->type === 'image', 403, 'এই বিজ্ঞাপন শুধু অ্যাডমিন বদলাতে পারবেন।');
    }

    private function payload(Request $request, ?Ad $ad = null): array
    {
        $isAdmin = $request->user()->isAdmin();

        $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'slot' => ['required', Rule::in(array_keys(Ad::SLOTS))],
            'type' => ['required', Rule::in($isAdmin ? array_keys(Ad::TYPES) : ['image'])],
            'link_url' => ['nullable', 'url:http,https', 'max:500'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'code' => ['nullable', 'string', 'max:20000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => array_filter(['nullable', 'date', $request->filled('starts_at') ? 'after:starts_at' : null]),
            'status' => ['nullable'],
        ], [
            'title.required' => 'বিজ্ঞাপনের একটি নাম দিন।',
            'slot.required' => 'বিজ্ঞাপনের জায়গা বেছে নিন।',
            'slot.in' => 'বিজ্ঞাপনের জায়গা সঠিক নয়।',
            'type.in' => 'এই ধরনের বিজ্ঞাপন যোগ করার অনুমতি আপনার নেই।',
            'link_url.url' => 'সঠিক লিংক দিন (http:// বা https:// দিয়ে শুরু)।',
            'image.image' => 'শুধু ছবি আপলোড করা যাবে।',
            'image.mimes' => 'শুধু jpg, png, webp বা gif ছবি চলবে।',
            'image.max' => 'ছবি সর্বোচ্চ ২ MB হতে পারবে।',
            'image.uploaded' => 'ছবি আপলোড ব্যর্থ হয়েছে (ফাইল খুব বড় হতে পারে)।',
            'ends_at.after' => 'শেষের সময় শুরুর সময়ের পরে হতে হবে।',
        ]);

        $type = $request->input('type');
        $file = $request->file('image');

        if ($type === 'image' && ! $file && ! ($ad && $ad->type === 'image' && $ad->image)) {
            throw ValidationException::withMessages(['image' => 'বিজ্ঞাপনের ছবি দিন।']);
        }

        if ($type === 'code' && trim((string) $request->input('code')) === '') {
            throw ValidationException::withMessages(['code' => 'বিজ্ঞাপনের কোড দিন।']);
        }

        $image = null;

        if ($type === 'image') {
            $image = $file ? $this->storeImage($file) : $ad?->image;
        }

        return [
            'title' => trim((string) $request->input('title')),
            'slot' => $request->input('slot'),
            'type' => $type,
            'image' => $image,
            'link_url' => $type === 'image' ? ($request->input('link_url') ?: null) : null,
            'code' => $type === 'code' ? (string) $request->input('code') : null,
            'starts_at' => $this->date($request->input('starts_at')),
            'ends_at' => $this->date($request->input('ends_at')),
            'status' => $request->boolean('status'),
        ];
    }

    private function date(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value, config('app.timezone')) : null;
    }

    private function storeImage(UploadedFile $file): string
    {
        $name = Str::uuid().'.'.$file->extension();
        $file->move(public_path(Ad::DIR), $name);

        return $name;
    }

    private function deleteImage(?string $name): void
    {
        if ($name) {
            File::delete(public_path(Ad::DIR.'/'.basename($name)));
        }
    }
}