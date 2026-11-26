<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="theme-color" content="#0e57a6" />
    <meta name="robots" content="noindex, nofollow" />

    <title inertia>{{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any" />
    <link rel="apple-touch-icon" href="/apple-touch-icon-180x180.png" />

    {{-- Terapkan tema sebelum React dimuat supaya tidak berkedip (kunci localStorage sama dengan ThemeProvider). --}}
    <script nonce="{{ Vite::cspNonce() }}">
        try {
            var t = localStorage.getItem('theme');
            var dark = t === 'dark' || ((t === null || t === 'system') && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.add(dark ? 'dark' : 'light');
        } catch (e) {}
    </script>

    @viteReactRefresh
    @vite(['resources/js/main.tsx'])
    @inertiaHead
</head>
<body class="h-full">
    @inertia

    <noscript>
        <h1>{{ config('app.name') }}</h1>
        <p>Aplikasi ini memerlukan JavaScript.</p>
    </noscript>
</body>
</html>
