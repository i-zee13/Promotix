<?php

namespace App\Console\Commands;

use Database\Seeders\GeoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SeedGeoCatalog extends Command
{
    protected $signature = 'geo:seed';

    protected $description = 'Seed geo_countries, geo_states, and geo_cities from bundled JSON datasets';

    public function handle(): int
    {
        $dir = database_path('data');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        if (! File::exists(database_path('data/geo_countries.json'))) {
            $this->components->warn('Downloading countries dataset…');
            $this->download(
                'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/master/json/countries.json',
                database_path('data/geo_countries.json')
            );
        }

        if (! File::exists(database_path('data/geo_states.json'))) {
            $this->components->warn('Downloading states dataset…');
            $this->download(
                'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/master/json/states.json',
                database_path('data/geo_states.json')
            );
        }

        $this->call('db:seed', ['--class' => GeoSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }

    private function download(string $url, string $target): void
    {
        $context = stream_context_create(['http' => ['timeout' => 120]]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false || $body === '') {
            $this->components->error('Failed to download: ' . $url);

            return;
        }

        File::put($target, $body);
        $this->components->info('Saved ' . $target);
    }
}
