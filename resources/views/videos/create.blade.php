@extends('layouts.app')
@section('title', 'Publicar vídeo — CCEM Dance')

@section('content')
<section class="form-page">
    <div class="form-intro"><span class="kicker">NOVO CONTEÚDO</span><h1>Coloque uma nova dança no palco.</h1><p>Informe a música, quem canta e envie a capa e o vídeo.</p></div>
    <form class="upload-form" method="post" action="{{ route('videos.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($errors->any())<div class="errors"><b>Revise os campos:</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="field-row">
            <label><span>Nome da música</span><input name="title" value="{{ old('title') }}" required placeholder="Ex.: Ninguém Te Ama Como Eu"></label>
            <label><span>Cantor(a)</span><input name="artist" value="{{ old('artist') }}" required placeholder="Ex.: Padre Marcelo Rossi"></label>
        </div>
        <div class="field-row">
            @include('videos.partials.file-picker', ['kind' => 'image', 'label' => 'Capa da música', 'help' => '(JPG/PNG, até 5 MB)', 'name' => 'cover', 'accept' => 'image/*', 'button' => 'Escolher capa', 'empty' => 'Veja a imagem antes de publicar', 'required' => true])
            @include('videos.partials.file-picker', ['kind' => 'video', 'label' => 'Arquivo do vídeo', 'help' => '(MP4/WebM, até 500 MB)', 'name' => 'video', 'accept' => 'video/mp4,video/webm,video/quicktime', 'button' => 'Escolher vídeo', 'empty' => 'Confira o arquivo antes de enviar', 'required' => true])
        </div>
        <button class="button submit" type="submit" data-loading-text="Enviando vídeo…">Publicar vídeo →</button>
    </form>
</section>
@endsection
