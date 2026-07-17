function getElements() {
    return {
        active: document.getElementById('ar_active'),
        box: document.getElementById('ar_box'),
        file: document.getElementById('ar_file'),
        status: document.getElementById('ar_status'),
        progress: document.getElementById('ar_progress'),
        previewButton: document.getElementById('ar_preview_btn'),
        attachment: document.getElementById('ar_attachment_id'),
    };
}

function selectedType() {
    return document.querySelector('input[name="ar_type"]:checked')?.value || 'image';
}

function validate(file, type) {
    const isImage = ['image/jpeg', 'image/png'].includes(file.type);
    const isVideo = ['video/mp4', 'video/quicktime'].includes(file.type);

    if (type === 'image' && !isImage) return 'Выберите JPG или PNG.';
    if (type === 'video' && !isVideo) return 'Выберите MP4 или MOV.';
    if (isImage && file.size > 10 * 1024 * 1024) return 'Изображение больше 10 МБ.';
    if (isVideo && file.size > 50 * 1024 * 1024) return 'Видео больше 50 МБ.';

    return null;
}

function resetState(elements) {
    elements.file.value = '';
    elements.attachment.value = '';
    elements.previewButton.disabled = true;
    elements.status.textContent = '';
    window.AR_PREVIEW = null;
}

function setStatus(elements, message, isError = false) {
    elements.status.textContent = message;
    elements.status.classList.toggle('uk-text-danger', isError);
    elements.status.classList.toggle('uk-text-success', !isError && Boolean(message));
}

function uploadFile(file, type, elements) {
    const config = window.MOSPAL_AR_CONFIG;
    if (!config?.ajaxUrl || !config?.nonce) {
        setStatus(elements, 'Настройки загрузки недоступны. Обновите страницу.', true);
        return;
    }

    const data = new FormData();
    data.append('action', 'mospal_greeting_upload');
    data.append('ar_file_upload', file);
    data.append('nonce', config.nonce);

    elements.file.disabled = true;
    elements.previewButton.disabled = true;
    elements.progress.hidden = false;
    elements.progress.value = 0;
    setStatus(elements, 'Загрузка…');

    const xhr = new XMLHttpRequest();
    xhr.upload.onprogress = (event) => {
        if (event.lengthComputable) {
            elements.progress.value = (event.loaded / event.total) * 100;
        }
    };

    xhr.onload = () => {
        try {
            const response = JSON.parse(xhr.responseText);
            if (!response.success) {
                throw new Error(response.data?.message || 'Не удалось загрузить файл.');
            }

            const { id, url, type: mime } = response.data;
            elements.attachment.value = id;
            window.AR_PREVIEW = { url, type: mime };
            elements.previewButton.disabled = false;
            setStatus(elements, 'Файл загружен. Его можно посмотреть в предпросмотре.');
        } catch (error) {
            resetState(elements);
            setStatus(elements, error.message || 'Не удалось обработать ответ сервера.', true);
        } finally {
            elements.progress.hidden = true;
            elements.file.disabled = false;
        }
    };

    xhr.onerror = () => {
        elements.progress.hidden = true;
        elements.file.disabled = false;
        setStatus(elements, 'Ошибка сети. Попробуйте загрузить файл ещё раз.', true);
    };

    xhr.open('POST', config.ajaxUrl);
    xhr.send(data);
}

function bindEvents(elements) {
    if (elements.active.dataset.bound) return;

    elements.active.addEventListener('change', () => {
        elements.box.hidden = !elements.active.checked;
        if (!elements.active.checked) resetState(elements);
    });

    document.querySelectorAll('input[name="ar_type"]').forEach((radio) => {
        radio.addEventListener('change', () => resetState(elements));
    });

    elements.file.addEventListener('change', (event) => {
        const file = event.target.files?.[0];
        if (!file) return;

        const error = validate(file, selectedType());
        if (error) {
            resetState(elements);
            setStatus(elements, error, true);
            return;
        }

        uploadFile(file, selectedType(), elements);
    });

    elements.active.dataset.bound = '1';
}

function initARCheckout() {
    const elements = getElements();
    if (!elements.active || !elements.file || !elements.box) return;
    bindEvents(elements);
}

document.addEventListener('DOMContentLoaded', initARCheckout);
jQuery(document.body).on('updated_checkout', initARCheckout);
