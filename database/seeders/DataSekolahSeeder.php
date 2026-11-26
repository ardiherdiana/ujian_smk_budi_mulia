<?php

namespace Database\Seeders;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\Mapel;
use App\Models\Classroom\Rombel;
use App\Models\Classroom\Siswa;
use App\Models\Classroom\TahunAjaran;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Data awal sekolah, berdiri sendiri (tidak membaca basis data lain): tahun ajaran, mapel, rombel, akun admin/kepsek/guru,
 * siswa, dan ruang kelas. Daftar pendek ada di konstanta di bawah; akun dan ruang kelas di `database/seeders/data/*.php`
 * (diambil sekali dari DB ujian produksi 23 Nov 2026, sandi hanya berupa hash bcrypt).
 *
 * Hanya MENAMBAH yang belum ada, dicocokkan lewat kunci alaminya (username, nama mapel/rombel, tahun + semester, rombel +
 * mapel + guru + tahun). Data yang sudah ada tidak pernah ditimpa atau dihapus, jadi perubahan admin lewat halaman Data
 * (nama, sandi, rombel, status) tetap. Data yang sudah dihapus admin akan dibuat lagi, karena itu DatabaseSeeder hanya
 * memanggil seeder ini pada basis data yang masih kosong; untuk menambah yang kurang dengan sengaja:
 *
 *   php artisan db:seed --class=DataSekolahSeeder
 */
class DataSekolahSeeder extends Seeder
{
    /** @var list<array{0: string, 1: 'GANJIL'|'GENAP', 2: bool}> [nama, semester, aktif] */
    public const TAHUN_AJARAN = [
        ['2026/2027', 'GANJIL', true],
    ];

    /** @var list<string> */
    public const MAPEL = [
        'Bahasa Indonesia (Kolaboratif Jurusan)',
        'Bahasa Indonesia (TKA+AN+UTBK+Kebekerjaan)',
        'Bahasa Inggris',
        'Bahasa Inggris (Kolaboratif Jurusan)',
        'Bahasa Inggris (TKA+SNBT)',
        'Bahasa Jepang',
        'Bhs. Inggris (Kebekerjaan)',
        'BK/BP/Pembina Karakter',
        'Ilmu Pengetahuan Alam dan Sosial (IPAS)',
        'Informatika',
        'Mata Pelajaran Pilihan (Informatika Lanjutan)',
        'Mata Pelajaran Pilihan (Pembekalan PKL/Informatika)',
        'Matematika (Kolaboratif Jurusan)',
        'Matematika (TKA+AN+SNBT+Kebekerjaan)',
        'Pendidikan Pancasila (Penguatan Karakter)',
        'Penjaskes',
        'Penjaskes (Persiapan Tes Fisik)',
        'Produktif AKT',
        'Produktif RPL',
        'Produktif TKJ',
        'Project Kreatif & Kewirausahaan',
        'Project Kreatif & Kewirausahaan (Kolaboratif Jurusan)',
        'Project Kreatif & Kewirausahaan (TKA)',
    ];

    /** @var array<string, array{0: 'X'|'XI'|'XII', 1: string, 2: string|null}> nama => [tingkat, jurusan, username wali kelas] */
    public const ROMBEL = [
        'X AKT' => ['X', 'AKT', null],
        'X RPL' => ['X', 'RPL', null],
        'X TKJ 1' => ['X', 'TKJ', null],
        'X TKJ 2' => ['X', 'TKJ', null],
        'X TKJ 3' => ['X', 'TKJ', null],
        'XI AKT' => ['XI', 'AKT', null],
        'XI RPL' => ['XI', 'RPL', null],
        'XI TKJ 1' => ['XI', 'TKJ', null],
        'XI TKJ 2' => ['XI', 'TKJ', null],
        'XI TKJ 3' => ['XI', 'TKJ', null],
        'XII AKT' => ['XII', 'AKT', null],
        'XII TKJ 1' => ['XII', 'TKJ', null],
        'XII TKJ 2' => ['XII', 'TKJ', null],
        'XII TKJ 3' => ['XII', 'TKJ', null],
    ];

    /** @var array<string, int> */
    private array $ringkasan = [];

    /** @param  string|null  $folderData  folder berkas data; null = `database/seeders/data` (tes memakai folder kecil) */
    public function __construct(private ?string $folderData = null) {}

    public function run(): void
    {
        $this->ringkasan = [];

        DB::transaction(function () {
            $this->tahunAjaran();
            $this->mapel();
            $this->akun();
            $this->rombel();
            $this->siswa();
            $this->kelas();
        });

        foreach ($this->ringkasan as $label => $jumlah) {
            $this->command?->line(sprintf('  %-36s %d', $label, $jumlah));
        }
        $this->command?->info('Data sekolah selesai diisi (yang sudah ada tidak diubah).');
    }

    /** @return array<string, int> jumlah baris baru/dilewati per jenis dari jalan terakhir; dibaca tes */
    public function ringkasan(): array
    {
        return $this->ringkasan;
    }

    /** @return list<array<int, mixed>> */
    private function data(string $nama): array
    {
        return require ($this->folderData ?? __DIR__.'/data')."/{$nama}.php";
    }

    private function catat(string $label, int $tambah = 1): void
    {
        $this->ringkasan[$label] = ($this->ringkasan[$label] ?? 0) + $tambah;
    }

