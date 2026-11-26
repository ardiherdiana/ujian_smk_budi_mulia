<?php

namespace App\Services\Classroom;

use App\Exceptions\DataTidakDitemukan;
use App\Models\Classroom\Akun;
use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Soal;
use App\Services\MediaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gambar pada soal. Berkas ada di `soal/` (permanen) atau `soal-tmp/` (sementara: hasil impor dokumen atau unggahan
 * yang belum disimpan bersama soalnya). Satu berkas boleh dipakai banyak soal (wacana bersama, salinan ke ruang kelas
 * lain), jadi berkas hanya dihapus bila tidak ada soal yang masih merujuknya.
 *
 * Berkasnya di disk PRIVAT `soal` (bukan public/media): gambar soal tidak boleh terbuka sebelum ujian dimulai. URL-nya
 * menuju GambarSoalController, yang memeriksa `bolehDilihat()`.
 */
class SoalGambarService
{
    public const FINAL = 'soal';

    public const SEMENTARA = 'soal-tmp';

    public const DISK = 'soal';

    public const URL_DASAR = '/gambar-soal/';

    private MediaService $media;

    public function __construct(MediaService $media)
    {
        $this->media = $media->pakaiDisk(self::DISK);
    }

    /** Simpan satu gambar (unggahan guru atau hasil impor) sebagai berkas sementara; mengembalikan path-nya. */
    public function simpanSementara(UploadedFile $file, string $field = 'gambar'): string
    {
        return $this->media->storeImage($file, self::SEMENTARA, $field);
    }

    /** URL gambar (lewat controller berotorisasi), atau null bila path tidak sah. */
    public function url(?string $path): ?string
    {
        return $this->sah($path) ? self::URL_DASAR.$path : null;
    }

