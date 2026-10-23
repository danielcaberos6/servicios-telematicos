document.querySelectorAll('[data-document-viewer]').forEach((viewer) => {
    const preview = viewer.querySelector('[data-document-preview]');
    const thumbnail = viewer.querySelector('[data-document-thumbnail]');
    const open = viewer.querySelector('[data-document-open]');
    const error = viewer.querySelector('[data-document-error]');
    const dialog = viewer.querySelector('[data-document-dialog]');
    const viewport = viewer.querySelector('[data-document-viewport]');
    const sheet = viewer.querySelector('[data-document-sheet]');
    const image = viewer.querySelector('[data-document-image]');
    const zoom = viewer.querySelector('[data-document-zoom]');
    const zoomIn = viewer.querySelector('[data-document-in]');
    const zoomOut = viewer.querySelector('[data-document-out]');
    const input = document.getElementById(viewer.dataset.documentInput);
    let objectUrl;
    let revision = 0;
    let scale = 1;
    let rotation = 0;
    let fitScale = 1;

    const render = () => {
        const width = thumbnail.naturalWidth * scale;
        const height = thumbnail.naturalHeight * scale;
        sheet.style.width = `${rotation % 180 ? height : width}px`;
        sheet.style.height = `${rotation % 180 ? width : height}px`;
        image.style.width = `${width}px`;
        image.style.height = `${height}px`;
        image.style.transform = `translate(-50%, -50%) rotate(${rotation}deg)`;
        zoom.value = `${Math.round(scale * 100)}%`;
        zoomIn.disabled = scale >= 4;
        zoomOut.disabled = scale <= Math.min(fitScale, 0.05);
    };
    const fit = () => {
        const width = rotation % 180 ? thumbnail.naturalHeight : thumbnail.naturalWidth;
        const height = rotation % 180 ? thumbnail.naturalWidth : thumbnail.naturalHeight;
        fitScale = Math.min((viewport.clientWidth - 32) / width, (viewport.clientHeight - 32) / height, 1);
        scale = Math.max(0.001, fitScale);
        render();
        viewport.scrollTo(0, 0);
    };
    const changeZoom = (factor) => {
        scale = Math.max(Math.min(fitScale, 0.05), Math.min(4, scale * factor));
        render();
        viewport.scrollTo((viewport.scrollWidth - viewport.clientWidth) / 2, (viewport.scrollHeight - viewport.clientHeight) / 2);
    };
    open.addEventListener('click', () => {
        image.src = thumbnail.src;
        rotation = 0;
        dialog.showModal();
        fit();
    });
    dialog.addEventListener('close', () => {
        image.removeAttribute('src');
        open.focus();
    });
    viewer.querySelector('[data-document-close]').addEventListener('click', () => dialog.close());
    zoomIn.addEventListener('click', () => changeZoom(1.25));
    zoomOut.addEventListener('click', () => changeZoom(1 / 1.25));
    viewer.querySelector('[data-document-fit]').addEventListener('click', fit);
    viewer.querySelector('[data-document-rotate]').addEventListener('click', () => {
        rotation = (rotation + 90) % 360;
        fit();
    });
    window.addEventListener('resize', () => { if (dialog.open) fit(); });

    const showError = (message) => {
        preview.hidden = true;
        open.hidden = true;
        error.textContent = message;
        error.hidden = false;
        if (input) input.setCustomValidity(message);
    };
    const loadPreview = async () => {
        const current = ++revision;
        error.hidden = true;
        open.hidden = true;
        if (input) {
            input.setCustomValidity('');
            preview.hidden = true;
            thumbnail.removeAttribute('src');
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;
            const file = input.files?.[0];
            if (!file) return;
            if (!['image/jpeg', 'image/png'].includes(file.type) || file.size > 5 * 1024 * 1024) {
                showError('Selecciona un carnet JPG o PNG de hasta 5 MB.');
                return;
            }
            objectUrl = URL.createObjectURL(file);
            thumbnail.src = objectUrl;
        }
        try {
            await thumbnail.decode();
            if (current !== revision) return;
            if (input && (thumbnail.naturalWidth > 8000 || thumbnail.naturalHeight > 8000)) {
                showError('La imagen del carnet no puede superar 8000 píxeles por lado.');
                return;
            }
            preview.hidden = false;
            open.hidden = false;
        } catch {
            if (current === revision) showError('No se pudo mostrar el carnet. Comprueba que la imagen sea válida y esté disponible.');
        }
    };
    if (input) {
        input.addEventListener('change', loadPreview);
        input.form?.addEventListener('reset', () => window.setTimeout(loadPreview, 0));
        window.addEventListener('pagehide', (event) => {
            if (!event.persisted && objectUrl) URL.revokeObjectURL(objectUrl);
        });
        loadPreview();
    } else {
        loadPreview();
    }
});
