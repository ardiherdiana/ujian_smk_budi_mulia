<?php

namespace App\Http\Controllers\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\Mapel;
use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Post;
use App\Models\Classroom\Rombel;
use App\Models\Classroom\Siswa;
use App\Models\Classroom\TahunAjaran;
use App\Services\Classroom\NilaiService;
use App\Services\Classroom\PostCleaner;
use App\Services\Classroom\PostPresenter;
use App\Services\Classroom\PostPublisher;
use App\Support\XlsxExport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Ruang kelas (disalin dari Classroom): daftar, buat, ubah deskripsi, arsip, hapus, anggota, dan rekap nilai. Guru membuat
 * ruang kelas untuk dirinya sendiri; admin memilih guru pengajarnya.
 */
class KelasController extends Controller
{
    public function __construct(private PostPresenter $presenter, private NilaiService $nilai, private PostPublisher $publisher) {}

    public function index(Request $request)
    {
        $user = $this->akun($request);
        $arsip = $request->boolean('arsip');
        $aktif = TahunAjaran::aktif();

        $query = Kelas::with(['mapel:id,nama', 'rombel:id,nama', 'guru:id,name,username', 'tahunAjaran'])
            ->addSelect(['jumlahSiswa' => Siswa::selectRaw('count(*)')->whereColumn('siswa.rombelId', 'kelas.rombelId')])
            ->where('diarsipkan', $arsip);

        if (! $arsip && $aktif) {
            $query->where('tahunAjaranId', $aktif->id);
        }

        $query->untukPengguna($user);

        $bisaMembuat = in_array($user->role, ['ADMIN', 'GURU'], true);

        // Tanpa membedakan huruf besar-kecil dan angka secara alami, sama dengan urutan sidebar.
        $daftar = $query->orderBy('rombelId')->get()
            ->sortBy(fn (Kelas $k) => $k->rombel->nama.'|'.$k->mapel->nama.'|'.$k->guru->namaTampil(), SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
        $ids = $daftar->pluck('id')->all();

        // Penanda di kartu: ujian yang masih bisa dikerjakan tapi belum diselesaikan (siswa), atau hasil yang menunggu
        // koreksi essay (guru, admin, kepsek). Ujian yang belum punya soal belum bisa dikerjakan, jadi tidak dihitung.
        $penanda = collect();
        if ($ids && $user->isSiswa() && $user->siswa) {
            $penanda = Post::selectRaw('kelasId, count(*) as total')
                ->whereIn('kelasId', $ids)->where('tipe', 'UJIAN')
                ->whereHas('soal')
                ->where(fn ($q) => $q->whereNull('selesaiAt')->orWhere('selesaiAt', '>=', now()))
                ->whereDoesntHave('percobaan', fn ($q) => $q->where('siswaId', $user->siswa->id)->where('status', '!=', 'BERJALAN'))
                ->groupBy('kelasId')->pluck('total', 'kelasId');
        } elseif ($ids && ! $user->isSiswa()) {
            $penanda = Percobaan::selectRaw('classroom_posts.kelasId, count(*) as total')
                ->join('classroom_posts', 'classroom_posts.id', '=', 'percobaan.postId')
                ->whereIn('classroom_posts.kelasId', $ids)->where('percobaan.status', 'SELESAI')
                ->groupBy('classroom_posts.kelasId')->pluck('total', 'kelasId');
        }

        return Inertia::render('classroom/kelas/daftar-page', [
            'kelas' => $daftar->map(fn (Kelas $k) => [
                'id' => $k->id,
                'mapel' => $k->mapel->nama,
                'rombel' => $k->rombel->nama,
                'guru' => $k->guru->namaTampil(),
                'tahunAjaran' => $k->tahunAjaran->label(),
                'jumlahSiswa' => (int) $k->jumlahSiswa,
                'diarsipkan' => $k->diarsipkan,
                'penanda' => (int) ($penanda[$k->id] ?? 0),
            ])->all(),
            'arsip' => $arsip,
            'tahunAjaran' => $aktif?->label(),
            'bisaMembuat' => $bisaMembuat,
            'opsi' => $bisaMembuat ? [
                'rombel' => Rombel::orderBy('nama')->get(['id', 'nama']),
                'mapel' => Mapel::orderBy('nama')->get(['id', 'nama']),
                'guru' => $user->isAdmin()
                    ? Akun::whereIn('role', Akun::PERAN_PENGAJAR)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'username'])
                        ->map(fn (Akun $g) => ['id' => $g->id, 'nama' => $g->namaTampil()])->values()
                    : null,
            ] : null,
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->akun($request);
        $validated = $request->validate([
            'rombelId' => ['required', 'string', Rule::exists('rombel', 'id')],
            'mapelId' => ['nullable', 'string', Rule::exists('mapel', 'id')],
            'mapelBaru' => ['nullable', 'string', 'max:100'],
            'guruId' => ['nullable', 'integer', Rule::exists('profil_akun', 'id')->whereIn('role', Akun::PERAN_PENGAJAR)],
            'deskripsi' => ['nullable', 'string', 'max:500'],
        ], [
            'rombelId.required' => 'Pilih rombel',
        ]);

        $tahun = TahunAjaran::aktif();
        abort_unless($tahun, 409, 'Belum ada tahun ajaran aktif. Minta admin mengaktifkannya dulu');

        $namaBaru = trim((string) ($validated['mapelBaru'] ?? ''));
        abort_if(empty($validated['mapelId']) && $namaBaru === '', 422, 'Pilih mata pelajaran atau ketik nama mapel baru');
        $mapelId = $namaBaru !== '' && empty($validated['mapelId'])
            ? Mapel::firstOrCreate(['nama' => $namaBaru])->id
            : $validated['mapelId'];

        $guruId = $user->isAdmin() ? ($validated['guruId'] ?? null) : $user->id;
        abort_unless($guruId, 422, 'Pilih guru pengajar');

        // Satu mapel di satu rombel boleh dipegang beberapa guru, tapi tiap guru sekali per tahun ajaran.
        abort_if(
            Kelas::where(['rombelId' => $validated['rombelId'], 'mapelId' => $mapelId, 'guruId' => $guruId, 'tahunAjaranId' => $tahun->id])->exists(),
            409,
            'Guru ini sudah punya ruang kelas untuk mapel dan rombel ini di tahun ajaran aktif',
        );

        $kelas = Kelas::create([
            'rombelId' => $validated['rombelId'],
            'mapelId' => $mapelId,
            'guruId' => $guruId,
            'tahunAjaranId' => $tahun->id,
            'deskripsi' => $validated['deskripsi'] ?? null,
        ]);

        return redirect("/kelas/{$kelas->id}")->with('toast', ['type' => 'success', 'message' => 'Ruang kelas berhasil dibuat']);
    }

    public function update(Request $request, Kelas $kelas)
    {
        abort_unless($kelas->dapatDikelola($this->akun($request)), 403, 'Anda tidak memiliki akses');

        $validated = $request->validate(['deskripsi' => ['nullable', 'string', 'max:500']]);
        $kelas->update(['deskripsi' => $validated['deskripsi'] ?? null]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Ruang kelas diperbarui']);
    }

    public function arsip(Request $request, Kelas $kelas)
    {
        abort_unless($kelas->dapatDikelola($this->akun($request)), 403, 'Anda tidak memiliki akses');

        $kelas->update(['diarsipkan' => ! $kelas->diarsipkan]);

        return redirect('/kelas')->with('toast', [
            'type' => 'success',
            'message' => $kelas->diarsipkan ? 'Ruang kelas diarsipkan' : 'Ruang kelas dipulihkan dari arsip',
        ]);
    }

    public function destroy(Request $request, Kelas $kelas)
    {
        abort_unless($kelas->dapatDikelola($this->akun($request)), 403, 'Anda tidak memiliki akses');

        app(PostCleaner::class)->hapusKelas($kelas);

        return redirect('/kelas')->with('toast', ['type' => 'success', 'message' => 'Ruang kelas dihapus']);
    }

    /** Daftar ujian satu ruang kelas, terbaru dulu (halaman "Ujian" kelas; di Classroom namanya Forum). */
    public function show(Request $request, Kelas $kelas)
    {
        $user = $this->akun($request);
        abort_unless($kelas->dapatDiakses($user), 403, 'Anda tidak memiliki akses ke ruang kelas ini');

        $posts = Post::with('kelas')->where('kelasId', $kelas->id)->where('tipe', 'UJIAN')->orderByDesc('createdAt')->limit(50)->get();

        return Inertia::render('classroom/kelas/stream-page', [
            'kelas' => $this->presenter->kelas($kelas, $user),
            'posts' => $this->presenter->daftar($posts, $user),
            'mendatang' => $this->mendatang($kelas, $user),
            // Pilihan tujuan untuk pembuat ujian: ruang kelas lain milik guru yang sama (tingkat dan jurusan dari rombelnya).
            'tujuan' => $kelas->dapatDikelola($user) && ! $kelas->diarsipkan
                ? $this->publisher->tujuan($kelas)->map(fn (Kelas $k) => [
                    'id' => $k->id,
                    'mapel' => $k->mapel->nama,
                    'rombel' => $k->rombel->nama,
                    'tingkat' => $k->rombel->tingkat,
                    'jurusan' => $k->rombel->jurusan,
                ])->all()
                : [],
        ]);
    }

    public function anggota(Request $request, Kelas $kelas)
    {
        $user = $this->akun($request);
        abort_unless($kelas->dapatDiakses($user), 403, 'Anda tidak memiliki akses ke ruang kelas ini');

        $kelas->loadMissing('rombel.waliKelas:id,name,username');

        return Inertia::render('classroom/kelas/anggota-page', [
            'kelas' => $this->presenter->kelas($kelas, $user),
            'guru' => $kelas->guru->namaTampil(),
            'waliKelas' => $kelas->rombel->waliKelas?->namaTampil(),
            // NIS hanya untuk staf; siswa melihat sesama anggota hanya sebagai nama.
            'siswa' => Siswa::where('rombelId', $kelas->rombelId)->orderBy('nama')->get()
                ->map(fn (Siswa $s) => ['id' => $s->id, 'nama' => $s->nama, 'nis' => $user->isSiswa() ? null : $s->nis])->all(),
        ]);
    }

    public function nilai(Request $request, Kelas $kelas)
    {
        $user = $this->akun($request);
        abort_unless($kelas->dapatDiakses($user), 403, 'Anda tidak memiliki akses ke ruang kelas ini');

        if ($user->isSiswa()) {
            $matriks = $this->nilai->matriks($kelas);
            $saya = collect($matriks['baris'])->firstWhere('siswaId', $user->siswa?->id);

            return Inertia::render('classroom/kelas/nilai-page', [
                'kelas' => $this->presenter->kelas($kelas, $user),
                // Ujian tanpa soal (total poin 0) belum bisa dikerjakan, jadi belum tampil di Nilai Saya.
                'kolom' => array_values(array_filter($matriks['kolom'], fn (array $k) => $k['maks'] > 0)),
                'baris' => $saya ? [$saya] : [],
                'saya' => true,
            ]);
        }

        return Inertia::render('classroom/kelas/nilai-page', [
            'kelas' => $this->presenter->kelas($kelas, $user),
            ...$this->nilai->matriks($kelas),
            'saya' => false,
        ]);
    }

    public function exportNilai(Request $request, Kelas $kelas)
    {
        $user = $this->akun($request);
        abort_if($user->isSiswa() || ! $kelas->dapatDiakses($user), 403, 'Anda tidak memiliki akses');

        $matriks = $this->nilai->matriks($kelas);
        $kelas->loadMissing('mapel', 'rombel', 'tahunAjaran');

        $headers = ['No', 'NIS', 'Nama', ...array_map(fn ($k) => $k['judul'].' (/'.rtrim(rtrim(number_format($k['maks'], 2, '.', ''), '0'), '.').')', $matriks['kolom']), 'Rata-rata (%)'];
        $rows = [];
        foreach ($matriks['baris'] as $i => $b) {
            $rows[] = [
                $i + 1, $b['nis'] ?? '', $b['nama'],
                ...array_map(fn ($k) => $b['nilai'][$k['id']] ?? '', $matriks['kolom']),
                $b['rata'] ?? '',
            ];
        }

        return XlsxExport::downloadTabel(
            'nilai-'.Str::slug($kelas->rombel->nama.' '.$kelas->mapel->nama).'.xlsx',
            config('classroom.nama_sekolah'),
            "Rekap Nilai Ujian {$kelas->mapel->nama} - {$kelas->rombel->nama}",
            'Tahun ajaran '.$kelas->tahunAjaran->label(),
            $headers,
            $rows,
        );
    }

    /**
     * Ujian yang masih terbuka untuk siswa ini (belum dikerjakan atau masih berjalan), terdekat dulu. Ujian yang belum
     * punya soal dilewati karena belum bisa dikerjakan.
     *
     * @return list<array{id: string, tipe: string, judul: ?string, batas: ?string}>
     */
    private function mendatang(Kelas $kelas, Akun $user): array
    {
        if (! $user->isSiswa() || ! $user->siswa) {
            return [];
        }

        $siswaId = $user->siswa->id;

        return Post::where('kelasId', $kelas->id)->where('tipe', 'UJIAN')
            ->whereHas('soal')
            ->where(fn ($q) => $q->whereNull('selesaiAt')->orWhere('selesaiAt', '>=', now()))
            ->whereDoesntHave('percobaan', fn ($q) => $q->where('siswaId', $siswaId)->where('status', '!=', 'BERJALAN'))
            ->get()
            ->map(fn (Post $p) => [
                'id' => $p->id,
                'tipe' => $p->tipe,
                'judul' => $p->judul,
                'batas' => PostPresenter::iso($p->selesaiAt),
            ])
            ->sortBy(fn ($p) => $p['batas'] ?? '9999')
            ->take(5)->values()->all();
    }
}
