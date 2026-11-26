<?php

/*
| Proxy yang boleh mengisi X-Forwarded-For / X-Forwarded-Proto (dibaca middleware TrustProxies bawaan Laravel).
|
| Kosong (bawaan): tidak ada proxy yang dipercaya, IP klien = REMOTE_ADDR. Benar untuk server yang menerima koneksi
| langsung dari pengunjung, dan aman karena header X-Forwarded-For dari pengunjung diabaikan.
|
| Di belakang proxy (Cloudflare, load balancer hosting): isi TRUSTED_PROXIES dengan IP/CIDR proxy itu, dipisah koma,
| mis. daftar resmi https://www.cloudflare.com/ips/. `*` hanya boleh bila server benar-benar tidak bisa diakses selain
| lewat proxy; kalau tidak, IP bisa dipalsukan dan rate limit login (per IP) bisa diakali.
*/

$proxies = trim((string) env('TRUSTED_PROXIES', ''));

return [
    'proxies' => match ($proxies) {
        '' => null,
        '*', '**' => $proxies,
        default => array_values(array_filter(array_map('trim', explode(',', $proxies)))),
    },
];