    public function kirim(string $path): StreamedResponse
    {
        throw_unless($this->sah($path) && $this->ada($path), DataTidakDitemukan::class, 'Gambar tidak ditemukan');

        return $this->media->disk()->response($path, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Siapa boleh melihat satu gambar soal:
     * - berkas sementara: guru/admin yang sedang menyusun soal (namanya ULID acak dan tidak pernah tampil ke siswa);
     * - berkas permanen: admin dan kepsek; guru yang bisa membuka ruang kelas ujiannya; siswa hanya SELAMA mengerjakan
     *   ujian yang memakai gambar itu (percobaan berjalan), sehingga soal tidak bisa diintip sebelum atau sesudahnya.
     */
    public function bolehDilihat(Akun $akun, string $path): bool
    {
        if (str_starts_with($path, self::SEMENTARA.'/')) {
            return in_array($akun->role, ['ADMIN', 'GURU'], true);
        }

        if (in_array($akun->role, ['ADMIN', 'KEPSEK'], true)) {
            return true;
        }

        $soal = $this->soalYangMemakai($path)->with('post.kelas')->get();

        if ($akun->isSiswa()) {
            $postId = $soal->filter(fn (Soal $s) => $s->post && $s->post->kelas->dapatDiakses($akun))->pluck('postId')->unique();

            return $postId->isNotEmpty() && $akun->siswa !== null && Percobaan::whereIn('postId', $postId)
                ->where('siswaId', $akun->siswa->id)->where('status', 'BERJALAN')->exists();
        }

        return $soal->contains(fn (Soal $s) => $s->post && $s->post->kelas->dapatDiakses($akun));
    }

    /** Path yang boleh muncul di kolom gambar soal: media sah di folder `soal/` atau `soal-tmp/`. */
    public function sah(?string $path): bool
    {
        return $this->media->isPath($path)
            && (str_starts_with($path, self::FINAL.'/') || str_starts_with($path, self::SEMENTARA.'/'));
    }

    /** Berkas sementara hanya valid selama masih ada di disk. */
    public function ada(string $path): bool
    {
        return $this->media->disk()->exists($path);
    }

    /** @return array{path: string, url: string}|null untuk editor guru (path dikirim balik saat menyimpan) */
    public function item(?string $path): ?array
    {
        $url = $this->url($path);

        return $url === null ? null : ['path' => $path, 'url' => $url];
    }

    /**
     * @param  list<string>|null  $paths
     * @return list<array{path: string, url: string}>
     */
    public function daftarItem(?array $paths): array
    {
        return array_values(array_filter(array_map(fn ($p) => $this->item($p), $paths ?? [])));
    }

    /**
     * Sejajar dengan pilihan: satu entri per pilihan, null bila pilihan tanpa gambar.
     *
     * @param  list<string|null>|null  $paths
     * @return list<array{path: string, url: string}|null>
     */
    public function itemSejajar(?array $paths, int $jumlah): array
    {
        $hasil = [];
        for ($i = 0; $i < $jumlah; $i++) {
            $hasil[] = $this->item($paths[$i] ?? null);
        }

        return $hasil;
    }

    /**
     * URL saja, untuk siswa.
     *
     * @param  list<string>|null  $paths
     * @return list<string>
     */
    public function daftarUrl(?array $paths): array
    {
        return array_map(fn (array $i) => $i['url'], $this->daftarItem($paths));
    }

    /**
     * @param  list<string|null>|null  $paths
     * @return list<string|null>
     */
    public function urlSejajar(?array $paths, int $jumlah): array
    {
        return array_map(fn (?array $i) => $i['url'] ?? null, $this->itemSejajar($paths, $jumlah));
    }

    /**
     * Gambar soal untuk ditampilkan (siswa, koreksi, ringkasan guru): URL saja, tanpa path penyimpanan.
     *
     * @return array{gambar: list<string>, opsiGambar: list<string|null>}
     */
    public function tampil(Soal $soal): array
    {
        return [
            'gambar' => $this->daftarUrl($soal->gambar),
            'opsiGambar' => $this->urlSejajar($soal->opsiGambar, count($soal->opsi ?? [])),
        ];
    }

    /**
     * Gambar soal untuk editor guru: path (dikirim balik saat menyimpan) dan URL (pratinjau).
     *
     * @return array{gambar: list<array{path: string, url: string}>, opsiGambar: list<array{path: string, url: string}|null>}
     */
    public function untukEditor(Soal $soal): array
    {
        return [
            'gambar' => $this->daftarItem($soal->gambar),
            'opsiGambar' => $this->itemSejajar($soal->opsiGambar, count($soal->opsi ?? [])),
        ];
    }

    /**
     * Path permanen yang akan dipakai bila berkas sementara dipindahkan: `soal-tmp/x.png` menjadi `soal/x.png`.
     * Hanya path sementara yang ikut; dipakai untuk menulis path final ke database sebelum berkasnya dipindahkan.
     *
     * @param  list<string>  $paths
     * @return array<string, string>
     */
    public function petaSementara(array $paths): array
    {
        $peta = [];
        foreach (array_unique($paths) as $path) {
            if (str_starts_with($path, self::SEMENTARA.'/') && $this->media->isPath($path)) {
                $peta[$path] = self::FINAL.'/'.basename($path);
            }
        }

        return $peta;
    }

    /**
     * Pindahkan berkas sementara ke folder permanen sesuai peta dari `petaSementara`.
     *
     * @param  array<string, string>  $peta
     */
    public function pindahkanSementara(array $peta): void
    {
        $disk = $this->media->disk();
        foreach ($peta as $lama => $baru) {
            if ($disk->exists($lama)) {
                $disk->move($lama, $baru);
            }
        }
    }

    /**
     * Hapus berkas permanen yang sudah tidak dirujuk soal mana pun.
     *
     * @param  iterable<string|null>  $paths
     */
    public function lepas(iterable $paths): void
    {
        foreach ($paths as $path) {
            if ($path === null || ! str_starts_with($path, self::FINAL.'/') || ! $this->media->isPath($path)) {
                continue;
            }
            if (! $this->dipakai($path)) {
                $this->media->delete($path);
            }
        }
    }

    /** Hapus berkas sementara yang lebih tua dari `$jam` (impor yang tidak pernah disimpan). */
    public function sapuSementara(int $jam = 24): void
    {
        $disk = $this->media->disk();
        $batas = time() - $jam * 3600;

        foreach ($disk->files(self::SEMENTARA) as $berkas) {
            if ($disk->lastModified($berkas) < $batas) {
                $disk->delete($berkas);
            }
        }
    }

    public function dipakai(string $path): bool
    {
        return $this->soalYangMemakai($path)->exists();
    }

    /** @return Builder<Soal> */
    private function soalYangMemakai(string $path)
    {
        return Soal::query()->where(fn ($q) => $q
            ->whereRaw('JSON_SEARCH(gambar, ?, ?) IS NOT NULL', ['one', $path])
            ->orWhereRaw('JSON_SEARCH(opsiGambar, ?, ?) IS NOT NULL', ['one', $path]));
    }
}
