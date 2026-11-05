<?php

namespace App\Console\Commands;

use App\Support\BanglaTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Reports the files under public/uploads that no content and no code points at, and — only
 * after the desk answers yes to --delete — removes exactly those.
 *
 * The count is only trustworthy against the database that actually serves readers: a dev
 * database has far fewer articles and would call published photos unused. That is why the
 * numbers are printed first and an empty answer deletes nothing.
 */
class AuditUploads extends Command
{
    protected $signature = 'news:audit-uploads
        {--list : রেফারেন্সহীন ফাইলের পূর্ণ তালিকা দেখাও}
        {--delete : যে ফাইল কোনো খবর, মিডিয়া লাইব্রেরি বা কোডে নেই সেগুলো মুছে ফেলো; আগে অনুমোদন চাইবে}
        {--force : মুছে ফেলার আগে প্রশ্ন করা হবে না}';

    protected $description = 'কোন ছবি/ফাইল কোনো খবর বা কোডে ব্যবহার হচ্ছে না তার তালিকা; --delete দিলে জিজ্ঞেস করে শুধু সেগুলোই মুছে ফেলে';

    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'mp3', 'mp4', 'svg'];

    private const REFERENCE_PATTERN = '#([^\s"\'()<>{},\\\\/]+\.(?:jpg|jpeg|png|webp|gif|pdf|mp3|mp4|svg))#i';

    public function handle(): int
    {
        $root = public_path('uploads');

        if (! is_dir($root)) {
            $this->error('public/uploads ফোল্ডারটি পাওয়া যায়নি।');

            return self::FAILURE;
        }

        $files = $this->filesOnDisk($root);

        if ($files === []) {
            $this->info('public/uploads খালি।');

            return self::SUCCESS;
        }

        $this->components->info('রেফারেন্স খোঁজা হচ্ছে: সম্পূর্ণ ডেটাবেস + কোড');

        $referenced = $this->referencedNames();

        $unused = [];
        $bytes = 0;

        foreach ($files as $path) {
            $name = strtolower(basename($path));

            if (isset($referenced[$name])) {
                continue;
            }

            $unused[] = $path;
            $bytes += (int) @filesize($path);
        }

        $this->newLine();
        $this->table(['মেপে', 'মান'], [
            ['আপলোড ফাইল', $this->bn(count($files))],
            ['ব্যবহৃত', $this->bn(count($files) - count($unused))],
            ['অব্যবহৃত', $this->bn(count($unused))],
            ['অব্যবহৃতের সাইজ', $this->bn(round($bytes / 1048576, 1)).' MB'],
            ['ডেটাবেস', (string) config('database.default')],
        ]);

        if ($this->option('list')) {
            $this->newLine();

            foreach ($unused as $path) {
                $relative = str_replace([public_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $path);
                $this->line($relative);
            }
        }

        if ($this->option('delete')) {
            return $this->deleteUnused($unused, $bytes);
        }

        $this->newLine();
        $this->warn('কিছুই মুছে ফেলা হয়নি — এটি শুধু তালিকা। প্রোডাকশনের ডেটাবেসে চালানোর পরেই সংখ্যা বিশ্বাসযোগ্য।');

        return self::SUCCESS;
    }

    /**
     * Only the files this database does not know. An empty answer — or no answer at all —
     * leaves the folder exactly as it was.
     *
     * @param  list<string>  $unused
     */
    private function deleteUnused(array $unused, int $bytes): int
    {
        $this->newLine();

        if ($unused === []) {
            $this->info('মুছে ফেলার মতো কিছু নেই — প্রতিটি ফাইল কোথাও না কোথাও ব্যবহৃত।');

            return self::SUCCESS;
        }

        if (! $this->option('force')
            && ! $this->confirm('এই '.$this->bn(count($unused)).'টি ফাইল ('.$this->bn(round($bytes / 1048576, 1)).' MB) মুছে দেওয়া হবে? ব্যবহৃত ফাইল ছোঁয়া হবে না।')) {
            $this->warn('কিছুই মুছে ফেলা হয়নি।');

            return self::SUCCESS;
        }

        $deleted = 0;

        foreach ($unused as $path) {
            if (File::delete($path)) {
                $deleted++;
            }
        }

        $this->newLine();
        $this->info($this->bn($deleted).'টি ফাইল মুছে ফেলা হয়েছে।');
        $this->line('ফাইলগুলো গিটে ছিল — ফেরাতে চাইলে: git checkout HEAD -- public/uploads');

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function filesOnDisk(string $root): array
    {
        $walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        $files = [];

        foreach ($walk as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getFilename() === '.htaccess') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }

    /** Every filename that some row or some source file mentions. @return array<string,true> */
    private function referencedNames(): array
    {
        $names = [];

        foreach ($this->codeFiles() as $file) {
            $this->collectFrom((string) file_get_contents($file), $names);
        }

        $like = [];

        foreach (self::EXTENSIONS as $ext) {
            $like[] = "%.{$ext}%";
        }

        foreach (Schema::getTables() as $table) {
            $name = (string) $table['name'];

            if (str_starts_with($name, 'sqlite_')) {
                continue;
            }

            foreach (Schema::getColumns($name) as $column) {
                if (! preg_match('/text|char|varchar|clob|json/i', (string) $column['type'])) {
                    continue;
                }

                $query = DB::table($name)
                    ->select($column['name'])
                    ->where(function ($q) use ($column, $like) {
                        foreach ($like as $index => $pattern) {
                            $index === 0
                                ? $q->where($column['name'], 'like', $pattern)
                                : $q->orWhere($column['name'], 'like', $pattern);
                        }
                    });

                foreach ($query->cursor() as $row) {
                    foreach ((array) $row as $value) {
                        $this->collectFrom((string) $value, $names);
                    }
                }
            }
        }

        return $names;
    }

    /** @return list<string> */
    private function codeFiles(): array
    {
        $roots = [app_path(), base_path('resources'), base_path('routes'), base_path('config'), public_path('css'), public_path('js')];
        $files = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            foreach ($walk as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && preg_match('/\.(php|blade\.php|js|css|json)$/i', $file->getFilename())) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    private function collectFrom(string $value, array &$names): void
    {
        if (preg_match_all(self::REFERENCE_PATTERN, $value, $matches)) {
            foreach ($matches[1] as $file) {
                $names[strtolower(basename(str_replace('\\', '/', $file)))] = true;
            }
        }
    }

    private function bn(int|float $value): string
    {
        return BanglaTime::digits((string) $value);
    }
}
