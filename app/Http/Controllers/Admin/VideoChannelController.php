<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VideoChannel;
use App\Services\YoutubeChannelSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class VideoChannelController extends Controller
{
    public function index(): View
    {
        return view('admin.videos.channels', [
            'channels' => VideoChannel::withCount('videos')->latest('id')->get(),
        ]);
    }

    public function store(Request $request, YoutubeChannelSync $youtube): RedirectResponse
    {
        $request->validate([
            'channel' => ['required', 'string', 'max:300'],
        ], [
            'channel.required' => 'চ্যানেলের লিংক বা আইডি দিন।',
        ]);

        $channelId = $youtube->resolveChannelId((string) $request->input('channel'));

        if (! $channelId) {
            throw ValidationException::withMessages([
                'channel' => 'চ্যানেল চেনা যায়নি। youtube.com/@নাম বা youtube.com/channel/UC... লিংক দিন, অথবা সরাসরি চ্যানেল আইডি (UC দিয়ে শুরু) দিন।',
            ]);
        }

        if (VideoChannel::where('channel_id', $channelId)->exists()) {
            throw ValidationException::withMessages(['channel' => 'এই চ্যানেলটি আগেই যোগ করা আছে।']);
        }

        try {
            $feed = $youtube->fetchFeed($channelId);
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['channel' => $e->getMessage()]);
        }

        $channel = VideoChannel::create([
            'channel_id' => $channelId,
            'name' => Str::limit($feed['name'] ?: $channelId, 190, ''),
            'url' => 'https://www.youtube.com/channel/'.$channelId,
            'auto_publish' => $request->boolean('auto_publish'),
            'created_by' => $request->user()->id,
        ]);

        $added = $youtube->sync($channel, $feed);

        return back()->with('success', "চ্যানেল যোগ হয়েছে, {$added}টি ভিডিও আনা হয়েছে।");
    }

    public function sync(VideoChannel $channel, YoutubeChannelSync $youtube): RedirectResponse
    {
        $added = $youtube->sync($channel);

        return $channel->last_error
            ? back()->withErrors(['channel' => 'সিঙ্ক ব্যর্থ হয়েছে: '.$channel->last_error])
            : back()->with('success', "{$added}টি নতুন ভিডিও এসেছে।");
    }

    public function syncAll(YoutubeChannelSync $youtube): RedirectResponse
    {
        $added = 0;

        foreach (VideoChannel::all() as $channel) {
            $added += $youtube->sync($channel);
        }

        return back()->with('success', "মোট {$added}টি নতুন ভিডিও এসেছে।");
    }

    public function destroy(VideoChannel $channel): RedirectResponse
    {
        $channel->delete();

        return back()->with('success', 'চ্যানেলটি সরানো হয়েছে। আগে আনা ভিডিওগুলো গ্যালারিতে থেকে যাবে।');
    }
}