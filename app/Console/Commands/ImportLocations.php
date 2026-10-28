<?php

namespace App\Console\Commands;

use App\Support\LocationImporter;
use Illuminate\Console\Command;

class ImportLocations extends Command
{
    protected $signature = 'locations:import {file? : JSON file (default: database/data/bd-locations.json)}';

    protected $description = 'Load Bangladesh divisions, districts and upazilas. Safe to run again.';

    public function handle(): int
    {
        try {
            $stats = LocationImporter::import($this->argument('file'));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Locations imported: {$stats['created']} created, {$stats['updated']} already present.");

        return self::SUCCESS;
    }
}
