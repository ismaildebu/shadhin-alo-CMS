<?php

namespace App\Console\Commands;

use App\Support\BanglaTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Lists files under public/uploads that no content and no code points at.
 *
 * This command only reports. Deleting is the desk's decision, and the answer is only
 * trustworthy against the database that actually serves readers — a dev database has
 * far fewer articles and would call published photos unused.
 */
class AuditUploads extends Command
{
    protected $signature = 'news:audit-uploads
        {--list : রেফারেন্সহীন ফাইলের পূর্ণ তালিকা দেখাও}';

    protected $description = 'কোন ছবি/ফাইল কোনো খবর বা কোডে ব্যবহার হচ্ছে না তার তালিকা; কিছুই মুছে ফেলা হয় না';

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

        $this->newLine();
        $this->warn('কিছুই মুছে ফেলা হয়নি — এটি শুধু তালিকা। প্রোডাকশনের ডেটাবেসে চালানোর পরেই সংখ্যা বিশ্বাসযোগ্য।');

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
