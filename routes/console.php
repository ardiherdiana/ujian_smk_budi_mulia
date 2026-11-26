<?php

use App\Services\Classroom\UjianService;
use Illuminate\Support\Facades\Schedule;

// Percobaan ujian yang waktunya habis dinilai di sini (butuh `php artisan schedule:work` atau cron `schedule:run`).
// Tanpa penjadwal tetap aman: halaman ujian memeriksa dan menutup percobaan kedaluwarsa saat dibuka (cadangan).
Schedule::call(fn () => app(UjianService::class)->finalisasiSemuaKedaluwarsa())
    ->everyMinute()->name('ujian:finalisasi')->withoutOverlapping();
