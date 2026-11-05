<?php

namespace App\Support;

use App\Models\News;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * একটি প্রকাশিত নিউজ থেকে ফেসবুক রিলস (১০৮০x১৯২০) ভিডিও বানায়।
 * লেখা টুকরো টুকরো করে ফেড-ইন হয়: ব্রাউজারে প্রতিটি ধাপের ফ্রেম লম্বালম্বি সাজানো
 * থাকে (filmstrip), ffmpeg সেগুলো কেটে জোড়া লাগায়—বাংলা লেখা এনিমেট করার একমাত্র
 * নিরাপদ পথ, কারণ drawtext-এ যুক্তাক্ষর ভেঙে যায়।
 */
final class NewsVideo
{
    public const WIDTH = 1080;

    public const HEIGHT = 1920;

    private const FPS = 30;

    /** দুই স্লাইডের মাঝের ট্রানজিশন */
    private const FADE = 0.6;

    /** একটি লেখার গ্রুপ ফেড-ইন হওয়ার সময় */
    private const STATE_FADE = 0.4;

    /** একটি স্লাইডে লেখার সর্বোচ্চ ধাপ */
    private const MAX_GROUPS = 6;

    /** প্রতিবেদন থেকে কয়টি লেখার স্লাইড বানানো হবে */
    private const MAX_BODY = 4;

    /**
     * @return array{path: string, duration: int, slides: int, build_dir: ?string}
     */
    public static function make(
        News $news,
        ?string $output = null,
        bool $keep = false,
        ?callable $log = null,
        ?string $music = null
    ): array {
        $log ??= static fn (string $message) => null;
        $news->loadMissing(['category', 'categories']);

        $slides = self::slides($news);

        if ($slides === []) {
            throw new RuntimeException('এই নিউজ থেকে ভিডিও বানানোর মতো তথ্য পাওয়া যায়নি।');
        }

        $dir = storage_path('app/video-build/news-'.$news->id.'-'.Str::lower(Str::random(6)));
        File::ensureDirectoryExists($dir);

        $ffmpeg = self::ffmpeg();
        $chrome = self::chrome();
        $durations = array_map(static fn (array $slide): int => (int) $slide['duration'], $slides);

        // ফেডগুলো সময়ের ওপর চড়ে বসে, তাই মোট সময় কিছুটা কমে
        $total = array_sum($durations) - max(0, count($durations) - 1) * self::FADE;

        try {
            $strips = [];

            foreach ($slides as $index => $slide) {
                $number = $index + 1;
                $htmlPath = $dir.'/slide-'.sprintf('%02d', $number).'.html';
                $pngPath = $dir.'/strip-'.sprintf('%02d', $number).'.png';

                File::put($htmlPath, $slide['html']);

                $log('স্লাইড '.$number.' — '.$slide['states'].'টি ধাপ ক্যাপচার হচ্ছে…');

                self::run([
                    $chrome,
                    '--headless=new',
                    '--disable-gpu',
                    '--hide-scrollbars',
                    '--no-first-run',
                    '--no-default-browser-check',
                    '--user-data-dir='.str_replace('\\', '/', $dir.'/chrome-profile'),
                    '--force-device-scale-factor=1',
                    '--window-size='.self::WIDTH.','.(self::HEIGHT * $slide['states']),
                    '--virtual-time-budget=6000',
                    '--screenshot='.str_replace('\\', '/', $pngPath),
                    'file:///'.str_replace('\\', '/', $htmlPath),
                ], 180);

                if (! is_file($pngPath) || filesize($pngPath) < 1000) {
                    throw new RuntimeException('স্লাইডের ছবি তৈরি হয়নি (Chrome)।');
                }

                $strips[] = $pngPath;
            }

            $log('ব্যাকগ্রাউন্ড সাউন্ড তৈরি হচ্ছে…');
            $audio = self::audio($ffmpeg, $dir, $total, $music);

            $script = $dir.'/graph.txt';
            File::put($script, self::graph($slides, $total));

            $output = self::outputPath($news, $output);
            File::ensureDirectoryExists(dirname($output));

            $log('ভিডিও রেন্ডার হচ্ছে…');

            $inputs = [];

            foreach ($strips as $index => $strip) {
                $inputs[] = '-loop';
                $inputs[] = '1';
                $inputs[] = '-framerate';
                $inputs[] = (string) self::FPS;
                $inputs[] = '-t';
                $inputs[] = (string) $durations[$index];
                $inputs[] = '-i';
                $inputs[] = $strip;
            }

            $inputs[] = '-i';
            $inputs[] = $audio;

            self::run(
                array_merge(
                    [$ffmpeg, '-y'],
                    $inputs,
                    [
                        '-/filter_complex', $script,
                        '-map', '[v]', '-map', count($strips).':a:0',
                        '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '20', '-pix_fmt', 'yuv420p',
                        '-r', (string) self::FPS,
                        '-c:a', 'aac', '-b:a', '128k',
                        '-shortest', '-movflags', '+faststart', $output,
                    ]
                ),
                1800
            );

            if (! is_file($output) || filesize($output) < 10000) {
                throw new RuntimeException('ভিডিও ফাইল তৈরি হয়নি।');
            }
        } catch (Throwable $e) {
            if (! $keep) {
                File::deleteDirectory($dir);
            }

            throw $e;
        }

        if (! $keep) {
            File::deleteDirectory($dir);
        }

        return [
            'path' => $output,
            'duration' => (int) round($total),
            'slides' => count($slides),
            'build_dir' => $keep ? $dir : null,
        ];
    }

