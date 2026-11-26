<?php

namespace App\Services\Classroom;

use App\Models\Classroom\Kelas;
use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Post;
use App\Models\Classroom\Siswa;
use App\Models\Classroom\Soal;

/**
 * Rekap nilai satu ruang kelas: kolom = ujian (urut waktu dibuat), baris = siswa di rombelnya.
 */
class NilaiService
{
    /**
     * @return array{
     *   kolom: list<array{id: string, judul: string, tipe: string, maks: float}>,
     *   baris: list<array{siswaId: string, nama: string, nis: ?string, nilai: array<string, ?float>, rata: ?float}>
     * }
     */
    public function matriks(Kelas $kelas): array
    {
        $posts = Post::where('kelasId', $kelas->id)->where('tipe', 'UJIAN')->orderBy('createdAt')->get();
        $idUjian = $posts->pluck('id')->all();

        $totalPoin = $idUjian
            ? Soal::selectRaw('postId, sum(poin) as total')->whereIn('postId', $idUjian)->groupBy('postId')->pluck('total', 'postId')
            : collect();

        $kolom = $posts->map(fn (Post $p) => [
            'id' => $p->id,
            'judul' => $p->judul ?? '(tanpa judul)',
            'tipe' => $p->tipe,
            'maks' => (float) ($totalPoin[$p->id] ?? 0),
        ])->values()->all();

        $nilaiUjian = $idUjian
            ? Percobaan::whereIn('postId', $idUjian)->whereIn('status', ['SELESAI', 'DINILAI'])->get()->groupBy('siswaId')
            : collect();

        $baris = [];
        foreach (Siswa::where('rombelId', $kelas->rombelId)->orderBy('nama')->get() as $siswa) {
            $nilai = [];
            foreach ($nilaiUjian->get($siswa->id, collect()) as $p) {
                $nilai[$p->postId] = $p->skorTotal();
            }

            $persen = [];
            foreach ($kolom as $k) {
                if (isset($nilai[$k['id']]) && $k['maks'] > 0) {
                    $persen[] = $nilai[$k['id']] / $k['maks'] * 100;
                }
            }

            $baris[] = [
                'siswaId' => $siswa->id,
                'nama' => $siswa->nama,
                'nis' => $siswa->nis,
                'nilai' => collect($kolom)->mapWithKeys(fn ($k) => [$k['id'] => $nilai[$k['id']] ?? null])->all(),
                'rata' => $persen ? round(array_sum($persen) / count($persen), 1) : null,
            ];
        }

        return ['kolom' => $kolom, 'baris' => $baris];
    }
}
