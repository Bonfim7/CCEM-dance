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
    <div class="form-stack">
        <form class="upload-form" method="post" action="{{ route('videos.update', $video) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @if ($errors->any())<div class="errors"><b>Revise os campos:</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <div class="field-row">
                <label><span>Nome da música</span><input name="title" value="{{ old('title', $video->title) }}" required placeholder="Ex.: Ninguém Te Ama Como Eu"></label>
                <label><span>Cantor(a)</span><input name="artist" value="{{ old('artist', $video->artist) }}" required placeholder="Ex.: Padre Marcelo Rossi"></label>
            </div>
            <div class="field-row">
                @include('videos.partials.file-picker', ['kind' => 'image', 'label' => 'Nova capa', 'help' => '(opcional, JPG/PNG até 5 MB)', 'name' => 'cover', 'accept' => 'image/*', 'button' => 'Trocar capa', 'empty' => 'A capa atual será mantida'])
                @include('videos.partials.file-picker', ['kind' => 'video', 'label' => 'Novo vídeo', 'help' => '(opcional, MP4/WebM até 500 MB)', 'name' => 'video_file', 'accept' => 'video/mp4,video/webm,video/quicktime', 'button' => 'Trocar vídeo', 'empty' => 'O vídeo atual será mantido'])
            </div>
            <button class="button submit" type="submit" data-loading-text="Salvando alterações…">Salvar alterações →</button>
        </form>

        <form class="delete-form" method="post" action="{{ route('videos.destroy', $video) }}" data-confirm-delete>
            @csrf
            @method('DELETE')
            <div>
                <b>Excluir esta música</b>
                <p>A música, a capa e o vídeo serão removidos permanentemente.</p>
            </div>
            <button class="delete-button" type="submit">Excluir música</button>
        </form>
    </div>
</section>
@endsection
