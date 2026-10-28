<?php

namespace App\Support;

/**
 * Bengali letters such as য় can be stored in two visually identical ways
 * (U+09DF as one character, or U+09AF + U+09BC as two). Strings that look the
 * same then compare as different. Normalize before comparing.
 */
final class BengaliText
{
    /** Canonical (NFC) form, so equal-looking strings are byte-equal. */
    public static function normalize(?string $text): string
    {
        $text = (string) $text;

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);

            return $normalized === false ? $text : $normalized;
        }

        // Fallback without the intl extension: the Bengali composition cases.
        // (U+09DC/09DD/09DF are composition-exclusions, so NFC keeps the two-character form.)
        return strtr($text, [
            "\u{09DC}" => "\u{09A1}\u{09BC}", // ড়
            "\u{09DD}" => "\u{09A2}\u{09BC}", // ঢ়
            "\u{09DF}" => "\u{09AF}\u{09BC}", // য়
            "\u{09C7}\u{09BE}" => "\u{09CB}", // ো
            "\u{09C7}\u{09D7}" => "\u{09CC}", // ৌ
        ]);
    }

    public static function same(?string $a, ?string $b): bool
    {
        return self::normalize($a) === self::normalize($b);
    }

    /** @param  iterable<string>  $haystack */
    public static function in(?string $needle, iterable $haystack): bool
    {
        $needle = self::normalize($needle);

        foreach ($haystack as $item) {
            if (self::normalize($item) === $needle) {
                return true;
            }
        }

        return false;
    }
}
