<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga sesi untuk semua rute yang butuh login:
 *
 * 1. Akun yang dinonaktifkan (`User::bolehMasuk()`) langsung dikeluarkan, termasuk sesi yang sudah terbuka sebelum
 *    akunnya dinonaktifkan.
 * 2. Sesi dan cookie "ingat saya" terikat ke hash sandi (AuthenticateSession bawaan Laravel): begitu sandi diganti,
 *    oleh pemiliknya maupun direset admin, semua sesi lain akun itu ikut berakhir.
 *
 * Di rute Classroom profil `Akun` ditaruh di atribut request (lihat RequirePeran), jadi hash sandi selalu dibaca dari
 * user guard, bukan dari `$request->user()`.
 */
class JagaSesi extends AuthenticateSession
{
    /** Id akun pemilik hash sandi di sesi. */
    private const KUNCI_PEMILIK = 'password_hash_pemilik';

    public function handle($request, Closure $next): Response
    {
        $user = $this->guard()->user();

        if ($user !== null && ! $user->bolehMasuk()) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => 'Akun Anda sudah dinonaktifkan'], 401);
            }

            return redirect('/login')->withErrors(['email' => 'Akun Anda sudah dinonaktifkan. Hubungi admin sekolah.']);
        }

        // Hash yang tersimpan milik akun lain (sesi yang sama dipakai login akun berbeda): mulai dari hash akun ini.
        if ($user !== null && $request->hasSession() && $request->session()->get(self::KUNCI_PEMILIK) !== $user->getAuthIdentifier()) {
            $request->session()->forget('password_hash_'.$this->auth->getDefaultDriver());
            $request->session()->put(self::KUNCI_PEMILIK, $user->getAuthIdentifier());
        }

        return parent::handle($request, $next);
    }

    /** @param  Request  $request */
    protected function storePasswordHashInSession($request): void
    {
        $user = $this->guard()->user();
        if (! $user) {
            return;
        }

        $request->session()->put([
            'password_hash_'.$this->auth->getDefaultDriver() => $this->guard()->hashPasswordForCookie($user->getAuthPassword()),
        ]);
    }
}
