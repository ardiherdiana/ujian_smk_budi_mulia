<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Classroom\Akun;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LoginController extends Controller
{
    /** Gagal per identitas + IP sebelum dikunci, dan lama kuncinya (detik). */
    private const BATAS_PER_IP = 5;

    private const JEDA_PER_IP = 60;

    /** Gagal per identitas dari IP mana pun: menahan tebakan sandi yang disebar ke banyak IP. */
    private const BATAS_PER_AKUN = 10;

    private const JEDA_PER_AKUN = 900;

    /**
     * Hash bcrypt untuk sandi acak. Dicek saat identitas tidak ditemukan supaya waktu respons akun ada dan tidak ada
     * sama (identitas tidak bisa ditebak dari lamanya respons).
     */
    private const HASH_PENGGANTI = '$2y$12$sEkIYM4a4g9Ju8kgBCwvC.vTc6WDUyKcTELN4igPx2qFy4Oin2dKi';

    public function create()
    {
        return Inertia::render('login-page');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $identitas = Str::lower(trim($credentials['email']));
        $kunciIp = 'login:'.$identitas.'|'.$request->ip();
        $kunciAkun = 'login-akun:'.$identitas;

        foreach ([[$kunciIp, self::BATAS_PER_IP], [$kunciAkun, self::BATAS_PER_AKUN]] as [$kunci, $batas]) {
            if (RateLimiter::tooManyAttempts($kunci, $batas)) {
                $detik = RateLimiter::availableIn($kunci);
                throw ValidationException::withMessages([
                    'email' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$detik} detik.",
                ]);
            }
        }

        $user = $this->cariAkun($credentials['email']);
        $sandiBenar = $this->sandiCocok($credentials['password'], $user?->password);

        if (! $user || ! $sandiBenar || ! $user->bolehMasuk()) {
            RateLimiter::hit($kunciIp, self::JEDA_PER_IP);
            RateLimiter::hit($kunciAkun, self::JEDA_PER_AKUN);

            throw ValidationException::withMessages(['email' => 'Email/username atau password salah']);
        }

        RateLimiter::clear($kunciIp);
        RateLimiter::clear($kunciAkun);

        // "Ingat saya" hanya untuk staf. Siswa tidak: komputer lab dipakai bergantian.
        Auth::login($user, remember: $user->isStaf());
        $request->session()->regenerate();

        Inertia::clearHistory();

        return redirect()->intended('/kelas');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Inertia::clearHistory();

        return redirect('/login');
    }

    /**
     * Satu kolom "Email / Username". Berisi "@": email akun (termasuk email sintetis `<username>@akun.local`).
     * Selain itu: username profil sekolah (guru, kepsek, admin, siswa). Sandi selalu di `users.password`.
     * Status akun (profil nonaktif, peran di luar Classroom) diperiksa `User::bolehMasuk()` untuk KEDUA jalur.
     */
    private function cariAkun(string $identitas): ?User
    {
        if (str_contains($identitas, '@')) {
            return User::where('email', $identitas)->first();
        }

        $akun = Akun::with('pengguna')->where('username', $identitas)->first();
        $user = $akun?->pengguna;
        $user?->setRelation('akun', $akun);

        return $user;
    }

    /**
     * Hanya hash bcrypt yang diterima. Nilai yang bukan
     * bcrypt (mentah atau format hash lain) tidak pernah dibandingkan apa adanya.
     */
    private function sandiCocok(string $sandi, ?string $hash): bool
    {
        if ($hash === null || ! str_starts_with($hash, '$2')) {
            Hash::check($sandi, self::HASH_PENGGANTI);

            return false;
        }

        return Hash::check($sandi, $hash);
    }
}
