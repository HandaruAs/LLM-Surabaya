<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#11874a">
<link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
<title>{{ isset($title) && $title !== '' ? $title.' - ' : '' }}{{ config('app.name', 'LLM Surabaya') }}</title>
{{-- Tema terang/gelap pilihan pengguna dipasang sebelum halaman tampil, supaya tidak berkedip. --}}
<script nonce="{{ Vite::cspNonce() }}">
  (function () {
    try {
      var t = localStorage.getItem('llm-theme');
      if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t;
    } catch (e) {}
  })();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Source+Serif+4:opsz,wght@8..60,400;8..60,600;8..60,700&display=swap" rel="stylesheet">
{{-- Wajib sebelum @vite: preamble React Fast Refresh saat `npm run dev` (tidak berpengaruh saat build). --}}
@viteReactRefresh
@vite(['resources/css/app.css', 'resources/js/app.js'])
