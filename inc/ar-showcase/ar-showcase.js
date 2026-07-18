import { createStandardPreview } from '../ar-viewer/modes/standardPreview.js';

const engines = [];

function showError(container, message) {
    container.innerHTML = `<p class="mospal-ar-showcase__status uk-text-danger">${message}</p>`;
    container.setAttribute('aria-busy', 'false');
}

async function startShowcase(container) {
    let config;

    try {
        config = JSON.parse(container.dataset.mospalArShowcase || '{}');
    } catch (error) {
        showError(container, 'Не удалось прочитать настройки 3D-превью.');
        return;
    }

    if (!config.model || !config.media?.url) {
        showError(container, 'Укажите видео в параметре video у элемента [mospal_ar_preview].');
        return;
    }

    try {
        const engine = await createStandardPreview({
            container,
            modelUrl: config.model,
            media: config.media,
            assets: config.assets,
            animation: config.animation,
        });

        container.replaceChildren();
        container.setAttribute('aria-busy', 'false');
        engine.start();
        engines.push(engine);

        if ('ResizeObserver' in window) {
            new ResizeObserver(() => engine.resize()).observe(container);
        }
    } catch (error) {
        console.error('Mospal AR showcase could not start.', error);
        showError(container, 'Не удалось загрузить 3D-превью. Проверьте видео и модель.');
    }
}

function initShowcases() {
    const containers = document.querySelectorAll('[data-mospal-ar-showcase]');

    if (!('IntersectionObserver' in window)) {
        containers.forEach(startShowcase);
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;

            observer.unobserve(entry.target);
            startShowcase(entry.target);
        });
    }, { rootMargin: '180px 0px' });

    containers.forEach((container) => observer.observe(container));
}

initShowcases();

window.addEventListener('pagehide', () => {
    engines.forEach((engine) => engine.destroy());
});
