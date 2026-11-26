<?php

namespace App\Exceptions;

/** Pengguna tidak boleh melakukan aksi ini (403), mis. akun nonaktif atau di luar radius sekolah. */
class AksiDitolak extends PelanggaranAturan
{
    public function statusHttp(): int
    {
        return 403;
    }
}
