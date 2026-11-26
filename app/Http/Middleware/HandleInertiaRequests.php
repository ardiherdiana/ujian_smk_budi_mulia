<?php

namespace App\Http\Middleware;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\TahunAjaran;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props yang dibagikan ke semua halaman. `auth.user`: `role` = peran profil (ADMIN|KEPSEK|GURU|SISWA), `admin` = users.role
     * admin. `$request->user()` selalu model User; profilnya ada di atribut request (RequirePeran).
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Props ini dihitung saat respons dibuat (lazy), SETELAH middleware `peran` menaruh profil Classroom di atribut
        // request. Inertia memanggil share() sebelum route middleware jalan.
        return [
            ...parent::share($request),
            'auth' => fn () => $this->auth($request),
            // Daftar ruang kelas aktif untuk sidebar.
            'kelasSaya' => function () use ($request) {
                $akun = $request->attributes->get(RequirePeran::ATRIBUT);

                return $akun instanceof Akun ? $this->kelasSaya($akun) : [];
            },
            'flash' => [
                'toast' => fn () => $request->session()->get('toast'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /** @return array{user: array<string, mixed>|null} */
    private function auth(Request $request): array
    {
        $pengguna = $request->user();
        if ($pengguna === null) {
            return ['user' => null];
        }

        $akun = $request->attributes->get(RequirePeran::ATRIBUT) ?? $pengguna->akun;
        $akun?->loadMissing('siswa.rombel:id,nama');

        return [
            'user' => [
                'id' => $pengguna->id,
                'email' => $pengguna->email,
                'username' => $akun?->username,
                'role' => $akun?->role,
                'admin' => $pengguna->isAdmin(),
                'name' => $akun?->namaTampil(),
                'is_active' => $akun?->is_active ?? false,
                'fotoUrl' => null,
                'siswa' => $akun?->siswa ? [
                    'id' => $akun->siswa->id,
                    'nis' => $akun->siswa->nis,
                    'rombel' => $akun->siswa->rombel?->nama,
                ] : null,
            ],
        ];
    }

    /** @return list<array{id: string, mapel: string, rombel: string}> */
    private function kelasSaya(Akun $user): array
    {
        $query = Kelas::with(['mapel:id,nama', 'rombel:id,nama'])->where('diarsipkan', false)->untukPengguna($user);

        if ($aktif = TahunAjaran::aktif()) {
            $query->where('tahunAjaranId', $aktif->id);
        }

        return $query->get()
            ->sortBy(fn (Kelas $k) => $k->mapel->nama.'|'.$k->rombel->nama)
            ->map(fn (Kelas $k) => ['id' => $k->id, 'mapel' => $k->mapel->nama, 'rombel' => $k->rombel->nama])
            ->values()->all();
    }
}
