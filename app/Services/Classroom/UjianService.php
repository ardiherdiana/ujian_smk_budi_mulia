<?php

namespace App\Services\Classroom;

use App\Exceptions\KeadaanBentrok;
use App\Models\Classroom\Jawaban;
use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Post;
use App\Models\Classroom\Siswa;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class UjianService
{
    /** Tanpa 0/O dan 1/I supaya mudah dibaca dari layar proyektor. */
    private const ALFABET_TOKEN = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const PANJANG_TOKEN = 6;

    public function buatToken(): string
    {
        $token = '';
        for ($i = 0; $i < self::PANJANG_TOKEN; $i++) {
            $token .= self::ALFABET_TOKEN[random_int(0, strlen(self::ALFABET_TOKEN) - 1)];
        }

        return $token;
    }

    /**
     * Untuk ujian ber-token: token harus cocok sebelum percobaan baru dibuat. Salah berkali-kali dibatasi per siswa dan ujian
     * (hanya percobaan salah yang dihitung, pola LoginController).
     */
    public function periksaToken(Post $ujian, Siswa $siswa, ?string $token): void
    {
        if ($ujian->tokenAkses === null) {
            return;
        }

        $kunci = "token-ujian:{$siswa->id}:{$ujian->id}";
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            throw ValidationException::withMessages([
                'token' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($kunci).' detik.',
            ]);
        }

        if (! hash_equals($ujian->tokenAkses, strtoupper(trim((string) $token)))) {
            RateLimiter::hit($kunci, 60);

            throw ValidationException::withMessages(['token' => 'Token salah']);
        }

        RateLimiter::clear($kunci);
    }

    /** Keadaan jendela ujian: BELUM_DIBUKA, BERLANGSUNG, atau DITUTUP. */
    public function jendela(Post $ujian): string
    {
        $sekarang = now();

        if ($ujian->mulaiAt && $sekarang->lt($ujian->mulaiAt)) {
            return 'BELUM_DIBUKA';
        }
        if ($ujian->selesaiAt && $sekarang->gt($ujian->selesaiAt)) {
            return 'DITUTUP';
        }

        return 'BERLANGSUNG';
    }

    /**
     * Mulai ujian untuk seorang siswa, atau lanjutkan percobaan yang sedang berjalan. Satu siswa satu percobaan.
     * Token hanya diminta saat percobaan baru dibuat; siswa yang terputus tetap bisa melanjutkan tanpa token.
     */
    public function mulai(Post $ujian, Siswa $siswa, ?string $token = null): Percobaan
    {
        $ada = Percobaan::where('postId', $ujian->id)->where('siswaId', $siswa->id)->first();
        if ($ada) {
            $this->finalisasiJikaKedaluwarsa($ada);

            return $ada->refresh();
        }

        throw_if($ujian->soal()->doesntExist(), KeadaanBentrok::class, 'Ujian ini belum memiliki soal');
        throw_if($this->jendela($ujian) === 'BELUM_DIBUKA', KeadaanBentrok::class, 'Ujian belum dibuka');
        throw_if($this->jendela($ujian) === 'DITUTUP', KeadaanBentrok::class, 'Ujian sudah ditutup');
        $this->periksaToken($ujian, $siswa, $token);

        $mulai = now();
        $batas = $mulai->copy()->addMinutes($ujian->durasiMenit ?? 60);
        if ($ujian->selesaiAt && $batas->gt($ujian->selesaiAt)) {
            $batas = Carbon::instance($ujian->selesaiAt);
        }

        try {
            return Percobaan::create([
                'postId' => $ujian->id,
                'siswaId' => $siswa->id,
                'mulaiAt' => $mulai,
                'batasAt' => $batas,
                'status' => 'BERJALAN',
            ]);
        } catch (UniqueConstraintViolationException) {
            // Klik "Mulai" ganda: permintaan lain sudah membuat percobaannya (unique postId + siswaId). Lanjutkan saja.
            return Percobaan::where('postId', $ujian->id)->where('siswaId', $siswa->id)->firstOrFail();
        }
    }

    /** Detik tersisa menurut jam server (jam peramban siswa tidak dipercaya). */
    public function sisaDetik(Percobaan $percobaan): int
    {
        return max(0, (int) now()->diffInSeconds($percobaan->batasAt, false));
    }

    /** Simpan (atau perbarui) satu jawaban selama percobaan masih berjalan. */
    public function simpanJawaban(Percobaan $percobaan, string $soalId, ?string $isi): void
    {
        Jawaban::updateOrCreate(
            ['percobaanId' => $percobaan->id, 'soalId' => $soalId],
            ['jawaban' => $isi],
        );
    }

    /**
     * Akhiri semua percobaan yang waktunya sudah habis (dijalankan penjadwal tiap menit, routes/console.php), supaya
     * halaman guru dan rekap jarang perlu menilai di tengah permintaan GET. Mengembalikan jumlah yang diakhiri.
     */
    public function finalisasiSemuaKedaluwarsa(): int
    {
        $jumlah = 0;
        Percobaan::query()
            ->where('status', 'BERJALAN')
            ->where('batasAt', '<', now()->subSeconds(5))
            ->with('post')
            ->each(function (Percobaan $percobaan) use (&$jumlah) {
                if ($this->finalisasiJikaKedaluwarsa($percobaan)) {
                    $jumlah++;
                }
            });

        return $jumlah;
    }

    /** Akhiri percobaan yang waktunya sudah habis. Mengembalikan true bila baru saja diakhiri. */
    public function finalisasiJikaKedaluwarsa(Percobaan $percobaan): bool
    {
        // Toleransi 5 detik untuk jawaban terakhir yang masih dalam perjalanan saat waktu habis.
        if ($percobaan->status === 'BERJALAN' && now()->gt($percobaan->batasAt->copy()->addSeconds(5))) {
            $this->selesai($percobaan, $percobaan->batasAt);

            return true;
        }

        return false;
    }

    /**
     * Akhiri percobaan: nilai otomatis semua pilihan ganda, buat baris jawaban untuk setiap soal, dan tandai
     * DINILAI bila tidak ada essay yang menunggu koreksi.
     */
    public function selesai(Percobaan $percobaan, ?Carbon $waktu = null): Percobaan
    {
        if ($percobaan->status !== 'BERJALAN') {
            return $percobaan;
        }

        return DB::transaction(function () use ($percobaan, $waktu) {
            // Dikunci lalu diperiksa ulang: "Selesai" ganda atau finalisasi otomatis yang bersamaan hanya menilai sekali.
            $terbaru = Percobaan::query()->whereKey($percobaan->getKey())->lockForUpdate()->firstOrFail();
            $percobaan->setRawAttributes($terbaru->getAttributes(), true);
            if ($percobaan->status !== 'BERJALAN') {
                return $percobaan;
            }

            $soal = $percobaan->post->soal()->get();
            $jawaban = $percobaan->jawaban()->get()->keyBy('soalId');

            $skorOtomatis = 0.0;
            $adaEssay = false;

            foreach ($soal as $item) {
                $baris = $jawaban->get($item->id) ?? new Jawaban(['percobaanId' => $percobaan->id, 'soalId' => $item->id]);

                if ($item->tipe !== 'ESSAY') {
                    $baris->skor = $item->skorUntuk($baris->jawaban);
                    $skorOtomatis += $baris->skor;
                } else {
                    $adaEssay = true;
                    // Essay kosong langsung 0; yang berisi menunggu guru.
                    $baris->skor = trim((string) $baris->jawaban) === '' ? 0 : null;
                }

                $baris->save();
            }

            $menunggu = $adaEssay && $percobaan->jawaban()->whereNull('skor')->exists();

            $percobaan->forceFill([
                'status' => $menunggu ? 'SELESAI' : 'DINILAI',
                'selesaiAt' => $waktu ?? now(),
                'skorOtomatis' => $skorOtomatis,
                'skorManual' => $adaEssay ? (float) $percobaan->jawaban()->whereHas('soal', fn ($q) => $q->where('tipe', 'ESSAY'))->sum('skor') : 0,
            ])->save();

            return $percobaan;
        });
    }

    /** Hitung ulang skor essay dan status setelah guru memberi/mengubah skor. */
    public function hitungUlangKoreksi(Percobaan $percobaan): void
    {
        $essay = $percobaan->jawaban()->whereHas('soal', fn ($q) => $q->where('tipe', 'ESSAY'));

        $percobaan->forceFill([
            'skorManual' => (float) (clone $essay)->sum('skor'),
            'status' => (clone $essay)->whereNull('skor')->exists() ? 'SELESAI' : 'DINILAI',
        ])->save();
    }

    /** Total poin seluruh soal. */
    public function totalPoin(Post $ujian): float
    {
        return (float) $ujian->soal()->sum('poin');
    }
}
