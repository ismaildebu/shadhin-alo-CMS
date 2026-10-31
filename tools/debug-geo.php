<?php

/**
 * Shows step by step why the district/upazila map does or does not appear.
 *
 *   php tools/debug-geo.php                 uses the upazila "manirampur"
 *   php tools/debug-geo.php savar           any upazila or district slug
 *
 * Read-only: it changes nothing.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Location;
use App\Support\BdGeo;

$slug = $argv[1] ?? 'manirampur';

function line(string $label, mixed $value): void
{
    echo str_pad($label, 34).' '.(is_scalar($value) || $value === null ? var_export($value, true) : json_encode($value, JSON_UNESCAPED_UNICODE))."\n";
}

echo "== files\n";
line('class BdGeo exists', class_exists(BdGeo::class));
line('index.json', is_file(base_path('database/data/bd-geo/index.json')));
line('shapes folder', is_dir(base_path('database/data/bd-geo/shapes')));
line('shape files found', count(glob(base_path('database/data/bd-geo/shapes/*.json')) ?: []));

$partial = file_get_contents(base_path('resources/views/frontend/partials/no-image.blade.php'));
line('partial has district map code', str_contains((string) $partial, 'sa-noimg-district'));

echo "\n== place \"{$slug}\"\n";
$place = Location::where('slug', $slug)->orderByDesc('id')->first();

if (! $place) {
    echo "No location with that slug.\n";
    exit(1);
}

$path = $place->path();
line('path (type: name_bn / name_en)', $path->map(fn ($p) => $p->type.': '.$p->name_bn.' / '.$p->name_en)->all());

$district = $path->first(fn ($p) => $p->type === Location::DISTRICT);
$upazila = $path->first(fn ($p) => $p->type === Location::UPAZILA);

echo "\n== matching\n";
$reflect = new ReflectionClass(BdGeo::class);

foreach (['district' => [$district], 'upazila' => null] as $step => $args) {
    // run the private steps without the class's own error handling, so a real error is visible
    try {
        if ($step === 'district' && $district) {
            $m = $reflect->getMethod('district');
            line('district matched to index', $m->invoke(null, $district));
        }

        if ($step === 'upazila' && $district && $upazila) {
            $m = $reflect->getMethod('district');
            $di = $m->invoke(null, $district);
            line('upazila matched to shape', $di === null ? 'district not matched' : $reflect->getMethod('upazila')->invoke(null, $di, $upazila));
        }
    } catch (Throwable $e) {
        line($step.' step failed', get_class($e).': '.$e->getMessage());
    }
}

$geo = BdGeo::locate($district, $upazila);
line('BdGeo::locate()', $geo);

if ($geo) {
    $html = BdGeo::render($geo);
    line('BdGeo::render() produced', $html ? 'defs '.strlen($html['defs']).' bytes, svg '.strlen($html['svg']).' bytes' : null);
}
