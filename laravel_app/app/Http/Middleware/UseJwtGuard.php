<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman chat, login/register user, dan semua /api/* memakai guard 'jwt':
 * user dikenali dari JWT di cookie, bukan dari session.
 *
 * Setelah middleware ini, auth()->user(), $request->user(), @auth di Blade,
 * middleware 'auth', dan 'guest' otomatis memakai JWT.
 * Area /admin tidak memakai middleware ini, jadi tetap login lewat session.
 */
class UseJwtGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('jwt');

        return $next($request);
    }
}
