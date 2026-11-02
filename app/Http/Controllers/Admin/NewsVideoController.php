<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Support\NewsVideo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class NewsVideoController extends Controller
{
    public function store(News $news): RedirectResponse
    {
        Gate::authorize('view', $news);

        // স্লাইড ক্যাপচার ও রেন্ডারে এক মিনিটের বেশি লাগতে পারে।
        @set_time_limit(600);

        try {
            $video = NewsVideo::make($news);
        } catch (Throwable $e) {
            return back()->withErrors(['video' => 'ভিডিও তৈরি করা যায়নি: '.$e->getMessage()]);
        }

        $size = @filesize($video['path']);

        return back()->with('success', sprintf(
            'ভিডিও তৈরি হয়েছে — %d সেকেন্ড, %dটি স্লাইড%s। লিস্টে “ভিডিও দেখুন” লিংক থেকে খুলুন।',
            $video['duration'],
            $video['slides'],
            $size ? ' ('.number_format($size / 1048576, 1).' MB)' : ''
        ));
    }

    public function show(Request $request, News $news): BinaryFileResponse
    {
        Gate::authorize('view', $news);

        $path = NewsVideo::path($news);

        abort_unless(is_file($path), 404);

        if ($request->boolean('download')) {
            return response()->download($path, basename($path), ['Content-Type' => 'video/mp4']);
        }

        return response()->file($path, [
            'Content-Type' => 'video/mp4',
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }
}
