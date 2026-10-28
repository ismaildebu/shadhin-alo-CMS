<?php

namespace Database\Seeders;

use App\Support\LocationImporter;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        LocationImporter::import();
    }
}
