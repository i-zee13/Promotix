<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Import a large SQL dump that cannot be uploaded via phpMyAdmin / DO panel (2MB limit).
 * Place .sql or .sql.gz files in database/dumps/ (or pass --path).
 */
class ImportLocalDumpCommand extends Command
{
    protected $signature = 'db:import-dump
        {--path= : Absolute path or path relative to database/dumps}
        {--parts : Import promotix-db-2026-08-24-part1.sql.gz then part2}
        {--force : Skip confirmation}';

    protected $description = 'Import SQL dump(s) from database/dumps via mysql CLI (bypasses upload size limits)';

    public function handle(): int
    {
        $files = $this->resolveFiles();
        if ($files === []) {
            $this->error('No dump file found. Put .sql / .sql.gz in database/dumps/ or pass --path=');

            return self::FAILURE;
        }

        $db = (string) config('database.connections.mysql.database');
        $host = (string) config('database.connections.mysql.host');
        $port = (string) (config('database.connections.mysql.port') ?: 3306);
        $user = (string) config('database.connections.mysql.username');

        $this->warn('This will run SQL against: '.$user.'@'.$host.':'.$port.'/'.$db);
        foreach ($files as $file) {
            $this->line('  → '.$file.' ('.$this->humanSize(filesize($file)).')');
        }

        if (! $this->option('force') && ! $this->confirm('Continue? Existing tables/data in this dump may be dropped/replaced.', false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        foreach ($files as $i => $file) {
            $this->info('Importing part '.($i + 1).'/'.count($files).': '.basename($file));
            $ok = $this->importFile($file, $host, $port, $user, $db);
            if (! $ok) {
                return self::FAILURE;
            }
            $this->info('OK: '.basename($file));
        }

        $this->info('Dump import finished.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveFiles(): array
    {
        if ($this->option('parts')) {
            $parts = [
                database_path('dumps/promotix-db-2026-08-24-part1.sql.gz'),
                database_path('dumps/promotix-db-2026-08-24-part2.sql.gz'),
            ];
            foreach ($parts as $part) {
                if (! is_file($part)) {
                    $this->error('Missing: '.$part);

                    return [];
                }
            }

            return $parts;
        }

        $path = trim((string) $this->option('path'));
        if ($path === '') {
            // Prefer gzipped parts if present, else first .sql.gz / .sql in dumps/
            $defaultParts = [
                database_path('dumps/promotix-db-2026-08-24-part1.sql.gz'),
                database_path('dumps/promotix-db-2026-08-24-part2.sql.gz'),
            ];
            if (is_file($defaultParts[0]) && is_file($defaultParts[1])) {
                return $defaultParts;
            }

            $dir = database_path('dumps');
            if (! is_dir($dir)) {
                return [];
            }
            $found = collect(File::files($dir))
                ->filter(fn ($f) => preg_match('/\.(sql|sql\.gz)$/i', $f->getFilename()))
                ->sortBy(fn ($f) => $f->getFilename())
                ->map(fn ($f) => $f->getPathname())
                ->values()
                ->all();

            return $found;
        }

        if (! str_starts_with($path, '/')) {
            $path = database_path('dumps/'.$path);
        }
        if (! is_file($path)) {
            $this->error('File not found: '.$path);

            return [];
        }

        return [$path];
    }

    private function importFile(string $file, string $host, string $port, string $user, string $db): bool
    {
        $password = (string) config('database.connections.mysql.password');
        $isGz = str_ends_with(strtolower($file), '.gz');

        $env = array_merge($_ENV, [
            'MYSQL_PWD' => $password,
        ]);

        if ($isGz) {
            $cmd = sprintf(
                'gunzip -c %s | mysql --protocol=TCP -h %s -P %s -u %s %s',
                escapeshellarg($file),
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($user),
                escapeshellarg($db),
            );
            $process = Process::fromShellCommandline($cmd, base_path(), $env, null, 3600);
        } else {
            $process = new Process(
                [
                    'mysql',
                    '--protocol=TCP',
                    '-h', $host,
                    '-P', $port,
                    '-u', $user,
                    $db,
                ],
                base_path(),
                $env,
                null,
                3600,
            );
            $process->setInput(fopen($file, 'r'));
        }

        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->error('mysql import failed (exit '.$process->getExitCode().')');
            $err = trim($process->getErrorOutput());
            if ($err !== '') {
                $this->error($err);
            }

            return false;
        }

        return true;
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1024 / 1024, 2).' MB';
    }
}
