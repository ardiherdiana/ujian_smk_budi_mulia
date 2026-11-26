<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Rombel;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Data > Guru (disalin dari GuruCrudTest Classroom, tanpa foto dan tanpa tautan absensi). */
class GuruCrudTest extends TestCase
{
    private function adminMasuk(): Akun
    {
        $admin = $this->akun('ADMIN', 'adm');
        $this->actingAs($admin);

        return $admin;
    }

    private function data(array $ubah = []): array
    {
        return $ubah + ['nama' => 'Dina Qoyimah', 'username' => 'bu.dina', 'password' => 'rahasia123', 'role' => 'GURU'];
    }

    public function test_admin_menambah_guru_dan_guru_bisa_masuk(): void
    {
        $this->adminMasuk();

        $this->post('/data-guru', $this->data())->assertSessionHasNoErrors();

        $akun = Akun::where('username', 'bu.dina')->firstOrFail();
        $this->assertSame('GURU', $akun->role);
        $this->assertSame('Dina Qoyimah', $akun->name);
        $this->assertTrue($akun->is_active);
        $this->assertTrue(Hash::check('rahasia123', $akun->password));

        $this->post('/logout');
        $this->post('/login', ['email' => 'bu.dina', 'password' => 'rahasia123'])->assertRedirect('/kelas');
        $this->assertAuthenticatedAs(User::find($akun->id));
    }

    public function test_daftar_hanya_guru_dan_kepsek_dengan_pencarian(): void
    {
        $this->akun('GURU', 'guru1', 'Budi Santoso');
        $this->akun('KEPSEK', 'kepala', 'Teti');
        $this->siswa($this->rombel(), 'siswa01');
        $this->adminMasuk();

        $this->get('/data-guru')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('classroom/admin/guru-page')
            ->has('guru.data', 2)
            ->where('guru.data.0.nama', 'Budi Santoso'));

        $this->get('/data-guru?q=teti')->assertInertia(fn (Assert $page) => $page->has('guru.data', 1)->where('guru.data.0.role', 'KEPSEK'));
    }

    public function test_validasi_username_dobel_karakter_terlarang_dan_peran(): void
    {
        $this->akun('GURU', 'bu.dina');
        $this->adminMasuk();

        $this->post('/data-guru', $this->data())->assertSessionHasErrors('username');
        $this->post('/data-guru', $this->data(['username' => 'a@b']))->assertSessionHasErrors('username');
        $this->post('/data-guru', $this->data(['username' => 'baru', 'password' => '123']))->assertSessionHasErrors('password');
        $this->post('/data-guru', $this->data(['username' => 'baru', 'role' => 'ADMIN']))->assertSessionHasErrors('role');
        $this->post('/data-guru', $this->data(['username' => 'baru', 'role' => 'SISWA']))->assertSessionHasErrors('role');
        $this->post('/data-guru', $this->data(['username' => 'baru', 'nama' => str_repeat('a', 256)]))->assertSessionHasErrors('nama');

        $this->assertDatabaseMissing('profil_akun', ['username' => 'baru']);
    }

    public function test_hanya_admin_yang_mengubah_data_guru(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $kepsek = $this->akun('KEPSEK', 'kepala');
        $siswa = $this->siswa($this->rombel(), 'siswa01');

        foreach ([$guru, $kepsek] as $pengguna) {
            $this->actingAs($pengguna);
            $this->post('/data-guru', $this->data(['username' => 'x'.$pengguna->id]))->assertForbidden();
            $this->patch("/data-guru/{$guru->id}", $this->data(['username' => 'guru1']))->assertForbidden();
            $this->delete("/data-guru/{$guru->id}")->assertForbidden();
        }

        // Kepsek tetap boleh melihat daftarnya; guru dan siswa tidak.
        $this->actingAs($kepsek)->get('/data-guru')->assertOk();
        $this->actingAs($guru)->get('/data-guru')->assertForbidden();
        $this->actingAs($siswa)->get('/data-guru')->assertForbidden();
    }

    public function test_admin_mengubah_nama_peran_sandi_dan_status(): void
    {
        $guru = $this->akun('GURU', 'guru1', 'Nama Lama');
        $this->adminMasuk();

        $this->patch("/data-guru/{$guru->id}", $this->data([
            'nama' => 'Nama Baru', 'username' => 'guru.baru', 'password' => 'sandibaru1', 'role' => 'KEPSEK', 'aktif' => 0,
        ]))->assertSessionHasNoErrors();

        $guru->refresh();
        $this->assertSame('Nama Baru', $guru->name);
        $this->assertSame('guru.baru', $guru->username);
        $this->assertSame('KEPSEK', $guru->role);
        $this->assertFalse($guru->is_active);
        $this->assertTrue(Hash::check('sandibaru1', $guru->password));
        // Email sintetis ikut username baru.
        $this->assertSame('guru.baru@akun.local', $guru->pengguna->email);
    }

    public function test_sandi_dibiarkan_bila_kosong_dan_username_sendiri_tidak_dianggap_dobel(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $lama = $guru->password;
        $this->adminMasuk();

        $this->patch("/data-guru/{$guru->id}", $this->data(['username' => 'guru1', 'password' => '']))->assertSessionHasNoErrors();

        $this->assertSame($lama, $guru->fresh()->password);
    }

    public function test_admin_tidak_bisa_menonaktifkan_diri_sendiri_dan_peran_admin_tidak_berubah(): void
    {
        $admin = $this->adminMasuk();

        $this->patch("/data-guru/{$admin->id}", $this->data(['username' => $admin->username, 'aktif' => 0]))->assertStatus(409);
        $this->patch("/data-guru/{$admin->id}", $this->data(['username' => $admin->username, 'role' => 'GURU']))->assertSessionHasNoErrors();

        $this->assertSame('ADMIN', $admin->fresh()->role);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_hapus_guru_menghapus_akun_dan_mengosongkan_wali_kelas(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        Rombel::whereKey($rombel->id)->update(['waliKelasId' => $guru->id]);
        $this->adminMasuk();

        $this->delete("/data-guru/{$guru->id}")->assertRedirect();

        $this->assertDatabaseMissing('profil_akun', ['id' => $guru->id]);
        $this->assertDatabaseMissing('users', ['id' => $guru->id]);
        $this->assertNull($rombel->fresh()->waliKelasId);
    }

    public function test_guru_yang_masih_mengajar_pembuat_ujian_admin_dan_siswa_tidak_bisa_dihapus(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $kelas = $this->kelas($guru, $this->rombel());
        $pembuat = $this->akun('GURU', 'guru2');
        $this->buatPost($kelas, $pembuat, 'UJIAN');
        $siswa = $this->siswa($this->rombel('X TKJ 2'), 'siswa01');
        $admin = $this->adminMasuk();

        $this->delete("/data-guru/{$guru->id}")->assertStatus(409);
        $this->delete("/data-guru/{$pembuat->id}")->assertStatus(409);
        $this->delete("/data-guru/{$admin->id}")->assertStatus(409);
        $this->delete("/data-guru/{$siswa->id}")->assertNotFound();
        $this->patch("/data-guru/{$siswa->id}", $this->data(['username' => 'siswa01']))->assertNotFound();

        foreach ([$guru, $pembuat, $admin, $siswa] as $akun) {
            $this->assertDatabaseHas('profil_akun', ['id' => $akun->id]);
        }
    }
}
