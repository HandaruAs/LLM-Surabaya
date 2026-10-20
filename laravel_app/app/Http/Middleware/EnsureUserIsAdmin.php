<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya akun dengan role 'admin' yang boleh masuk area /admin.
 * Dipasang SETELAH middleware 'auth', jadi di sini user pasti sudah login.
 * User biasa yang mencoba masuk mendapat 403.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Halaman ini khusus admin.');

        return $next($request);
    }
}
