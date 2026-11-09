<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
@include('partials.head')
@vite('resources/js/chat/main.jsx')
</head>
@php
    $adminMode = $adminMode ?? false;
    $user = $adminMode ? auth('web')->user() : auth('jwt')->user();
    $admin = $adminMode && $user?->isAdmin() ? $user : null;
    $displayUser = $admin ?? $user;
    $initialsOf = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: 'AN';
    $initials = $displayUser ? $initialsOf($displayUser->name) : 'AN';
    $chatConfig = [
        'api' => $adminMode ? url('/admin/ai-chat') : url('/api'),
        'csrf' => csrf_token(),
        'loggedIn' => $displayUser !== null,
        'user' => $displayUser ? ['name' => $displayUser->name, 'email' => $displayUser->email, 'initials' => $initials] : null,
        'isAdmin' => $admin !== null,
        'admin' => $admin ? ['name' => $admin->name, 'email' => $admin->email, 'initials' => $initials] : null,
        'routes' => [
            'login' => route('login'),
            'register' => route('register'),
            'logout' => $adminMode ? route('admin.logout') : route('logout'),
            'admin' => route('admin.dashboard'),
            'adminLogout' => route('admin.logout'),
        ],
        'logo' => asset('images/lambang-surabaya.webp'),
        'intro' => $user !== null && session('welcome_intro') === true,
    ];
@endphp
<body class="h-full bg-paper font-sans text-ink">
<div id="chat-root" data-config="{{ json_encode($chatConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
  <div class="grid h-dvh place-items-center bg-aurora">
    <div class="flex flex-col items-center gap-3 text-sm text-mute">
      <img src="{{ asset('images/lambang-surabaya.webp') }}" alt="" class="h-12 w-auto animate-pulse">
      Memuat…
    </div>
  </div>
</div>
<noscript>
  <p class="p-6 text-center text-sm">Halaman ini membutuhkan JavaScript. Aktifkan JavaScript di browser lalu muat ulang.</p>
</noscript>
</body>
</html>
