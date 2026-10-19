<?php

namespace App\Providers;

use App\Http\Middleware\EnsureVisitorId;
use App\Services\ChatService;
use App\Services\Rag\RagClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        RateLimiter::for('admin-login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return Limit::perMinute(6)->by($email.'|'.$request->ip());
        });

        // FR-16: batas per pengunjung DAN per IP untuk POST /api/chat.
        RateLimiter::for('chat', function (Request $request) {
            $visitor = strtolower((string) $request->header(EnsureVisitorId::HEADER));

            return [
                Limit::perMinute((int) config('chat.rate_limit.per_visitor'))->by('visitor:'.$visitor),
                Limit::perMinute((int) config('chat.rate_limit.per_ip'))->by('ip:'.$request->ip()),
            ];
        });
    }
}
