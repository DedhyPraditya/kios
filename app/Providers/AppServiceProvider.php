<?php

namespace App\Providers;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $this->catatKejadianMasuk();

        if (str_starts_with(config('app.url'), 'https://') || app()->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }

    /**
     * Semua kejadian masuk/keluar dicatat di Log Aktivitas (dan lonceng admin),
     * termasuk percobaan masuk dengan kata sandi atau email yang salah.
     */
    private function catatKejadianMasuk(): void
    {
        $agen = fn () => ['user_agent' => substr((string) request()->userAgent(), 0, 200)];

        Event::listen(Login::class, fn (Login $e) => ActivityLog::record(
            'auth.login', "{$e->user->name} masuk ke aplikasi", null, $agen(), $e->user->id
        ));

        Event::listen(Logout::class, fn (Logout $e) => $e->user && ActivityLog::record(
            'auth.logout', "{$e->user->name} keluar dari aplikasi", null, [], $e->user->id
        ));

        // Pelaku belum tentu pemilik akun, jadi user_id dikosongkan; email yang
        // dicoba disimpan di properti.
        Event::listen(Failed::class, fn (Failed $e) => ActivityLog::record(
            'auth.failed',
            $e->user
                ? "Percobaan masuk gagal ke akun {$e->user->name} ({$e->credentials['email']}): kata sandi salah"
                : "Percobaan masuk gagal dengan email tak terdaftar: ".($e->credentials['email'] ?? '-'),
            null,
            ['email' => $e->credentials['email'] ?? null, ...$agen()],
            ActivityLog::TANPA_PENGGUNA
        ));

        Event::listen(Lockout::class, fn (Lockout $e) => ActivityLog::record(
            'auth.lockout',
            'Login dikunci sementara setelah terlalu banyak percobaan untuk '.$e->request->input('email'),
            null,
            ['email' => $e->request->input('email'), ...$agen()],
            ActivityLog::TANPA_PENGGUNA
        ));
    }
}
