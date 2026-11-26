<?php

namespace App\Models;

use App\Models\Classroom\Akun;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Baris login: email + password + role (admin|user), tanpa timestamps dan tanpa kolom remember_token (skema sama dengan
 * tabel `users` Classroom di landing_page).
 *
 * Profil sekolah (username, nama, status aktif, peran ADMIN|KEPSEK|GURU|SISWA) ada di `profil_akun` (model `Akun`, `id`
 * sama). `role` admin selalu berpasangan dengan peran profil ADMIN (dijaga di `Akun::saved`).
 */
#[Fillable(['email', 'password', 'role'])]
#[Hidden(['password', 'akun'])]
class User extends Authenticatable
{
    /** Peran profil yang boleh memakai aplikasi ujian (sama dengan peran Classroom). */
    public const PERAN = ['ADMIN', 'KEPSEK', 'GURU', 'SISWA'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function akun(): HasOne
    {
        return $this->hasOne(Akun::class, 'id', 'id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Staf sekolah (bukan siswa). Dipakai untuk "ingat saya" saat login. */
    public function isStaf(): bool
    {
        return $this->akun !== null && $this->akun->isStaf();
    }

    /**
     * Satu-satunya pemeriksa "akun ini boleh masuk dan memakai aplikasi", dipakai login dan semua middleware akses:
     * harus punya profil yang aktif dengan peran Classroom.
     */
    public function bolehMasuk(): bool
    {
        $akun = $this->akun;

        return $akun !== null && $akun->is_active && in_array($akun->role, self::PERAN, true);
    }

    // ---- Ingat saya: token di `profil_akun` (tabel `users` tidak punya kolomnya) -----------------------------------

    /** Kosong supaya trait bawaan tidak pernah menulis kolom `users.remember_token` yang tidak ada. */
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return $this->akun?->remember_token;
    }

    public function setRememberToken($value): void
    {
        $this->akun?->forceFill(['remember_token' => $value])->saveQuietly();
    }
}
