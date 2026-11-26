<?php

namespace App\Http\Controllers\Classroom;

use App\Services\Classroom\SoalGambarService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Gambar soal ujian dari disk privat; siapa boleh melihat ditentukan SoalGambarService::bolehDilihat(). */
class GambarSoalController extends Controller
{
    public function __construct(private SoalGambarService $gambar) {}

    public function show(Request $request, string $path): StreamedResponse
    {
        abort_unless($this->gambar->sah($path), 404);
        abort_unless($this->gambar->bolehDilihat($this->akun($request), $path), 403, 'Anda tidak memiliki akses ke gambar ini');

        return $this->gambar->kirim($path);
    }
}
