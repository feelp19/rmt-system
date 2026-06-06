<?php

use App\Jobs\ExpireBoostsJob;
use App\Jobs\ReconcilePendingPixChargesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Fallback de confirmação de PIX (caso o webhook não chegue) + expiração de boosts.
Schedule::job(new ReconcilePendingPixChargesJob)->everyFiveMinutes();
Schedule::job(new ExpireBoostsJob)->daily();
