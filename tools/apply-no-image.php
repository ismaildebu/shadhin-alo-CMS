<?php

/**
 * One-time codemod: gives every frontend news template an @else branch that shows the
 * "no image" placeholder (frontend.partials.no-image) where the template used to show nothing.
 *
 *   php tools/apply-no-image.php --dry-run    list what would change, touch nothing
 *   php tools/apply-no-image.php              apply; originals are copied to storage/app/no-image-backup
 *
 * Safe to run twice: files that already use the placeholder are skipped. Templates whose
 * markup does not match the expected shapes are left unchanged and reported.
 */

$root = dirname(__DIR__);
$dry = in_array('--dry-run', $argv, true);

$skipNames = ['news.blade.php', 'article-full.blade.php', 'no-image.blade.php', 'bd-map-defs.blade.php'];
$backupDir = $root.'/storage/app/no-image-backup';

// A whole <img ...> tag; quoted attribute values may contain ">" (for example "$item->title").
$tag = '<img\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>';

/** Builds "@if(...) <img> @else @include(placeholder) @endif" from the pieces of the old block. */
$withFallback = function (string $condition, string $imgTag, string $newsVar): string {
    $class = preg_match('/\bclass="([^"]*)"/', $imgTag, $found) ? trim($found[1]) : '';
    $class = addcslashes($class, "'\\");

    return $condition."\n".$imgTag."\n@else\n@include('frontend.partials.no-image', ['news' => ".$newsVar.", 'sizeClass' => '".$class."'])\n@endif";
};

$changes = [];
$skipped = [];

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root.'/resources/views/frontend', FilesystemIterator::SKIP_DOTS)
);

foreach ($files as $file) {
    $name = $file->getFilename();

    if (! str_ends_with($name, '.blade.php') || in_array($name, $skipNames, true)) {
        continue;
    }

    $path = $file->getPathname();
    $source = file_get_contents($path);

    if ($source === false || str_contains($source, 'frontend.partials.no-image')) {
        continue;
    }

    $count = 0;
    $out = $source;

    // 1) @if($img($x)) <img> @endif   and   @if($x->image) <img src=".../uploads/news/..."> @endif
    $out = preg_replace_callback(
        '/(@if\(\s*(?:\$img\((\$\w+)\)|(\$\w+)->image)\s*\))\s*('.$tag.')\s*@endif/u',
        function (array $m) use ($withFallback, &$count) {
            $viaClosure = ($m[2] ?? '') !== '';

            // The ->image form must really be a news image, not another model's.
            if (! $viaClosure && ! str_contains($m[4], 'uploads/news')) {
                return $m[0];
            }

            $count++;

            return $withFallback($m[1], $m[4], $viaClosure ? $m[2] : $m[3]);
        },
        $out
    );

    // 2) @if($leadImg) <img> @endif   and   @if($firstImg) <img> @endif
    $out = preg_replace_callback(
        '/(@if\(\s*\$(leadImg|firstImg)\s*\))\s*('.$tag.')\s*@endif/u',
        function (array $m) use ($withFallback, &$count) {
            $count++;

            return $withFallback($m[1], $m[3], $m[2] === 'leadImg' ? '$lead' : '$first');
        },
        $out
    );

    // 3) existing grey placeholder spans (small cards and list thumbnails)
    $out = preg_replace_callback(
        '/<span class="([^"]*\bsa-nb-ph\b[^"]*)" aria-hidden="true"><\/span>/u',
        function (array $m) use (&$count) {
            $count++;

            $class = trim(preg_replace('/\bsa-nb-ph\b/', '', $m[1]));
            $class = addcslashes(preg_replace('/\s+/', ' ', $class), "'\\");

            return "@include('frontend.partials.no-image', ['news' => \$item, 'sizeClass' => '".$class."'])";
        },
        $out
    );

    if ($count === 0 || $out === null || $out === $source) {
        if (str_contains($source, 'uploads/news')) {
            $skipped[] = substr($path, strlen($root) + 1);
        }

        continue;
    }

    $relative = substr($path, strlen($root) + 1);
    $changes[$relative] = $count;

    if (! $dry) {
        $backup = $backupDir.'/'.$relative;

        if (! is_dir(dirname($backup))) {
            mkdir(dirname($backup), 0777, true);
        }

        copy($path, $backup);
        file_put_contents($path, $out);
    }
}

ksort($changes);

echo ($dry ? "DRY RUN - nothing was changed\n\n" : "Applied\n\n");

foreach ($changes as $file => $n) {
    echo sprintf("  %-70s %d place(s)\n", $file, $n);
}

echo "\nFiles changed: ".count($changes)."\n";

if ($skipped !== []) {
    echo "\nShow news images but have a different markup (left untouched):\n";

    foreach ($skipped as $file) {
        echo '  '.$file."\n";
    }
}

if (! $dry && $changes !== []) {
    echo "\nOriginals saved in storage/app/no-image-backup\n";
}
