<?php

namespace App\Console\Commands;

use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class ImportMedia extends Command
{
    protected $signature = 'media:import';

    protected $description = 'uploads/news ফোল্ডারের বিদ্যমান ছবি গ্যালারিতে যোগ করে (বারবার চালানো নিরাপদ)';

    public function handle(): int
    {
        $dir = public_path(Media::DIR);

        if (! File::isDirectory($dir)) {
            $this->warn('ছবির ফোল্ডার পাওয়া যায়নি: '.$dir);

            return self::SUCCESS;
        }

        $known = Media::pluck('filename')->flip();
        $added = 0;

        foreach (File::files($dir) as $file) {
            $name = $file->getFilename();

            if ($known->has($name) || ! in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)) {
                continue;
            }

            $time = Carbon::createFromTimestamp($file->getMTime());

            (new Media)->forceFill([
                'filename' => $name,
                'original_name' => $name,
                'mime' => File::mimeType($file->getPathname()) ?: null,
                'size' => $file->getSize(),
                'created_at' => $time,
                'updated_at' => $time,
            ])->save();

            $added++;
        }

        $this->info("{$added}টি ছবি গ্যালারিতে যোগ হয়েছে।");

        return self::SUCCESS;
    }
}