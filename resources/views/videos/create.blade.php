@extends('layouts.app')
@section('title', 'Publicar vídeo — CCEM Dance')

@section('content')
<section class="form-page">
    <div class="form-intro"><span class="kicker">NOVO CONTEÚDO</span><h1>Coloque uma nova dança no palco.</h1><p>Adicione os dados da música, uma capa bonita e o vídeo da coreografia.</p></div>
    <form class="upload-form" method="post" action="{{ route('videos.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($errors->any())<div class="errors"><b>Revise os campos:</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="field-row">
            <label><span>Nome da música</span><input name="title" value="{{ old('title') }}" required placeholder="Ex.: Levitating"></label>
            <label><span>Cantor(a)</span><input name="artist" value="{{ old('artist') }}" required placeholder="Ex.: Dua Lipa"></label>
        </div>
        <label><span>Estilo da dança</span><input name="dance_style" value="{{ old('dance_style') }}" required placeholder="Ex.: Pop, Salsa, Hip Hop"></label>
        <label><span>Descrição <small>(opcional)</small></span><textarea name="description" rows="4" placeholder="Conte um pouco sobre a coreografia...">{{ old('description') }}</textarea></label>
        <div class="field-row">
            <label class="file-field"><span>Capa da música <small>(JPG/PNG, até 5 MB)</small></span><input type="file" name="cover" accept="image/*"></label>
            <label class="file-field"><span>Arquivo do vídeo <small>(MP4/WebM, até 500 MB)</small></span><input type="file" name="video" accept="video/mp4,video/webm,video/quicktime" required></label>
        </div>
        <button class="button submit" type="submit">Publicar vídeo →</button>
    </form>
</section>
@endsection
