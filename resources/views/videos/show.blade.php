@extends('layouts.app')
@section('title', $video->title.' — '.$video->artist)

@section('content')
<section class="watch-page">
    <a class="back" href="{{ route('home') }}">← Voltar para explorar</a>
    <div class="watch-layout">
        <div class="player-wrap">
            @if ($video->video_url)
                <video controls preload="metadata" poster="{{ $video->cover_url }}"><source src="{{ $video->video_url }}">Seu navegador não suporta vídeos.</video>
            @else
                <div class="player-empty"><span>▶</span><p>Vídeo de demonstração ainda não enviado.</p></div>
            @endif
        </div>
        <aside class="track-info">
            <span class="kicker">MÚSICA & DANÇA</span>
            <h1>{{ $video->title }}</h1>
            <p class="artist">por <strong>{{ $video->artist }}</strong></p>
            <a class="edit-link" href="{{ route('videos.edit', $video) }}">✎ Editar música</a>
            @if ($video->video_path)
                <a class="button download" href="{{ route('videos.download', $video) }}">↓ Baixar vídeo</a>
                <small>O download começará automaticamente.</small>
            @endif
        </aside>
    </div>

    @if ($related->isNotEmpty())
        <div class="related"><span class="kicker">CONTINUE DANÇANDO</span><h2>Outras músicas</h2>
            <div class="mini-grid">@foreach($related as $item)<a href="{{ route('videos.show', $item) }}"><b>{{ $item->title }}</b><span>{{ $item->artist }}</span></a>@endforeach</div>
        </div>
    @endif
</section>
@endsection
