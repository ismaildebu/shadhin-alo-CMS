<?php

namespace App\Support;

final class Colors
{
    public static function isHex(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    /** গাঢ় রং হলে true: তখন ব্লকের লেখা সাদা করা হয়। */
    public static function isDark(string $hex): bool
    {
        $n = hexdec(ltrim($hex, '#'));
        $r = ($n >> 16) & 255;
        $g = ($n >> 8) & 255;
        $b = $n & 255;

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) < 140;
    }
}