<?php

namespace App\Support;

use App\Models\Category;
use App\Models\FacebookPost;
use App\Models\News;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * ফেসবুক পেজে খবর পোস্ট করা: এডিটরের তৈরি ফটোকার্ডটি ছবি হিসেবে পোস্ট, তার নিচে
 * কমেন্টে খবরের লিংক।
 *
 * টোকেন ও পেজ আইডি কেবল .env থেকে আসে (config/services.php)। সেটিংস টেবিল এই রিপোতে
 * commit হয়, তাই সেখানে গোপন মান বসানোর জো নেই।
 *
 * Graph API-এর দুইটি ডাক: /photos (ক্যাপশনসহ, তাই পেজের টাইমলাইনে পোস্টটি তৈরি হয়)
 * ও সেই পোস্টের /comments। ছবি একবার বসে গেলে আর ফেরত নেওয়া যায় না, তাই ছবির আইডি
 * সঙ্গে সঙ্গে লাইনে রাখা হয়: পরের চেষ্টা কেবল কমেন্টটি বসায়, ডুপ্লিকেট পোস্ট হয় না।
 */
final class FacebookShare
{
    /** ব্যর্থ পোস্ট আপনি কতবার চেষ্টা করবে (অ্যাডমিনের "আবার পাঠান" এই সীমা মানে না)। */
    public const MAX_ATTEMPTS = 5;

    /** ক্যাপশনে সারাংশের সর্বোচ্চ দৈর্ঘ্য। */
    public const SUMMARY_CHARS = 300;

    /** চালু নিশানা + পেজ আইডি + টোকেন — তিনটিই ঠিক থাকলে পোস্ট চলতে পারে। */
    public static function ready(): bool
    {
        return Settings::get('fb_enabled') === '1'
            && self::pageId() !== ''
            && self::token() !== '';
    }

    /**
     * খবর প্রকাশ পাওয়ামাত্র লাইনে তোলা। লাইন আগে থেকে থাকলে নতুন করে পোস্ট হয় না,
     * তাই একই খবর বারবার প্রকাশ করলে ফেসবুকে ডুপ্লিকেট যায় না।
     *
     * সুইচ বন্ধ থাকলে লাইনই তৈরি হয় না — বন্ধ সময়ে প্রকাশ হওয়া কয়েক হাজার খবর পরে
     * সুইচ চালু করলে একসঙ্গে ফেসবুকে উঠে বসে।
     */
    public static function enqueue(News $news): void
    {
        if (Settings::get('fb_enabled') !== '1') {
            return;
        }

        if (FacebookPost::where('news_id', $news->id)->exists()) {
            self::sync($news);

            return;
        }

        FacebookPost::create([
            'news_id' => $news->id,
            'status' => self::cardPath($news) === null ? FacebookPost::NEEDS_CARD : FacebookPost::QUEUED,
        ]);
    }

    /**
     * কার্ড তৈরি বা মোছার পর লাইনের অবস্থা নতুন করে হিসাব করা।
     */
    public static function sync(News $news): void
    {
        $post = FacebookPost::where('news_id', $news->id)->first();

        if ($post === null || $post->status === FacebookPost::POSTED) {
            return;
        }

        $values = self::cardPath($news) === null
            ? ['status' => FacebookPost::NEEDS_CARD]
            : ['status' => FacebookPost::QUEUED, 'error' => '', 'attempts' => 0];

        $post->forceFill($values)->save();
    }

