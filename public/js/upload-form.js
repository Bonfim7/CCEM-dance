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

    document.querySelectorAll('form.upload-form').forEach((form) => {
        const submit = form.querySelector('button[type="submit"]');

        form.addEventListener('submit', (event) => {
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }

            form.dataset.submitting = 'true';
            form.setAttribute('aria-busy', 'true');

            if (submit) {
                submit.dataset.originalText = submit.textContent.trim();
                submit.disabled = true;
                submit.innerHTML = `<span class="submit-spinner" aria-hidden="true"></span>${submit.dataset.loadingText || 'Enviando…'}`;
            }
        });
    });
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('form.upload-form[data-submitting="true"]').forEach((form) => {
        const submit = form.querySelector('button[type="submit"]');
        delete form.dataset.submitting;
        form.removeAttribute('aria-busy');

        if (submit) {
            submit.disabled = false;
            submit.textContent = submit.dataset.originalText || 'Enviar';
        }
    });
});

function formatBytes(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
}
