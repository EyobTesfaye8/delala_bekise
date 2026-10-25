<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SRS FR-1.6: Daily purge of unverified accounts > 72 hours with 0 jobs
Schedule::command('delala:purge-unverified')->daily();

// Garbage collection of guest accounts inactive for 5+ days with zero ongoing deals
Schedule::command('delala:purge-inactive-guests')->daily();

// SRS §10 & §11.1 Automated Schedulers
Schedule::command('delala:auto-release-escrow')->hourly();
Schedule::command('delala:handover-warning')->hourly();
Schedule::command('delala:auto-refund-cancellations')->hourly();
Schedule::command('delala:chapa-polling-fallback')->everyFifteenMinutes();
Schedule::command('delala:nightly-reconciliation')->dailyAt('02:00');
