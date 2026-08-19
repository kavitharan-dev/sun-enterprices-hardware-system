<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Off-site disk
    |--------------------------------------------------------------------------
    |
    | A backup stored only on the server it came from is lost with that server.
    | Set this to a configured filesystem disk (for example "s3") so each dump
    | is copied somewhere the server itself cannot destroy. Leaving it empty
    | keeps backups local only, and the backup command will warn about it.
    |
    */

    'offsite_disk' => env('BACKUP_OFFSITE_DISK'),

    'offsite_path' => env('BACKUP_OFFSITE_PATH', 'database-backups'),

    /*
    |--------------------------------------------------------------------------
    | Local staging directory
    |--------------------------------------------------------------------------
    |
    | Where each dump is written before it is copied off-site.
    |
    */

    'local_path' => storage_path('app/private/backups'),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Backups run hourly, so local retention stays short to protect disk space
    | while the off-site copy holds the longer history.
    |
    */

    'keep_local_days' => (int) env('BACKUP_KEEP_LOCAL_DAYS', 2),

    'keep_offsite_days' => (int) env('BACKUP_KEEP_OFFSITE_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Compression
    |--------------------------------------------------------------------------
    |
    | Gzip keeps hourly dumps affordable to store and transfer. Restore with:
    | gunzip < backup.sql.gz | mysql -u user -p database
    |
    */

    'compress' => (bool) env('BACKUP_COMPRESS', true),

];
