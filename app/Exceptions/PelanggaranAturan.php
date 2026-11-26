<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Aturan bisnis dilanggar. Service dan model melempar turunan kelas ini, bukan `abort()`, supaya logika domain tidak
 * terikat ke HTTP dan tetap bisa dipakai dari konsol atau antrean. `bootstrap/app.php` memetakan tiap turunan ke status
 * HTTP-nya (lihat `statusHttp()`), jadi responsnya sama persis dengan `abort()`: pesan form di Inertia, JSON untuk
 * permintaan JSON, halaman error untuk GET biasa.
 */
abstract class PelanggaranAturan extends RuntimeException
{
    /** Status HTTP saat pelanggaran ini sampai ke pengguna lewat permintaan web. */
    abstract public function statusHttp(): int;
}
