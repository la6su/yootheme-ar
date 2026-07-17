import { createStandardPreview } from '../ar-viewer/modes/standardPreview.js';

let engine = null;
let resizeHandler = null;

function setPreviewError(message) {
    const container = document.getElementById('ar_preview_container');
    if (container) {
        container.innerHTML = `<p class="uk-text-danger uk-text-center uk-padding">${message}</p>`;
    }
}

async function initThreePreview() {
    const container = document.getElementById('ar_preview_container');
    const config = window.MOSPAL_AR_CONFIG;
    const preview = window.AR_PREVIEW;

    if (!container || !config || !preview) return;

    container.innerHTML = '';
    engine = await createStandardPreview({
        container,
        modelUrl: config.model,
        media: {
            type: preview.type.includes('video') ? 'video' : 'image',
            url: preview.url,
        },
        assets: config.assets,
        animation: config.animation,
    });

    engine.start();
    resizeHandler = engine.resize;
    window.addEventListener('resize', resizeHandler);
}

function destroyThreePreview() {
    window.removeEventListener('resize', resizeHandler);
    resizeHandler = null;

    if (engine) {
        engine.destroy();
        engine = null;
    }

    const container = document.getElementById('ar_preview_container');
    if (container) container.innerHTML = '';
}

if (window.UIkit) {
    UIkit.util.on('#ar_modal', 'shown', () => {
        window.setTimeout(async () => {
            if (engine) {
                engine.resize();
                return;
            }

            try {
                await initThreePreview();
            } catch (error) {
                console.error('AR preview could not be started.', error);
                setPreviewError('Не удалось открыть 3D-предпросмотр. Попробуйте ещё раз.');
            }
        }, 50);
    });

    UIkit.util.on('#ar_modal', 'hidden', destroyThreePreview);
}
