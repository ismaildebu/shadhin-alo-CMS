<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VideoController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        $videos = Video::with('creator')
            ->when($term !== '', fn ($q) => $q->where('title', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.videos.index', compact('videos', 'term'));
    }

    public function create(): View
    {
        return view('admin.videos.create', ['video' => new Video(['status' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Video::create($data + [
            'published_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('videos.index')->with('success', 'ভিডিও যোগ হয়েছে।');
    }

    public function edit(Video $video): View
    {
        return view('admin.videos.edit', compact('video'));
    }

    public function update(Request $request, Video $video): RedirectResponse
    {
        $video->update($this->validated($request, $video));

        return redirect()->route('videos.index')->with('success', 'ভিডিও আপডেট হয়েছে।');
    }

    public function destroy(Video $video): RedirectResponse
    {
        $video->delete();

        return redirect()->route('videos.index')->with('success', 'ভিডিও মোছা হয়েছে।');
    }

    /** @return array{title: string, youtube_id: string, description: ?string, status: bool} */
    private function validated(Request $request, ?Video $video = null): array
    {
        $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'url' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable'],
        ], [
            'title.required' => 'ভিডিওর শিরোনাম লিখুন।',
            'title.max' => 'শিরোনাম সর্বোচ্চ ২০০ অক্ষরের হতে পারবে।',
            'url.required' => 'ইউটিউব লিংক দিন।',
            'description.max' => 'বিবরণ সর্বোচ্চ ২০০০ অক্ষরের হতে পারবে।',
        ]);

        $id = Video::extractYoutubeId((string) $request->input('url'));

        if (! $id) {
            throw ValidationException::withMessages([
                'url' => 'সঠিক ইউটিউব লিংক দিন (যেমন https://www.youtube.com/watch?v=...)।',
            ]);
        }

        $duplicate = Video::where('youtube_id', $id)
            ->when($video, fn ($q) => $q->where('id', '!=', $video->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['url' => 'এই ভিডিওটি আগেই যোগ করা আছে।']);
        }

        $description = trim((string) $request->input('description', ''));

        return [
            'title' => trim((string) $request->input('title')),
            'youtube_id' => $id,
            'description' => $description !== '' ? $description : null,
            'status' => $request->boolean('status'),
        ];
    }
}