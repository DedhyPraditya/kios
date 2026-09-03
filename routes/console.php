<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ganti hari = shift kemarin dikunci, hitungan hari ini mulai dari nol.
// Butuh penjadwal berjalan (Task Scheduler menjalankan `php artisan schedule:run`
// tiap menit). Kalau komputer mati tengah malam, shift tetap terkunci sendiri
// saat aplikasi dibuka lagi — lihat CashSession::sealStale().
Schedule::command('shift:tutup-otomatis')
    ->dailyAt('00:01')
    ->withoutOverlapping();
