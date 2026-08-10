@extends('layouts.app')
@section('title', 'Editar '.$video->title.' — CCEM Dance')

@section('content')
<section class="form-page">
    <div class="form-intro">
        <span class="kicker">EDITAR MÚSICA</span>
        <h1>Atualize o que precisar.</h1>
        <p>Você pode alterar os dados ou substituir a capa e o vídeo. Arquivos não selecionados serão mantidos.</p>
        @if ($video->cover_url)<img class="edit-cover" src="{{ $video->cover_url }}" alt="Capa atual de {{ $video->title }}">@endif
    </div>
    <form class="upload-form" method="post" action="{{ route('videos.update', $video) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @if ($errors->any())<div class="errors"><b>Revise os campos:</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <label><span>Nome da música</span><input name="title" value="{{ old('title', $video->title) }}" required></label>
        <label><span>Cantor(a)</span><input name="artist" value="{{ old('artist', $video->artist) }}" required></label>
        <label class="file-field"><span>Nova capa <small>(opcional, JPG/PNG até 5 MB)</small></span><input type="file" name="cover" accept="image/*"></label>
        <label class="file-field"><span>Novo vídeo <small>(opcional, MP4/WebM até 500 MB)</small></span><input type="file" name="video_file" accept="video/mp4,video/webm,video/quicktime"></label>
        <button class="button submit" type="submit">Salvar alterações →</button>
    </form>
</section>
@endsection
