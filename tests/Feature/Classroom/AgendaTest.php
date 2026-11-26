<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Percobaan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Daftar ujian lintas kelas dan Kalender (AgendaTest Classroom, versi ujian). */
class AgendaTest extends TestCase
{
    /** @return array<string, mixed> */
    private function jendela(int $hariTutup = 1): array
    {
        return ['durasiMenit' => 30, 'mulaiAt' => now()->subDay(), 'selesaiAt' => now()->addDays($hariTutup)];
    }

    public function test_siswa_hanya_melihat_ujian_dan_kelas_rombelnya(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombelA = $this->rombel('X TKJ 1');
        $rombelB = $this->rombel('X TKJ 2');
        $kelasA = $this->kelas($guru, $rombelA, 'Jaringan Dasar');
        $kelasB = $this->kelas($guru, $rombelB, 'Jaringan Dasar');
        $siswa = $this->siswa($rombelA, 'siswa01');
        $ujianA = $this->buatPost($kelasA, $guru, 'UJIAN', $this->jendela());
        $this->soalPg($ujianA);
        $this->soalPg($this->buatPost($kelasB, $guru, 'UJIAN', $this->jendela()));

        $this->actingAs($siswa)->get('/ujian')->assertInertia(fn (Assert $page) => $page
            ->component('classroom/agenda/tugas-page')
            ->has('items', 1)
            ->where('items.0.id', $ujianA->id)
            ->where('items.0.tipe', 'UJIAN')
            ->where('items.0.status', 'BELUM')
            ->has('kelasSaya', 1)
            ->where('kelasSaya.0.id', $kelasA->id));
    }

    public function test_status_pengerjaan_siswa_dan_hitungan_menunggu_koreksi_guru(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        $kelas = $this->kelas($guru, $rombel);
        $siswa = $this->siswa($rombel, 'siswa01');
        $ujian = $this->buatPost($kelas, $guru, 'UJIAN', $this->jendela());
        $this->soalPg($ujian);
        Percobaan::create([
            'postId' => $ujian->id, 'siswaId' => $siswa->siswa->id, 'mulaiAt' => now(), 'batasAt' => now()->addMinutes(30),
            'selesaiAt' => now(), 'status' => 'SELESAI',
        ]);

        $this->actingAs($siswa)->get('/ujian')->assertInertia(fn (Assert $page) => $page
            ->where('items.0.status', 'DIKUMPULKAN'));
        $this->actingAs($guru)->get('/ujian')->assertInertia(fn (Assert $page) => $page
            ->where('items.0.menunggu', 1)
            ->missing('items.0.status'));
    }

    public function test_ujian_tanpa_soal_bukan_tugas_siswa_tetapi_tetap_terlihat_guru(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        $kelas = $this->kelas($guru, $rombel);
        $siswa = $this->siswa($rombel, 'siswa01');
        // Kasus S-01: dibuka besok, token aktif, belum ada soal.
        $kosong = $this->buatPost($kelas, $guru, 'UJIAN', [
            'durasiMenit' => 30, 'mulaiAt' => now()->addDay(), 'selesaiAt' => now()->addDays(2), 'tokenAkses' => 'ABCDEF',
        ]);

        $this->actingAs($siswa)->get('/ujian')->assertInertia(fn (Assert $page) => $page->has('items', 0));
        $this->actingAs($siswa)->get('/kalender?bulan='.now()->addDays(2)->format('Y-m'))
            ->assertInertia(fn (Assert $page) => $page->has('items', 0));
        $this->actingAs($guru)->get('/ujian')->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)->where('items.0.id', $kosong->id));

        // Begitu guru menambahkan soal, ujiannya menjadi tugas siswa.
        $this->soalPg($kosong);
        $this->actingAs($siswa)->get('/ujian')->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)->where('items.0.status', 'BELUM'));
    }

    public function test_guru_hanya_melihat_kelasnya_dan_arsip_tidak_muncul(): void
    {
        $guru1 = $this->akun('GURU', 'guru1');
        $guru2 = $this->akun('GURU', 'guru2');
        $rombel = $this->rombel();
        $milik = $this->kelas($guru1, $rombel, 'Jaringan Dasar');
        $lain = $this->kelas($guru2, $rombel, 'Pemrograman Web');
        $arsip = $this->kelas($guru1, $rombel, 'Basis Data');
        $arsip->update(['diarsipkan' => true]);
        $this->buatPost($milik, $guru1, 'UJIAN', $this->jendela());
        $this->buatPost($lain, $guru2, 'UJIAN', $this->jendela());
        $this->buatPost($arsip, $guru1, 'UJIAN', $this->jendela());

        $this->actingAs($guru1)->get('/ujian')->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.kelasId', $milik->id)
            ->has('kelasSaya', 1));
    }

    public function test_kalender_menyaring_per_bulan(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $kelas = $this->kelas($guru, $this->rombel());
        $bulanIni = $this->buatPost($kelas, $guru, 'UJIAN', ['durasiMenit' => 30, 'selesaiAt' => now()->startOfMonth()->addDays(2)]);
        $this->buatPost($kelas, $guru, 'UJIAN', ['durasiMenit' => 30, 'selesaiAt' => now()->addMonths(2)]);
        // Tanpa waktu tutup: masuk kalender di bulan dibukanya.
        $tanpaTutup = $this->buatPost($kelas, $guru, 'UJIAN', ['durasiMenit' => 30, 'mulaiAt' => now()->startOfMonth()->addDays(5)]);

        $this->actingAs($guru)->get('/kalender?bulan='.now()->format('Y-m'))->assertInertia(fn (Assert $page) => $page
            ->component('classroom/agenda/kalender-page')
            ->has('items', 2)
            ->where('items.0.id', $bulanIni->id)
            ->where('items.1.id', $tanpaTutup->id));
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/ujian')->assertRedirect('/login');
        $this->get('/kalender')->assertRedirect('/login');
    }
}
