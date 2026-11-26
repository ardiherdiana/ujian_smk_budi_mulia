<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Mapel;
use App\Models\Classroom\Rombel;
use App\Models\Classroom\Siswa;
use App\Models\Classroom\TahunAjaran;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Data induk (Tahun Ajaran, Mata Pelajaran, Rombel, Siswa): admin mengelola, kepsek hanya melihat. */
class DataIndukTest extends TestCase
{
    private function adminMasuk(): Akun
    {
        return tap($this->akun('ADMIN', 'adm'), fn (Akun $a) => $this->actingAs($a));
    }

    public function test_hak_akses_halaman_data_induk(): void
    {
        $halaman = [
            '/tahun-ajaran' => 'classroom/admin/tahun-ajaran-page',
            '/mapel' => 'classroom/admin/mapel-page',
            '/data-guru' => 'classroom/admin/guru-page',
            '/rombel' => 'classroom/admin/rombel-page',
            '/siswa' => 'classroom/admin/siswa-page',
        ];
        $rombel = $this->rombel();
        $guru = $this->akun('GURU', 'guru1');
        $siswa = $this->siswa($rombel, 'siswa01');

        foreach ([$this->akun('ADMIN', 'adm'), $this->akun('KEPSEK', 'kepala')] as $akun) {
            foreach ($halaman as $url => $komponen) {
                $this->actingAs($akun)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component($komponen));
            }
        }
        foreach ([$guru, $siswa] as $akun) {
            foreach (array_keys($halaman) as $url) {
                $this->actingAs($akun)->get($url)->assertForbidden();
            }
        }

