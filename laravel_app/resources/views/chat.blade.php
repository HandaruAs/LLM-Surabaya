<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
@include('partials.head')
@vite('resources/js/chat/main.jsx')
</head>
@php
    $user = auth('jwt')->user();
    // Admin membuka chat dari menu "Chatbot AI" (login lewat session, bukan JWT).
    $admin = $user === null && auth('web')->user()?->isAdmin() ? auth('web')->user() : null;
    $initialsOf = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: 'AN';
    $initials = $user ? $initialsOf($user->name) : 'AN';
    $faq = [
        ['Apa perbedaan pajak daerah dan retribusi daerah?', 'Pajak daerah adalah pungutan wajib kepada daerah oleh orang pribadi atau badan tanpa imbalan langsung, dan hasilnya dipakai untuk kepentingan daerah. Retribusi dibayar atas jasa atau perizinan tertentu yang disediakan pemerintah daerah, sehingga pembayarnya menerima imbalan langsung.'],
        ['Apa itu APBD dan mengapa penting bagi warga?', 'APBD adalah rencana keuangan tahunan pemerintah daerah yang ditetapkan bersama DPRD. Isinya mencakup pendapatan, belanja, dan pembiayaan daerah. Dari sinilah layanan publik dan pembangunan dibiayai.'],
        ['Apa itu PBB-P2 dan siapa yang wajib membayarnya?', 'PBB-P2 adalah Pajak Bumi dan Bangunan Perdesaan dan Perkotaan yang dikelola pemerintah daerah. Yang wajib membayar adalah orang pribadi atau badan yang memiliki, menguasai, atau memperoleh manfaat atas bumi dan/atau bangunan.'],
        ['Apa itu BPHTB dan kapan dibayar?', 'BPHTB adalah Bea Perolehan Hak atas Tanah dan Bangunan, yaitu pajak atas perolehan hak, misalnya karena jual beli, hibah, atau waris. Umumnya dibayar oleh pihak yang memperoleh hak. Waktu dan tata cara pembayarannya mengikuti ketentuan daerah, sebaiknya konfirmasi ke petugas.'],
        ['Bagaimana jika terlambat membayar pajak daerah?', 'Keterlambatan umumnya dikenai sanksi administratif berupa denda. Besaran dan ketentuannya diatur dalam peraturan daerah, jadi periksa ketentuan yang berlaku atau tanyakan kepada petugas.'],
    ];

    // Data awal untuk React (resources/js/chat). Tampilan & logikanya ada di sana.
    $chatConfig = [
        'api' => url('/api'),
        'csrf' => csrf_token(),
        'loggedIn' => $user !== null,
        'user' => $user ? ['name' => $user->name, 'email' => $user->email, 'initials' => $initials] : null,
        'isAdmin' => $admin !== null,
        'admin' => $admin ? ['name' => $admin->name, 'email' => $admin->email, 'initials' => $initialsOf($admin->name)] : null,
        'routes' => [
            'login' => route('login'),
            'register' => route('register'),
            'logout' => route('logout'),
            'admin' => route('admin.dashboard'),
            'adminLogout' => route('admin.logout'),
        ],
        'logo' => asset('images/lambang-surabaya.webp'),
        'icons' => \App\Support\Icons::ALL,
        'faq' => $faq,
        // true sekali saja tepat setelah login/daftar -> animasi pembuka (Intro.jsx).
        'intro' => $user !== null && session('welcome_intro') === true,
    ];
@endphp
<body class="h-full bg-paper font-sans text-ink">
<div id="chat-root" data-config="{{ json_encode($chatConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
  {{-- Tampil sebentar sebelum React selesai dimuat. --}}
  <div class="grid h-dvh place-items-center text-sm text-mute">Memuat…</div>
</div>
<noscript>
  <p class="p-6 text-center text-sm">Halaman ini membutuhkan JavaScript. Aktifkan JavaScript di browser lalu muat ulang.</p>
</noscript>
</body>
</html>
