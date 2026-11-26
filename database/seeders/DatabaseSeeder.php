<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * `php artisan db:seed`: isi data awal sekolah (DataSekolahSeeder) bila basis data masih kosong. Pada basis data yang
     * sudah berisi dilewati, supaya data yang sengaja dihapus admin tidak muncul lagi.
     */
    public function run(): void
    {
        if (DB::table('profil_akun')->exists()) {
            $this->command?->warn('Data awal dilewati karena sudah ada akun. Untuk menambah data awal yang belum ada: php artisan db:seed --class=DataSekolahSeeder');

            return;
        }

        $this->call(DataSekolahSeeder::class);
    }
}
