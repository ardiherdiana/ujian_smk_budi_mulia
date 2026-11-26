<?php

namespace Tests\Feature\Classroom;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Temuan uji coba siswa, guru, dan admin (bug_siswa.xlsx, bug_guru.xlsx, bug_admin.xlsx) yang diperbaiki di server. */
class TemuanUjiCobaTest extends TestCase
{
    // ---- S-03, G-03, BUG-10: halaman 403/404 bergaya aplikasi ----------------------------------------------------

    public function test_kelas_guru_lain_menampilkan_halaman_403_dengan_pesannya(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $kelasLain = $this->kelas($this->akun('GURU', 'guru2'), $this->rombel());

        $this->actingAs($guru)->get("/kelas/{$kelasLain->id}")
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('error-page')
                ->where('status', 403)
                ->where('pesan', 'Anda tidak memiliki akses ke ruang kelas ini'));
    }

    public function test_siswa_membuka_editor_soal_mendapat_halaman_403(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        $ujian = $this->buatPost($this->kelas($guru, $rombel), $guru, 'UJIAN', ['durasiMenit' => 30]);
        $siswa = $this->siswa($rombel, 'siswa01');

        $this->actingAs($siswa)->get("/post/{$ujian->id}/soal")
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('error-page')->where('status', 403));
    }

    public function test_alamat_tidak_dikenal_menampilkan_halaman_404_tanpa_pesan_teknis(): void
    {
        $guru = $this->akun('GURU', 'guru1');

        // Rute tidak ada sama sekali, dan id ruang kelas yang tidak ada (pesan aslinya memuat nama model).
        foreach (['/tidak-ada', '/kelas/arsip'] as $url) {
            $this->actingAs($guru)->get($url)
                ->assertNotFound()
                ->assertInertia(fn (Assert $page) => $page->component('error-page')->where('status', 404)->where('pesan', null));
        }
        $this->get('/tidak-ada')->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('error-page'));
    }

    public function test_permintaan_json_dan_kiriman_formulir_tetap_memakai_respons_lama(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $kelasLain = $this->kelas($this->akun('GURU', 'guru2'), $this->rombel());

        $this->actingAs($guru)->getJson("/kelas/{$kelasLain->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Anda tidak memiliki akses ke ruang kelas ini');

        // Kiriman Inertia yang ditolak kembali ke formulir dengan pesan, bukan halaman error.
        $this->actingAs($guru)->from("/kelas/{$kelasLain->id}")
            ->post("/kelas/{$kelasLain->id}/post", ['tipe' => 'UJIAN', 'judul' => 'Kuis', 'durasiMenit' => 30], ['X-Inertia' => 'true'])
            ->assertRedirect("/kelas/{$kelasLain->id}")
            ->assertSessionHasErrors('message');
    }

    // ---- BUG-05: istilah "Ujian dihapus" ---------------------------------------------------------------------------

    public function test_toast_hapus_memakai_istilah_ujian(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $kelas = $this->kelas($guru, $this->rombel());
        $ujian = $this->buatPost($kelas, $guru, 'UJIAN', ['durasiMenit' => 30]);

        $this->actingAs($guru)->delete("/post/{$ujian->id}")
            ->assertRedirect("/kelas/{$kelas->id}")
            ->assertSessionHas('toast', ['type' => 'success', 'message' => 'Ujian dihapus']);
    }

    // ---- S-02, BUG-06: sidebar --------------------------------------------------------------------------------------

    public function test_sidebar_memuat_guru_dan_urut_tanpa_membedakan_huruf_besar_kecil(): void
    {
        $guruA = $this->akun('GURU', 'guru1', 'Andi');
        $guruB = $this->akun('GURU', 'guru2', 'Budi');
        $rombel = $this->rombel('XI TKJ 1', 'XI');
        $this->kelas($guruA, $rombel, 'BK/BP/Pembina Karakter');
        $this->kelas($guruA, $rombel, 'Bahasa Indonesia');
        // Satu mapel di satu rombel diajar dua guru: namanya sama, gurunya yang membedakan.
        $this->kelas($guruB, $rombel, 'Produktif TKJ');
        $this->kelas($guruA, $rombel, 'Produktif TKJ');
        $siswa = $this->siswa($rombel, 'siswa01');

        $this->actingAs($siswa)->get('/kelas')->assertInertia(fn (Assert $page) => $page
            ->has('kelasSaya', 4)
            ->where('kelasSaya.0.mapel', 'Bahasa Indonesia')
            ->where('kelasSaya.1.mapel', 'BK/BP/Pembina Karakter')
            ->where('kelasSaya.2.mapel', 'Produktif TKJ')
            ->where('kelasSaya.2.guru', 'Andi')
            ->where('kelasSaya.3.guru', 'Budi')
            // Beranda memakai urutan yang sama.
            ->where('kelas.0.mapel', 'Bahasa Indonesia')
            ->where('kelas.1.mapel', 'BK/BP/Pembina Karakter'));
    }

    // ---- S-01: ujian tanpa soal belum menjadi tugas siswa -----------------------------------------------------------

    public function test_ujian_tanpa_soal_tidak_masuk_mendatang_dan_nilai_saya(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        $kelas = $this->kelas($guru, $rombel);
        $siswa = $this->siswa($rombel, 'siswa01');
        $berisi = $this->buatPost($kelas, $guru, 'UJIAN', ['durasiMenit' => 30, 'selesaiAt' => now()->addDay()]);
        $this->soalPg($berisi);
        $this->buatPost($kelas, $guru, 'UJIAN', ['durasiMenit' => 30, 'mulaiAt' => now()->addDay()]);

        $this->actingAs($siswa)->get("/kelas/{$kelas->id}")->assertInertia(fn (Assert $page) => $page
            ->has('posts', 2) // ujiannya tetap terlihat di ruang kelas
            ->has('mendatang', 1)
            ->where('mendatang.0.id', $berisi->id));

        $this->actingAs($siswa)->get("/kelas/{$kelas->id}/nilai")->assertInertia(fn (Assert $page) => $page
            ->has('kolom', 1)
            ->where('kolom.0.id', $berisi->id));
        // Guru tetap melihat semua kolom ujian di rekap.
        $this->actingAs($guru)->get("/kelas/{$kelas->id}/nilai")->assertInertia(fn (Assert $page) => $page->has('kolom', 2));
    }
}