    private function tahunAjaran(): void
    {
        foreach (self::TAHUN_AJARAN as [$nama, $semester, $aktif]) {
            $tahun = TahunAjaran::firstOrCreate(['nama' => $nama, 'semester' => $semester], ['aktif' => false]);
            if (! $tahun->wasRecentlyCreated) {
                continue;
            }
            // Hanya satu tahun ajaran aktif; tahun baru dari daftar ini aktif hanya bila belum ada yang aktif.
            if ($aktif && ! TahunAjaran::where('aktif', true)->exists()) {
                $tahun->update(['aktif' => true]);
            }
            $this->catat('Tahun ajaran baru');
        }
    }

    private function mapel(): void
    {
        foreach (self::MAPEL as $nama) {
            if (Mapel::firstOrCreate(['nama' => $nama])->wasRecentlyCreated) {
                $this->catat('Mapel baru');
            }
        }
    }

    private function akun(): void
    {
        foreach ($this->data('akun') as [$username, $nama, $peran, $hash, $aktif]) {
            $this->buatAkun($username, $nama, $peran, $hash, $aktif) !== null
                ? $this->catat('Akun admin/kepsek/guru baru')
                : $this->catat('Akun admin/kepsek/guru sudah ada', 1);
        }
    }

    private function rombel(): void
    {
        foreach (self::ROMBEL as $nama => [$tingkat, $jurusan, $wali]) {
            $rombel = Rombel::firstOrCreate(['nama' => $nama], [
                'tingkat' => $tingkat,
                'jurusan' => $jurusan,
                'waliKelasId' => $wali ? Akun::where('username', $wali)->whereIn('role', Akun::PERAN_PENGAJAR)->value('id') : null,
            ]);
            if ($rombel->wasRecentlyCreated) {
                $this->catat('Rombel baru');
            }
        }
    }

    private function siswa(): void
    {
        $rombel = [];
        foreach ($this->data('siswa') as [$namaRombel, $nama, $username, $hash, $nis, $aktif]) {
            $id = $this->buatAkun($username, $nama, 'SISWA', $hash, $aktif);
            if ($id === null) {
                $this->catat('Siswa sudah ada');

                continue;
            }

            $rombel[$namaRombel] ??= Rombel::where('nama', $namaRombel)->value('id') ?? $this->rombelDariNama($namaRombel);
            Siswa::create([
                'userId' => $id,
                'rombelId' => $rombel[$namaRombel],
                // NIS yang sudah dipakai siswa lain (diubah admin) dikosongkan supaya kunci unik tidak bentrok.
                'nis' => $nis !== null && ! Siswa::where('nis', $nis)->exists() ? $nis : null,
                'nama' => $nama,
            ]);
            $this->catat('Siswa baru');
        }
    }

    private function kelas(): void
    {
        $guru = Akun::whereIn('role', Akun::PERAN_PENGAJAR)->pluck('id', 'username');

        foreach ($this->data('kelas') as [$namaRombel, $namaMapel, $username, $namaTahun, $semester, $deskripsi, $arsip]) {
            $guruId = $guru[$username] ?? null;
            if ($guruId === null) {
                // Guru sudah dihapus atau diganti perannya oleh admin: ruang kelasnya tidak dibuat lagi.
                $this->catat('Ruang kelas dilewati (guru tidak ada)');

                continue;
            }

            $kelas = Kelas::firstOrCreate([
                'rombelId' => Rombel::where('nama', $namaRombel)->value('id') ?? $this->rombelDariNama($namaRombel),
                'mapelId' => Mapel::firstOrCreate(['nama' => $namaMapel])->id,
                'guruId' => $guruId,
                'tahunAjaranId' => TahunAjaran::firstOrCreate(['nama' => $namaTahun, 'semester' => $semester], ['aktif' => false])->id,
            ], ['deskripsi' => $deskripsi, 'diarsipkan' => $arsip]);

            $this->catat($kelas->wasRecentlyCreated ? 'Ruang kelas baru' : 'Ruang kelas sudah ada');
        }
    }

    /** Rombel yang dihapus/diganti nama oleh admin tetapi masih dipakai data: dibuat lagi, tingkat dan jurusan dari namanya. */
    private function rombelDariNama(string $nama): string
    {
        [$tingkat, $jurusan] = explode(' ', $nama);

        return Rombel::create(['nama' => $nama, 'tingkat' => $tingkat, 'jurusan' => $jurusan])->id;
    }

    /**
     * Akun baru (baris `users` + `profil_akun`) dengan hash sandi apa adanya; null bila username sudah dipakai. Lewat query
     * builder supaya hash tidak di-hash ulang oleh cast `hashed` dan 400 akun tidak perlu dihitung bcrypt satu per satu.
     */
    private function buatAkun(string $username, string $nama, string $peran, string $hash, bool $aktif): ?int
    {
        // Email sintetis sama dengan yang dibuat model Akun; login memakai username.
        $email = Str::limit($username.'@akun.local', 100, '');
        if (Akun::where('username', $username)->exists() || DB::table('users')->where('email', $email)->exists()) {
            return null;
        }

        $id = DB::table('users')->insertGetId([
            'email' => $email,
            'password' => $hash,
            'role' => $peran === 'ADMIN' ? 'admin' : 'user',
        ]);
        DB::table('profil_akun')->insert([
            'id' => $id,
            'username' => $username,
            'name' => $nama,
            'role' => $peran,
            'is_active' => $aktif,
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        return $id;
    }
}