    /**
     * ভিডিওর স্লাইড (HTML) — ফাইল/ব্রাউজার ছাড়াই যাচাই করা যায়।
     *
     * @return list<array{kind: string, duration: int, states: int, html: string}>
     */
    public static function slides(News $news): array
    {
        $news->loadMissing(['category', 'categories']);

        $excerpt = self::excerpt($news);
        $blocks = self::blocks($news);
        $chart = trim((string) $news->chart_shortcode) !== ''
            ? ArticleVisuals::thumb(trim((string) $news->chart_shortcode))
            : null;
        $image = self::imageData($news);

        if ($blocks === []) {
            $blocks = $excerpt !== '' ? [$excerpt] : [];
        }

        $base = [
            'site' => (string) Settings::get('site_name'),
            'tagline' => (string) Settings::get('site_tagline'),
            'category' => (string) ($news->category?->name ?? $news->categories->first()?->name ?? ''),
            'subline' => trim((string) ($news->sub_title ?? '')),
            'title' => trim((string) $news->title),
            'headline' => '',
            'label' => '',
            'text' => '',
            'items' => [],
            'chart' => $chart['svg'] ?? null,
            'image' => $image,
            'visual' => $image !== null ? 'image' : 'none',
            'url' => self::publicUrl($news),
            'accent' => self::accent(),
            'logo' => self::logoData(),
        ];

        $slides = [
            self::slide('cover', array_merge($base, [
                'headline' => self::reveal((string) $news->title, 2, true),
            ]), 7, 11),
        ];

        $kinds = ['lead', 'rail', 'points', 'quote'];
        $labels = ['প্রতিবেদন', 'আরও', 'মূল কথা', 'উল্লেখযোগ্য'];

        foreach (array_slice($blocks, 0, self::MAX_BODY) as $index => $block) {
            $kind = $kinds[$index % count($kinds)];

            $data = array_merge($base, self::bodyData($kind, $block, $labels[$index % count($labels)], $index === 0));

            $slides[] = self::slide($kind, $data, 6, 11);
        }

        if ($chart !== null) {
            $slides[] = self::slide('chart', array_merge($base, [
                'label' => self::chartTitle((string) $news->chart_shortcode) ?? 'গ্রাফে দেখুন',
            ]), 6, 9);
        }

        $slides[] = self::slide('closing', array_merge($base, [
            'text' => self::reveal('বিস্তারিত পড়ুন', 1, true),
        ]), 6, 9);

        return $slides;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{kind: string, duration: int, states: int, html: string}
     */
    private static function slide(string $kind, array $data, int $floor, int $ceiling): array
    {
        $html = view('video.slide', $data + ['kind' => $kind])->render();

        // ব্রাউজারে যতগুলো গ্রুপ দেখাবে, ঠিক ততগুলো ফ্রেম স্ট্রিপে থাকে
        $states = max(1, substr_count($html, ' data-rv>'));
        $duration = (int) round(min($ceiling, max($floor, 2.4 + 1.2 * $states)));

        return [
            'kind' => $kind,
            'duration' => max(3, $duration),
            'states' => $states,
            'html' => $html,
        ];
    }

    /**
     * @return array{label: string, text: string, items: list<array{n: string, text: string}>}
     */
    private static function bodyData(string $kind, string $block, string $label, bool $tint = false): array
    {
        if ($kind === 'points') {
            $items = [];

            foreach (self::chunks($block, 4) as $index => $unit) {
                $items[] = ['n' => BanglaTime::digits($index + 1), 'text' => e($unit)];
            }

            return ['label' => $label, 'text' => '', 'items' => $items];
        }

        return ['label' => $label, 'text' => self::reveal($block, self::MAX_GROUPS, $tint), 'items' => []];
    }

    /**
     * লেখা ছোট টুকরোয় ভেঙে ধাপে ধাপে দেখানোর মার্কআপ।
     */
    private static function reveal(string $text, int $max = self::MAX_GROUPS, bool $tint = false): string
    {
        $chunks = self::chunks($text, $max);
        // দুই বা বেশি টুকরো হলেই প্রথমটা অ্যাকসেন্টের ছায়ায় ধরে
        $tint = $tint && count($chunks) > 1;
        $html = '';

        foreach ($chunks as $index => $chunk) {
            $html .= '<span'.($index === 0 && $tint ? ' class="hot"' : '').' data-rv>'.e($chunk).'</span> ';
        }

        return $html;
    }

    /**
     * একবারে পড়া যায় এমন টুকরো—বাক্য ধরে, দরকারে শব্দ ধরে; সংখ্যা সীমিত রাখা।
     *
     * @return list<string>
     */
    private static function chunks(string $text, int $max): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '') {
            return [];
        }

