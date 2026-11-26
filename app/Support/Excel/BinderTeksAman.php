<?php

namespace App\Support\Excel;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * Value binder untuk semua ekspor xlsx: teks yang diawali `=` disimpan sebagai TEKS, bukan rumus. Tanpa ini isian
 * pengguna (mis. tujuan SPPD `=HYPERLINK(...)`) menjadi rumus aktif saat berkas dibuka di Excel (formula injection).
 * Angka, tanggal, dan nilai lain tetap diperlakukan seperti binder bawaan.
 */
class BinderTeksAman extends DefaultValueBinder
{
    public static function dataTypeForValue(mixed $value): string
    {
        if (is_string($value) && strlen($value) > 1 && $value[0] === '=') {
            return DataType::TYPE_STRING;
        }

        return parent::dataTypeForValue($value);
    }
}
