<?php

namespace App\Exceptions;

/** Data yang diminta tidak ada (404). */
class DataTidakDitemukan extends PelanggaranAturan
{
    public function statusHttp(): int
    {
        return 404;
    }
}
