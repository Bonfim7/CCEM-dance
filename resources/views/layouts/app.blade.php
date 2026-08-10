<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Vídeos, música e movimento em um só lugar.">
    <title>@yield('title', 'CCEM Dance')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="{{ route('home') }}" aria-label="CCEM Dance - início">
            <span class="brand-mark">C</span>
            <span>CCEM <b>Dance</b></span>
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
    <footer><span>CCEM Dance</span><span>Feito para quem vive em movimento.</span></footer>
</body>
</html>
