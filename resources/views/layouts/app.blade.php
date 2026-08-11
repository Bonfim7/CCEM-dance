<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b0910">
    <meta name="description" content="Vídeos, música e movimento em um só lugar.">
    <title>@yield('title', 'CCEM Dance')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="{{ route('home') }}" aria-label="CCEM Ministério de Dança - início">
            <img class="brand-logo" src="{{ asset('images/ccem-logo.png') }}" alt="" aria-hidden="true">
            <span>CCEM <b>Ministério de Dança</b></span>
        </a>
        <nav>
            <a href="{{ route('home') }}">Explorar</a>
            <a class="button button-small" href="{{ route('videos.create') }}">+ Publicar vídeo</a>
        </nav>
    </header>

    @if (session('success'))
        <div class="flash">{{ session('success') }}</div>
    @endif

    <main>@yield('content')</main>
    <footer><span>CCEM — Ministério de Dança</span><span>Círculo Católico Estrela da Manhã</span></footer>
    <nav class="mobile-nav" aria-label="Navegação principal">
        <a class="{{ request()->routeIs('home', 'videos.show') ? 'active' : '' }}" href="{{ route('home') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11.5 12 4l9 7.5M5.5 10v10h13V10M9 20v-6h6v6"/></svg>
            <span>Explorar</span>
        </a>
        <a class="{{ request()->routeIs('videos.create') ? 'active' : '' }}" href="{{ route('videos.create') }}">
            <span class="mobile-plus">+</span>
            <span>Publicar</span>
        </a>
    </nav>
    <script src="{{ asset('js/upload-form.js') }}" defer></script>
</body>
</html>
