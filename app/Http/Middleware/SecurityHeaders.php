<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk semua respons web (disalin dari landing_page, tanpa sumber YouTube/OpenStreetMap yang tidak
 * dipakai modul ujian). CSP membatasi script ke origin sendiri + nonce per permintaan; gambar dari origin sendiri atau
 * data:/blob:. Aplikasi ini hanya lokal, jadi tidak pernah boleh diindeks.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Nonce per-request: hanya <script> inline milik kita (tema) & tag Vite yang boleh jalan.
        $nonce = base64_encode(random_bytes(16));
        Vite::useCspNonce($nonce);

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Content-Security-Policy', $this->csp($nonce));
        $headers->set('X-Robots-Tag', 'noindex, nofollow');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function csp(string $nonce): string
    {
        // Saat `npm run dev` (hanya di environment lokal), Vite menyajikan script/HMR dari origin terpisah. Di Windows
        // alamatnya sering `http://[::1]:5173`, dan browser menolak literal IPv6 di CSP, jadi pakai sumber berbasis
        // skema (`http:`/`ws:`). Cabang ini hanya aktif bila ada `public/hot`.
        $dev = app()->isLocal() && file_exists(public_path('hot'));
        $vite = $dev ? 'http:' : null;
        $viteWs = $dev ? 'ws:' : null;

        $script = ["'self'", "'nonce-{$nonce}'", $vite];
        $style = ["'self'", "'unsafe-inline'", $vite];
        $font = ["'self'", 'data:', $vite];
        $connect = ["'self'", $vite, $viteWs];

        $directives = [
            "default-src 'self'",
            'script-src '.implode(' ', array_filter($script)),
            'style-src '.implode(' ', array_filter($style)),
            "img-src 'self' data: blob:",
            "media-src 'self' blob:",
            'font-src '.implode(' ', array_filter($font)),
            'connect-src '.implode(' ', array_filter($connect)),
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        return implode('; ', $directives);
    }
}
