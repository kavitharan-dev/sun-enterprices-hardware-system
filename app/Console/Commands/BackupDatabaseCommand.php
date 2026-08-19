<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'app:backup-database';

    protected $description = 'Create a timestamped database backup and copy it off-site for restore.';

    public function handle(): int
    {
        $directory = (string) config('backup.local_path', storage_path('app/private/backups'));
        File::ensureDirectoryExists($directory);

        $connection = config('database.default');
        $timestamp = now()->format('Y-m-d_His');
        $path = $directory.DIRECTORY_SEPARATOR."backup-{$connection}-{$timestamp}".$this->extension($connection);

        $dumped = match ($connection) {
            'mysql' => $this->dumpMysql($path),
            'sqlite' => $this->copySqlite($path),
            default => $this->failUnsupported($connection),
        };

        if (! $dumped) {
            return self::FAILURE;
        }

        $uploaded = $this->copyOffsite($path);

        $this->pruneLocal($directory);
        $this->pruneOffsite();

        if ($uploaded === false) {
            return self::FAILURE;
        }

        $this->info('Database backup completed: '.basename($path).' ('.$this->readableSize($path).').');

        return self::SUCCESS;
    }

    private function dumpMysql(string $path): bool
    {
        $config = config('database.connections.mysql');

        $process = new Process(
            [
                'mysqldump',
                '--host='.($config['host'] ?? '127.0.0.1'),
                '--port='.($config['port'] ?? 3306),
                '--user='.($config['username'] ?? 'root'),
                '--single-transaction',
                '--routines',
                '--triggers',
                $config['database'],
            ],
            null,
            // Passing the password by environment keeps it out of the process
            // list, where any account on the server could otherwise read it.
            ['MYSQL_PWD' => (string) ($config['password'] ?? '')],
        );

        $process->setTimeout(900);

        $handle = $this->openWriter($path);

        if ($handle === false) {
            $this->error("Could not open {$path} for writing.");

            return false;
        }

        $errorOutput = '';

        // Streamed rather than buffered: a full dump held in memory would hit
        // the PHP memory limit once the shop has a year of sales.
        $process->run(function (string $type, string $buffer) use ($handle, &$errorOutput): void {
            if ($type === Process::OUT) {
                $this->writeChunk($handle, $buffer);

                return;
            }

            $errorOutput .= $buffer;
        });

        $this->closeWriter($handle);

        if (! $process->isSuccessful()) {
            File::delete($path);
            $this->error('mysqldump failed. Ensure mysqldump is installed on the server.');
            report(new RuntimeException('mysqldump failed: '.$errorOutput));

            return false;
        }

        return true;
    }

    private function copySqlite(string $path): bool
    {
        $database = config('database.connections.sqlite.database');

        if (! is_string($database) || ! File::exists($database)) {
            $this->error('SQLite database file was not found.');

            return false;
        }

        $source = fopen($database, 'rb');
        $handle = $this->openWriter($path);

        if ($source === false || $handle === false) {
            $this->error('Could not copy the SQLite database.');

            return false;
        }

        while (! feof($source)) {
            $this->writeChunk($handle, (string) fread($source, 1048576));
        }

        fclose($source);
        $this->closeWriter($handle);

        return true;
    }

    /**
     * @return bool|null true copied, false configured but failed, null not configured
     */
    private function copyOffsite(string $path): ?bool
    {
        $disk = config('backup.offsite_disk');

        if (blank($disk)) {
            $this->warn('No off-site copy: set BACKUP_OFFSITE_DISK. This backup only exists on this server.');

            return null;
        }

        $remote = trim((string) config('backup.offsite_path'), '/').'/'.basename($path);
        $stream = fopen($path, 'rb');

        if ($stream === false) {
            $this->error('Could not read the backup for off-site upload.');

            return false;
        }

        try {
            // Disks are configured with throw => false, so a falsy return is
            // the failure signal rather than an exception.
            if (Storage::disk($disk)->writeStream($remote, $stream) === false) {
                throw new RuntimeException("Disk [{$disk}] rejected {$remote}.");
            }

            $this->info("Copied off-site to [{$disk}] {$remote}.");

            return true;
        } catch (Throwable $exception) {
            $this->error('Off-site upload failed: '.$exception->getMessage());
            report($exception);

            return false;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function pruneLocal(string $directory): void
    {
        $days = (int) config('backup.keep_local_days');

        if ($days <= 0) {
            return;
        }

        $cutoff = now()->subDays($days)->getTimestamp();

        foreach (File::files($directory) as $file) {
            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
            }
        }
    }

    private function pruneOffsite(): void
    {
        $disk = config('backup.offsite_disk');
        $days = (int) config('backup.keep_offsite_days');

        if (blank($disk) || $days <= 0) {
            return;
        }

        $storage = Storage::disk($disk);
        $directory = trim((string) config('backup.offsite_path'), '/');
        $cutoff = now()->subDays($days)->getTimestamp();

        try {
            foreach ($storage->files($directory) as $file) {
                if ($storage->lastModified($file) < $cutoff) {
                    $storage->delete($file);
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function extension(string $connection): string
    {
        $base = $connection === 'sqlite' ? '.sqlite' : '.sql';

        return $this->compresses() ? $base.'.gz' : $base;
    }

    private function compresses(): bool
    {
        return (bool) config('backup.compress', true);
    }

    /**
     * @return resource|false
     */
    private function openWriter(string $path)
    {
        return $this->compresses() ? gzopen($path, 'wb6') : fopen($path, 'wb');
    }

    /**
     * @param  resource  $handle
     */
    private function writeChunk($handle, string $buffer): void
    {
        if ($buffer === '') {
            return;
        }

        $this->compresses() ? gzwrite($handle, $buffer) : fwrite($handle, $buffer);
    }

    /**
     * @param  resource  $handle
     */
    private function closeWriter($handle): void
    {
        $this->compresses() ? gzclose($handle) : fclose($handle);
    }

    private function readableSize(string $path): string
    {
        $bytes = (int) File::size($path);

        return $bytes >= 1048576
            ? round($bytes / 1048576, 2).' MB'
            : round($bytes / 1024, 1).' KB';
    }

    private function failUnsupported(string $connection): bool
    {
        $this->error("Backup is not configured for [{$connection}].");

        return false;
    }
}
