<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Token JWT yang dipalsukan, kedaluwarsa, atau sudah di-logout harus ditolak.
 */
class JwtSecurityTest extends TestCase
{
    use RefreshDatabase;

    private JwtService $jwt;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwt = $this->app->make(JwtService::class);
        $this->user = User::factory()->create();
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /** Ubah payload token yang sah tanpa menandatangani ulang. */
    private function tamper(string $token, array $changes): string
    {
        [$header, $payload, $signature] = explode('.', $token);
        $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

        return $header.'.'.$this->b64(json_encode(array_merge($data, $changes))).'.'.$signature;
    }

    private function getSessions(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withUnencryptedCookie(config('jwt.cookie'), $token)->withCredentials()->getJson('/api/sessions');
    }

    public function test_valid_token_is_accepted(): void
    {
        [$token] = $this->jwt->issue($this->user);

        $this->assertSame($this->user->id, $this->jwt->userFromToken($token)?->id);
        $this->getSessions($token)->assertOk();
    }

    public function test_changed_payload_is_rejected(): void
    {
        [$token] = $this->jwt->issue($this->user);
        $other = User::factory()->create();

        // Mengganti "sub" ke user lain tanpa kunci rahasia -> tanda tangan tidak cocok.
        $forged = $this->tamper($token, ['sub' => (string) $other->id]);

        $this->assertNull($this->jwt->verify($forged));
        $this->getSessions($forged)->assertUnauthorized();
    }

    public function test_alg_none_token_is_rejected(): void
    {
        $header = $this->b64(json_encode(['alg' => 'none', 'typ' => 'JWT']));
        $payload = $this->b64(json_encode([
            'iss' => config('app.url'), 'sub' => (string) $this->user->id,
            'iat' => time(), 'nbf' => time(), 'exp' => time() + 3600, 'jti' => 'x',
        ]));

        $this->assertNull($this->jwt->verify("{$header}.{$payload}."));
        $this->assertNull($this->jwt->verify("{$header}.{$payload}.abc"));
    }

    public function test_token_signed_with_other_secret_is_rejected(): void
    {
        config(['jwt.secret' => 'kunci-rahasia-lain-yang-panjang-sekali-123']);
        [$token] = $this->jwt->issue($this->user);
        config(['jwt.secret' => null]);

        $this->assertNull($this->jwt->verify($token));
    }

    public function test_expired_token_is_rejected(): void
    {
        config(['jwt.ttl' => 1]);
        [$token] = $this->jwt->issue($this->user);

        $this->travel(2)->minutes();

        $this->assertNull($this->jwt->verify($token));
        $this->getSessions($token)->assertUnauthorized();
    }

    public function test_token_from_other_app_url_is_rejected(): void
    {
        [$token] = $this->jwt->issue($this->user);
        config(['app.url' => 'https://situs-lain.test']);

        $this->assertNull($this->jwt->verify($token));
    }

    public function test_logout_invalidates_token_on_all_devices(): void
    {
        // Login dari dua perangkat -> dua token berbeda.
        [$laptop] = $this->jwt->issue($this->user);
        [$hp] = $this->jwt->issue($this->user);

        // Logout dari laptop.
        $this->jwt->revoke($laptop);

        $this->assertSame(1, $this->user->fresh()->token_version);
        $this->assertNull($this->jwt->userFromToken($laptop));
        $this->assertNull($this->jwt->userFromToken($hp), 'Logout mengeluarkan user dari semua perangkat');
        $this->getSessions($laptop)->assertUnauthorized();

        // Logout lagi dengan token yang sudah mati tidak menaikkan versi lagi.
        $this->jwt->revoke($laptop);
        $this->assertSame(1, $this->user->fresh()->token_version);

        // Login baru setelah logout tetap bisa dipakai.
        [$baru] = $this->jwt->issue($this->user->fresh());
        $this->getSessions($baru)->assertOk();
    }

    public function test_logout_of_one_user_does_not_affect_other_users(): void
    {
        $other = User::factory()->create();
        [$mine] = $this->jwt->issue($this->user);
        [$theirs] = $this->jwt->issue($other);

        $this->jwt->revoke($mine);

        $this->assertNull($this->jwt->userFromToken($mine));
        $this->assertSame($other->id, $this->jwt->userFromToken($theirs)?->id);
    }

    public function test_token_without_version_is_rejected(): void
    {
        // Token lama (dibuat sebelum ada "ver") atau buatan sendiri tanpa "ver" ditolak.
        [$token] = $this->jwt->issue($this->user);
        [$header, $payload] = explode('.', $token);
        $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        unset($data['ver']);
        $unsigned = $header.'.'.$this->b64(json_encode($data));
        $key = hash_hmac('sha256', 'jwt-signing-key', (string) config('app.key'), true);
        $signed = $unsigned.'.'.$this->b64(hash_hmac('sha256', $unsigned, $key, true));

        $this->assertNull($this->jwt->verify($signed));
    }

    public function test_token_of_deleted_user_is_rejected(): void
    {
        [$token] = $this->jwt->issue($this->user);
        $this->user->delete();

        $this->assertNull($this->jwt->userFromToken($token));
    }

    public function test_garbage_tokens_are_rejected(): void
    {
        foreach ([null, '', 'abc', 'a.b', 'a.b.c', '...', '!!!.###.$$$', str_repeat('a', 5000)] as $garbage) {
            $this->assertNull($this->jwt->verify($garbage));
        }
    }

    public function test_remember_me_gives_longer_token(): void
    {
        [, $normal] = $this->jwt->issue($this->user);
        [, $remember] = $this->jwt->issue($this->user, remember: true);

        $this->assertTrue($remember->greaterThan($normal->copy()->addDays(7)));
    }
}
