<?php

namespace App\Console\Commands;

use App\Models\Media;
use Illuminate\Console\Command;

class BuildImageThumbnails extends Command
{
    protected $signature = 'images:thumbnails {--force : Rebuild thumbnails that already exist}';

    protected $description = 'Build 400px and 800px thumbnails for images already in the gallery';

    public function handle(): int
    {
        $built = 0;
        $skipped = 0;
        $missing = 0;
        $force = (bool) $this->option('force');

        Media::query()->orderBy('id')->chunkById(100, function ($items) use (&$built, &$skipped, &$missing, $force) {
            foreach ($items as $media) {
                $source = public_path(Media::DIR.'/'.$media->filename);

                if (! is_file($source)) {
                    $missing++;

                    continue;
                }

                $needed = $force;

                if (! $needed) {
                    foreach (Media::THUMB_WIDTHS as $width) {
                        if (! is_file(Media::thumbDir($width).'/'.$media->filename)) {
                            $needed = true;
                            break;
                        }
                    }
                }

                if (! $needed) {
                    $skipped++;

                    continue;
                }

                Media::writeThumbnails($media->filename);
                $built++;
            }
        });

        $this->info("Processed: {$built}, already done: {$skipped}, original file missing: {$missing}");

        return self::SUCCESS;
    }
}