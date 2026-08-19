<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    private string $backupDirectory;

    private string $sourceDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupDirectory = storage_path('framework/testing/backups-'.uniqid());
        $this->sourceDatabase = storage_path('framework/testing/source-'.uniqid().'.sqlite');

        File::ensureDirectoryExists(dirname($this->sourceDatabase));
        File::put($this->sourceDatabase, str_repeat('sun enterprices sales data ', 500));

        config([
            'backup.local_path' => $this->backupDirectory,
            'backup.offsite_disk' => null,
            'backup.compress' => true,
            'backup.keep_local_days' => 2,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->sourceDatabase,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupDirectory);
        File::delete($this->sourceDatabase);

        parent::tearDown();
    }

    public function test_backup_writes_a_compressed_file_that_can_be_read_back(): void
    {
        $this->artisan('app:backup-database')->assertSuccessful();

        $files = File::files($this->backupDirectory);

        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.sqlite.gz', $files[0]->getFilename());

        // A backup that cannot be read back is not a backup.
        $this->assertSame(
            File::get($this->sourceDatabase),
            gzdecode(File::get($files[0]->getPathname())),
        );
    }

    public function test_backup_is_copied_to_the_offsite_disk(): void
    {
        Storage::fake('offsite');
        config(['backup.offsite_disk' => 'offsite']);

        $this->artisan('app:backup-database')->assertSuccessful();

        $remote = Storage::disk('offsite')->files('database-backups');

        $this->assertCount(1, $remote);
        $this->assertStringEndsWith('.sqlite.gz', $remote[0]);
    }

    public function test_backup_warns_when_no_offsite_disk_is_configured(): void
    {
        $this->artisan('app:backup-database')
            ->expectsOutputToContain('No off-site copy')
            ->assertSuccessful();
    }

    public function test_backups_older_than_the_retention_window_are_removed(): void
    {
        File::ensureDirectoryExists($this->backupDirectory);
        $stale = $this->backupDirectory.DIRECTORY_SEPARATOR.'backup-sqlite-old.sqlite.gz';
        File::put($stale, 'old');
        touch($stale, now()->subDays(5)->getTimestamp());

        $this->artisan('app:backup-database')->assertSuccessful();

        $this->assertFileDoesNotExist($stale);
        $this->assertCount(1, File::files($this->backupDirectory));
    }

    public function test_uncompressed_backups_are_written_when_compression_is_disabled(): void
    {
        config(['backup.compress' => false]);

        $this->artisan('app:backup-database')->assertSuccessful();

        $files = File::files($this->backupDirectory);

        $this->assertStringEndsWith('.sqlite', $files[0]->getFilename());
        $this->assertSame(File::get($this->sourceDatabase), File::get($files[0]->getPathname()));
    }
}
