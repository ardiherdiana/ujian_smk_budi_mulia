<?php

namespace App\Exceptions;

/** Aksi bertentangan dengan keadaan data saat ini (409), mis. sudah absen, sudah diproses, atau masih dipakai modul lain. */
class KeadaanBentrok extends PelanggaranAturan
{
    public function statusHttp(): int
    {
        return 409;
    }
}
