<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('system:heartbeat')
    ->everyMinute()
    ->withoutOverlapping(2);

Schedule::command('media:cleanup --stalled-only')
    ->everyTenMinutes()
    ->withoutOverlapping(15)
    ->onOneServer();

Schedule::command('media:cleanup')
    ->dailyAt('01:30')
    ->withoutOverlapping(120)
    ->onOneServer();

Schedule::command('system:backup')
    ->dailyAt('02:30')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(240)
    ->onOneServer()
    ->when(fn (): bool => (bool) config('backup.enabled', false));

Schedule::command('system:backup-check')
    ->weeklyOn(0, '03:30')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(240)
    ->onOneServer()
    ->when(fn (): bool => (bool) config('backup.enabled', false));

Schedule::command('system:backup-restore-test')
    ->weeklyOn(0, '04:30')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(240)
    ->onOneServer()
    ->when(fn (): bool => (bool) config('backup.enabled', false));
