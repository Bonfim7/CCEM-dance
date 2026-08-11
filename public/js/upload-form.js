document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-file-picker]').forEach((control) => {
        const input = control.querySelector('.file-input');
        const picker = control.querySelector('.file-picker');
        const preview = control.querySelector('.file-preview');
        const media = control.querySelector('.file-preview-media');
        const name = control.querySelector('[data-file-name]');
        const meta = control.querySelector('[data-file-meta]');
        const remove = control.querySelector('.file-remove');
        let objectUrl;

        const clearPreview = () => {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = undefined;
            media.replaceChildren();
            preview.hidden = true;
            picker.hidden = false;
        };

        input.addEventListener('change', () => {
            clearPreview();
            const file = input.files?.[0];
            if (!file) return;

            objectUrl = URL.createObjectURL(file);
            const element = document.createElement(control.dataset.kind === 'image' ? 'img' : 'video');
            element.src = objectUrl;
            element.alt = control.dataset.kind === 'image' ? `Prévia de ${file.name}` : '';

            if (element instanceof HTMLVideoElement) {
                element.controls = true;
                element.preload = 'metadata';
            }

            media.append(element);
            name.textContent = file.name;
            meta.textContent = `${file.type || 'Arquivo'} · ${formatBytes(file.size)}`;
            picker.hidden = true;
            preview.hidden = false;
        });

        remove.addEventListener('click', () => {
            input.value = '';
            clearPreview();
            input.focus();
        });
    });
});

function formatBytes(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
}
