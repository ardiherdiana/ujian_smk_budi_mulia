<?php

namespace App\Http\Controllers\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Post;
use App\Models\Classroom\TahunAjaran;
use App\Services\Classroom\PostPresenter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

/** Ujian lintas ruang kelas: Daftar ujian dan Kalender (disalin dari agenda Classroom, hanya ujian). */
class AgendaController extends Controller
{
    public function daftar(Request $request)
    {
        $user = $this->akun($request);
        $posts = $this->postsAgenda($user)->get();

        return Inertia::render('classroom/agenda/tugas-page', [
            'items' => $this->items($posts, $user),
        ]);
    }

    public function kalender(Request $request)
    {
        $user = $this->akun($request);
        $request->validate(['bulan' => ['nullable', 'date_format:Y-m']], ['bulan.date_format' => 'Format bulan tidak valid']);

        $awal = Carbon::createFromFormat('Y-m-d', ($request->query('bulan') ?: now()->format('Y-m')).'-01')->startOfDay();
        $akhir = $awal->copy()->endOfMonth();

        $posts = $this->postsAgenda($user)
            ->where(function (Builder $q) use ($awal, $akhir) {
                $q->whereBetween('selesaiAt', [$awal, $akhir])
                    ->orWhere(fn (Builder $w) => $w->whereNull('selesaiAt')->whereBetween('mulaiAt', [$awal, $akhir]));
            })
            ->get();

        return Inertia::render('classroom/agenda/kalender-page', [
            'bulan' => $awal->format('Y-m'),
            'items' => $this->items($posts, $user),
        ]);
    }

    /** Ujian dari ruang kelas aktif milik pengguna (tidak diarsipkan, tahun ajaran aktif). */
    private function postsAgenda(Akun $user): Builder
    {
        $kelas = Kelas::untukPengguna($user)->where('diarsipkan', false);
        if ($aktif = TahunAjaran::aktif()) {
            $kelas->where('tahunAjaranId', $aktif->id);
        }

        return Post::with(['kelas:id,mapelId,rombelId', 'kelas.mapel:id,nama', 'kelas.rombel:id,nama'])
            ->where('tipe', 'UJIAN')
            ->whereIn('kelasId', $kelas->select('kelas.id'));
    }

    /**
     * Siswa: status miliknya sendiri. Guru/admin/kepsek: jumlah yang menunggu koreksi essay.
     *
     * @param  Collection<int, Post>  $posts
     * @return list<array<string, mixed>>
     */
    private function items(Collection $posts, Akun $user): array
    {
        $idUjian = $posts->pluck('id')->all();
        $siswaId = $user->isSiswa() ? $user->siswa?->id : null;

        $percobaanSaya = collect();
        $menunggu = collect();

        if ($user->isSiswa()) {
            if ($siswaId) {
                $percobaanSaya = Percobaan::whereIn('postId', $idUjian)->where('siswaId', $siswaId)->pluck('status', 'postId');
            }
        } else {
            $menunggu = Percobaan::selectRaw('postId, count(*) as total')
                ->whereIn('postId', $idUjian)->where('status', 'SELESAI')->groupBy('postId')->pluck('total', 'postId');
        }

        return $posts->map(function (Post $post) use ($user, $percobaanSaya, $menunggu) {
            $item = [
                'id' => $post->id,
                'judul' => $post->judul,
                'tipe' => $post->tipe,
                'tenggat' => PostPresenter::iso($post->selesaiAt ?? $post->mulaiAt),
                'diposting' => PostPresenter::iso($post->createdAt),
                'kelasId' => $post->kelasId,
                'mapel' => $post->kelas->mapel->nama,
                'rombel' => $post->kelas->rombel->nama,
            ];

            if ($user->isSiswa()) {
                // BERJALAN dianggap belum selesai; SELESAI = sudah dikumpulkan, menunggu koreksi.
                $item['status'] = match ($percobaanSaya->get($post->id)) {
                    'DINILAI' => 'DINILAI',
                    'SELESAI' => 'DIKUMPULKAN',
                    default => 'BELUM',
                };
            } else {
                $item['menunggu'] = (int) ($menunggu->get($post->id) ?? 0);
            }

            return $item;
        })->values()->all();
    }
}
