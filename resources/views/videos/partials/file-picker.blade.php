<div class="file-control" data-file-picker data-kind="{{ $kind }}">
    <span class="file-label">{{ $label }} <small>{{ $help }}</small></span>
    <label class="file-picker">
        <input
            class="file-input"
            type="file"
            name="{{ $name }}"
            accept="{{ $accept }}"
            @if ($required ?? false) required @endif
        >
        <span class="file-picker-icon" aria-hidden="true">
            @if ($kind === 'image')
                <svg viewBox="0 0 24 24"><path d="M4 5.5h16v13H4zM7 15l3-3 2.5 2.5L15 12l3 3M8.5 9.5h.01"/></svg>
            @else
                <svg viewBox="0 0 24 24"><path d="M5 4.5h14v15H5zM10 9l5 3-5 3z"/></svg>
            @endif
        </span>
        <span class="file-picker-copy">
            <b>{{ $button }}</b>
            <small>{{ $empty }}</small>
        </span>
        <span class="file-picker-action">Selecionar</span>
    </label>
    <div class="file-preview" hidden>
        <div class="file-preview-media"></div>
        <div class="file-preview-copy">
            <b data-file-name></b>
            <small data-file-meta></small>
        </div>
        <button class="file-remove" type="button" aria-label="Remover arquivo selecionado">Remover</button>
    </div>
</div>
