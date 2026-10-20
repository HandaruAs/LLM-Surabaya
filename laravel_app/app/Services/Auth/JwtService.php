<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use JsonException;

/**
 * JWT (JSON Web Token) dengan algoritma HS256, tanpa paket tambahan.
 *
 * Bentuk token: base64url(header) . "." . base64url(payload) . "." . base64url(tanda tangan)
 *  - header   : {"alg":"HS256","typ":"JWT"}
 *  - payload  : {"iss", "sub" (id user), "ver" (versi token user), "iat", "nbf", "exp", "jti"}
 *  - tanda tangan = HMAC-SHA256(header.payload, kunci rahasia)
 *
 * Server tidak menyimpan token. Supaya logout tetap langsung mematikan token,
 * setiap user punya kolom users.token_version. Token hanya sah bila "ver"-nya
 * sama dengan token_version user saat ini. Logout menaikkan token_version,
 * sehingga SEMUA token lama user itu (di perangkat mana pun) tidak berlaku lagi.
 */
class JwtService
{
    private const ALG = 'HS256';

    /** Buat token untuk user. Mengembalikan [token, waktu kedaluwarsa]. */
    public function issue(User $user, bool $remember = false): array
    {
        $now = Carbon::now()->getTimestamp();
        $ttlMinutes = $remember ? (int) config('jwt.remember_ttl') : (int) config('jwt.ttl');
        $expiresAt = $now + $ttlMinutes * 60;

        $header = ['alg' => self::ALG, 'typ' => 'JWT'];
        $payload = [
            'iss' => $this->issuer(),
            'sub' => (string) $user->getKey(),
            'ver' => (int) $user->token_version,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $expiresAt,
            'jti' => (string) Str::uuid(),
        ];

        $unsigned = $this->encode($header).'.'.$this->encode($payload);
        $token = $unsigned.'.'.$this->base64UrlEncode($this->sign($unsigned));

        return [$token, Carbon::createFromTimestamp($expiresAt)];
    }

    /**
     * Periksa token. Mengembalikan payload bila sah, atau null bila:
     * format salah, algoritma bukan HS256, tanda tangan tidak cocok,
     * penerbit beda, belum berlaku, atau kedaluwarsa.
     * (Cek logout dilakukan di userFromToken, karena butuh data user.)
     */
    public function verify(?string $token): ?array
    {
        if (! is_string($token) || substr_count($token, '.') !== 2 || strlen($token) > 4096) {
            return null;
        }

        [$headerPart, $payloadPart, $signaturePart] = explode('.', $token);

        $header = $this->decode($headerPart);
        $payload = $this->decode($payloadPart);
        $signature = $this->base64UrlDecode($signaturePart);

        // Algoritma dikunci ke HS256: token ber-"alg":"none" atau lainnya selalu ditolak.
        if ($header === null || $payload === null || $signature === null
            || ($header['alg'] ?? null) !== self::ALG || ($header['typ'] ?? 'JWT') !== 'JWT') {
            return null;
        }

        // Bandingkan tanda tangan dengan waktu konstan (anti timing attack).
        if (! hash_equals($this->sign($headerPart.'.'.$payloadPart), $signature)) {
            return null;
        }

        $now = Carbon::now()->getTimestamp();
        $leeway = (int) config('jwt.leeway');
        foreach (['sub', 'ver', 'exp', 'nbf', 'jti'] as $claim) {
            if (! isset($payload[$claim])) {
                return null;
            }
        }
        if (($payload['iss'] ?? null) !== $this->issuer()
            || ! is_int($payload['exp']) || $now > $payload['exp'] + $leeway
            || ! is_int($payload['nbf']) || $now < $payload['nbf'] - $leeway
            || ! is_int($payload['ver']) || ! is_string($payload['jti'])) {
            return null;
        }

        return $payload;
    }

    /**
     * Ambil user dari token yang sah, atau null.
     * Token ditolak bila versinya sudah tidak sama dengan token_version user
     * (artinya user sudah logout setelah token itu dibuat).
     */
    public function userFromToken(?string $token): ?User
    {
        $payload = $this->verify($token);
        if ($payload === null || ! ctype_digit((string) $payload['sub'])) {
            return null;
        }

        $user = User::find((int) $payload['sub']);
        if ($user === null || (int) $user->token_version !== $payload['ver']) {
            return null;
        }

        return $user;
    }

    /**
     * Logout: naikkan token_version user pemilik token, sehingga token ini
     * dan semua token lain milik user itu langsung tidak berlaku.
     * Token yang tidak sah (atau sudah tidak berlaku) diabaikan.
     */
    public function revoke(?string $token): void
    {
        $user = $this->userFromToken($token);

        $user?->increment('token_version');
    }

    private function sign(string $data): string
    {
        return hash_hmac('sha256', $data, $this->secret(), true);
    }

    private function secret(): string
    {
        $secret = (string) config('jwt.secret');
        if ($secret !== '') {
            return $secret;
        }

        // Turunan dari APP_KEY, supaya tetap jalan tanpa JWT_SECRET
        // tapi tidak memakai APP_KEY mentah untuk keperluan lain.
        return hash_hmac('sha256', 'jwt-signing-key', (string) config('app.key'), true);
    }

    private function issuer(): string
    {
        return (string) config('app.url');
    }

    private function encode(array $data): string
    {
        return $this->base64UrlEncode(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function decode(string $part): ?array
    {
        $json = $this->base64UrlDecode($part);
        if ($json === null) {
            return null;
        }

        try {
            $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): ?string
    {
        if ($data === '' || preg_match('/^[A-Za-z0-9_-]+$/', $data) !== 1) {
            return null;
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
