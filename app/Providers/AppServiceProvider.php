<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceHttps();
        }

        Carbon::setLocale(config('app.locale'));

        // Halaman login: batas kasar per IP (batas per identitas, lepas dari IP, ada di LoginController). Satu lab
        // sekolah keluar lewat satu IP, jadi batasnya cukup longgar untuk satu kelas yang masuk bersamaan.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // Satu aturan sandi untuk semua formulir (Profil): `Password::defaults()`. Sama dengan Classroom.
        Password::defaults(fn () => Password::min(8)->letters()->numbers()->max(100));

        // Lokal: lazy loading di koleksi (N+1) dicatat ke log supaya ketahuan, tanpa memutus halaman.
        if ($this->app->isLocal()) {
            Model::preventLazyLoading();
            Model::handleLazyLoadingViolationUsing(fn (Model $model, string $relasi) => Log::warning('Lazy loading (N+1): '.$model::class.'::'.$relasi));
        }
    }
}
