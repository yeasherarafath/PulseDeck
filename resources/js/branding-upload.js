/**
 * Branding image dropzones: click-to-browse + drag-drop with live preview.
 * Loaded only on pages with [data-branding-dropzone] (see app.js).
 */

document.querySelectorAll('[data-branding-dropzone]').forEach((zone) => {
    const input = zone.querySelector('[data-branding-input]');
    const preview = zone.querySelector('[data-branding-preview]');
    const browse = zone.querySelector('[data-branding-browse]');

    if (!input || !preview) {
        return;
    }

    const showPreview = (file) => {
        if (!file || !file.type.startsWith('image/')) {
            return;
        }

        const url = URL.createObjectURL(file);
        preview.innerHTML = '';
        const img = document.createElement('img');
        img.src = url;
        img.alt = 'Selected image preview';
        img.onload = () => URL.revokeObjectURL(url);
        preview.appendChild(img);
    };

    browse?.addEventListener('click', () => input.click());
    preview.addEventListener('click', () => input.click());
    input.addEventListener('change', () => showPreview(input.files[0]));

    ['dragenter', 'dragover'].forEach((name) => {
        zone.addEventListener(name, (event) => {
            event.preventDefault();
            zone.classList.add('dragging');
        });
    });

    ['dragleave', 'drop'].forEach((name) => {
        zone.addEventListener(name, (event) => {
            event.preventDefault();
            zone.classList.remove('dragging');
        });
    });

    zone.addEventListener('drop', (event) => {
        const file = event.dataTransfer?.files?.[0];

        if (file) {
            input.files = event.dataTransfer.files;
            showPreview(file);
        }
    });
});
