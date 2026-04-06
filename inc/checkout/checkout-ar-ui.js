// ============================
// CHECKOUT AR UI (CLEAN)
// ============================

function getElements() {
    return {
        active: document.getElementById('ar_active'),
        box: document.getElementById('ar_box'),
        file: document.getElementById('ar_file'),
        status: document.getElementById('ar_status'),
        progress: document.getElementById('ar_progress'),
        previewBtn: document.getElementById('ar_preview_btn'),
        hidden: document.getElementById('ar_attachment_id'),
        preview: document.getElementById('ar_preview_container')
    };
}

// ============================
// VALIDATION
// ============================

function validate(file, type) {

    const isImage = ['image/jpeg','image/png'].includes(file.type);
    const isVideo = ['video/mp4','video/quicktime'].includes(file.type);

    if (type === 'image' && !isImage) return 'Только JPG/PNG';
    if (type === 'video' && !isVideo) return 'Только MP4/MOV';

    if (type === 'image' && file.size > 10 * 1024 * 1024) return 'Фото > 10MB';
    if (type === 'video' && file.size > 50 * 1024 * 1024) return 'Видео > 50MB';

    return null;
}

// ============================
// RESET
// ============================

function resetState(el) {
    if (!el.file) return;

    el.file.value = '';
    el.hidden.value = '';
    window.AR_ATTACHMENT_ID = null;

    if (el.status) el.status.innerHTML = '';
    if (el.previewBtn) el.previewBtn.disabled = true;
    if (el.preview) el.preview.innerHTML = '';
}

// ============================
// UPLOAD
// ============================

function uploadFile(file, type, el) {

    const data = new FormData();
    data.append('action', 'ar_async_upload');
    data.append('ar_file_upload', file);
    data.append('nonce', AR_CONFIG.nonce);

    el.status.innerHTML = 'Загрузка...';
    el.progress.hidden = false;
    el.previewBtn.disabled = true;
    el.file.disabled = true;

    const xhr = new XMLHttpRequest();

    xhr.upload.onprogress = (e) => {
        if (e.lengthComputable) {
            el.progress.value = (e.loaded / e.total) * 100;
        }
    };

    xhr.onload = () => {

        let res;
        try {
            res = JSON.parse(xhr.responseText);
        } catch {
            el.status.innerHTML = 'Ошибка ответа';
            return;
        }

        if (res.success) {

            window.AR_ATTACHMENT_ID = res.data.id;
            el.hidden.value = res.data.id;

            window.AR_PREVIEW = {
                url: res.data.url,
                type: res.data.type
            };

            el.status.innerHTML = 'Файл загружен';
            el.previewBtn.disabled = false;

            // fallback preview
            if (el.preview) {
                if (res.data.type.includes('video')) {
                    el.preview.innerHTML = `<video controls style="max-width:100%"><source src="${res.data.url}"></video>`;
                } else {
                    el.preview.innerHTML = `<img src="${res.data.url}" style="max-width:100%">`;
                }
            }

        } else {
            el.status.innerHTML = res.data;
        }

        el.progress.hidden = true;
        el.file.disabled = false;
    };

    xhr.onerror = () => {
        el.status.innerHTML = 'Ошибка сети';
        el.progress.hidden = true;
        el.file.disabled = false;
    };

    xhr.open("POST", AR_CONFIG.ajax);
    xhr.send(data);
}

// ============================
// BIND EVENTS (ОДИН РАЗ)
// ============================

function bindEvents(el) {

    if (!el.active) return;

    // toggle
    if (!el.active.dataset.bound) {
        el.active.addEventListener('change', () => {
            el.box.hidden = !el.active.checked;

            if (!el.active.checked) {
                resetState(el);
            }
        });
        el.active.dataset.bound = '1';
    }

    // radio reset
    document.querySelectorAll('input[name="ar_type"]').forEach(radio => {
        if (!radio.dataset.bound) {
            radio.addEventListener('change', () => resetState(el));
            radio.dataset.bound = '1';
        }
    });

    // file upload
    if (el.file && !el.file.dataset.bound) {

        el.file.addEventListener('change', (e) => {

            const file = e.target.files[0];
            if (!file) return;

            const type = document.querySelector('input[name="ar_type"]:checked')?.value;

            const error = validate(file, type);
            if (error) {
                el.status.innerHTML = error;
                return;
            }

            uploadFile(file, type, el);
        });

        el.file.dataset.bound = '1';
    }
}

// ============================
// INIT
// ============================

function initARCheckout() {

    const el = getElements();
    if (!el.active) return;

    window.AR_ATTACHMENT_ID = window.AR_ATTACHMENT_ID || null;

    bindEvents(el);
}

// ============================
// EVENTS
// ============================

// initial load
document.addEventListener('DOMContentLoaded', initARCheckout);

// WooCommerce AJAX (главное)
jQuery(document.body).on('updated_checkout', initARCheckout);

// UIkit (если реально нужен)
if (typeof UIkit !== 'undefined') {
    UIkit.util.on('#checkout-switcher', 'shown', initARCheckout);
}