<?php

namespace App\Console\Commands;

use App\Support\NewsImport\NewsImporter;
use Illuminate\Console\Command;

class ImportNews extends Command
{
    protected $signature = 'news:import
        {--dry-run : কিছুই সংরক্ষণ না করে শুধু দেখাবে কী আসবে}
        {--refresh : আগে আনা সংবাদগুলোর পূর্ণ প্রতিবেদন আবার এনে বসাও}
        {--force : --refresh-এর সাথে; বডি আগেই পূর্ণ হলেও আবার আনো}
        {--source=* : নির্দিষ্ট সোর্স (যেমন prothomalo); না দিলে সব সোর্স}
        {--limit= : প্রতি সোর্সে সর্বোচ্চ কতটি নতুন সংবাদ}';

    protected $description = 'কনফিগ করা RSS ফিড থেকে সংবাদ ও ছবি এনে খসড়া হিসেবে রাখে';

    private const STATE_LABELS = [
        'new' => 'নতুন',
        'imported' => 'যোগ হয়েছে',
        'duplicate' => 'ডুপ্লিকেট',
        'old' => 'পুরোনো',
        'skipped' => 'বাদ',
        'failed' => 'ব্যর্থ',
    ];

    public function handle(): int
    {
        $config = (array) config('news_import', []);

        if (empty($config['enabled'])) {
            $this->warn('স্বয়ংক্রিয় সংগ্রহ বন্ধ আছে (config/news_import.php → enabled)।');

            return self::FAILURE;
        }

        $only = array_values(array_filter((array) $this->option('source'), fn ($key) => is_string($key) && $key !== ''));
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($this->option('refresh')) {
            return $this->refresh($only, (bool) $this->option('force'));
        }

        $this->info($dryRun
            ? 'প্রিভিউ: কিছুই সংরক্ষণ করা হবে না।'
            : 'নতুন সংবাদ খসড়া হিসেবে সংরক্ষণ করা হচ্ছে...');
        $this->newLine();

        $blocks = NewsImporter::run($only, $limit, $dryRun);

        $counts = ['new' => 0, 'imported' => 0, 'duplicate' => 0, 'old' => 0, 'skipped' => 0, 'failed' => 0];
        $samples = [];

        foreach ($blocks as $key => $block) {
            $this->line('<options=bold>'.$block['name'].'</> <fg=gray>('.$key.')</>  '.$block['feed']);

            if ($block['error']) {
                $this->error('  ফিড আনা যায়নি: '.$block['error']);
                $this->newLine();

                continue;
            }

            $rows = [];

            foreach ($block['items'] as $item) {
                $state = (string) ($item['state'] ?? 'new');
                $counts[$state] = ($counts[$state] ?? 0) + 1;

                $reason = (string) ($item['reason'] ?? '');

                if ($state === 'new') {
                    $reason = $dryRun ? 'সংরক্ষণ হবে' : '';
                }

                if (is_string($item['image'] ?? null) && $item['image'] !== '') {
                    $image = (string) (parse_url($item['image'], PHP_URL_HOST) ?: 'আছে');

                    if (count($samples) < 2) {
                        $samples[] = $item['image'];
                    }
                } else {
                    $image = '—';
                }

                $rows[] = [
                    self::STATE_LABELS[$state] ?? $state,
                    $item['category'] ?? '—',
                    mb_strimwidth((string) $item['title'], 0, 78, '…', 'UTF-8'),
                    $image,
                    $reason,
                ];
            }

            if ($rows === []) {
                $this->line('  ফিডে নতুন কিছু পাওয়া যায়নি।');
            } else {
                $this->table(['অবস্থা', 'বিভাগ', 'শিরোনাম', 'ছবি', 'কারণ'], $rows);
            }

            $this->newLine();
        }

        if ($samples !== []) {
            $this->line('<fg=gray>নমুনা ছবির ঠিকানা:</>');

            foreach ($samples as $sample) {
                $this->line('  '.$sample);
            }

            $this->newLine();
        }

        $this->line(sprintf(
            'মোট: %d নতুন, %d ডুপ্লিকেট, %d পুরোনো, %d বাদ%s।',
            $counts['new'] + $counts['imported'],
            $counts['duplicate'],
            $counts['old'],
            $counts['skipped'],
            $counts['failed'] > 0 ? ', '.$counts['failed'].' ব্যর্থ' : ''
        ));

        if ($dryRun) {
            $this->info('প্রিভিউ শেষ — কোনো সংবাদ বা ছবি সংরক্ষণ হয়নি।');
        } else {
            $this->info($counts['imported'].'টি সংবাদ খসড়া হিসেবে যোগ হয়েছে। সম্পাদক পর্যালোচনা করে প্রকাশ করবেন।');
        }

        if ($blocks !== [] && collect($blocks)->every(fn ($block) => $block['error'] !== null)) {
            $this->error('কোনো ফিডই আনা যায়নি — ইন্টারনেট বা ফিডের ঠিকানা দেখুন।');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Backfill full bodies for drafts that were imported before the extractor existed.
     *
     * @param  list<string>  $only
     */
    private function refresh(array $only, bool $force = false): int
    {
        $this->info('আগে আনা সংবাদগুলোর পূর্ণ প্রতিবেদন আনা হচ্ছে...');
        $this->newLine();

        $result = NewsImporter::refresh($only, $force);

        if ($result['rows'] !== []) {
            $this->table(
                ['#', 'অবস্থা', 'সোর্স', 'শিরোনাম', 'ফলাফল'],
                array_map(fn (array $row) => [
                    $row['id'],
                    self::STATE_LABELS[$row['state']] ?? $row['state'],
                    $row['source'],
                    mb_strimwidth((string) $row['title'], 0, 78, '…', 'UTF-8'),
                    $row['reason'],
                ], $result['rows'])
            );
        }

        $this->newLine();
        $this->line(sprintf(
            'মোট: %d হালনাগাদ, %d বাদ, %d ব্যর্থ।',
            $result['updated'],
            $result['skipped'],
            $result['failed']
        ));

        $this->info('হালনাগাদ হওয়া খসড়াগুলো সম্পাদক পর্যালোচনা করে প্রকাশ করবেন।');

        return $result['failed'] > 0 && $result['updated'] === 0 ? self::FAILURE : self::SUCCESS;
    }
}
