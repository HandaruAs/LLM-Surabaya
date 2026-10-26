<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Satu pintu login untuk semua akun, plus registrasi dan logout user.
 *
 * - User biasa  -> JWT di cookie httpOnly, diarahkan ke halaman chat.
 * - Admin       -> session (guard 'web'), diarahkan langsung ke /admin.
 *
 * Setelah login, server membuat JWT lalu menyimpannya di cookie httpOnly.
 * Browser mengirim cookie itu otomatis di setiap request (termasuk /api/*),
 * dan guard 'jwt' memeriksa tanda tangannya. Server tidak menyimpan data login;
 * saat logout, users.token_version dinaikkan supaya semua token lama user itu langsung tidak berlaku.
 */
class UserAuthController extends Controller
{
    public function __construct(private readonly JwtService $jwt) {}

    public function showLogin(): View|RedirectResponse
    {
        // Admin yang masih login tidak perlu melihat form login lagi.
        if (Auth::guard('web')->user()?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);
        $credentials['email'] = strtolower(trim($credentials['email']));

        $user = User::where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['email' => 'Email atau kata sandi salah.'])->onlyInput('email');
        }

        // Admin: login lewat session (sama seperti /admin/login), langsung ke dashboard admin.
        if ($user->isAdmin()) {
            Auth::guard('web')->login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            return redirect()->intended(route('admin.dashboard'));
        }

        // User biasa: JWT. Abaikan halaman tujuan sebelumnya (mis. /admin) supaya tidak berakhir di 403.
        $request->session()->forget('url.intended');

        return $this->loggedIn($request, $user, $request->boolean('remember'), redirect()->route('chat'));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'name.max' => 'Nama maksimal :max karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar. Silakan masuk.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min' => 'Kata sandi minimal :min karakter.',
        ]);

        // role tidak fillable -> otomatis 'user' (default kolom). Password di-hash oleh cast model.
        $user = User::create($data);

        return $this->loggedIn($request, $user, false, redirect()->route('chat'));
    }

    public function logout(Request $request): RedirectResponse
    {
        // Cabut token supaya tidak bisa dipakai lagi walau masih tersimpan di tempat lain.
        $this->jwt->revoke($request->cookie(config('jwt.cookie')));
        $request->session()->regenerateToken();

        return redirect()->route('chat')->withCookie(Cookie::forget((string) config('jwt.cookie')));
    }

    /** Buat JWT, simpan di cookie httpOnly, lalu arahkan ke halaman tujuan. */
    private function loggedIn(Request $request, User $user, bool $remember, RedirectResponse $redirect): RedirectResponse
    {
        [$token, $expiresAt] = $this->jwt->issue($user, $remember);

        // Ganti token CSRF setelah login.
        $request->session()->regenerateToken();

        // Penanda sekali pakai: halaman chat memutar animasi pembuka (logo) satu kali setelah login/daftar.
        return $redirect->with('welcome_intro', true)->withCookie(new SymfonyCookie(
            name: (string) config('jwt.cookie'),
            value: $token,
            expire: $expiresAt,
            path: '/',
            secure: $request->isSecure() || (bool) config('session.secure'),
            httpOnly: true,                 // tidak bisa dibaca JavaScript (aman dari XSS)
            raw: false,
            sameSite: SymfonyCookie::SAMESITE_LAX, // tidak ikut terkirim dari form situs lain
        ));
    }
}
