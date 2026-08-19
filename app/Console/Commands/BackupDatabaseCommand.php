<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'app:backup-database';

    protected $description = 'Create a timestamped local database backup for production restore.';

    public function handle(): int
    {
        $diskPath = storage_path('app/private/backups');
        File::ensureDirectoryExists($diskPath);

        $timestamp = now()->format('Y-m-d_His');
        $connection = config('database.default');
        $filename = $diskPath.DIRECTORY_SEPARATOR."backup-{$connection}-{$timestamp}";

        $ok = match ($connection) {
            'mysql' => $this->backupMysql($filename.'.sql'),
            'sqlite' => $this->backupSqlite($filename.'.sqlite'),
            default => $this->failUnsupported($connection),
        };

        if (! $ok) {
            return self::FAILURE;
        }

        $this->pruneOldBackups($diskPath);
        $this->info('Database backup completed.');

        return self::SUCCESS;
    }

    private function backupMysql(string $path): bool
    {
        $config = config('database.connections.mysql');

        $process = new Process([
            'mysqldump',
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? 3306),
            '--user='.($config['username'] ?? 'root'),
            '--password='.($config['password'] ?? ''),
            '--single-transaction',
            '--routines',
            '--triggers',
            $config['database'],
        ]);

        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('mysqldump failed. Ensure mysqldump is installed on the VPS.');
            report(new \RuntimeException('mysqldump failed: '.$process->getErrorOutput()));

            return false;
        }

        File::put($path, $process->getOutput());

        return true;
    }

    private function backupSqlite(string $path): bool
    {
        $database = config('database.connections.sqlite.database');

        if (! is_string($database) || ! File::exists($database)) {
            $this->error('SQLite database file was not found.');

            return false;
        }

        File::copy($database, $path);

        return true;
    }

    private function failUnsupported(string $connection): bool
    {
        $this->error("Backup is not configured for [{$connection}].");

        return false;
    }

    private function pruneOldBackups(string $directory): void
    {
        $keepDays = 14;
        $cutoff = now()->subDays($keepDays)->getTimestamp();

        foreach (File::files($directory) as $file) {
            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
            }
        }
    }
}
