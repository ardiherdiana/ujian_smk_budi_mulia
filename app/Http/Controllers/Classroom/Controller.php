<?php

namespace App\Http\Controllers\Classroom;

use App\Http\Middleware\RequirePeran;
use App\Models\Classroom\Akun;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /** Profil Classroom pengguna yang sedang masuk; disiapkan middleware `peran:` (RequirePeran). */
    protected function akun(Request $request): Akun
    {
        $akun = $request->attributes->get(RequirePeran::ATRIBUT);
        abort_unless($akun instanceof Akun, 403, 'Anda tidak memiliki akses');

        return $akun;
    }

    /**
     * Jumlah baris per halaman untuk daftar berpaginasi (query `per_page`).
     */
    protected function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page');

        return in_array($perPage, [50, 100, 200], true) ? $perPage : 50;
    }
}
