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
 * ভয়েস ছাড়া: স্লাইডের লেখা তথ্য বহন করে, পটভূমিতে হালকা সাউন্ড থাকে।
 */
final class NewsVideo
{
    public const WIDTH = 1080;

    public const HEIGHT = 1920;

    private const FPS = 30;

    private const FADE = 0.6;

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
        $clips = [];
        $total = 0;

        try {
            foreach ($slides as $index => $slide) {
                $number = $index + 1;
                $htmlPath = $dir.'/slide-'.sprintf('%02d', $number).'.html';
                $pngPath = $dir.'/slide-'.sprintf('%02d', $number).'.png';
                $clipPath = $dir.'/clip-'.sprintf('%02d', $number).'.mp4';

                File::put($htmlPath, $slide['html']);

                $log('স্লাইড '.$number.' ক্যাপচার হচ্ছে…');

                self::run([
                    $chrome,
                    '--headless=new',
                    '--disable-gpu',
                    '--hide-scrollbars',
                    '--no-first-run',
                    '--no-default-browser-check',
                    '--user-data-dir='.str_replace('\\', '/', $dir.'/chrome-profile'),
                    '--force-device-scale-factor=1',
                    '--window-size='.self::WIDTH.','.self::HEIGHT,
                    '--virtual-time-budget=6000',
                    '--screenshot='.str_replace('\\', '/', $pngPath),
                    'file:///'.str_replace('\\', '/', $htmlPath),
                ], 180);

                if (! is_file($pngPath) || filesize($pngPath) < 1000) {
                    throw new RuntimeException('স্লাইডের ছবি তৈরি হয়নি (Chrome)।');
                }

                $duration = (int) $slide['duration'];
                $total += $duration;

                self::run(
                    self::clipCommand($ffmpeg, $pngPath, $clipPath, $duration, $slide['kind'] === 'closing'),
                    600
                );

                $clips[] = $clipPath;
            }

            $log('স্লাইডগুলো জোড়া লাগানো হচ্ছে…');

            $listPath = $dir.'/clips.txt';
            $lines = array_map(
                static fn (string $clip) => "file '".str_replace('\\', '/', $clip)."'",
                $clips
            );
            File::put($listPath, implode(PHP_EOL, $lines).PHP_EOL);

            $silent = $dir.'/silent.mp4';
            self::run([
                $ffmpeg, '-y', '-f', 'concat', '-safe', '0', '-i', $listPath,
                '-c', 'copy', '-movflags', '+faststart', $silent,
            ], 600);

            $log('ব্যাকগ্রাউন্ড সাউন্ড তৈরি হচ্ছে…');
            $audio = self::audio($ffmpeg, $dir, $total, $music);

            $output = self::outputPath($news, $output);
            File::ensureDirectoryExists(dirname($output));

            $log('ভিডিও রেন্ডার হচ্ছে…');

            self::run([
                $ffmpeg, '-y', '-i', $silent, '-i', $audio,
                '-map', '0:v:0', '-map', '1:a:0',
                '-c:v', 'copy', '-c:a', 'aac', '-b:a', '128k',
                '-shortest', '-movflags', '+faststart', $output,
            ], 600);

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
            'duration' => $total,
            'slides' => count($slides),
            'build_dir' => $keep ? $dir : null,
        ];
    }

    /**
     * ভিডিওর স্লাইড (HTML) — ফাইল/ব্রাউজার ছাড়াই যাচাই করা যায়।
     *
     * @return list<array{kind: string, duration: int, html: string}>
     */
    public static function slides(News $news): array
    {
        $news->loadMissing(['category', 'categories']);

        $site = (string) Settings::get('site_name');
        $tagline = (string) Settings::get('site_tagline');
        $category = (string) ($news->category?->name ?? $news->categories->first()?->name ?? '');
        $title = trim((string) $news->title);
        $date = $news->display_date->format('d M Y');
        $excerpt = self::excerpt($news);
        $chart = trim((string) $news->chart_shortcode) !== ''
            ? ArticleVisuals::thumb(trim((string) $news->chart_shortcode))
            : null;
        $chartSvg = $chart['svg'] ?? null;
        $image = self::imageData($news);
        $url = self::publicUrl($news);

        $base = [
            'site' => $site,
            'tagline' => $tagline,
            'category' => $category,
            'title' => $title,
            'date' => $date,
            'excerpt' => $excerpt,
            'chart' => $chartSvg,
            'image' => $image,
            'url' => $url,
        ];

        $slides = [];

        $slides[] = self::slide('cover', 13, $base + [
            'visual' => $image !== null ? 'image' : ($chartSvg !== null ? 'chart' : 'none'),
        ]);

        if ($excerpt !== '') {
            $slides[] = self::slide('summary', 15, $base);
        }

        if ($chartSvg !== null) {
            $slides[] = self::slide('chart', 14, $base);
        }

        $slides[] = self::slide('closing', 8, $base);

        return $slides;
    }

    /** @param array<string, mixed> $data */
    private static function slide(string $kind, int $duration, array $data): array
    {
        return [
            'kind' => $kind,
            'duration' => $duration,
            'html' => view('video.slide', $data + ['kind' => $kind])->render(),
        ];
    }

    private static function excerpt(News $news): string
    {
        $raw = (string) ($news->summary ?: $news->description ?: '');
        $text = strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= 320) {
            return $text;
        }

        $cut = mb_substr($text, 0, 320);
        $sentence = mb_strrpos($cut, '।');

        if ($sentence !== false && $sentence > 140) {
            return trim(mb_substr($cut, 0, $sentence + 1));
        }

        $space = mb_strrpos($cut, ' ');
        $cut = $space !== false ? mb_substr($cut, 0, $space) : $cut;

        return rtrim($cut, ' ,;:').'…';
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

    /** @return list<string> */
    private static function clipCommand(string $ffmpeg, string $png, string $clip, int $seconds, bool $zoomOut): array
    {
        $frames = $seconds * self::FPS;
        $step = number_format(0.06 / $frames, 6, '.', '');
        $zoom = $zoomOut
            ? "z='max(1.06-".$step."*on,1.0)'"
            : "z='min(1+".$step."*on,1.06)'";
        $fadeOutAt = $seconds - self::FADE;

        $filter = 'zoompan='.$zoom.':x=\'iw/2-(iw/zoom/2)\':y=\'ih/2-(ih/zoom/2)\':d='.$frames.
            ':s='.self::WIDTH.'x'.self::HEIGHT.':fps='.self::FPS.
            ',fade=t=in:st=0:d='.self::FADE.',fade=t=out:st='.$fadeOutAt.':d='.self::FADE.
            ',format=yuv420p';

        return [
            $ffmpeg, '-y', '-i', $png, '-vf', $filter, '-t', (string) $seconds,
            '-r', (string) self::FPS, '-c:v', 'libx264', '-preset', 'veryfast',
            '-crf', '21', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', $clip,
        ];
    }

    private static function audio(string $ffmpeg, string $dir, int $seconds, ?string $music): string
    {
        $bed = $dir.'/bed.m4a';
        $fadeOutAt = max(0, $seconds - 3);

        if ($music !== null && trim($music) !== '') {
            $path = trim($music);
            $path = preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) ? $path : base_path($path);

            if (! is_file($path)) {
                throw new RuntimeException('মিউজিক ফাইল পাওয়া যায়নি: '.$path);
            }

            self::run([
                $ffmpeg, '-y', '-stream_loop', '-1', '-i', $path, '-t', (string) $seconds,
                '-af', 'volume=0.30,afade=t=in:st=0:d=1.5,afade=t=out:st='.$fadeOutAt.':d=3',
                '-ac', '2', '-c:a', 'aac', '-b:a', '128k', $bed,
            ], 300);

            return $bed;
        }

        // ফ্রি, লাইসেন্স-মুক্ত হালকা প্যাড: A মাইনর কর্ড + ধীর ট্রেমোলো।
        self::run([
            $ffmpeg, '-y',
            '-f', 'lavfi', '-i', 'sine=frequency=220:duration='.$seconds,
            '-f', 'lavfi', '-i', 'sine=frequency=261.63:duration='.$seconds,
            '-f', 'lavfi', '-i', 'sine=frequency=329.63:duration='.$seconds,
            '-filter_complex',
            '[0:a][1:a][2:a]amix=inputs=3:normalize=0,volume=0.45,'.
            'tremolo=f=0.11:d=0.35,afade=t=in:st=0:d=2,afade=t=out:st='.$fadeOutAt.':d=3',
            '-ac', '2', '-c:a', 'aac', '-b:a', '128k', $bed,
        ], 300);

        return $bed;
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
