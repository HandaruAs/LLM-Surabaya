<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/**
 * Login admin (session) dan login/registrasi user biasa (JWT di cookie).
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function jwtCookie(TestResponse $response): ?Cookie
    {
        return $response->getCookie(config('jwt.cookie'), false);
    }

    /* ---------- Admin ---------- */

    public function test_admin_pages_require_authentication(): void
    {
        // Satu pintu login: alamat lama /admin/login diarahkan ke /login.
        $this->get('/admin/login')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/admin/documents')->assertRedirect('/login');
    }

    public function test_admin_can_log_in_and_log_out(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'CorrectHorseBattery1!']);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'CorrectHorseBattery1!'])
            ->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
        $this->get('/admin')->assertRedirect('/admin/documents');
        $this->get('/admin/documents')->assertOk();

        $this->post('/admin/logout')->assertRedirect('/admin/login'); // lalu diteruskan ke /login
        $this->assertGuest();
    }

    public function test_invalid_admin_credentials_do_not_authenticate(): void
    {
        $admin = User::factory()->admin()->create();

        $this->from('/admin/login')
            ->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong'])
            ->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_regular_user_cannot_log_in_through_admin_login(): void
    {
        $user = User::factory()->create(['password' => 'UserPassword123']);

        $this->from('/admin/login')
            ->post('/admin/login', ['email' => $user->email, 'password' => 'UserPassword123'])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_regular_user_gets_403_on_admin_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')->get('/admin')->assertForbidden();
        $this->actingAs($user, 'web')->get('/admin/documents')->assertForbidden();
    }

    public function test_user_jwt_does_not_open_admin_area(): void
    {
        // Admin memakai session; JWT user (bahkan milik akun admin) tidak membuka /admin.
        $admin = User::factory()->admin()->create(['password' => 'AdminPassword123']);
        $token = $this->jwtCookie($this->post('/login', ['email' => $admin->email, 'password' => 'AdminPassword123']))?->getValue();

        // (Akun admin yang login lewat /login mendapat session, bukan JWT. Lihat tes di bawah.)
        $this->assertNull($token);
    }

    public function test_admin_logging_in_from_main_login_goes_straight_to_admin_area(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'AdminPassword123']);

        $response = $this->post('/login', ['email' => $admin->email, 'password' => 'AdminPassword123'])
            ->assertRedirect('/admin');

        // Admin memakai session (guard web), tanpa JWT.
        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertNull($response->getCookie(config('jwt.cookie'), false));
        $this->get('/admin')->assertRedirect('/admin/documents');
        $this->get('/admin/documents')->assertOk();

        // Membuka /login lagi saat masih login admin -> langsung ke dashboard.
        $this->get('/login')->assertRedirect('/admin');
    }

    public function test_admin_returns_to_page_they_wanted_after_login(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'AdminPassword123']);

        $this->get('/admin/documents')->assertRedirect('/login');
        $this->post('/login', ['email' => $admin->email, 'password' => 'AdminPassword123'])
            ->assertRedirect('/admin/documents');
    }

    public function test_regular_user_who_tried_admin_area_goes_to_chat_after_login(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        $this->get('/admin')->assertRedirect('/login');
        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia123'])->assertRedirect('/');
        $this->assertGuest('web');
    }

    public function test_admin_chat_page_shows_link_back_to_admin_area(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'web')
            ->get('/admin/ai-chat')->assertOk()->assertSee('Kembali ke area admin');
    }

    public function test_admin_login_is_rate_limited(): void
    {
        $admin = User::factory()->admin()->create();
        foreach (range(1, 6) as $_) {
            $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong']);
        }
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong'])
            ->assertTooManyRequests();
    }

    /* ---------- User biasa ---------- */

    public function test_login_and_register_pages_are_reachable(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk');
        $this->get('/register')->assertOk()->assertSee('Buat akun');
    }

    public function test_anyone_can_register_as_regular_user_and_is_logged_in(): void
    {
        $response = $this->post('/register', [
            'name' => 'Budi',
            'email' => 'Budi@Example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ]);
        $response->assertRedirect('/');

        $user = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertSame(User::ROLE_USER, $user->role);
        $this->assertFalse($user->isAdmin());

        // Langsung login: JWT dikirim di cookie httpOnly, bukan di session.
        $cookie = $this->jwtCookie($response);
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame(2, substr_count($cookie->getValue(), '.'), 'Bentuk JWT: header.payload.signature');

        $this->withUnencryptedCookie(config('jwt.cookie'), $cookie->getValue())
            ->get('/')->assertSee('Budi')->assertSee('Keluar');
    }

    public function test_register_cannot_set_admin_role(): void
    {
        $this->post('/register', [
            'name' => 'Penyusup',
            'email' => 'penyusup@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'role' => 'admin',
        ])->assertRedirect('/');

        $this->assertSame(User::ROLE_USER, User::where('email', 'penyusup@example.com')->value('role'));
    }

    public function test_register_validation(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->from('/register')->post('/register', [
            'name' => 'A', 'email' => 'ADA@example.com', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('email');

        $this->from('/register')->post('/register', [
            'name' => 'A', 'email' => 'baru@example.com', 'password' => 'pendek', 'password_confirmation' => 'pendek',
        ])->assertSessionHasErrors('password');

        $this->from('/register')->post('/register', [
            'name' => 'A', 'email' => 'baru@example.com', 'password' => 'rahasia123', 'password_confirmation' => 'beda12345',
        ])->assertSessionHasErrors('password');

        $this->assertSame(1, User::count());
    }

    public function test_user_can_log_in_and_log_out(): void
    {
        User::factory()->create(['email' => 'siti@example.com', 'password' => 'rahasia123']);

        $login = $this->post('/login', ['email' => 'Siti@Example.com', 'password' => 'rahasia123'])->assertRedirect('/');
        $token = $this->jwtCookie($login)->getValue();

        $this->withUnencryptedCookie(config('jwt.cookie'), $token)->withCredentials()->getJson('/api/sessions')->assertOk();

        // Logout menghapus cookie DAN mencabut token di server.
        $logout = $this->withUnencryptedCookie(config('jwt.cookie'), $token)->post('/logout')->assertRedirect('/');
        $this->assertTrue($this->jwtCookie($logout)->isCleared());
        $this->assertDatabaseHas('users', ['email' => 'siti@example.com', 'token_version' => 1]);

        // Token lama (mis. dicuri sebelum logout) sudah tidak berlaku.
        // (Di test, aplikasi dipakai ulang antar-request; lupakan user yang sudah
        // tersimpan di guard agar token benar-benar diperiksa ulang, seperti request baru.)
        $this->app['auth']->forgetGuards();
        $this->withUnencryptedCookie(config('jwt.cookie'), $token)->withCredentials()->getJson('/api/sessions')->assertUnauthorized();
    }

    public function test_wrong_password_does_not_log_in(): void
    {
        $user = User::factory()->create();

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'salah'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email')
            ->assertCookieMissing(config('jwt.cookie'));
    }

    public function test_user_login_is_rate_limited(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 6) as $_) {
            $this->post('/login', ['email' => $user->email, 'password' => 'salah']);
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'salah'])->assertTooManyRequests();
    }

    public function test_logged_in_user_is_redirected_away_from_login_pages(): void
    {
        $this->actingAs(User::factory()->create(), 'jwt')->get('/login')->assertRedirect('/');
        $this->actingAs(User::factory()->admin()->create(), 'web')->get('/admin/login')->assertRedirect('/admin');
    }

    public function test_chat_page_shows_guest_or_user_state(): void
    {
        $this->get('/')->assertOk()->assertSee('Riwayat tidak disimpan')->assertSee('/register')
            ->assertDontSee('Kembali ke area admin');

        $user = User::factory()->create(['name' => 'Rina Pengguna']);
        $this->actingAs($user, 'jwt')->get('/')->assertOk()->assertSee('Rina Pengguna')->assertSee('Keluar')
            ->assertDontSee('Riwayat tidak disimpan');
    }
}