    /**
     * একটি লাইন ফেসবুকে পাঠানো হয় — অটো কমান্ড আর অ্যাডমিনের "পাঠান" বোতাম, দুই জায়গাতেই এটিই চলে।
     */
    public static function send(FacebookPost $post, ?User $actor = null): FacebookPost
    {
        $news = $post->news;

        if ($news === null) {
            return self::fail($post, 'খবরটি আর নেই।');
        }

        if (! self::ready()) {
            return self::fail($post, 'ফেসবুক পোস্ট বন্ধ আছে, বা .env-এ পেজ আইডি ও টোকেন বসানো নেই।');
        }

        if ($news->status !== News::STATUS_PUBLISHED) {
            return self::fail($post, 'শুধু প্রকাশিত খবর ফেসবুকে পাঠানো যায়।');
        }

        $message = self::message($news);
        $comment = self::comment($news);

        if ($post->post_id === null) {
            $card = self::cardPath($news);

            if ($card === null) {
                $post->forceFill([
                    'status' => FacebookPost::NEEDS_CARD,
                    'error' => 'ফটোকার্ডের ছবিটি পাওয়া যায়নি — ফটোকার্ড কেন্দ্র থেকে কার্ডটি বানান।',
                ])->save();

                return $post;
            }

            $photo = self::sendPhoto($card, $message);

            if (is_string($photo)) {
                return self::fail($post, $photo);
            }

            // ছবি ফেসবুকে চলে গেছে; এরপর কিছু থেমে গেলেও ডুপ্লিকেট ছবি যাবে না।
            $post->forceFill(['photo_id' => $photo[0], 'post_id' => $photo[1]])->save();
        }

        $linkError = self::sendComment((string) $post->post_id, $comment);

        $post->forceFill([
            'status' => FacebookPost::POSTED,
            'message' => $message,
            'comment' => $comment,
            'error' => $linkError === null ? '' : 'কার্ডের পোস্ট হয়েছে, কিন্তু লিংকের কমেন্ট বসানো যায়নি: '.$linkError,
            'posted_at' => now(),
            'approved_by' => $actor?->id ?? $post->approved_by,
        ])->save();

        return $post;
    }

    /**
     * "আবার পাঠান": আগের পোস্টের হিসাব মুছে খবরটি নতুন করে ফেসবুকে তোলা।
     */
    public static function resend(FacebookPost $post, ?User $actor = null): FacebookPost
    {
        $post->forceFill([
            'status' => FacebookPost::QUEUED,
            'photo_id' => null,
            'post_id' => null,
            'posted_at' => null,
            'error' => '',
            'attempts' => 0,
        ])->save();

        return self::send($post, $actor);
    }

    /**
     * টিক করা ক্যাটাগরির অপেক্ষমাণ লাইন পাঠানো হয়; প্রতি মিনিটের কমান্ড এটিই ডাকে।
     *
     * @return array{posted: int, failed: int, waiting: int}
     */
    public static function sendQueued(int $limit = 10): array
    {
        $waiting = FacebookPost::whereIn('status', [FacebookPost::NEEDS_CARD, FacebookPost::QUEUED])->count();

        if (! self::ready()) {
            return ['posted' => 0, 'failed' => 0, 'waiting' => $waiting];
        }

        $rows = FacebookPost::query()
            ->whereIn('status', [FacebookPost::QUEUED, FacebookPost::FAILED])
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->whereHas('news', function ($query) {
                $query->where('status', News::STATUS_PUBLISHED)
                    ->whereHas('category', fn ($category) => $category->where('fb_auto', true));
            })
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        $posted = 0;
        $failed = 0;

        foreach ($rows as $row) {
            try {
                $result = self::send($row);
            } catch (Throwable $e) {
                $result = self::fail($row, 'ফেসবুকের সঙ্গে যোগাযোগ হয়নি: '.$e->getMessage());
            }

            $result->status === FacebookPost::POSTED ? $posted++ : $failed++;
        }

        return ['posted' => $posted, 'failed' => $failed, 'waiting' => $waiting];
    }

    /** টোকেন ও পেজ ঠিক আছে কি না — অ্যাডমিনের "যাচাই করুন" বোতামের উত্তর। */
    public static function verify(): array
    {
        if (self::pageId() === '' || self::token() === '') {
            return ['ok' => false, 'message' => '.env-এ FACEBOOK_PAGE_ID ও FACEBOOK_PAGE_TOKEN বসাননি।'];
        }

        $body = self::http()->get(self::graph().'/'.self::pageId(), [
            'fields' => 'id,name',
            'access_token' => self::token(),
        ])->json() ?? [];

        if (isset($body['error'])) {
            return ['ok' => false, 'message' => self::errorText($body['error'])];
        }

        return [
            'ok' => true,
            'message' => 'পেজ: '.($body['name'] ?? '').' | পোস্ট চালু: '
                .(Settings::get('fb_enabled') === '1' ? 'হ্যাঁ' : 'না')
                .' | অটো পোস্টের ক্যাটাগরি: '.BanglaTime::digits(Category::where('fb_auto', true)->count()).'টি',
        ];
    }

