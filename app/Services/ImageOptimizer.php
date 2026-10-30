<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class ImageOptimizer
{
    public const WIDTH = 1200;
    public const HEIGHT = 800;
    public const MAX_BYTES = 1_000_000;      // stay safely below 1 MB
    public const MAX_PIXELS = 64_000_000;    // decoding needs ~4 bytes per pixel

    private const QUALITIES = [85, 80, 75, 70, 65, 60, 55, 50, 45, 40];
    private const PNG_COLORS = [256, 128, 64, 32];

    /**
     * Fit the image inside a fixed WIDTH x HEIGHT white canvas (no cropping)
     * and re-encode it in its own format (JPEG, PNG or WebP) below MAX_BYTES.
     *
     * @return array{data: string, type: int} binary data and the IMAGETYPE_* constant
     */
    public static function optimize(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $info = $path ? @getimagesize($path) : false;

        if (! $info) {
            throw new RuntimeException('ছবিটি পড়া যায়নি।');
        }

        [$srcW, $srcH, $type] = $info;

        if ($srcW * $srcH > self::MAX_PIXELS) {
            throw new RuntimeException('ছবির রেজোলিউশন খুব বেশি। ছোট রেজোলিউশনের ছবি দিন।');
        }

        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => false,
        };

        if (! $src) {
            throw new RuntimeException('ছবিটি প্রসেস করা যায়নি।');
        }

        if ($type === IMAGETYPE_JPEG) {
            $src = self::applyExifOrientation($src, $path);
        }

        $w = imagesx($src);
        $h = imagesy($src);

        // Never upscale small images; only shrink large ones.
        $scale = min(self::WIDTH / $w, self::HEIGHT / $h, 1);
        $newW = max(1, (int) round($w * $scale));
        $newH = max(1, (int) round($h * $scale));

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));

        imagecopyresampled(
            $canvas, $src,
            (int) ((self::WIDTH - $newW) / 2), (int) ((self::HEIGHT - $newH) / 2),
            0, 0,
            $newW, $newH,
            $w, $h
        );

        $data = match ($type) {
            IMAGETYPE_PNG => self::encodePng($canvas),
            IMAGETYPE_WEBP => self::encodeWebp($canvas),
            default => self::encodeJpeg($canvas),
        };

        return ['data' => $data, 'type' => $type];
    }

    private static function encodeJpeg(GdImage $canvas): string
    {
        imageinterlace($canvas, true);

        $data = '';
        foreach (self::QUALITIES as $quality) {
            $data = self::capture(fn () => imagejpeg($canvas, null, $quality));

            if (strlen($data) <= self::MAX_BYTES) {
                break;
            }
        }

        return $data;
    }

    private static function encodeWebp(GdImage $canvas): string
    {
        $data = '';
        foreach (self::QUALITIES as $quality) {
            $data = self::capture(fn () => imagewebp($canvas, null, $quality));

            if (strlen($data) <= self::MAX_BYTES) {
                break;
            }
        }

        return $data;
    }

    private static function encodePng(GdImage $canvas): string
    {
        // PNG has no quality setting: try lossless first, then reduce the palette.
        $data = self::capture(fn () => imagepng($canvas, null, 9));

        if (strlen($data) <= self::MAX_BYTES) {
            return $data;
        }

        foreach (self::PNG_COLORS as $colors) {
            $copy = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
            imagecopy($copy, $canvas, 0, 0, 0, 0, self::WIDTH, self::HEIGHT);
            imagetruecolortopalette($copy, true, $colors);

            $data = self::capture(fn () => imagepng($copy, null, 9));

            if (strlen($data) <= self::MAX_BYTES) {
                break;
            }
        }

        return $data;
    }

    private static function capture(callable $encode): string
    {
        ob_start();
        $encode();

        return (string) ob_get_clean();
    }

    private static function applyExifOrientation(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        return imagerotate($image, $angle, 0) ?: $image;
    }
}