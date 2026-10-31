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

        $decoder = match ($type) {
            IMAGETYPE_JPEG => 'imagecreatefromjpeg',
            IMAGETYPE_PNG => 'imagecreatefrompng',
            IMAGETYPE_WEBP => 'imagecreatefromwebp',
            default => null,
        };

        if ($decoder === null) {
            throw new RuntimeException('শুধু jpg, png বা webp ছবি চলবে।');
        }

        // Some PHP builds ship GD without JPEG/PNG/WebP support; fail with a clear message
        // instead of a fatal "undefined function" error.
        if (! extension_loaded('gd') || ! function_exists($decoder)) {
            throw new RuntimeException(
                $type === IMAGETYPE_WEBP
                    ? 'এই সার্ভারের PHP-তে WebP ছবি প্রসেস করার সুবিধা চালু নেই। jpg বা png ছবি দিন।'
                    : 'এই সার্ভারের PHP-তে ছবি প্রসেস করার সুবিধা (GD) চালু নেই।'
            );
        }

        if ($type === IMAGETYPE_WEBP && ! function_exists('imagewebp')) {
            throw new RuntimeException('এই সার্ভারের PHP-তে WebP ছবি প্রসেস করার সুবিধা চালু নেই। jpg বা png ছবি দিন।');
        }

        $src = @$decoder($path);

        if (! $src) {
            throw new RuntimeException('ছবিটি প্রসেস করা যায়নি।');
        }

        if ($type === IMAGETYPE_JPEG) {
            $src = self::applyExifOrientation($src, $path);
        }

        $w = imagesx($src);
        $h = imagesy($src);

        // Scale to fit the canvas, enlarging small images too, so a small photo
        // does not end up floating inside a wide white border.
        $scale = min(self::WIDTH / $w, self::HEIGHT / $h);
        $newW = max(1, (int) round($w * $scale));
        $newH = max(1, (int) round($h * $scale));

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));

        // Whenever the photo does not cover the whole canvas, fill the empty area with
        // a blurred copy of the same photo instead of plain white.
        if ($newW < self::WIDTH - 1 || $newH < self::HEIGHT - 1) {
            self::paintBlurredBackground($canvas, $src, $w, $h);
        }

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

    /**
     * Build a smaller copy of an already stored image, scaled to $width and keeping its aspect ratio.
     * Returns null when the image is not wider than $width (the original is used then) or cannot be read.
     */
    public static function makeThumbnail(string $path, int $width): ?string
    {
        $info = @getimagesize($path);

        if (! $info || $info[0] <= $width) {
            return null;
        }

        [$srcW, $srcH, $type] = $info;

        $decoder = match ($type) {
            IMAGETYPE_JPEG => 'imagecreatefromjpeg',
            IMAGETYPE_PNG => 'imagecreatefrompng',
            IMAGETYPE_WEBP => 'imagecreatefromwebp',
            default => null,
        };

        if ($decoder === null || ! function_exists($decoder)) {
            return null;
        }

        if ($type === IMAGETYPE_WEBP && ! function_exists('imagewebp')) {
            return null;
        }

        $src = @$decoder($path);

        if (! $src) {
            return null;
        }

        $height = max(1, (int) round($srcH * $width / $srcW));
        $thumb = imagecreatetruecolor($width, $height);

        if ($type === IMAGETYPE_PNG) {
            // Keep transparency of older PNG files.
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            imagefill($thumb, 0, 0, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
        } else {
            imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));
        }

        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $width, $height, $srcW, $srcH);

        return match ($type) {
            IMAGETYPE_PNG => self::capture(fn () => imagepng($thumb, null, 9)),
            IMAGETYPE_WEBP => self::capture(fn () => imagewebp($thumb, null, 80)),
            default => self::capture(function () use ($thumb) {
                imageinterlace($thumb, true);
                imagejpeg($thumb, null, 80);
            }),
        };
    }

    /**
     * Paint a blurred, slightly darkened, cover-scaled copy of the photo over the whole canvas.
     * The copy is shrunk first, blurred while small (fast), then enlarged: that gives a smooth, strong blur.
     */
    private static function paintBlurredBackground(GdImage $canvas, GdImage $src, int $w, int $h): void
    {
        $smallW = 120;
        $smallH = 80;

        $small = imagecreatetruecolor($smallW, $smallH);
        imagefill($small, 0, 0, imagecolorallocate($small, 255, 255, 255));

        // Cover-scale the photo into the small image (centered crop).
        $scale = max($smallW / $w, $smallH / $h);
        $cropW = max(1, (int) round($smallW / $scale));
        $cropH = max(1, (int) round($smallH / $scale));
        $cropX = (int) max(0, floor(($w - $cropW) / 2));
        $cropY = (int) max(0, floor(($h - $cropH) / 2));

        imagecopyresampled($small, $src, 0, 0, $cropX, $cropY, $smallW, $smallH, $cropW, $cropH);

        for ($i = 0; $i < 4; $i++) {
            imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
        }

        imagefilter($small, IMG_FILTER_BRIGHTNESS, -12);

        imagecopyresampled($canvas, $small, 0, 0, 0, 0, self::WIDTH, self::HEIGHT, $smallW, $smallH);
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