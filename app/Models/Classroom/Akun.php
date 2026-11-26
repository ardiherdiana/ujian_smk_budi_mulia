<?php

namespace App\Models\Classroom;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Profil sekolah untuk satu baris `users`. `id` SAMA dengan `users.id`. Username, nama, dan peran
 * (ADMIN|KEPSEK|GURU|SISWA) ada di `profil_akun`; sandi tetap di
 * `users.password`, dan atribut `password` di model ini meneruskannya ke sana, jadi
 * `forceFill([... 'password' => ...])->save()` tetap bekerja. Peran ADMIN selalu berpasangan dengan `users.role` admin.
 */
#[Fillable(['username', 'name', 'role', 'is_active'])]
#[Hidden(['remember_token'])]
class Akun extends Model
{
    protected $table = 'profil_akun';

    public $incrementing = false;

    protected $keyType = 'int';

    /** Peran yang mengajar ruang kelas dan bisa jadi wali kelas (halaman Data > Guru). */
    public const PERAN_PENGAJAR = ['GURU', 'KEPSEK'];

    private ?string $sandiBaru = null;

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getCreatedAtColumn(): string
    {
        return 'createdAt';
    }

    public function getUpdatedAtColumn(): string
    {
        return 'updatedAt';
    }

    protected static function booted(): void
    {
        // Baris `users` dibuat lebih dulu (id-nya dipakai profil). Email sintetis hanya memenuhi kolom NOT NULL; login
        // staf/siswa memakai username.
        static::creating(function (Akun $akun) {
            if ($akun->id) {
                return;
            }

            $pengguna = new User;
            $pengguna->forceFill([
                'email' => Str::limit($akun->username.'@akun.local', 100, ''),
                'password' => $akun->sandiBaru ?? Str::random(40),
                'role' => $akun->role === 'ADMIN' ? 'admin' : 'user',
            ])->save();

            $akun->sandiBaru = null;
            $akun->id = $pengguna->id;
        });

        static::saved(function (Akun $akun) {
            $pengguna = User::find($akun->id);
            if (! $pengguna) {
                return;
            }

            if ($akun->sandiBaru !== null) {
                $pengguna->password = $akun->sandiBaru;
                $akun->sandiBaru = null;
            }
            if (str_ends_with((string) $pengguna->email, '@akun.local')) {
                $pengguna->email = Str::limit($akun->username.'@akun.local', 100, '');
            }
            // Peran profil ADMIN <=> users.role admin.
            if ($akun->wasRecentlyCreated || $akun->wasChanged('role')) {
                $pengguna->role = $akun->role === 'ADMIN' ? 'admin' : 'user';
            }
            if ($pengguna->isDirty()) {
                $pengguna->save();
            }
        });

        static::deleted(fn (Akun $akun) => User::whereKey($akun->id)->delete());
    }

    /** Hash sandi dari `users` (untuk Hash::check). */
    public function getPasswordAttribute(): ?string
    {
        return $this->pengguna?->password;
    }

    public function setPasswordAttribute(?string $value): void
    {
        $this->sandiBaru = $value;
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id');
    }

    /** Aplikasi ujian tidak menyimpan foto (foto guru milik modul Website di landing_page), jadi selalu null. */
    public function fotoUrl(): ?string
    {
        return null;
    }

    /** Profil milik pengguna yang sedang masuk; null bila akun tidak punya profil. */
    public static function untukPengguna(User $pengguna): ?self
    {
        return $pengguna->relationLoaded('akun') ? $pengguna->akun : static::find($pengguna->id);
    }

    public function siswa(): HasOne
    {
        return $this->hasOne(Siswa::class, 'userId');
    }

    /** Rombel yang dia walikelasi. */
    public function rombelWali(): HasMany
    {
        return $this->hasMany(Rombel::class, 'waliKelasId');
    }

    /** Ruang kelas yang dia ajar. */
    public function kelasDiajar(): HasMany
    {
        return $this->hasMany(Kelas::class, 'guruId');
    }

    /** Staf (admin dan pengajar). */
    public function isStaf(): bool
    {
        return in_array($this->role, ['ADMIN', 'KEPSEK', 'GURU'], true);
    }

    public function isGuru(): bool
    {
        return $this->role === 'GURU';
    }

    public function isSiswa(): bool
    {
        return $this->role === 'SISWA';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }

    /** Nama yang tampil di antarmuka. */
    public function namaTampil(): string
    {
        return $this->name ?: ($this->role === 'ADMIN' ? 'Admin' : $this->username);
    }
}
