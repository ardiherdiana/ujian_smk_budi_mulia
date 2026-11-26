<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Kelas;
use App\Models\Classroom\Lampiran;
use App\Models\Classroom\Percobaan;
use App\Models\Classroom\TahunAjaran;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KelasAksesTest extends TestCase
{
    /** @return array<string, mixed> */
    private function ujianBaru(string $judul = 'Kuis 1'): array
    {
        return ['tipe' => 'UJIAN', 'judul' => $judul, 'durasiMenit' => 30];
    }

    public function test_siswa_hanya_melihat_ruang_kelas_rombelnya(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombelA = $this->rombel('X TKJ 1');
        $rombelB = $this->rombel('X TKJ 2');
        $saya = $this->kelas($guru, $rombelA, 'Jaringan Dasar');
        $lain = $this->kelas($guru, $rombelB, 'Jaringan Dasar');
        $siswa = $this->siswa($rombelA, 'siswa01');

        $this->actingAs($siswa)->get('/kelas')
            ->assertInertia(fn (Assert $page) => $page->component('classroom/kelas/daftar-page')->has('kelas', 1)->where('kelas.0.id', $saya->id));

        $this->actingAs($siswa)->get("/kelas/{$saya->id}")->assertOk();
        $this->actingAs($siswa)->get("/kelas/{$lain->id}")->assertForbidden();
    }

    public function test_guru_hanya_melihat_ruang_kelas_yang_diajarnya(): void
    {
        $guru1 = $this->akun('GURU', 'guru1');
        $guru2 = $this->akun('GURU', 'guru2');
        $rombel = $this->rombel();
        $punya1 = $this->kelas($guru1, $rombel, 'Jaringan Dasar');
        $punya2 = $this->kelas($guru2, $rombel, 'Matematika');

        $this->actingAs($guru1)->get('/kelas')
            ->assertInertia(fn (Assert $page) => $page->has('kelas', 1)->where('kelas.0.id', $punya1->id));
        $this->actingAs($guru1)->get("/kelas/{$punya2->id}")->assertForbidden();
    }

    public function test_siswa_dan_kepsek_tidak_bisa_membuat_ujian(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        $kelas = $this->kelas($guru, $rombel);
        $siswa = $this->siswa($rombel, 'siswa01');

        $this->actingAs($siswa)->post("/kelas/{$kelas->id}/post", $this->ujianBaru())->assertForbidden();
        $this->actingAs($this->akun('KEPSEK', 'kepala'))->post("/kelas/{$kelas->id}/post", $this->ujianBaru())->assertForbidden();
        $this->assertDatabaseCount('classroom_posts', 0);
    }

    public function test_hanya_ujian_yang_bisa_dibuat(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $kelas = $this->kelas($guru, $this->rombel());

        foreach (['PENGUMUMAN', 'MATERI', 'TUGAS'] as $tipe) {
            $this->actingAs($guru)->post("/kelas/{$kelas->id}/post", ['tipe' => $tipe, 'judul' => 'x', 'isi' => 'x'])
                ->assertSessionHasErrors('tipe');
        }
        $this->assertDatabaseCount('classroom_posts', 0);
    }

    public function test_guru_membuat_ujian_dan_siswa_berkomentar(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        $kelas = $this->kelas($guru, $rombel);
        $siswa = $this->siswa($rombel, 'siswa01');

        $this->actingAs($guru)->post("/kelas/{$kelas->id}/post", $this->ujianBaru())->assertRedirect();
        $post = $kelas->posts()->first();
        $this->assertSame('UJIAN', $post->tipe);

        $this->actingAs($siswa)->post("/post/{$post->id}/komentar", ['isi' => 'Siap, Bu'])->assertRedirect();
        $this->assertDatabaseHas('komentar', ['postId' => $post->id, 'userId' => $siswa->id, 'isi' => 'Siap, Bu']);

        $this->actingAs($siswa)->get("/kelas/{$kelas->id}")
            ->assertInertia(fn (Assert $page) => $page->has('posts', 1)->where('posts.0.komentar.0.isi', 'Siap, Bu'));
    }

    public function test_ruang_kelas_diarsipkan_tidak_menerima_ujian_baru(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $kelas = $this->kelas($guru, $this->rombel());
        $kelas->update(['diarsipkan' => true]);

        $this->actingAs($guru)->post("/kelas/{$kelas->id}/post", $this->ujianBaru())->assertStatus(409);
        $this->actingAs($guru)->get('/kelas?arsip=1')
            ->assertInertia(fn (Assert $page) => $page->where('arsip', true)->has('kelas', 1));
    }

    public function test_penanda_kartu_kelas_ujian_belum_dikerjakan_dan_perlu_koreksi(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        $kelas = $this->kelas($guru, $rombel);
        $siswa = $this->siswa($rombel, 'siswa01');
        $ujian = $this->buatPost($kelas, $guru, 'UJIAN', ['durasiMenit' => 30, 'selesaiAt' => now()->addDay()]);
        // Ujian yang sudah ditutup tidak dihitung lagi untuk siswa.
        $this->buatPost($kelas, $guru, 'UJIAN', ['durasiMenit' => 30, 'selesaiAt' => now()->subDay()]);

        $this->actingAs($siswa)->get('/kelas')->assertInertia(fn (Assert $page) => $page->where('kelas.0.penanda', 1));

        Percobaan::create([
            'postId' => $ujian->id, 'siswaId' => $siswa->siswa->id, 'mulaiAt' => now(), 'batasAt' => now()->addMinutes(30),
            'selesaiAt' => now(), 'status' => 'SELESAI',
        ]);
        $this->actingAs($siswa)->get('/kelas')->assertInertia(fn (Assert $page) => $page->where('kelas.0.penanda', 0));
        $this->actingAs($guru)->get('/kelas')->assertInertia(fn (Assert $page) => $page->where('kelas.0.penanda', 1));
    }

    public function test_guru_membuat_ruang_kelas_untuk_diri_sendiri(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        TahunAjaran::create(['nama' => '2026/2027', 'semester' => 'GANJIL', 'aktif' => true]);

        $this->actingAs($guru)->get('/kelas')->assertInertia(fn (Assert $page) => $page
            ->where('bisaMembuat', true)->has('opsi.rombel', 1)->where('opsi.guru', null));

        $this->actingAs($guru)->post('/kelas', ['rombelId' => $rombel->id, 'mapelBaru' => 'Basis Data', 'guruId' => $this->akun('GURU', 'guru9')->id])
            ->assertRedirect();

        // guruId dari guru diabaikan: ruang kelasnya selalu milik guru itu sendiri.
        $kelas = Kelas::firstOrFail();
        $this->assertSame($guru->id, $kelas->guruId);
        $this->assertDatabaseHas('mapel', ['nama' => 'Basis Data']);

        // Mapel dan rombel yang sama di tahun ajaran yang sama ditolak untuk guru yang sama.
        $this->actingAs($guru)->withHeader('X-Inertia', 'true')->post('/kelas', ['rombelId' => $rombel->id, 'mapelBaru' => 'Basis Data'])->assertSessionHasErrors('message');
        $this->assertDatabaseCount('kelas', 1);

        // Guru lain boleh mengajar mapel yang sama di rombel itu.
        $lain = $this->akun('GURU', 'guru2');
        $this->actingAs($lain)->post('/kelas', ['rombelId' => $rombel->id, 'mapelId' => $kelas->mapelId])->assertRedirect();
        $this->assertDatabaseCount('kelas', 2);
    }

    public function test_admin_memilih_guru_dan_kepsek_siswa_tidak_bisa_membuat_ruang_kelas(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        $siswa = $this->siswa($rombel, 'siswa01');
        TahunAjaran::create(['nama' => '2026/2027', 'semester' => 'GANJIL', 'aktif' => true]);
        $admin = $this->akun('ADMIN', 'adm');

        $this->actingAs($admin)->get('/kelas')->assertInertia(fn (Assert $page) => $page->where('bisaMembuat', true)->has('opsi.guru', 1));
        $this->actingAs($admin)->post('/kelas', ['rombelId' => $rombel->id, 'mapelBaru' => 'Basis Data'])->assertStatus(422);
        $this->actingAs($admin)->post('/kelas', ['rombelId' => $rombel->id, 'mapelBaru' => 'Basis Data', 'guruId' => $guru->id])->assertRedirect();
        $this->assertDatabaseHas('kelas', ['guruId' => $guru->id, 'rombelId' => $rombel->id]);

        foreach ([$this->akun('KEPSEK', 'kepala'), $siswa] as $akun) {
            $this->actingAs($akun)->get('/kelas')->assertInertia(fn (Assert $page) => $page->where('bisaMembuat', false)->where('opsi', null));
            $this->actingAs($akun)->post('/kelas', ['rombelId' => $rombel->id, 'mapelBaru' => 'Lain', 'guruId' => $guru->id])->assertForbidden();
        }
        $this->assertDatabaseCount('kelas', 1);
    }

    public function test_ruang_kelas_butuh_tahun_ajaran_aktif_dan_mapel(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();

        $this->actingAs($guru)->post('/kelas', ['rombelId' => $rombel->id, 'mapelBaru' => 'Basis Data'])->assertStatus(409);

        TahunAjaran::create(['nama' => '2026/2027', 'semester' => 'GANJIL', 'aktif' => true]);
        $this->actingAs($guru)->post('/kelas', ['rombelId' => $rombel->id])->assertStatus(422);
        $this->actingAs($guru)->post('/kelas', ['mapelBaru' => 'Basis Data'])->assertSessionHasErrors('rombelId');
        $this->assertDatabaseCount('kelas', 0);
    }

    public function test_pengajar_mengubah_mengarsipkan_dan_menghapus_ruang_kelas(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $kelas = $this->kelas($guru, $this->rombel());
        $lain = $this->akun('GURU', 'guru2');

        foreach ([$lain, $this->akun('KEPSEK', 'kepala')] as $akun) {
            $this->actingAs($akun)->patch("/kelas/{$kelas->id}", ['deskripsi' => 'x'])->assertForbidden();
            $this->actingAs($akun)->post("/kelas/{$kelas->id}/arsip")->assertForbidden();
            $this->actingAs($akun)->delete("/kelas/{$kelas->id}")->assertForbidden();
        }

        $this->actingAs($guru)->patch("/kelas/{$kelas->id}", ['deskripsi' => 'Kelas pagi'])->assertRedirect();
        $this->assertSame('Kelas pagi', $kelas->fresh()->deskripsi);

        $this->actingAs($guru)->post("/kelas/{$kelas->id}/arsip")->assertRedirect('/kelas');
        $this->assertTrue($kelas->fresh()->diarsipkan);
        $this->actingAs($guru)->post("/kelas/{$kelas->id}/arsip")->assertRedirect('/kelas');
        $this->assertFalse($kelas->fresh()->diarsipkan);

        // Hapus ruang kelas ikut membuang ujian dan berkas lampirannya.
        $ujian = $this->buatPost($kelas, $guru, 'UJIAN');
        Storage::disk('local')->put('lampiran/kisi.pdf', '%PDF-1.4');
        Lampiran::create(['postId' => $ujian->id, 'tipe' => 'FILE', 'nama' => 'kisi.pdf', 'path' => 'lampiran/kisi.pdf', 'mime' => 'application/pdf', 'ukuran' => 8]);

        $this->actingAs($guru)->delete("/kelas/{$kelas->id}")->assertRedirect('/kelas');
        $this->assertDatabaseMissing('kelas', ['id' => $kelas->id]);
        $this->assertDatabaseMissing('classroom_posts', ['id' => $ujian->id]);
        Storage::disk('local')->assertMissing('lampiran/kisi.pdf');
    }
}
