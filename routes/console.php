<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

ScheduleFacade::command('schedules:sync-status')->everyMinute();

// Pembersihan otomatis data Riwayat Hapus (Soft Delete) yang telah melewati batas masa retensi 30 hari kalender
ScheduleFacade::command('trash:purge-expired')->daily();
