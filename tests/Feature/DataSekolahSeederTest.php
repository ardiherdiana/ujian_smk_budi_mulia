<?php

namespace Tests\Feature;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\Mapel;
use App\Models\Classroom\Rombel;
use App\Models\Classroom\Siswa;
use App\Models\Classroom\TahunAjaran;
use Database\Seeders\DataSekolahSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * DataSekolahSeeder berdiri sendiri: datanya di `database/seeders/data`, tidak ada koneksi ke basis data Classroom. Tes
 * perilaku memakai folder data kecil di folder sementara supaya berkas data asli tidak tersentuh.
 */
class DataSekolahSeederTest extends TestCase
{
    private string $folder;

    private string $hash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hash = password_hash('Rahasia123', PASSWORD_BCRYPT);
        $this->folder = sys_get_temp_dir().'/data-sekolah-'.uniqid();
        mkdir($this->folder);
        $this->tulis('akun', [
            ['adm', 'Administrator', 'ADMIN', $this->hash, true],
            ['bu.dina', 'Dina Qoyimah', 'GURU', $this->hash, true],
            ['pak.budi', 'Budi Santoso', 'GURU', $this->hash, true],
        ]);
        $this->tulis('siswa', [
            ['X TKJ 1', 'Ani', '26270001', $this->hash, '1001', true],
            ['X TKJ 2', 'Budi', '26270002', $this->hash, '1002', true],
        ]);
        $this->tulis('kelas', [
            ['X TKJ 1', 'Produktif TKJ', 'bu.dina', '2026/2027', 'GANJIL', 'Kelas pagi', false],
            ['X TKJ 2', 'Informatika', 'pak.budi', '2026/2027', 'GANJIL', null, false],
        ]);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->folder.'/*.php') ?: []);
        @rmdir($this->folder);

        parent::tearDown();
    }

    private function tulis(string $nama, array $baris): void
    {
        file_put_contents("{$this->folder}/{$nama}.php", '<?php return '.var_export($baris, true).';');
    }

    private function seedKecil(): DataSekolahSeeder
    {
        return tap(new DataSekolahSeeder($this->folder), fn (DataSekolahSeeder $s) => $s->run());
    }

    public function test_tidak_ada_lagi_koneksi_ke_basis_data_classroom(): void
    {
        $this->assertNull(config('database.connections.classroom'));
        $this->assertFileDoesNotExist(database_path('seeders/SalinClassroomSeeder.php'));
    }

    public function test_db_seed_mengisi_data_asli_lalu_dilewati_bila_sudah_berisi(): void
    {
        $this->seed();

        $this->assertSame(402, Akun::count());
        $peran = Akun::query()->selectRaw('role, count(*) n')->groupBy('role')->pluck('n', 'role')->map(fn ($n) => (int) $n)->all();
        ksort($peran);
        $this->assertSame(['ADMIN' => 1, 'GURU' => 23, 'KEPSEK' => 1, 'SISWA' => 377], $peran);
        $this->assertSame(377, Siswa::count());
        $this->assertSame(14, Rombel::count());
        $this->assertSame(23, Mapel::count());
        $this->assertSame(158, Kelas::count());
        $this->assertSame(1, TahunAjaran::where('aktif', true)->count());

        // Hash sandi ditulis apa adanya, tidak di-hash ulang.
        $data = require database_path('seeders/data/akun.php');
        $akun = Akun::where('username', $data[0][0])->firstOrFail();
        $this->assertSame($data[0][3], DB::table('users')->where('id', $akun->id)->value('password'));

        // db:seed kedua kali tidak mengisi apa pun lagi, walau ada data yang sudah dihapus admin.
        Kelas::query()->delete();
        $this->seed();
        $this->assertSame(0, Kelas::count());
        $this->assertSame(402, Akun::count());
    }

    public function test_akun_dari_data_bisa_masuk_dengan_sandinya(): void
    {
        $this->seedKecil();

        $this->post('/login', ['email' => 'bu.dina', 'password' => 'Rahasia123'])->assertRedirect('/kelas');
        $this->assertAuthenticated();
        $this->assertSame('bu.dina@akun.local', DB::table('users')->where('id', Akun::where('username', 'bu.dina')->value('id'))->value('email'));
    }

    public function test_hanya_menambah_yang_belum_ada_tanpa_menimpa_perubahan_admin(): void
    {
        $this->seedKecil();

        $guru = Akun::where('username', 'bu.dina')->firstOrFail();
        $guru->forceFill(['name' => 'Dina Q.', 'password' => 'SandiBaru123', 'is_active' => false])->save();
        $ani = Siswa::where('nis', '1001')->firstOrFail();
        $ani->update(['rombelId' => Rombel::where('nama', 'X TKJ 2')->value('id')]);
        Kelas::where('deskripsi', 'Kelas pagi')->update(['deskripsi' => 'Diubah guru']);
        Mapel::create(['nama' => 'Mapel Tambahan Admin']);
        Siswa::where('nis', '1002')->firstOrFail()->user->delete();

        $ringkasan = $this->seedKecil()->ringkasan();

        $guru->refresh();
        $this->assertSame('Dina Q.', $guru->name);
        $this->assertFalse($guru->is_active);
        $this->assertTrue(Hash::check('SandiBaru123', $guru->password));
        $this->assertSame('X TKJ 2', $ani->fresh()->rombel->nama);
        $this->assertTrue(Kelas::where('deskripsi', 'Diubah guru')->exists());
        $this->assertTrue(Mapel::where('nama', 'Mapel Tambahan Admin')->exists());

        // Siswa yang dihapus dibuat lagi (seeder data awal); sisanya tidak berlipat.
        $this->assertSame(1, $ringkasan['Siswa baru'] ?? 0);
        $this->assertSame(2, Siswa::count());
        $this->assertSame(5, Akun::count());
        $this->assertSame(2, Kelas::count());
        $this->assertSame(14, Rombel::count());
    }

    public function test_ruang_kelas_guru_yang_sudah_dihapus_dilewati(): void
    {
        $this->tulis('akun', [['bu.dina', 'Dina Qoyimah', 'GURU', $this->hash, true]]);

        $ringkasan = $this->seedKecil()->ringkasan();

        $this->assertSame(1, Kelas::count());
        $this->assertSame(1, $ringkasan['Ruang kelas dilewati (guru tidak ada)']);
        $this->assertSame('bu.dina', Kelas::first()->guru->username);
    }

    public function test_nis_yang_sudah_dipakai_siswa_lain_dikosongkan(): void
    {
        $lama = $this->siswa($this->rombel('XI RPL', 'XI', 'RPL'), 'siswa.lama');
        Siswa::where('userId', $lama->id)->update(['nis' => '1001']);

        $this->seedKecil();

        $ani = Akun::where('username', '26270001')->firstOrFail()->siswa;
        $this->assertNull($ani->nis);
        $this->assertSame('1001', $lama->siswa->fresh()->nis);
    }
}
