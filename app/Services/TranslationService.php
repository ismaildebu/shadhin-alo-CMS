<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Machine translation into Bangla for the trends subsystem.
 *
 * The default driver is Google's open web endpoint: it needs no account and no key, and
 * it is the reason foreign reports can be offered to a Bangla desk at all. Quality-first
 * installs can switch to Google Cloud or DeepL by putting a key in `.env` — the code
 * path is the same.
 *
 * Three things keep it honest:
 *   budget      one run only spends a fixed number of characters, so a bad feed hour
 *               cannot turn into an unbounded bill or a stalled queue;
 *   cache       a sentence is translated once and remembered;
 *   sanity      output that holds no Bangla letter, or is the input echoed back, is
 *               treated as a failure — never stored as if it were a translation.
 *
 * Text that fails is not dropped: the caller tags the draft "অনুবাদ প্রয়োজন" so the
 * desk sees it needs a human.
 */
class TranslationService
{
    private const BANGLA = '/[\x{0980}-\x{09FF}]/u';

    private int $budget;

    private int $used = 0;

    private int $requests = 0;

    public function __construct(?int $budget = null)
    {
        $this->budget = max(0, $budget ?? (int) config('news_trends.translate.max_chars_per_run', 6000));
    }

    public function remaining(): int
    {
        return max(0, $this->budget - $this->used);
    }

    public function used(): int
    {
        return $this->used;
    }

    public function requests(): int
    {
        return $this->requests;
    }

    /**
     * @return array{text: string, ok: bool, note: string}
     */
    public function toBangla(string $text, ?string $from = null): array
    {
        $text = trim($text);

        if ($text === '') {
            return ['text' => '', 'ok' => false, 'note' => 'অনুবাদ করার মতো লেখা নেই'];
        }

        // Already Bangla: nothing to ask of the provider, and no budget spent.
        if (preg_match(self::BANGLA, $text) === 1) {
            return ['text' => $text, 'ok' => true, 'note' => 'বাংলা ছিল'];
        }

        $target = (string) config('translate.target_language', 'bn');
        $key = 'translate.'.$target.'_'.sha1(mb_strtolower($text));
        $cached = Cache::get($key);

        if (is_string($cached) && $cached !== '') {
            return ['text' => $cached, 'ok' => true, 'note' => 'ক্যাশ থেকে'];
        }

        if ($this->remaining() < 1) {
            return ['text' => $text, 'ok' => false, 'note' => 'অনুবাদের বাজেট শেষ'];
        }

        $source = trim((string) ($from ?? config('translate.source_language', 'en'))) ?: 'auto';
        $chunks = $this->chunks($text, max(200, (int) config('news_trends.translate.max_chars_per_request', 1500)));
        $parts = [];

        foreach ($chunks as $chunk) {
            $cost = min(mb_strlen($chunk), $this->remaining());

            if ($cost < 1) {
                $parts = [];

                break;
            }

            $this->used += $cost;
            $translated = $this->request($chunk, $source);

            if ($translated === null) {
                $parts = [];

                break;
            }

            $parts[] = $translated;
        }

        $out = trim(implode(' ', $parts));

        if ($out === '' || ! $this->looksTranslated($out, $text)) {
            return ['text' => $text, 'ok' => false, 'note' => 'অনুবাদ পাওয়া যায়নি'];
        }

        Cache::put($key, $out, now()->addHours((int) config('translate.cache_hours', 720)));

        return ['text' => $out, 'ok' => true, 'note' => ''];
    }

    /**
     * Translate a paragraph body without disturbing its structure: every `<p>` is
     * un-wrapped, translated on its own and re-wrapped, so one long report never
     * becomes one over-limit request.
     *
     * @return array{html: string, translated: bool, note: string}
     */
    public function translateParagraphs(string $html, ?string $from = null): array
    {
        if (trim(strip_tags($html)) === '') {
            return ['html' => '', 'translated' => false, 'note' => 'লেখা নেই'];
        }

        $translatedAll = true;
        $notes = [];
        $out = [];

        foreach ($this->paragraphs($html) as $paragraph) {
            $result = $this->toBangla($paragraph, $from);

            if (! $result['ok']) {
                $translatedAll = false;

                if ($result['note'] !== '' && ! in_array($result['note'], $notes, true)) {
                    $notes[] = $result['note'];
                }
            }

            $out[] = '<p>'.e($result['text']).'</p>';
        }

        return [
            'html' => implode("\n", $out),
            'translated' => $translatedAll,
            'note' => $translatedAll ? '' : $notes[0] ?? 'আংশিক অনুবাদ',
        ];
    }

    /** Output must be Bangla and must not simply be the input handed back. */
    private function looksTranslated(string $out, string $input): bool
    {
        return preg_match(self::BANGLA, $out) === 1 && trim($out) !== trim($input);
    }

