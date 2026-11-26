<?php

namespace App\Http\Middleware;

use App\Models\Classroom\Akun;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang modul Classroom: `peran:ADMIN,GURU` mengizinkan pengguna yang profil Classroom-nya berperan salah satunya.
 * `$request->user()` tetap model User (sama dengan modul lain); profilnya ditaruh di atribut request `akun` dan dibaca
 * controller Classroom lewat `Classroom\Controller::akun()`.
 */
class RequirePeran
{
    public const ATRIBUT = 'akun';

    public function handle(Request $request, Closure $next, string ...$peran): Response
    {
        $pengguna = $request->user();
        $akun = $pengguna ? Akun::untukPengguna($pengguna) : null;

        if (! $akun || ! $pengguna->bolehMasuk() || ! in_array($akun->role, $peran, true)) {
            abort(403, 'Anda tidak memiliki akses');
        }

        $akun->setRelation('pengguna', $pengguna);
        $pengguna->setRelation('akun', $akun);
        $request->attributes->set(self::ATRIBUT, $akun);

        return $next($request);
    }
}
