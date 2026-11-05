<?php

namespace App\Console\Commands;

use App\Support\NewsTrends\TrendsImporter;
use Illuminate\Console\Command;

class ImportTrends extends Command
{
    protected $signature = 'news:trends
        {--dry-run : কিছুই সংরক্ষণ না করে দেখাবে কী আসত এবং কীসের শিরোনাম পরামর্শ দেওয়া হলো}
        {--limit= : সর্বোচ্চ কতটি ট্রেন্ড দেখা হবে (ফিডে ১০টি থাকে)}
        {--verify= : একটি সংবাদ-ঠিকানা যাচাই করে দেখাবে; কিছুই সংরক্ষণ হবে না}';

    protected $description = 'বাংলাদেশে আলোচিত সার্চ (Google Trends) থেকে যাচাই করা খবর খসড়া হিসেবে আনে; না মিললে সম্ভাব্য শিরোনাম পরামর্শ দেয়';

    private const VERDICT_LABELS = [
        'imported' => 'যোগ হয়েছে',
        'suggested' => 'পরামর্শ',
        'duplicate' => 'আগেই আছে',
        'rejected' => 'বাদ',
    ];

    public function handle(): int
    {
        $config = (array) config('news_trends', []);

        $verify = is_string($this->option('verify')) ? trim((string) $this->option('verify')) : '';

        if ($verify !== '') {
            return $this->verify($verify, $config);
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

        $this->info($dryRun
            ? 'প্রিভিউ: কোনো সংবাদ সংরক্ষণ করা হবে না।'
            : 'আলোচিত সার্চ থেকে খবর আনা হচ্ছে...');
        $this->newLine();

        $result = TrendsImporter::run($dryRun, $limit);

        if ($result['error'] !== null) {
            $this->error((string) $result['error']);

            return self::FAILURE;
        }

        $rows = [];

        foreach ($result['rows'] as $row) {
            $verdict = (string) ($row['verdict'] ?? 'rejected');

            $rows[] = [
                self::VERDICT_LABELS[$verdict] ?? $verdict,
                mb_strimwidth((string) $row['query'], 0, 34, '…', 'UTF-8'),
                (string) $row['traffic'],
                $row['language'] === 'bn' ? 'বাংলা' : 'বিদেশি',
                mb_strimwidth((string) ($row['host'] ?: '—'), 0, 26, '…', 'UTF-8'),
                mb_strimwidth((string) $row['reason'], 0, 60, '…', 'UTF-8'),
            ];
        }

        $this->table(['অবস্থা', 'ট্রেন্ড', 'খোঁজ সংখ্যা', 'ভাষা', 'উৎস', 'কারণ'], $rows);

        foreach ($result['rows'] as $row) {
            if ($row['headlines'] === []) {
                continue;
            }

            $this->line('<options=bold>“'.((string) $row['query']).'” এর জন্য সম্ভাব্য শিরোনাম:</>');

            foreach ($row['headlines'] as $index => $headline) {
                $this->line('  '.($index + 1).'. '.$headline);
            }

            $this->newLine();
        }

        $this->line(sprintf(
            'মোট: %d খসড়া, %d পরামর্শ, %d আগেই ছিল, %d বাদ। অনুবাদ: %d অক্ষর, %d বার।',
            $result['imported'],
            $result['suggestions'],
            $result['duplicates'],
            $result['rejected'],
            $result['translation_used'],
            $result['translation_requests']
        ));

        if (($result['forecasts'] ?? 0) > 0) {
            $this->line('আগামী ট্রেন্ডের তালিকা: '.$result['forecasts'].'টি আইটেম নতুন করে গণনা হয়েছে।');
        }

        $this->newLine();
        $this->info($dryRun
            ? 'প্রিভিউ শেষ — কিছুই সংরক্ষণ হয়নি।'
            : $result['imported'].'টি সংবাদ খসড়া হিসেবে যোগ হয়েছে। সম্পাদক পর্যালোচনা করে প্রকাশ করবেন।');

        return self::SUCCESS;
    }

    /** @param  array<string, mixed>  $config */
    private function verify(string $url, array $config): int
    {
        $this->info('উৎস যাচাই: '.$url);
        $this->newLine();

        $result = TrendsImporter::verifySource($url, $config);

        $this->table(
            ['হোস্ট', 'তালিকায়', 'অবস্থা', 'প্রতিবেদন', 'ছবি', 'তারিখ / কারণ'],
            [[
                $result['host'],
                $result['blocked'] ? 'বাজি সাইট (বাদ)' : ($result['allowed'] ? 'অনুমোদিত' : 'নেই'),
                $result['status'],
                $result['body_chars'].' অক্ষর',
                $result['image'] ? 'আছে' : '—',
                trim($result['published_at'].' '.$result['reason']),
            ]]
        );

        return $result['body_chars'] > 0 && $result['published_at'] !== null ? self::SUCCESS : self::FAILURE;
    }
}