        $sentences = preg_split('/(?<=[।.!?…])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $units = [];

        foreach ($sentences as $sentence) {
            $words = preg_split('/\s+/u', $sentence, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if (count($words) <= 9) {
                $units[] = $sentence;

                continue;
            }

            foreach (array_chunk($words, 7) as $chunk) {
                $units[] = implode(' ', $chunk);
            }
        }

        // ধাপ বেশি হলে পাশাপাশি টুকরো জোড়া লাগে—স্ট্রিপের উচ্চতা সীমায় থাকে
        while (count($units) > $max) {
            $merged = [];

            for ($i = 0, $count = count($units); $i < $count; $i += 2) {
                $merged[] = isset($units[$i + 1])
                    ? trim($units[$i]).' '.trim($units[$i + 1])
                    : trim($units[$i]);
            }

            $units = $merged;
        }

        return $units;
    }

    /**
     * প্রতিবেদনের অনুচ্ছেদগুলো—পুরো লেখাই স্লাইডে বসে।
     *
     * @return list<string>
     */
    private static function blocks(News $news): array
    {
        $raw = trim((string) ($news->description ?: $news->content ?: ''));

        if ($raw === '') {
            return [];
        }

        // শর্টকোড ([chart …]) বাদ, ব্রেক ও অনুচ্ছেদ ট্যাগ লাইন-বিভাজক
        $raw = (string) preg_replace('/\[\h*\/?\h*\w+[^\]]*\]/u', ' ', $raw);
        $raw = (string) preg_replace('/<(?:br|\/p|\/div|\/li|\/h\d)\s*\/?>/i', "\n", $raw);
        $plain = strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        $blocks = [];

        foreach (preg_split('/\n+/u', $plain) ?: [] as $line) {
            $line = trim((string) preg_replace('/\s+/u', ' ', $line));

            if (mb_strlen($line) < 60) {
                continue;
            }

            $blocks[] = self::limit($line, 460);
        }

        return $blocks;
    }

    /** শর্টকোডের title="…" অংশ। */
    private static function chartTitle(string $shortcode): ?string
    {
        preg_match('/\btitle="([^"]*)"/u', $shortcode, $match);

        $title = trim(html_entity_decode($match[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $title !== '' ? $title : null;
    }

    /** বড় লেখা বাক্য শেষে কেটে দেয়। */
    private static function limit(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max);
        $danda = mb_strrpos($cut, '।');

        if ($danda !== false && $danda > 120) {
            return trim(mb_substr($cut, 0, $danda + 1));
        }

        $space = mb_strrpos($cut, ' ');
        $cut = $space !== false ? mb_substr($cut, 0, $space) : $cut;

        return rtrim($cut, ' ,;:—-').'…';
    }

    /** কার্ডের অ্যাকসেন্ট রঙ—না মিললে ডিফল্ট লাল। */
    private static function accent(): string
    {
        $accent = (string) Settings::get('card_accent');

        return preg_match('/^#[0-9a-fA-F]{6}$/', $accent) ? $accent : '#d60000';
    }

    /** লোগো ইনলাইন: Chrome file:// থেকে খুলবে, তাই asset() URL চলবে না। */
    private static function logoData(): ?string
    {
        $name = basename(trim((string) Settings::get('logo')));
        $path = $name !== '' ? public_path('uploads/site/'.$name) : public_path('images/logo.png');

        if (! is_file($path)) {
            return null;
        }

        $size = filesize($path);

        if ($size === false || $size > 2 * 1024 * 1024) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        if (! str_starts_with($mime, 'image/')) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }

    private static function excerpt(News $news): string
    {
        $raw = (string) ($news->summary ?: $news->description ?: '');
        $text = strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        // অনুচ্ছেদ বাদ চিহ্ন ([...]) পড়ার ছন্দ ভেঙে দেয়
        $text = trim((string) preg_replace('/\s*[\[(](?:\.{3}|…)[\])]\s*/u', ' ', $text));
        $text = rtrim($text, ' ,;:-');

        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= 320) {
            return $text;
        }

        return self::limit($text, 320);
    }

    private static function imageData(News $news): ?string
    {
        $name = basename(trim((string) $news->image));

        if ($name === '') {
            return null;
        }

        $path = public_path('uploads/news/'.$name);

        if (! is_file($path)) {
            return null;
        }

        $size = filesize($path);

        if ($size === false || $size > 12 * 1024 * 1024) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/jpeg';

        if (! str_starts_with($mime, 'image/')) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }

    private static function publicUrl(News $news): ?string
    {
        $base = rtrim((string) config('app.url'), '/');
        $host = parse_url($base, PHP_URL_HOST);

        if (! $host || in_array($host, ['127.0.0.1', 'localhost', '::1'], true) || $news->slug === null) {
            return null;
        }

        return $base.'/article/'.$news->slug;
    }

    public static function path(News $news): string
    {
        return self::outputPath($news, null);
    }

    public static function exists(News $news): bool
    {
        return is_file(self::path($news));
    }

    private static function outputPath(News $news, ?string $output): string
    {
        if ($output !== null && trim($output) !== '') {
            $output = trim($output);

            return preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $output) ? $output : base_path($output);
        }

        $slug = Str::slug((string) ($news->slug ?: '')) ?: 'news-'.$news->id;

        return storage_path('app/videos/'.$news->id.'-'.$slug.'.mp4');
    }

    /**
     * পুরো ভিডিওর ফিল্টার গ্রাফ এক কমান্ডে: স্ট্রিপ কাটা → ধাপের ফেড → স্লাইডের স্লাইড-আপ।
     *
     * @param  list<array{duration: int, states: int}>  $slides
     */
    private static function graph(array $slides, float $total): string
    {
        $parts = [];
        $labels = [];

        foreach (array_values($slides) as $index => $slide) {
            $duration = (float) $slide['duration'];
            $states = max(1, (int) $slide['states']);

            // ধাপগুলো পরস্পরের ওপর ফেড হয়, তাই ধাপপ্রতি সময় কিছুটা বেশি ধরতে হয়
            $hold = ($duration + ($states - 1) * self::STATE_FADE) / $states;
            $gap = max(0.1, $hold - self::STATE_FADE);

            $split = '['.$index.':v]split='.$states;
            $branches = [];

            for ($k = 0; $k < $states; $k++) {
                $split .= '[p'.$index.'_'.$k.']';
                $branches[] = 's'.$index.'_'.$k;
            }

            $parts[] = $split;

            for ($k = 0; $k < $states; $k++) {
                $parts[] = '[p'.$index.'_'.$k.']crop='.self::WIDTH.':'.self::HEIGHT.':0:'.
                    ($k * self::HEIGHT).',fps='.self::FPS.',settb=AVTB,setsar=1,format=yuv420p'.
                    '['.$branches[$k].']';
            }

            $label = $branches[0];

            for ($k = 1; $k < $states; $k++) {
                $next = 'f'.$index.'_'.$k;
                // লেখা মিলিয়ে দেখানো—টুকরো নিঃশব্দে জ্বলে ওঠে
                $parts[] = '['.$label.']['.$branches[$k].']xfade=transition=fade:duration='.
                    self::num(self::STATE_FADE).':offset='.self::num($k * $gap).'['.$next.']';
                $label = $next;
            }

            if ($states > 1) {
                $parts[] = '['.$label.']trim=duration='.self::num($duration).',setpts=PTS-STARTPTS[t'.$index.']';
                $label = 't'.$index;
            }

            $labels[] = $label;
        }

        $merged = array_shift($labels);
        $elapsed = (float) ($slides[0]['duration'] ?? 0);
        $durations = array_map(static fn (array $slide): int => (int) $slide['duration'], $slides);

        foreach ($labels as $step => $label) {
            $elapsed -= self::FADE;
            $next = 'v'.($step + 1);
            // লেখা দুই স্লাইডে একসাথে মিশে অস্পষ্ট হয়, তাই dissolve-এর বদলে slide
            $parts[] = '['.$merged.']['.$label.']xfade=transition=slideup:duration='.
                self::num(self::FADE).':offset='.self::num(max(0, $elapsed)).'['.$next.']';
            $elapsed += $durations[$step + 1];
            $merged = $next;
        }

        $fadeOut = max(0, $total - self::FADE);

        $parts[] = '['.$merged.']fade=t=in:st=0:d='.self::num(self::FADE).',fade=t=out:st='.
            self::num($fadeOut).':d='.self::num(self::FADE).',format=yuv420p[v]';

        return implode(";\n", $parts).';';
    }

    private static function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    private static function audio(string $ffmpeg, string $dir, float $seconds, ?string $music): string
    {
        $bed = $dir.'/bed.m4a';
        $length = self::num($seconds);
        $fadeOutAt = self::num(max(0, $seconds - 3));

        if ($music !== null && trim($music) !== '') {
            $path = trim($music);
            $path = preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) ? $path : base_path($path);

            if (! is_file($path)) {
                throw new RuntimeException('মিউজিক ফাইল পাওয়া যায়নি: '.$path);
            }

            self::run([
                $ffmpeg, '-y', '-stream_loop', '-1', '-i', $path, '-t', $length,
                '-af', 'volume=0.30,afade=t=in:st=0:d=1.5,afade=t=out:st='.$fadeOutAt.':d=3',
                '-ac', '2', '-c:a', 'aac', '-b:a', '128k', $bed,
            ], 300);

            return $bed;
        }

        // প্যাড: Am–F–C–G কোর্ড চলা, ধীরে ওঠা-নমা (সিনেওভ—ফ্রি ও লাইসেন্স-মুক্ত)।
        $pad = $dir.'/pad.m4a';
        $chords = [
            [220.00, 261.63, 329.63],
            [174.61, 220.00, 261.63],
            [130.81, 196.00, 261.63],
            [196.00, 246.94, 293.66],
        ];

        $inputs = [];
        $chains = [];

        foreach ($chords as $index => $chord) {
            foreach ($chord as $frequency) {
                $inputs[] = '-f';
                $inputs[] = 'lavfi';
                $inputs[] = '-i';
                $inputs[] = 'sine=frequency='.self::tone($frequency).':duration=4';
            }

            $base = $index * 3;

            $chains[] = '['.$base.':a]['.($base + 1).':a]['.($base + 2).':a]'.
                'amix=inputs=3,volume=4,afade=t=in:st=0:d=2.2,afade=t=out:st=2.1:d=1.9[c'.$index.']';
        }

        $chains[] = '[c0][c1][c2][c3]concat=n=4:v=0:a=1,alimiter=limit=0.85[pad]';

        self::run(
            array_merge(
                [$ffmpeg, '-y'],
                $inputs,
                [
                    '-filter_complex', implode(';', $chains),
                    '-map', '[pad]', '-ac', '2', '-ar', '44100', '-c:a', 'aac', '-b:a', '128k', $pad,
                ]
            ),
            300
        );

        // সুর: প্যাডের ওপর ধীর আরপেজ—কানে শ্রুতিমধু লাগে
        $notes = [440.00, 523.25, 659.25, 523.25, 392.00, 523.25, 659.25, 880.00];
        $arp = $dir.'/arp.m4a';
        $inputs = [];
        $chains = [];

        foreach ($notes as $index => $frequency) {
            $inputs[] = '-f';
            $inputs[] = 'lavfi';
            $inputs[] = '-i';
            $inputs[] = 'sine=frequency='.self::tone($frequency).':duration=0.9';

            $chains[] = '['.$index.':a]afade=t=in:st=0:d=0.03,afade=t=out:st=0.06:d=0.83,volume=3[n'.$index.']';
        }

        $chains[] = '[n0][n1][n2][n3][n4][n5][n6][n7]concat=n=8:v=0:a=1,alimiter=limit=0.9[arp]';

        self::run(
            array_merge(
                [$ffmpeg, '-y'],
                $inputs,
                [
                    '-filter_complex', implode(';', $chains),
                    '-map', '[arp]', '-ac', '2', '-ar', '44100', '-c:a', 'aac', '-b:a', '128k', $arp,
                ]
            ),
            300
        );

        self::run([
            $ffmpeg, '-y', '-stream_loop', '-1', '-i', $pad, '-stream_loop', '-1', '-i', $arp, '-t', $length,
            '-filter_complex',
            '[0:a]volume=0.80,lowpass=f=1700,tremolo=f=0.12:d=0.18[a0];'.
            '[1:a]volume=0.20,lowpass=f=3200,aecho=0.8:0.85:55|110:0.28|0.18[a1];'.
            '[a0][a1]amix=inputs=2:duration=first:normalize=0,alimiter=limit=0.9,'.
            'afade=t=in:st=0:d=2,afade=t=out:st='.$fadeOutAt.':d=3[v]',
            '-map', '[v]', '-ac', '2', '-c:a', 'aac', '-b:a', '128k', $bed,
        ], 300);

        return $bed;
    }

