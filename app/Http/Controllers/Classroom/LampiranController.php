<?php

namespace App\Http\Controllers\Classroom;

use App\Models\Classroom\Lampiran;
use App\Models\Classroom\Post;
use App\Services\Classroom\LampiranService;
use Illuminate\Http\Request;

class LampiranController extends Controller
{
    public function __construct(private LampiranService $lampiran) {}

    /** Unduh/lihat satu berkas lampiran ujian. Hak akses mengikuti ruang kelas ujiannya. */
    public function show(Request $request, Lampiran $lampiran)
    {
        $post = Post::with('kelas')->find($lampiran->postId);
        abort_unless($post && $post->kelas->dapatDiakses($this->akun($request)), 403, 'Anda tidak memiliki akses ke berkas ini');

        return $this->lampiran->kirim($lampiran);
    }

    public function destroy(Request $request, Lampiran $lampiran)
    {
        $post = Post::with('kelas')->findOrFail($lampiran->postId);
        abort_unless($post->kelas->dapatDikelola($this->akun($request)), 403, 'Anda tidak memiliki akses');

        $this->lampiran->hapus($lampiran);

        return back();
    }
}
