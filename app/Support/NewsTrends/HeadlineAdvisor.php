<?php

namespace App\Support\NewsTrends;

use App\Services\TranslationService;
use App\Support\NewsImport\LocationMatcher;
use Illuminate\Support\Str;

/**
 * What the desk gets when a trending term has no report we may run.
 *
 * Three working headlines, not invented facts:
 *   1. the strongest real headline from the feed's own news items, in Bangla;
 *   2. an explainer framing of the term itself — what happened and why it matters;
 *   3. a place or follow-up angle, using the district our own location matcher finds.
 *
 * Only line 1 carries a claim; lines 2 and 3 are deliberately frames to write into.
 */
final class HeadlineAdvisor
{
    /**
     * @param  array{query: string, items: list<array{title: string, link: string, source: string, image: ?string}>}  $term
     * @return array{headlines: list<string>, translated: bool, note: string}
     */
    public static function for(array $term, array $config, ?TranslationService $translator = null): array
    {
        $query = trim((string) ($term['query'] ?? ''));
        $titles = self::cleanTitles((array) ($term['items'] ?? []));
        $language = Relevance::language($query.($titles[0] ?? ''));
        $translated = true;
        $note = '';

        if ($language !== 'bn' && $titles !== []) {
            $service = $translator ?? new TranslationService;
            $result = $service->toBangla($titles[0], 'auto');
            $translated = $result['ok'];
            $note = $result['note'];
            $lead = $translated ? $result['text'] : $titles[0];
        } else {
            $lead = $titles[0] ?? '';
        }

        $headlines = [];

        if ($lead !== '') {
            $headlines[] = self::clip($lead);
        }

        $headlines[] = self::clip(($query !== '' ? $query : Str::before($headlines[0] ?? '', ':')).': কী ঘটল, কেন তাৎপর্য');

        $place = self::place($query.' '.implode(' ', $titles));

        $headlines[] = $place === ''
            ? self::clip('“'.$query.'” নিয়ে পাঠক যা জানতে চায়')
            : self::clip($place.': '.$query.' নিয়ে প্রতিক্রিয়া ও পরবর্তী পদক্ষেপ');

        return [
            'headlines' => array_values(array_unique(array_filter($headlines))),
            'translated' => $translated,
            'note' => $note,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<string>
     */
    private static function cleanTitles(array $items): array
    {
        $titles = [];

        foreach ($items as $item) {
            $title = trim(preg_replace('/\s+/u', ' ', (string) ($item['title'] ?? '')) ?? '');

            // Feed titles often carry "… | Publisher" — the paper name is not the headline.
            $title = trim(Str::beforeLast($title, '|'));

            if (mb_strlen($title) >= 8) {
                $titles[] = $title;
            }
        }

        return array_values(array_unique($titles));
    }

    /** Bangla district name for the place angle; empty when no place is recognised. */
    private static function place(string $text): string
    {
        try {
            return (string) (LocationMatcher::detect($text)?->name_bn ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    private static function clip(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return mb_strlen($text) > 160 ? rtrim(mb_substr($text, 0, 157)).'…' : $text;
    }
}
