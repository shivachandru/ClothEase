<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\BackupService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('backup:create', function (BackupService $backups) {
    if (!$backups->supported()) {
        $this->error('Automatic backup currently supports file-backed SQLite databases only.');
        return self::FAILURE;
    }

    try {
        $backup = $backups->create();
        $this->info('Verified backup created: '.$backup['name']);
        return self::SUCCESS;
    } catch (RuntimeException $exception) {
        $this->error($exception->getMessage());
        return self::FAILURE;
    }
})->purpose('Create and verify a private SQLite backup');

Schedule::command('backup:create')->dailyAt('02:00')->withoutOverlapping();
