<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Akun;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Login (disalin dari LoginMenuTest Classroom; aplikasi ujian tidak punya Menu Utama, login langsung ke /kelas). */
class LoginMenuTest extends TestCase
{
    private function recaller(): string
    {
        return $this->app['auth']->guard()->getRecallerName();
    }

    public function test_siswa_login_dengan_username_lalu_ke_daftar_kelas(): void
    {
        $this->siswa($this->rombel(), 'siswa01');

        $this->post('/login', ['email' => 'siswa01', 'password' => 'rahasia123'])->assertRedirect('/kelas');
        $this->assertAuthenticated();

        $this->get('/kelas')->assertInertia(fn (Assert $page) => $page
            ->component('classroom/kelas/daftar-page')
            ->where('auth.user.role', 'SISWA')
            ->where('auth.user.username', 'siswa01'));
    }

    public function test_sandi_siswa_salah_atau_nonaktif_ditolak(): void
    {
        $akun = $this->siswa($this->rombel(), 'siswa01');

        $this->post('/login', ['email' => 'siswa01', 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $akun->update(['is_active' => false]);
        $this->post('/login', ['email' => 'siswa01', 'password' => 'rahasia123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_guru_login_dengan_username(): void
    {
        $this->akun('GURU', 'bu.dina', 'Dina Qoyimah, S.Pd. Gr.');

        $this->post('/login', ['email' => 'bu.dina', 'password' => 'rahasia123'])->assertRedirect('/kelas');
        $this->assertAuthenticated();
        $this->get('/kelas')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.name', 'Dina Qoyimah, S.Pd. Gr.'));
    }

    public function test_sandi_guru_salah_ditolak(): void
    {
        $this->akun('GURU', 'bu.dina');

        $this->post('/login', ['email' => 'bu.dina', 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_hash_sandi_yang_disalin_dari_classroom_berlaku(): void
    {
        $akun = $this->akun('GURU', 'bu.dina');
        // Seperti DataSekolahSeeder: hash bcrypt ditulis apa adanya, tanpa di-hash ulang.
        DB::table('users')->where('id', $akun->id)->update(['password' => password_hash('SandiClassroom1', PASSWORD_BCRYPT)]);

        $this->post('/login', ['email' => 'bu.dina', 'password' => 'SandiClassroom1'])->assertRedirect('/kelas');
        $this->assertAuthenticated();
    }

    public function test_sandi_bukan_bcrypt_tidak_pernah_diterima(): void
    {
        $akun = $this->akun('GURU', 'bu.dina');
        DB::table('users')->where('id', $akun->id)->update(['password' => 'mentah123']);

        $this->post('/login', ['email' => 'bu.dina', 'password' => 'mentah123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_ingat_saya_hanya_untuk_staf(): void
    {
        $guru = $this->akun('GURU', 'bu.dina');

        $this->post('/login', ['email' => 'bu.dina', 'password' => 'rahasia123'])->assertCookie($this->recaller());
        $this->assertNotNull($guru->fresh()->remember_token);
        $this->post('/logout');

        $this->siswa($this->rombel(), 'siswa01');
        $this->post('/login', ['email' => 'siswa01', 'password' => 'rahasia123'])->assertCookieMissing($this->recaller());
    }

    public function test_login_dengan_email_akun_admin(): void
    {
        $admin = $this->akun('ADMIN', 'admin');
        DB::table('users')->where('id', $admin->id)->update(['email' => 'admin@test.local']);

        $this->post('/login', ['email' => 'admin@test.local', 'password' => 'rahasia123'])->assertRedirect('/kelas');
        $this->get('/kelas')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.role', 'ADMIN')->where('auth.user.admin', true));
    }

    public function test_akun_tanpa_profil_tidak_bisa_masuk(): void
    {
        // Mis. akun portal/SKL di Classroom: tidak disalin, dan kalaupun ada barisnya, tidak punya profil.
        $this->student('alumni@test.local', 'Rahasia123');

        $this->post('/login', ['email' => 'alumni@test.local', 'password' => 'Rahasia123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_akun_yang_dinonaktifkan_saat_masih_masuk_langsung_dikeluarkan(): void
    {
        $guru = $this->akun('GURU', 'bu.dina');
        $this->actingAs($guru)->get('/kelas')->assertOk();

        $guru->update(['is_active' => false]);
        $this->actingAs($guru->fresh())->get('/kelas')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_halaman_butuh_login(): void
    {
        $this->get('/kelas')->assertRedirect('/login');
        $this->get('/profil')->assertRedirect('/login');
    }

    public function test_tamu_yang_sudah_masuk_tidak_melihat_halaman_login_lagi(): void
    {
        $this->actingAs($this->akun('GURU', 'guru1'))->get('/login')->assertRedirect('/kelas');
    }

    public function test_profil_tercipta_hanya_satu_baris_users_per_akun(): void
    {
        $this->akun('GURU', 'guru1');

        $this->assertSame(1, Akun::count());
        $this->assertDatabaseCount('users', 1);
    }
}
