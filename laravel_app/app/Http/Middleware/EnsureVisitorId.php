<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pengunjung anonim dikenali dari header X-Visitor-Id (UUID v4 yang dibuat
 * frontend dan disimpan di localStorage). Nilainya disimpan di atribut
 * request "visitor_id" agar controller tidak membaca header langsung.
 */
class EnsureVisitorId
{
    public const HEADER = 'X-Visitor-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $visitorId = strtolower(trim((string) $request->header(self::HEADER)));

        if (! Str::isUuid($visitorId)) {
            return response()->json([
                'message' => 'Header X-Visitor-Id wajib diisi dengan UUID yang valid.',
                'errors' => ['visitor_id' => ['Header X-Visitor-Id tidak valid.']],
            ], 422);
        }

        $request->attributes->set('visitor_id', $visitorId);

        return $next($request);
    }
}
