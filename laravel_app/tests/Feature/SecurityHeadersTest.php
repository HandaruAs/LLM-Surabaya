<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_send_security_headers(): void
    {
        $response = $this->get('/')->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('X-Powered-By');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9]+'/", $csp);
    }

    public function test_external_chat_scripts_carry_the_csp_nonce(): void
    {
        $response = $this->get('/');
        preg_match("/'nonce-([A-Za-z0-9]+)'/", $response->headers->get('Content-Security-Policy'), $m);
        preg_match_all('/<script\\b[^>]*>/', $response->getContent(), $scripts);
        $externalNonces = [];
        foreach ($scripts[0] as $tag) {
            if (! str_contains($tag, 'src=')) {
                continue;
            }
            preg_match('/\\bnonce="([^"]+)"/', $tag, $nonce);
            $externalNonces[] = $nonce[1] ?? null;
        }

        $this->assertNotEmpty($externalNonces);
        foreach ($externalNonces as $nonce) {
            $this->assertSame($m[1], $nonce);
        }
    }

    public function test_nonce_is_different_per_request(): void
    {
        $a = $this->get('/')->headers->get('Content-Security-Policy');
        $b = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotSame($a, $b);
    }

    public function test_api_responses_are_not_cached(): void
    {
        $this->actingAs(User::factory()->create(), 'jwt')
            ->getJson('/api/sessions')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY');

        $this->assertStringContainsString('no-store', $this->getJson('/api/health')->headers->get('Cache-Control'));
    }

    public function test_admin_pages_also_send_headers(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'web')
            ->get('/admin/documents')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy');
    }
}
