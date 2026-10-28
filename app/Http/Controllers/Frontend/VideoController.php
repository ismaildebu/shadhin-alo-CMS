<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\View\View;

class VideoController extends Controller
{
    public function index(): View
    {
        $videos = Video::published()
            ->latest('published_at')
            ->latest('id')
            ->paginate(12);

        return view('frontend.videos', compact('videos'));
    }

    public function show(int $id): View
    {
        // লুকানো বা এখনো প্রকাশের সময় না হওয়া ভিডিও পাবলিকের জন্য 404।
        $video = Video::published()->findOrFail($id);

        $related = Video::published()
            ->where('id', '!=', $video->id)
            ->latest('published_at')
            ->latest('id')
            ->take(6)
            ->get();

        return view('frontend.video', compact('video', 'related'));
    }
}