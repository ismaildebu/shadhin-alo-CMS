<?php

namespace App\Models;

use App\Services\ImageOptimizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class Media extends Model
{
    /** সংবাদের ছবির ফোল্ডার: গ্যালারির ছবিও এখানেই থাকে, তাই পাবলিক সাইটের কোনো ভিউ বদলাতে হয় না। */
    public const DIR = 'uploads/news';

    /** ছবির ধরন অনুযায়ী গ্রহণযোগ্য এক্সটেনশন (প্রথমটি ডিফল্ট)। */
    private const EXTENSIONS = [
        IMAGETYPE_JPEG => ['jpg', 'jpeg'],
        IMAGETYPE_PNG => ['png'],
        IMAGETYPE_WEBP => ['webp'],
    ];

    private const MIMES = [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG => 'image/png',
        IMAGETYPE_WEBP => 'image/webp',
    ];

    /** আপলোডের সময় বানানো ছোট সংস্করণের প্রস্থ (পিক্সেলে)। */
    public const THUMB_WIDTHS = [400, 800];

    /** এই অনুরোধের জন্য মনে রাখা alt-এর তালিকা (altMap দেখুন)। */
    private static ?array $altMemo = null;

    protected $table = 'media';

    protected $fillable = ['filename', 'original_name', 'alt', 'focus_x', 'focus_y', 'mime', 'size', 'uploaded_by'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'focus_x' => 'integer', 'focus_y' => 'integer'];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return asset(self::DIR.'/'.$this->filename);
    }

    public function getSizeLabelAttribute(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1).' MB'
            : number_format($this->size / 1024).' KB';
    }

    /**
     * ছবি অপটিমাইজ (১২০০×৮০০, ১ MB-এর নিচে) করে গ্যালারিতে যোগ করে।
     * আপলোডের ফাইলনামই থাকে। শুধু একই নামের ফাইল আগে থেকে থাকলে নামের শেষে -1, -2 বসে,
     * যাতে অন্য সংবাদের ছবি মুছে না যায়।
     */
    public static function storeUpload(UploadedFile $file, ?User $user = null): self
    {
        $clientName = $file->getClientOriginalName();
        $result = ImageOptimizer::optimize($file);
        $name = self::uniqueFilename($clientName, $result['type']);

        File::ensureDirectoryExists(public_path(self::DIR));
        file_put_contents(public_path(self::DIR).'/'.$name, $result['data']);

        self::writeThumbnails($name);

        return static::create([
            'filename' => $name,
            'original_name' => Str::limit($clientName, 190, ''),
            'mime' => self::MIMES[$result['type']] ?? 'image/jpeg',
            'size' => strlen($result['data']),
            'uploaded_by' => $user?->id,
        ]);
    }

    /** ছবির বিবরণের (alt) তালিকা: ফাইলনাম => বিবরণ। migration চলার আগেও সাইট না ভেঙে খালি তালিকা দেয়। */
    public static function altMap(): array
    {
        // Kept for the rest of the request: a page shows dozens of images, so avoid one cache read per image.
        if (self::$altMemo !== null) {
            return self::$altMemo;
        }

        try {
            return self::$altMemo = Cache::remember('media.alt_map', 600, fn () => static::query()
                ->whereNotNull('alt')
                ->where('alt', '!=', '')
                ->pluck('alt', 'filename')
                ->all());
        } catch (\Throwable) {
            return self::$altMemo = [];
        }
    }

    /** ছবির বিবরণ (alt); গ্যালারিতে না দেওয়া থাকলে $fallback (সাধারণত সংবাদের শিরোনাম)। */
    public static function altFor(?string $filename, ?string $fallback = ''): string
    {
        if (! $filename) {
            return (string) $fallback;
        }

        return self::altMap()[basename($filename)] ?? (string) $fallback;
    }

    /**
     * ফোকাস পয়েন্ট দেওয়া ছবিগুলোর জন্য CSS। ছবি কেটে বসানোর সময় (object-fit: cover)
     * এই অংশটাই দেখা যাবে। শুধু ফোকাস দেওয়া ছবির নিয়ম থাকে, তাই ছোট।
     */
    public static function focusCss(): string
    {
        try {
            $rows = Cache::remember('media.focus_rows', 600, fn () => static::query()
                ->whereNotNull('focus_x')
                ->whereNotNull('focus_y')
                ->get(['filename', 'focus_x', 'focus_y'])
                ->map(fn ($m) => [$m->filename, (int) $m->focus_x, (int) $m->focus_y])
                ->all());
        } catch (\Throwable) {
            return '';
        }

        $css = '';

        foreach ($rows as [$file, $x, $y]) {
            $safe = addcslashes(str_replace(['<', '>'], '', $file), '"\\');
            $css .= 'img[src$="/'.$safe.'"]{object-position:'.$x.'% '.$y.'%}';
        }

        return $css;
    }

    /** alt ও ফোকাসের ক্যাশ মুছে ফেলে, যাতে বদল সাথে সাথে সাইটে দেখা যায়। */
    public static function flushCaches(): void
    {
        self::$altMemo = null;

        Cache::forget('media.alt_map');
        Cache::forget('media.focus_rows');
    }

    /** ছোট সংস্করণের ফোল্ডার, যেমন uploads/news/thumbs/400 */
    public static function thumbDir(int $width): string
    {
        return public_path(self::DIR.'/thumbs/'.$width);
    }

    /** ছবির ছোট সংস্করণ বানায়। ব্যর্থ হলেও আপলোড আটকায় না; তখন আসল ছবিই ব্যবহার হয়। */
    public static function writeThumbnails(string $filename): void
    {
        $source = public_path(self::DIR.'/'.$filename);

        foreach (self::THUMB_WIDTHS as $width) {
            try {
                $data = ImageOptimizer::makeThumbnail($source, $width);

                if ($data === null) {
                    continue;
                }

                File::ensureDirectoryExists(self::thumbDir($width));
                file_put_contents(self::thumbDir($width).'/'.$filename, $data);
            } catch (\Throwable) {
                // The original image is used when a thumbnail cannot be built.
            }
        }
    }

    /** আসল ছবি ও তার সব ছোট সংস্করণ মোছে। */
    public static function removeFiles(string $filename): void
    {
        $filename = basename($filename);

        File::delete(public_path(self::DIR.'/'.$filename));

        foreach (self::THUMB_WIDTHS as $width) {
            File::delete(self::thumbDir($width).'/'.$filename);
        }
    }

    /** তালিকা ও কার্ডের জন্য ছোট সংস্করণের ঠিকানা; না থাকলে আসল ছবির ঠিকানা। */
    public static function thumbUrl(?string $filename, int $width = 400): string
    {
        if (! $filename) {
            return '';
        }

        $filename = basename($filename);

        if (is_file(self::thumbDir($width).'/'.$filename)) {
            return asset(self::DIR.'/thumbs/'.$width.'/'.$filename);
        }

        return asset(self::DIR.'/'.$filename);
    }

    /**
     * ক্লায়েন্টের ফাইলনাম যতটা সম্ভব হুবহু রাখে; শুধু পাথ ও ইউআরএল ভাঙার মতো অক্ষর বাদ দেয়।
     * ফাইলের ভেতরের ছবির ধরনের সাথে এক্সটেনশন না মিললে সঠিক এক্সটেনশন বসে।
     */
    private static function uniqueFilename(string $clientName, int $type): string
    {
        $name = basename(str_replace('\\', '/', $clientName));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = preg_replace('/[\/\\\\:*?"<>|#%]/u', '', $name) ?? '';
        $name = rtrim(ltrim($name, '. '), '. ');

        $base = pathinfo($name, PATHINFO_FILENAME);
        $ext = pathinfo($name, PATHINFO_EXTENSION);

        $allowed = self::EXTENSIONS[$type] ?? ['jpg'];

        if (! in_array(strtolower($ext), $allowed, true)) {
            $ext = $allowed[0];
        }

        $base = trim(mb_substr($base, 0, 120));

        if ($base === '') {
            $base = 'image';
        }

        $candidate = $base.'.'.$ext;
        $i = 1;

        while (
            File::exists(public_path(self::DIR.'/'.$candidate))
            || static::where('filename', $candidate)->exists()
        ) {
            $candidate = $base.'-'.$i.'.'.$ext;
            $i++;
        }

        return $candidate;
    }
}