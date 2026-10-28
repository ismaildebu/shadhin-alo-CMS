<?php

namespace App\Support;

use App\Models\News;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ViewCounter
{
    /** রোবট, লিংক-প্রিভিউ ও স্বয়ংক্রিয় ক্লায়েন্টের ব্রাউজার-পরিচয় */
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|telegram|preview|curl|wget|python|java\/|httpclient|headless|monitor|lighthouse|pingdom|uptime/i';

    /** একই পাঠক একই খবর এত মিনিটের মধ্যে আবার খুললে গোনা হয় না */
    private const REPEAT_MINUTES = 30;

    /** পাঠ গুনলে true। বাদ পড়লে (রোবট, স্টাফ, বারবার খোলা) false। */
    public static function hit(News $news, Request $request): bool
    {
        if (! $request->isMethod('GET') || $request->user()) {
            return false;
        }

        $agent = (string) $request->userAgent();

        if ($agent === '' || preg_match(self::BOT_PATTERN, $agent) === 1) {
            return false;
        }

        $purpose = strtolower((string) ($request->header('Purpose') ?? $request->header('Sec-Purpose') ?? ''));

        if (str_contains($purpose, 'prefetch')) {
            return false;
        }

        // Cache::add: কী আগে থেকে থাকলে false ফেরত দেয়, অর্থাৎ এই পাঠক সম্প্রতি এই খবর পড়েছেন।
        $key = 'nv:'.$news->id.':'.sha1($request->ip().'|'.$agent);

        if (! Cache::add($key, 1, now()->addMinutes(self::REPEAT_MINUTES))) {
            return false;
        }

        // সরাসরি কোয়েরি: খবরের updated_at ও মডেল ইভেন্ট (ক্যাশ ফ্লাশ) ছোঁয়া হয় না।
        DB::table('news')->where('id', $news->id)->increment('view_count');

        self::daily($news->id);

        return true;
    }

    private static function daily(int $newsId): void
    {
        $day = now()->toDateString();
        $row = fn () => DB::table('news_view_stats')->where('news_id', $newsId)->where('day', $day);

        if ($row()->increment('views') > 0) {
            return;
        }

        try {
            DB::table('news_view_stats')->insert(['news_id' => $newsId, 'day' => $day, 'views' => 1]);
        } catch (QueryException) {
            // একই মুহূর্তে আরেকজন সারিটি বানিয়ে ফেললে গুনে দেওয়া
            $row()->increment('views');
        }
    }
}