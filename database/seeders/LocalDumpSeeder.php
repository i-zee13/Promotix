<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs the local SQL dump import (database/dumps/*.sql.gz).
 *
 * Usage on server:
 *   php artisan db:seed --class=LocalDumpSeeder --force
 *
 * Or directly:
 *   php artisan db:import-dump --parts --force
 */
class LocalDumpSeeder extends Seeder
{
    public function run(): void
    {
        $code = Artisan::call('db:import-dump', [
            '--parts' => true,
            '--force' => true,
        ]);

        $this->command?->getOutput()?->write(Artisan::output());

        if ($code !== 0) {
            throw new \RuntimeException('LocalDumpSeeder failed — see db:import-dump output above.');
        }
    }
}