    /** কার্ডের পোস্টের ক্যাপশন (টেমপ্লেটে জায়গামতো মান বসে)। */
    public static function message(News $news): string
    {
        return self::fill(Settings::get('fb_message'), Settings::DEFAULTS['fb_message'], $news);
    }

    /** পোস্টের নিচের কমেন্ট — সাধারণত খবরের লিংক। */
    public static function comment(News $news): string
    {
        return self::fill(Settings::get('fb_comment'), Settings::DEFAULTS['fb_comment'], $news);
    }

    private static function fill(string $template, string $fallback, News $news): string
    {
        $replacements = [
            '{title}' => (string) $news->title,
            '{category}' => (string) ($news->category?->name ?? ''),
            '{date}' => BanglaTime::date($news->display_date),
            '{summary}' => NewsCard::plain($news->summary ?: $news->description, self::SUMMARY_CHARS),
            '{url}' => route('frontend.news.show', $news->slug),
        ];

        return trim(strtr(trim($template) === '' ? $fallback : $template, $replacements));
    }

    /** @return array{0: ?string, 1: string}|string ছবি ও পোস্টের আইডি, না হয় ত্রুটির লেখা */
    private static function sendPhoto(string $card, string $caption): array|string
    {
        $contents = file_get_contents($card);

        if ($contents === false || $contents === '') {
            return 'ফটোকার্ডের ফাইলটি পড়া যায়নি।';
        }

        $body = self::http()->attach('source', $contents, basename($card))
            ->post(self::graph().'/'.self::pageId().'/photos', [
                'access_token' => self::token(),
                'caption' => $caption,
                'published' => 'true',
            ])->json() ?? [];

        if (isset($body['error'])) {
            return self::errorText($body['error']);
        }

        $photoId = isset($body['id']) ? (string) $body['id'] : null;
        $postId = isset($body['post_id']) ? (string) $body['post_id'] : $photoId;

        return $postId === null ? 'ফেসবুক ছবি গ্রহণ করেনি (কোনো আইডি ফেরত দেয়নি)।' : [$photoId, $postId];
    }

    /** কমেন্ট বসলে null, না হলে ত্রুটির লেখা। */
    private static function sendComment(string $postId, string $comment): ?string
    {
        if (trim($comment) === '') {
            return null;
        }

        $body = self::http()->post(self::graph().'/'.$postId.'/comments', [
            'access_token' => self::token(),
            'message' => $comment,
        ])->json() ?? [];

        return isset($body['error']) ? self::errorText($body['error']) : null;
    }

    private static function fail(FacebookPost $post, string $reason): FacebookPost
    {
        $post->forceFill([
            'status' => FacebookPost::FAILED,
            'error' => $reason,
            'attempts' => $post->attempts + 1,
        ])->save();

        return $post;
    }

    private static function errorText(array $error): string
    {
        return mb_substr((string) ($error['message'] ?? 'অজানা ত্রুটি'), 0, 480);
    }

    private static function http(): PendingRequest
    {
        return Http::timeout(45)->acceptJson();
    }

    private static function cardPath(News $news): ?string
    {
        if (empty($news->card_image)) {
            return null;
        }

        $path = public_path(NewsCard::DIR.'/'.basename((string) $news->card_image));

        return is_readable($path) ? $path : null;
    }

    private static function graph(): string
    {
        return 'https://graph.facebook.com/'.trim((string) config('services.facebook.graph_version', 'v23.0'), '/');
    }

    private static function pageId(): string
    {
        return trim((string) config('services.facebook.page_id', ''));
    }

    private static function token(): string
    {
        return trim((string) config('services.facebook.page_token', ''));
    }
}
