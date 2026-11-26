<?php

namespace App\Http\Controllers;

use App\Models\Classroom\Akun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

/**
 * Profil akun: data akun dan ganti sandi (disalin dari modul Profil landing_page, tanpa foto, data guru absensi, dan
 * role SPPD).
 */
class ProfilController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        $akun = Akun::untukPengguna($user);
        $akun?->loadMissing('siswa.rombel:id,nama');

        return Inertia::render('profil-page', [
            'akun' => [
                'nama' => $akun?->namaTampil() ?? $user->email,
                'username' => $akun?->username,
                // Email sintetis @akun.local tidak ditampilkan.
                'email' => $akun ? null : $user->email,
                'peran' => $akun?->role,
                'bergabung' => $akun?->createdAt?->toDateString(),
                'fotoUrl' => null,
            ],
            'siswa' => $akun?->siswa ? [
                'nis' => $akun->siswa->nis,
                'rombel' => $akun->siswa->rombel?->nama,
            ] : null,
        ]);
    }

    /** Satu aturan sandi untuk semua akun: minimal 8 karakter, huruf dan angka (`Password::defaults()`). */
    public function gantiPassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::defaults(), 'different:current_password'],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'current_password.current_password' => 'Password saat ini salah.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'password.different' => 'Password baru harus berbeda dari password saat ini.',
        ]);

        $request->user()->update(['password' => $validated['password']]);
        // Sesi dan cookie "ingat saya" di perangkat lain berakhir (JagaSesi membandingkan hash sandi); cookie perangkat
        // ini dibuat ulang dengan hash baru supaya tetap masuk.
        Auth::logoutOtherDevices($validated['password']);
        $request->session()->regenerate();

        return back()->with('toast', ['type' => 'success', 'message' => 'Password berhasil diubah']);
    }
}
