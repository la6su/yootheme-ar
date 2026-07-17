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

const UPLOAD_CHUNK_SIZE = 512 * 1024;

function createUploadId() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();

    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

function uploadChunk({ file, chunk, index, total, uploadId, config, elements }) {
    return new Promise((resolve, reject) => {
        const data = new FormData();
        data.append('action', 'mospal_greeting_upload_chunk');
        data.append('ar_file_chunk', chunk, `${file.name}.part`);
        data.append('upload_id', uploadId);
        data.append('chunk_index', String(index));
        data.append('total_chunks', String(total));
        data.append('file_name', file.name);
        data.append('file_type', file.type);
        data.append('file_size', String(file.size));
        data.append('nonce', config.nonce);

        const xhr = new XMLHttpRequest();
        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable) {
                elements.progress.value = ((index + event.loaded / event.total) / total) * 100;
            }
        };
        xhr.onload = () => {
            if (xhr.status === 413) {
                reject(new Error('Сервер отклонил часть файла как слишком большую. Обратитесь к администратору.'));
                return;
            }

            try {
                const response = JSON.parse(xhr.responseText);
                if (!response.success) {
                    throw new Error(response.data?.message || 'Не удалось загрузить файл.');
                }
                resolve(response.data);
            } catch (error) {
                reject(error);
            }
        };
        xhr.onerror = () => reject(new Error('Ошибка сети. Попробуйте загрузить файл ещё раз.'));
        xhr.open('POST', config.ajaxUrl);
        xhr.send(data);
    });
}

async function uploadFile(file, type, elements) {
    const config = window.MOSPAL_AR_CONFIG;
    if (!config?.ajaxUrl || !config?.nonce) {
        setStatus(elements, 'Настройки загрузки недоступны. Обновите страницу.', true);
        return;
    }

    elements.file.disabled = true;
    elements.previewButton.disabled = true;
    elements.progress.hidden = false;
    elements.progress.value = 0;
    setStatus(elements, 'Загрузка…');

    try {
        const uploadId = createUploadId();
        const total = Math.ceil(file.size / UPLOAD_CHUNK_SIZE);
        let result = null;

        for (let index = 0; index < total; index++) {
            const start = index * UPLOAD_CHUNK_SIZE;
            const chunk = file.slice(start, Math.min(start + UPLOAD_CHUNK_SIZE, file.size), file.type);
            result = await uploadChunk({ file, chunk, index, total, uploadId, config, elements });
        }

        if (!result?.complete || !result.id) {
            throw new Error('Не удалось завершить сборку файла. Попробуйте ещё раз.');
        }

        const { id, url, type: mime } = result;
        elements.attachment.value = id;
        window.AR_PREVIEW = { url, type: mime };
        elements.previewButton.disabled = false;
        setStatus(elements, 'Файл загружен. Его можно посмотреть в предпросмотре.');
    } catch (error) {
        resetState(elements);
        setStatus(elements, error.message || 'Не удалось загрузить файл.', true);
    } finally {
        elements.progress.hidden = true;
        elements.file.disabled = false;
    }
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
