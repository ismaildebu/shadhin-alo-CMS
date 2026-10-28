<?php

namespace App\Services;

use App\Models\Video;
use App\Models\VideoChannel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class YoutubeChannelSync
{
    private const USER_AGENT = 'Mozilla/5.0 (compatible; ShadhinAloBot/1.0)';

    /** চ্যানেলের লিংক (channel, @handle, c, user) বা সরাসরি আইডি (UC...) থেকে চ্যানেল আইডি বের করে। */
    public function resolveChannelId(string $input): ?string
    {
        $input = trim($input);

        if (preg_match('/^UC[A-Za-z0-9_-]{22}$/', $input)) {
            return $input;
        }

        if (preg_match('~youtube\.com/channel/(UC[A-Za-z0-9_-]{22})~i', $input, $m)) {
            return $m[1];
        }

        // @handle, /c/নাম, /user/নাম: আইডি খুঁজতে চ্যানেলের পেজ পড়তে হয়।
        $url = $this->channelPageUrl($input);

        if (! $url) {
            return null;
        }

        try {
            $html = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept-Language' => 'en',
                    'Cookie' => 'SOCS=CAI; CONSENT=YES+1',
                ])
                ->get($url)
                ->body();
        } catch (Throwable) {
            return null;
        }

        $patterns = [
            '~<link rel="canonical" href="https://www\.youtube\.com/channel/(UC[A-Za-z0-9_-]{22})"~',
            '~<meta itemprop="(?:identifier|channelId)" content="(UC[A-Za-z0-9_-]{22})"~',
            '~"externalId":"(UC[A-Za-z0-9_-]{22})"~',
            '~"channelId":"(UC[A-Za-z0-9_-]{22})"~',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    /**
     * ইউটিউবের চ্যানেল ফিড (সর্বশেষ ১৫টি ভিডিও)।
     *
     * @return array{name: string, videos: list<array{id: string, title: string, description: ?string, published_at: Carbon}>}
     */
    public function fetchFeed(string $channelId): array
    {
        $response = Http::timeout(20)
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->get('https://www.youtube.com/feeds/videos.xml', ['channel_id' => $channelId]);

        if (! $response->successful()) {
            throw new RuntimeException('ইউটিউব ফিড পাওয়া যায়নি (HTTP '.$response->status().')। চ্যানেল আইডি ঠিক আছে কিনা দেখুন।');
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body());
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new RuntimeException('ইউটিউবের ফিড পড়া যায়নি।');
        }

        $videos = [];

        foreach ($xml->entry as $entry) {
            $id = (string) $entry->children('yt', true)->videoId;

            if (! preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
                continue;
            }

            $description = trim((string) $entry->children('media', true)->group->description);

            $videos[] = [
                'id' => $id,
                'title' => trim((string) $entry->title),
                'description' => $description !== '' ? $description : null,
                'published_at' => Carbon::parse((string) $entry->published)->setTimezone(config('app.timezone')),
            ];
        }

        return ['name' => trim((string) $xml->title), 'videos' => $videos];
    }

    /** নতুন ভিডিও আনে; আগে থেকে থাকা (ট্র্যাশে থাকা সহ) ভিডিও বাদ দেয়। ফেরত দেয় কয়টি নতুন এসেছে। */
    public function sync(VideoChannel $channel, ?array $feed = null): int
    {
        try {
            $feed ??= $this->fetchFeed($channel->channel_id);
        } catch (Throwable $e) {
            $channel->update([
                'last_error' => Str::limit($e->getMessage(), 280, ''),
                'last_synced_at' => now(),
            ]);

            return 0;
        }

        $known = Video::withTrashed()
            ->whereIn('youtube_id', collect($feed['videos'])->pluck('id'))
            ->pluck('youtube_id')
            ->flip();

        $added = 0;

        // পুরোনো আগে, যাতে আইডির ক্রম সময়ের ক্রম মেনে চলে।
        foreach (array_reverse($feed['videos']) as $item) {
            if ($known->has($item['id'])) {
                continue;
            }

            Video::create([
                'title' => Str::limit($item['title'], 200, ''),
                'youtube_id' => $item['id'],
                'description' => $item['description'] ? Str::limit($item['description'], 2000, '') : null,
                'status' => $channel->auto_publish,
                'published_at' => $item['published_at'],
                'created_by' => $channel->created_by,
                'video_channel_id' => $channel->id,
            ]);

            $added++;
        }

        $channel->update(['last_synced_at' => now(), 'last_error' => null]);

        return $added;
    }

    private function channelPageUrl(string $input): ?string
    {
        if (preg_match('/^@[\p{L}\p{N}._-]{3,60}$/u', $input)) {
            return 'https://www.youtube.com/'.$input;
        }

        $parts = parse_url(str_contains($input, '://') ? $input : 'https://'.$input);
        $host = strtolower($parts['host'] ?? '');

        // শুধু ইউটিউবের ঠিকানা: অন্য কোনো সাইটে অনুরোধ যাবে না।
        if (! in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            return null;
        }

        $path = $parts['path'] ?? '';

        return ($path !== '' && $path !== '/') ? 'https://www.youtube.com'.$path : null;
    }
}