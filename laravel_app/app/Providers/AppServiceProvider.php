<?php

namespace App\Providers;

use App\Services\Auth\JwtService;
use App\Services\ChatService;
use App\Services\Rag\RagClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RagClient::class, fn () => new RagClient(
            baseUrl: rtrim((string) config('chat.rag.url'), '/'),
            timeout: (int) config('chat.rag.timeout'),
        ));

        $this->app->bind(ChatService::class, fn ($app) => new ChatService(
            rag: $app->make(RagClient::class),
            historyLimit: (int) config('chat.history_limit'),
        ));
    }

    public function boot(): void
    {
        // Guard 'jwt': ambil token dari cookie, periksa tanda tangan & masa berlaku,
        // lalu kembalikan user-nya (atau null = tamu).
        Auth::viaRequest('jwt', fn (Request $request) => $this->app->make(JwtService::class)
            ->userFromToken($request->cookie(config('jwt.cookie'))));

        // Cookie JWT tidak dienkripsi lagi oleh Laravel: isinya sudah ditandatangani,
        // dan cookie-nya httpOnly sehingga tetap tidak terbaca oleh JavaScript.
        EncryptCookies::except((string) config('jwt.cookie'));

        RateLimiter::for('admin-login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return Limit::perMinute(6)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('user-login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return Limit::perMinute(6)->by('user-login:'.$email.'|'.$request->ip());
        });

        // Cegah pembuatan akun massal dari satu IP.
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by('register:'.$request->ip()));

        // FR-16: batas per akun (bila login) DAN per IP untuk POST /api/chat.
        // Tamu tidak punya akun, jadi cukup dibatasi per IP.
        RateLimiter::for('chat', function (Request $request) {
            $limits = [
                Limit::perMinute((int) config('chat.rate_limit.per_ip'))->by('ip:'.$request->ip()),
            ];

            if ($user = $request->user('jwt')) {
                $limits[] = Limit::perMinute((int) config('chat.rate_limit.per_user'))->by('user:'.$user->id);
            } else {
                $limits[] = Limit::perMinute((int) config('chat.rate_limit.per_guest'))->by('guest:'.$request->ip());
            }

            return $limits;
        });
    }
}
