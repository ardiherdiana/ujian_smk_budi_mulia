<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ProfilController;
use Illuminate\Support\Facades\Route;

// Beranda: langsung ke daftar ruang kelas (tamu diarahkan ke /login oleh middleware auth).
Route::redirect('/', '/kelas');

// ---- Auth --------------------------------------------------------------------
Route::middleware(['guest', 'no-store'])->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth');

Route::middleware(['auth', 'sesi', 'no-store'])->group(function () {
    // ---- Profil: data akun + ganti sandi ------------------------------------------
    Route::get('/profil', [ProfilController::class, 'edit'])->name('profil');
    Route::patch('/profil/password', [ProfilController::class, 'gantiPassword'])->name('profil.password');

    // ---- Ujian (modul Ujian Classroom) ----------------------------------------
    require __DIR__.'/ujian.php';
});