    /**
     * Sentence-packed pieces, each within the provider's limit.
     *
     * @return list<string>
     */
    private function chunks(string $text, int $size): array
    {
        $sentences = preg_split('/(?<=[.!?।])\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [trim($text)];
        $pieces = [];
        $current = '';

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);

            if ($sentence === '') {
                continue;
            }

            // A single run-on sentence is cut on a space rather than dropped.
            while (mb_strlen($sentence) > $size) {
                $cut = mb_strrpos(mb_substr($sentence, 0, $size), ' ') ?: $size;
                $pieces[] = trim(mb_substr($sentence, 0, $cut));
                $sentence = trim(mb_substr($sentence, $cut));
            }

            if ($current !== '' && mb_strlen($current) + mb_strlen($sentence) + 1 > $size) {
                $pieces[] = $current;
                $current = '';
            }

            $current = $current === '' ? $sentence : $current.' '.$sentence;
        }

        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }

    /** @return list<string> plain text of each paragraph in the given HTML */
    private function paragraphs(string $html): array
    {
        $blocks = preg_split('/<\/p>\s*<p[^>]*>/iu', preg_replace(['/^\s*<p[^>]*>/iu', '/<\/p>\s*$/iu'], '', trim($html))) ?: [];
        $out = [];

        foreach ($blocks as $block) {
            $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($block), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

            if ($text !== '') {
                $out[] = $text;
            }
        }

        return $out;
    }

    private function request(string $text, string $source): ?string
    {
        $driver = (string) config('translate.driver', 'google_web');

        try {
            $this->requests++;

            return match ($driver) {
                'google_cloud' => $this->googleCloud($text, $source),
                'deepl' => $this->deepl($text, $source),
                'mock' => null,
                default => $this->googleWeb($text, $source),
            };
        } catch (Throwable $e) {
            logger()->warning('অনুবাদ ব্যর্থ ('.$driver.'): '.$e->getMessage());

            return null;
        }
    }

    /** The free web endpoint the site's own translation feature already relies on. */
    private function googleWeb(string $text, string $source): ?string
    {
        $response = $this->http()->get((string) config('translate.google_web.endpoint'), [
            'client' => (string) config('translate.google_web.client', 'gtx'),
            'sl' => $source,
            'tl' => (string) config('translate.target_language', 'bn'),
            'dt' => 't',
            'q' => $text,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload[0]) || ! is_array($payload[0])) {
            return null;
        }

        $out = '';

        foreach ($payload[0] as $segment) {
            if (is_array($segment) && isset($segment[0]) && is_string($segment[0])) {
                $out .= $segment[0];
            }
        }

        return trim($out) === '' ? null : trim($out);
    }

    private function googleCloud(string $text, string $source): ?string
    {
        $key = (string) config('translate.google_cloud.api_key', '');

        if ($key === '') {
            return null;
        }

        $response = $this->http()->postJson((string) config('translate.google_cloud.endpoint'), array_filter([
            'q' => [$text],
            'source' => $source === 'auto' ? null : $source,
            'target' => (string) config('translate.target_language', 'bn'),
            'format' => 'text',
            'key' => $key,
        ], static fn ($value) => $value !== null));

        $translated = $response->json('data.translations.0.translatedText');

        return is_string($translated) && trim($translated) !== '' ? trim($translated) : null;
    }

    private function deepl(string $text, string $source): ?string
    {
        $key = (string) config('translate.deepl.api_key', '');

        if ($key === '') {
            return null;
        }

        $response = $this->http()->asForm()->post((string) config('translate.deepl.endpoint'), array_filter([
            'auth_key' => $key,
            'text' => $text,
            'source_lang' => $source === 'auto' ? null : strtoupper($source),
            'target_lang' => strtoupper((string) config('translate.target_language', 'bn')),
        ], static fn ($value) => $value !== null));

        $translated = $response->json('translations.0.text');

        return is_string($translated) && trim($translated) !== '' ? trim($translated) : null;
    }

    private function http(): PendingRequest
    {
        $request = config('news_trends.translate.request', []);

        $http = Http::timeout(max(1, (int) ($request['timeout'] ?? 12)))
            ->connectTimeout(max(1, (int) ($request['connect_timeout'] ?? 6)))
            ->acceptJson()
            ->withHeaders(['User-Agent' => (string) config('news_trends.request.user_agent')])
            ->withOptions(['allow_redirects' => false]);

        // সার্টিফিকেট যাচাই ডিফল্টেই চালু; শুধু লোকাল ডেভেলপমেন্টে বন্ধ করা যায়।
        if (! config('translate.verify_ssl', true)) {
            $http = $http->withoutVerifying();
        }

        return $http;
    }
}
