<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * ই-পেপার পাতার লেআউটের চুক্তি ও বিশুদ্ধকরণ।
 *
 * layout = ['columns' => [['width' => ওজন, 'blocks' => [['news_id', 'image', 'image_height']]]]]
 *
 * প্রস্থ আপেক্ষিক ওজন (CSS flex-grow), যোগফল ১০০ হওয়া বাধ্যতামূলক নয় — তাই বারবার
 * সেভ করলেও কলামের অনুপাত বদলায় না, আর অ্যাডমিনের স্টেজ ও পাবলিক পাতা একই দেখায়।
 * সব সংখ্যা এখানেই ক্ল্যাম্প হয়; ভুল আকৃতি মানে InvalidArgumentException,
 * যাতে খারাপ JSON ডেটাবেসে ঢোকে না।
 */
final class EpaperLayout
{
    public const MIN_COLUMNS = 1;

    public const MAX_COLUMNS = 6;

    /** শতাংশে কলামের প্রস্থের সীমা (অনুপাত বজায় রাখতেই এই সীমা)। */
    public const MIN_WIDTH = 12.0;

    public const MAX_WIDTH = 70.0;

    /** টেনে ছবির উচ্চতা বদলানোর সীমা, পিক্সেল। */
    public const MIN_IMAGE_HEIGHT = 90;

    public const MAX_IMAGE_HEIGHT = 520;

    public const IMAGE_NONE = 'none';

    public const IMAGE_AUTO = 'auto';

    public const IMAGE_CUSTOM = 'custom';

    public const IMAGE_MODES = [self::IMAGE_NONE, self::IMAGE_AUTO, self::IMAGE_CUSTOM];

    /** পড়ার সময় খারাপ বা খালি JSON হলেও পাতা ভাঙে না — ডিফল্ট তিন কলাম ফেরত দেয়। */
    public static function read(?array $layout): array
    {
        try {
            return self::sanitize($layout ?? []);
        } catch (InvalidArgumentException) {
            return self::blank();
        }
    }

    /** নতুন পাতার শুরু: তিন সমান খালি কলাম। */
    public static function blank(int $columns = 3): array
    {
        $columns = max(self::MIN_COLUMNS, min(self::MAX_COLUMNS, $columns));

        $list = [];

        for ($i = 0; $i < $columns; $i++) {
            $list[] = ['width' => 100 / $columns, 'blocks' => []];
        }

        return self::sanitize(['columns' => $list]);
    }

    /**
     * @param  array<string, mixed>  $layout
     * @return array{columns: list<array{width: float, blocks: list<array{news_id: int, image: string, image_height: int|null}>}>}
     */
    public static function sanitize(array $layout): array
    {
        $columns = $layout['columns'] ?? null;

        if (! is_array($columns) || $columns === []) {
            throw new InvalidArgumentException('পাতায় অন্তত একটি কলাম থাকতে হবে।');
        }

        if (count($columns) > self::MAX_COLUMNS) {
            throw new InvalidArgumentException('এক পাতায় সর্বোচ্চ '.self::MAX_COLUMNS.'টি কলাম রাখা যাবে।');
        }

        $clean = [];

        foreach (array_values($columns) as $index => $column) {
            if (! is_array($column)) {
                throw new InvalidArgumentException('কলাম '.($index + 1).': গঠন ঠিক নয়।');
            }

            $clean[] = [
                'width' => self::width($column['width'] ?? null, $index),
                'blocks' => self::blocks($column['blocks'] ?? [], $index),
            ];
        }

        return ['columns' => $clean];
    }

    /** পাতার সব খবরের id (পুনরাবৃত্তি বাদ দিয়ে), ক্রম অনুযায়ী। */
    public static function newsIds(array $layout): array
    {
        $ids = [];

        foreach ($layout['columns'] ?? [] as $column) {
            foreach ($column['blocks'] ?? [] as $block) {
                $ids[(int) $block['news_id']] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * প্রকাশিত খবর বাদে আর কিছু পাওয়া গেলে পাতা ভাঙে না — ব্লকটি চুপচাপ বাদ পড়ে।
     *
     * @param  array<int>  $publishedIds
     */
    public static function prune(array $layout, array $publishedIds): array
    {
        $allowed = array_flip($publishedIds);
        $columns = [];

        foreach ($layout['columns'] ?? [] as $column) {
            $blocks = array_values(array_filter(
                $column['blocks'] ?? [],
                fn (array $block) => isset($allowed[(int) $block['news_id']])
            ));

            $columns[] = ['width' => $column['width'], 'blocks' => $blocks];
        }

        return ['columns' => $columns];
    }

    private static function width(mixed $width, int $index): float
    {
        if (! is_numeric($width)) {
            throw new InvalidArgumentException('কলাম '.($index + 1).': প্রস্থ সংখ্যায় লিখুন।');
        }

        return round(max(self::MIN_WIDTH, min(self::MAX_WIDTH, (float) $width)), 2);
    }

    /** @param  mixed  $blocks  আসে JSON থেকে, তাই প্রতিটি ধাপ আলাদা যাচাই। */
    private static function blocks(mixed $blocks, int $index): array
    {
        if (! is_array($blocks)) {
            throw new InvalidArgumentException('কলাম '.($index + 1).': ব্লকের তালিকা ঠিক নয়।');
        }

        $clean = [];

        foreach (array_values($blocks) as $position => $block) {
            if (! is_array($block) || ! isset($block['news_id']) || ! is_numeric($block['news_id'])) {
                throw new InvalidArgumentException(
                    'কলাম '.($index + 1).', ব্লক '.($position + 1).': কোনো সংবাদ নির্বাচন করুন।'
                );
            }

            $mode = in_array($block['image'] ?? self::IMAGE_AUTO, self::IMAGE_MODES, true)
                ? (string) ($block['image'] ?? self::IMAGE_AUTO)
                : self::IMAGE_AUTO;

            $height = null;

            if ($mode === self::IMAGE_CUSTOM) {
                $height = is_numeric($block['image_height'] ?? null)
                    ? (int) $block['image_height']
                    : self::MIN_IMAGE_HEIGHT;

                $height = max(self::MIN_IMAGE_HEIGHT, min(self::MAX_IMAGE_HEIGHT, $height));
            }

            $clean[] = [
                'news_id' => (int) $block['news_id'],
                'image' => $mode,
                'image_height' => $height,
            ];
        }

        return $clean;
    }
}
