<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk semua respons (halaman web dan /api/*).
 *
 * - Content-Security-Policy : browser hanya menjalankan script dari situs ini
 *                              dan <script> yang membawa nonce acak per request.
 *                              Script sisipan penyerang (XSS) tidak punya nonce, jadi diblokir.
 * - X-Frame-Options          : halaman tidak bisa dimuat di dalam iframe situs lain (clickjacking).
 * - X-Content-Type-Options   : browser tidak menebak-nebak jenis file (MIME sniffing).
 * - Referrer-Policy          : alamat lengkap halaman tidak bocor ke situs lain.
 * - Permissions-Policy       : kamera, mikrofon, lokasi, dll. dimatikan.
 * - Cache-Control (API)      : isi chat tidak disimpan di cache browser/proxy.
 * - Strict-Transport-Security: hanya saat HTTPS, paksa browser selalu memakai HTTPS.
 *
 * <script> inline di Blade wajib diberi nonce="{{ Vite::cspNonce() }}".
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Nonce acak untuk request ini; @vite otomatis memakainya.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->remove('X-Powered-By');

        if ($request->is('api/*')) {
            $headers->set('Cache-Control', 'no-store, private');
        }

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Halaman error debug Laravel memakai script inline tanpa nonce;
        // jangan pasang CSP di sana supaya error tetap terbaca saat development.
        $isDebugErrorPage = config('app.debug') && $response->getStatusCode() >= 500;
        if (! $isDebugErrorPage) {
            $headers->set('Content-Security-Policy', $this->contentSecurityPolicy($nonce));
        }

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        // Saat `npm run dev`, aset dimuat dari server Vite (mis. http://localhost:5173).
        $vite = [];
        $connectVite = [];
        if (Vite::isRunningHot()) {
            $origin = rtrim(trim((string) @file_get_contents(public_path('hot'))), '/');
            if (preg_match('#^https?://[^/\s]+$#', $origin) === 1) {
                $vite[] = $origin;
                $connectVite = [$origin, preg_replace('#^http#', 'ws', $origin)];
            }
        }

        $policy = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", ...$vite],
            // Tailwind/JS mengatur style lewat atribut; font dari Google Fonts.
            'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com', ...$vite],
            'font-src' => ["'self'", 'https://fonts.gstatic.com', 'data:'],
            // ...$vite: gambar yang di-import dari JS (mis. logo animasi pembuka) dimuat dari server Vite saat dev.
            'img-src' => ["'self'", 'data:', 'blob:', ...$vite],
            'connect-src' => ["'self'", ...$connectVite],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        return implode('; ', array_map(
            fn (string $directive, array $sources) => $directive.' '.implode(' ', $sources),
            array_keys($policy),
            $policy,
        ));
    }
}
