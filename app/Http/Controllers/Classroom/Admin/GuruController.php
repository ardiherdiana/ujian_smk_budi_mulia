<?php

namespace App\Http\Controllers\Classroom\Admin;

use App\Exceptions\KeadaanBentrok;
use App\Http\Controllers\Classroom\Controller;
use App\Models\Classroom\Akun;
use App\Models\Classroom\Post;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

/**
 * Akun guru dan kepala sekolah (Data > Guru), disalin dari Data Guru Classroom tanpa foto dan tanpa tautan ke absensi atau
 * website. Aturannya sama: peran admin tidak pernah diganti, akun yang sedang dipakai tidak bisa menonaktifkan dirinya,
 * dan guru yang masih mengajar atau pernah membuat ujian tidak bisa dihapus (sarannya nonaktifkan).
 */
class GuruController extends Controller
{
    /** Batas panjang nama (kolom `profil_akun.name` varchar 255). */
    private const NAMA_MAKS = 255;

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $guru = Akun::whereIn('role', Akun::PERAN_PENGAJAR)
            ->withCount(['kelasDiajar', 'rombelWali'])
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('username', 'like', "%{$q}%")))
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('classroom/admin/guru-page', [
            'guru' => $guru->through(fn (Akun $u) => [
                'id' => $u->id,
                'nama' => $u->namaTampil(),
                'username' => $u->username,
                'role' => $u->role,
                'aktif' => $u->is_active,
                'jumlahKelas' => $u->kelas_diajar_count,
                'waliKelas' => $u->rombel_wali_count > 0,
            ]),
            'filter' => ['q' => $q],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:'.self::NAMA_MAKS],
            'username' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('profil_akun', 'username')],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', Rule::in(Akun::PERAN_PENGAJAR)],
        ], $this->pesan());

        $akun = new Akun;
        $akun->forceFill([
            'username' => $validated['username'],
            'name' => $validated['nama'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'is_active' => true,
        ])->save();

        return back()->with('toast', ['type' => 'success', 'message' => 'Akun ditambahkan']);
    }

    public function update(Request $request, Akun $guru)
    {
        abort_unless($guru->isStaf(), 404);

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:'.self::NAMA_MAKS],
            'username' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('profil_akun', 'username')->ignore($guru->id)],
            'password' => ['nullable', 'string', Password::defaults()],
            'role' => ['nullable', Rule::in(Akun::PERAN_PENGAJAR)],
            'aktif' => ['nullable', 'boolean'],
        ], $this->pesan());

        $aktif = isset($validated['aktif']) ? (bool) $validated['aktif'] : $guru->is_active;
        if (! $aktif && $guru->is($this->akun($request))) {
            throw new KeadaanBentrok('Akun yang sedang dipakai tidak bisa dinonaktifkan');
        }

        $guru->username = $validated['username'];
        $guru->name = $validated['nama'];
        $guru->is_active = $aktif;
        if (! $guru->isAdmin() && ! empty($validated['role'])) {
            $guru->role = $validated['role'];
        }
        if (! empty($validated['password'])) {
            // Diteruskan Akun ke users.password; sandi baru memutus sesi lain akun itu (middleware `sesi`).
            $guru->password = $validated['password'];
        }
        $guru->save();

        return back()->with('toast', ['type' => 'success', 'message' => 'Akun diperbarui']);
    }

    public function destroy(Akun $guru)
    {
        abort_unless($guru->isStaf(), 404);

        if ($guru->isAdmin()) {
            throw new KeadaanBentrok('Akun admin tidak bisa dihapus');
        }
        if ($guru->kelasDiajar()->exists()) {
            throw new KeadaanBentrok('Guru masih mengajar ruang kelas. Hapus atau pindahkan ruang kelasnya dulu');
        }
        if (Post::where('dibuatOleh', $guru->id)->exists()) {
            throw new KeadaanBentrok('Guru ini masih tercatat sebagai pembuat ujian');
        }

        // Wali kelas di rombel dikosongkan (FK ON DELETE SET NULL); baris `users`-nya ikut terhapus.
        $guru->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Akun dihapus']);
    }

    /** @return array<string, string> */
    private function pesan(): array
    {
        return [
            'nama.required' => 'Nama wajib diisi',
            'username.required' => 'Username wajib diisi',
            'username.unique' => 'Username sudah dipakai',
            'username.regex' => 'Username hanya boleh huruf, angka, titik, garis bawah, dan strip',
            'password.required' => 'Password wajib diisi',
            'password.min' => 'Password minimal 8 karakter',
            'role.required' => 'Pilih peran',
        ];
    }
}
