<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Profil: data akun dan ganti sandi (disalin dari ProfilTest landing_page, tanpa foto, data guru, dan SPPD). */
class ProfilTest extends TestCase
{
    private function gantiSandi(string $lama, string $baru, ?string $ulang = null)
    {
        return $this->patch('/profil/password', ['current_password' => $lama, 'password' => $baru, 'password_confirmation' => $ulang ?? $baru]);
    }

    public function test_profil_butuh_login(): void
    {
        $this->get('/profil')->assertRedirect('/login');
        $this->patch('/profil/password')->assertRedirect('/login');
    }

    public function test_guru_melihat_data_akunnya(): void
    {
        $this->actingAs($this->akun('GURU', 'bu.dina', 'Dina Qoyimah'));

        $this->get('/profil')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('profil-page')
            ->where('akun.nama', 'Dina Qoyimah')
            ->where('akun.username', 'bu.dina')
            ->where('akun.peran', 'GURU')
            // Email sintetis @akun.local tidak ditampilkan.
            ->where('akun.email', null)
            ->where('siswa', null));
    }

    public function test_siswa_melihat_nis_dan_rombel(): void
    {
        $siswa = $this->siswa($this->rombel('XI TKJ 1', 'XI'), 'siswa01');
        $this->actingAs($siswa);

        $this->get('/profil')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('akun.peran', 'SISWA')
            ->where('siswa.rombel', 'XI TKJ 1')
            ->where('siswa.nis', $siswa->siswa->nis));
    }

    public function test_guru_dan_siswa_bisa_ganti_sandi(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $siswa = $this->siswa($this->rombel(), 'siswa01');

        foreach ([$guru, $siswa] as $akun) {
            $this->actingAs($akun);
            $this->gantiSandi('rahasia123', 'SandiBaru123')->assertSessionHasNoErrors()->assertSessionHas('toast');
            $this->assertTrue(Hash::check('SandiBaru123', $akun->pengguna->fresh()->password));
        }

        // Sandi baru langsung dipakai untuk login berikutnya.
        $this->post('/logout');
        $this->post('/login', ['email' => 'guru1', 'password' => 'SandiBaru123'])->assertRedirect('/kelas');
    }

    public function test_aturan_sandi_baru(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $this->actingAs($guru);

        $this->gantiSandi('salah', 'SandiBaru123')->assertSessionHasErrors('current_password');
        $this->gantiSandi('rahasia123', 'pendek1')->assertSessionHasErrors('password');
        $this->gantiSandi('rahasia123', 'hanyahuruf')->assertSessionHasErrors('password');
        $this->gantiSandi('rahasia123', 'SandiBaru123', 'beda')->assertSessionHasErrors('password');
        $this->gantiSandi('rahasia123', 'rahasia123')->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('rahasia123', $guru->pengguna->fresh()->password));
    }
}
