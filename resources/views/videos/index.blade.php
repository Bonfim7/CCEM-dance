@extends('layouts.app')

@section('title', 'CCEM Dance — Encontre seu próximo ritmo')

@section('content')
<section class="hero">
    <div class="eyebrow"><i></i> Sinta o ritmo. Viva o movimento.</div>
    <h1>Uma dança para<br><em>cada batida.</em></h1>
    <p>Descubra coreografias, aprenda novos passos e leve suas músicas favoritas para onde quiser.</p>
    <form class="search" action="{{ route('home') }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.3-4.3m2.3-5.2a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z"/></svg>
        <input name="busca" value="{{ $term }}" placeholder="Busque por música, cantor ou ritmo..." aria-label="Buscar vídeos">
        <button>Buscar</button>
    </form>
</section>

<section class="catalog">
    <div class="section-heading">
        <div><span class="kicker">NOSSA SELEÇÃO</span><h2>{{ $term ? 'Resultados da busca' : 'Danças em destaque' }}</h2></div>
        <span>{{ $videos->total() }} {{ Str::plural('vídeo', $videos->total()) }}</span>
    </div>

    @if ($videos->isEmpty())
        <div class="empty">
            <div class="empty-icon">♫</div>
            <h3>{{ $term ? 'Nenhuma dança encontrada' : 'O palco está pronto' }}</h3>
            <p>{{ $term ? 'Tente buscar por outro nome ou ritmo.' : 'Publique o primeiro vídeo e ele aparecerá aqui.' }}</p>
            <a class="button" href="{{ route('videos.create') }}">Publicar primeiro vídeo</a>
        </div>
    @else
        <div class="video-grid">
            @foreach ($videos as $video)
                <a class="video-card" href="{{ route('videos.show', $video) }}">
                    <div class="cover cover-{{ ($video->id % 5) + 1 }}">
                        @if ($video->cover_url)<img src="{{ $video->cover_url }}" alt="Capa de {{ $video->title }}">@endif
                        <span class="style">{{ $video->dance_style }}</span>
                        <span class="cover-letter">{{ Str::upper(Str::substr($video->title, 0, 1)) }}</span>
                        <span class="play"><svg viewBox="0 0 24 24"><path d="m9 7 8 5-8 5V7Z"/></svg></span>
                    </div>
                    <div class="card-copy">
                        <h3>{{ $video->title }}</h3>
                        <p>{{ $video->artist }}</p>
                        <span>Assistir agora <b>↗</b></span>
                    </div>
                </a>
            @endforeach
        </div>
        {{ $videos->links() }}
    @endif
</section>
@endsection
