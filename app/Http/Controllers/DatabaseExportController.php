<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

class DatabaseExportController extends Controller
{
    public function download(Request $request): StreamedResponse
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);

        $connection = (string) config('database.default');
        $config = (array) config("database.connections.{$connection}", []);
        $driver = (string) ($config['driver'] ?? '');

        $filename = 'promotix-db-' . now()->format('Y-m-d-His');

        if ($driver === 'sqlite') {
            return $this->downloadSqliteFile($config, $filename);
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $sql = $this->tryMysqldump($config);
            if ($sql !== null) {
                return $this->sqlDownloadResponse($sql, $filename . '.sql');
            }

            return $this->streamPhpSqlDump($connection, $filename . '.sql');
        }

        abort(501, 'Database export is only supported for MySQL/MariaDB and SQLite.');
    }

    /** @param  array<string, mixed>  $config */
    private function downloadSqliteFile(array $config, string $filename): StreamedResponse
    {
        $path = (string) ($config['database'] ?? '');
        if ($path === '' || ! is_readable($path)) {
            abort(500, 'SQLite database file not found.');
        }

        return response()->streamDownload(function () use ($path): void {
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                return;
            }
            while (! feof($handle)) {
                echo fread($handle, 1024 * 1024);
            }
            fclose($handle);
        }, $filename . '.sqlite', [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function tryMysqldump(array $config): ?string
    {
        $binary = $this->resolveMysqldumpBinary();
        if ($binary === null) {
            return null;
        }

        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '3306');
        $database = (string) ($config['database'] ?? '');
        $username = (string) ($config['username'] ?? 'root');
        $password = (string) ($config['password'] ?? '');

        if ($database === '') {
            return null;
        }

        $command = [
            $binary,
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $username,
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--default-character-set=utf8mb4',
            $database,
        ];

        $env = $_ENV;
        if ($password !== '') {
            $env['MYSQL_PWD'] = $password;
        }

        $process = new Process($command, null, $env, null, 600);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $output = $process->getOutput();

        return $output !== '' ? $output : null;
    }

    private function resolveMysqldumpBinary(): ?string
    {
        foreach (['mysqldump', 'mariadb-dump'] as $candidate) {
            $process = new Process([PHP_OS_FAMILY === 'Windows' ? 'where' : 'which', $candidate]);
            $process->run();
            if ($process->isSuccessful()) {
                $line = trim(strtok($process->getOutput(), PHP_EOL));
                if ($line !== '' && is_executable($line)) {
                    return $line;
                }
            }
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $wampRoots = ['E:/wamp64', 'C:/wamp64', 'D:/wamp64'];
            foreach ($wampRoots as $root) {
                $glob = glob($root . '/bin/mysql/mysql*/bin/mysqldump.exe') ?: [];
                if ($glob !== []) {
                    return $glob[0];
                }
            }
        }

        return null;
    }

    private function sqlDownloadResponse(string $sql, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($sql): void {
            echo $sql;
        }, $filename, [
            'Content-Type' => 'application/sql',
        ]);
    }

    private function streamPhpSqlDump(string $connection, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($connection): void {
            $db = DB::connection($connection);
            $tables = collect($db->select('SHOW TABLES'))
                ->map(fn ($row) => (string) array_values((array) $row)[0])
                ->filter()
                ->values();

            echo "-- PromoTix database export\n";
            echo '-- Generated: ' . now()->toDateTimeString() . "\n\n";
            echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

            foreach ($tables as $table) {
                $escaped = str_replace('`', '``', $table);
                $create = $db->selectOne('SHOW CREATE TABLE `' . $escaped . '`');
                $createSql = (string) (array_values((array) $create)[1] ?? '');

                echo "DROP TABLE IF EXISTS `{$escaped}`;\n";
                echo $createSql . ";\n\n";

                $db->table($table)->orderByRaw('1')->chunk(500, function ($rows) use ($escaped, $db): void {
                    if ($rows->isEmpty()) {
                        return;
                    }

                    $columns = array_keys((array) $rows->first());
                    $columnList = implode(', ', array_map(fn ($c) => '`' . str_replace('`', '``', $c) . '`', $columns));

                    foreach ($rows as $row) {
                        $values = [];
                        foreach ($columns as $column) {
                            $values[] = $this->sqlValue($row->{$column} ?? null, $db);
                        }
                        echo 'INSERT INTO `' . $escaped . '` (' . $columnList . ') VALUES (' . implode(', ', $values) . ");\n";
                    }
                });

                echo "\n";
            }

            echo "SET FOREIGN_KEY_CHECKS=1;\n";
        }, $filename, [
            'Content-Type' => 'application/sql',
        ]);
    }

    private function sqlValue(mixed $value, \Illuminate\Database\Connection $db): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $db->getPdo()->quote((string) $value);
    }
}