    private static function tone(float $frequency): string
    {
        return rtrim(rtrim(number_format($frequency, 2, '.', ''), '0'), '.');
    }

    private static function ffmpeg(): string
    {
        $configured = trim((string) config('video.ffmpeg'));

        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        $local = (string) getenv('LOCALAPPDATA');

        $candidates = [
            'C:/ffmpeg/bin/ffmpeg.exe',
            'C:/Program Files/ffmpeg/bin/ffmpeg.exe',
            'C:/ProgramData/chocolatey/bin/ffmpeg.exe',
            'C:/laragon/bin/ffmpeg/ffmpeg.exe',
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        foreach ((array) glob($local.'/Microsoft/WinGet/Packages/Gyan.FFmpeg*/*/bin/ffmpeg.exe') as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return 'ffmpeg';
    }

    private static function chrome(): string
    {
        $configured = trim((string) config('video.chrome'));

        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        $local = (string) getenv('LOCALAPPDATA');

        $candidates = [
            'C:/Program Files/Google/Chrome/Application/chrome.exe',
            'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
            $local.'/Google/Chrome/Application/chrome.exe',
            'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
            'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException('Chrome/Chromium পাওয়া যায়নি। CHROME_BINARY সেট করে পাথ দিন।');
    }

    /** @param list<string> $command */
    private static function run(array $command, int $timeout): void
    {
        $result = Process::timeout($timeout)->run($command);

        if ($result->failed()) {
            $message = trim($result->errorOutput()) ?: trim($result->output());

            throw new RuntimeException(mb_substr($message !== '' ? $message : 'বাহ্যিক প্রোগ্রাম চালানো যায়নি।', 0, 400));
        }
    }
}