        // Kepsek hanya melihat: menambah apa pun ditolak.
        $this->actingAs(Akun::where('username', 'kepala')->first());
        $this->post('/mapel', ['nama' => 'Fisika'])->assertForbidden();
        $this->post('/rombel', ['nama' => 'X RPL', 'tingkat' => 'X', 'jurusan' => 'RPL'])->assertForbidden();
        $this->post('/tahun-ajaran', ['nama' => '2027/2028', 'semester' => 'GANJIL'])->assertForbidden();
        $this->post('/siswa', ['nama' => 'A', 'username' => 'a1', 'password' => 'rahasia123', 'rombelId' => $rombel->id])->assertForbidden();
        $this->assertDatabaseMissing('mapel', ['nama' => 'Fisika']);
    }

    public function test_tahun_ajaran_hanya_satu_yang_aktif_dan_tidak_bisa_dihapus_bila_dipakai(): void
    {
        $this->adminMasuk();

        $this->post('/tahun-ajaran', ['nama' => '2026/2027', 'semester' => 'GANJIL', 'aktif' => 1])->assertSessionHasNoErrors();
        $this->post('/tahun-ajaran', ['nama' => '2026/2027', 'semester' => 'GENAP', 'aktif' => 1])->assertSessionHasNoErrors();
        $this->post('/tahun-ajaran', ['nama' => '2026/2027', 'semester' => 'GENAP'])->assertSessionHasErrors('semester');
        $this->post('/tahun-ajaran', ['nama' => '2026-2027', 'semester' => 'GANJIL'])->assertSessionHasErrors('nama');

        $ganjil = TahunAjaran::where('semester', 'GANJIL')->first();
        $genap = TahunAjaran::where('semester', 'GENAP')->first();
        $this->assertFalse($ganjil->aktif);
        $this->assertTrue($genap->aktif);

        $this->patch("/tahun-ajaran/{$ganjil->id}", ['nama' => '2026/2027', 'semester' => 'GANJIL', 'aktif' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($ganjil->fresh()->aktif);
        $this->assertFalse($genap->fresh()->aktif);

        $this->kelas($this->akun('GURU', 'guru1'), $this->rombel());
        $this->delete("/tahun-ajaran/{$ganjil->id}")->assertStatus(409);
        $this->delete("/tahun-ajaran/{$genap->id}")->assertRedirect();
        $this->assertDatabaseMissing('tahun_ajaran', ['id' => $genap->id]);
    }

    public function test_mapel_tambah_ubah_hapus(): void
    {
        $this->adminMasuk();

        $this->post('/mapel', ['nama' => 'Fisika'])->assertSessionHasNoErrors();
        $this->post('/mapel', ['nama' => 'Fisika'])->assertSessionHasErrors('nama');
        $mapel = Mapel::where('nama', 'Fisika')->firstOrFail();

        $this->patch("/mapel/{$mapel->id}", ['nama' => 'Fisika Terapan'])->assertSessionHasNoErrors();
        $this->assertSame('Fisika Terapan', $mapel->fresh()->nama);

        $dipakai = $this->kelas($this->akun('GURU', 'guru1'), $this->rombel(), 'Kimia')->mapel;
        $this->delete("/mapel/{$dipakai->id}")->assertStatus(409);
        $this->delete("/mapel/{$mapel->id}")->assertRedirect();
        $this->assertDatabaseMissing('mapel', ['id' => $mapel->id]);
        $this->assertDatabaseHas('mapel', ['id' => $dipakai->id]);
    }

    public function test_rombel_dengan_wali_kelas_dan_tidak_bisa_dihapus_bila_ada_siswa(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $siswaLain = $this->siswa($this->rombel('XII AKT', 'XII', 'AKT'), 'siswa09');
        $this->adminMasuk();

        $this->post('/rombel', ['nama' => 'X RPL', 'tingkat' => 'X', 'jurusan' => 'RPL', 'waliKelasId' => $guru->id])->assertSessionHasNoErrors();
        $this->post('/rombel', ['nama' => 'X RPL', 'tingkat' => 'X', 'jurusan' => 'RPL'])->assertSessionHasErrors('nama');
        $this->post('/rombel', ['nama' => 'X TKJ 9', 'tingkat' => 'XIII', 'jurusan' => 'TKJ'])->assertSessionHasErrors('tingkat');
        // Wali kelas harus pengajar, bukan siswa.
        $this->post('/rombel', ['nama' => 'X TKJ 9', 'tingkat' => 'X', 'jurusan' => 'TKJ', 'waliKelasId' => $siswaLain->id])->assertSessionHasErrors('waliKelasId');

        $rombel = Rombel::where('nama', 'X RPL')->firstOrFail();
        $this->assertSame($guru->id, $rombel->waliKelasId);

        $this->patch("/rombel/{$rombel->id}", ['nama' => 'X RPL 1', 'tingkat' => 'X', 'jurusan' => 'RPL', 'waliKelasId' => null])->assertSessionHasNoErrors();
        $this->assertSame('X RPL 1', $rombel->fresh()->nama);
        $this->assertNull($rombel->fresh()->waliKelasId);

        $this->delete('/rombel/'.$siswaLain->siswa->rombelId)->assertStatus(409);
        $this->delete("/rombel/{$rombel->id}")->assertRedirect();
        $this->assertDatabaseMissing('rombel', ['id' => $rombel->id]);
    }

    public function test_siswa_tambah_masuk_ubah_pindah_rombel_dan_hapus(): void
    {
        $rombelA = $this->rombel('X TKJ 1');
        $rombelB = $this->rombel('X TKJ 2');
        $this->adminMasuk();

        $this->post('/siswa', ['nama' => 'Ani', 'username' => '26270001', 'password' => 'rahasia123', 'rombelId' => $rombelA->id, 'nis' => '1001'])
            ->assertSessionHasNoErrors();
        $this->post('/siswa', ['nama' => 'Budi', 'username' => '26270001', 'password' => 'rahasia123', 'rombelId' => $rombelA->id])->assertSessionHasErrors('username');
        $this->post('/siswa', ['nama' => 'Budi', 'username' => '26270002', 'password' => 'rahasia123', 'rombelId' => $rombelA->id, 'nis' => '1001'])->assertSessionHasErrors('nis');
        $this->post('/siswa', ['nama' => 'Budi', 'username' => '26270002', 'password' => 'rahasia123'])->assertSessionHasErrors('rombelId');

        $siswa = Siswa::where('nis', '1001')->firstOrFail();
        $this->assertSame('SISWA', $siswa->user->role);
        $this->get('/siswa?q=ani')->assertInertia(fn (Assert $page) => $page->has('siswa.data', 1)->where('siswa.data.0.rombel', 'X TKJ 1'));
        $this->get("/siswa?rombelId={$rombelB->id}")->assertInertia(fn (Assert $page) => $page->has('siswa.data', 0));

        $this->patch("/siswa/{$siswa->id}", [
            'nama' => 'Ani Lestari', 'username' => '26270001', 'password' => 'sandibaru1', 'rombelId' => $rombelB->id, 'nis' => '1001', 'aktif' => 0,
        ])->assertSessionHasNoErrors();

        $siswa->refresh();
        $this->assertSame('Ani Lestari', $siswa->nama);
        $this->assertSame('Ani Lestari', $siswa->user->name);
        $this->assertSame($rombelB->id, $siswa->rombelId);
        $this->assertFalse($siswa->user->is_active);
        $this->assertTrue(Hash::check('sandibaru1', $siswa->user->password));

        $this->delete("/siswa/{$siswa->id}")->assertRedirect();
        $this->assertDatabaseMissing('siswa', ['id' => $siswa->id]);
        $this->assertDatabaseMissing('profil_akun', ['id' => $siswa->userId]);
        $this->assertNull(User::find($siswa->userId));
    }

    public function test_siswa_baru_bisa_masuk_dan_melihat_ruang_kelas_rombelnya(): void
    {
        $rombel = $this->rombel();
        $kelas = $this->kelas($this->akun('GURU', 'guru1'), $rombel);
        $this->adminMasuk();

        $this->post('/siswa', ['nama' => 'Citra', 'username' => '26270003', 'password' => 'rahasia123', 'rombelId' => $rombel->id])->assertSessionHasNoErrors();
        $this->post('/logout');

        $this->post('/login', ['email' => '26270003', 'password' => 'rahasia123'])->assertRedirect('/kelas');
        $this->get('/kelas')->assertInertia(fn (Assert $page) => $page->has('kelas', 1)->where('kelas.0.id', $kelas->id));
    }
}
