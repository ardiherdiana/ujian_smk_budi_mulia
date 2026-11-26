<?php

namespace App\Exceptions;

/** Isian sah secara format tetapi tidak masuk akal untuk aturan bisnisnya (422). */
class InputTidakValid extends PelanggaranAturan
{
    public function statusHttp(): int
    {
        return 422;
    }
}
