import { createMindarViewer } from './modes/mindarViewer.js';

const startButton = document.getElementById('start-btn');
const overlay = document.getElementById('start-overlay');
const spinner = document.getElementById('loading-spinner');
const status = document.getElementById('status-text');
const container = document.getElementById('container');
const config = window.MOSPAL_AR_CONFIG;

let engine = null;

function showError(message) {
    spinner.hidden = true;
    status.hidden = false;
    status.textContent = message;
    startButton.hidden = true;
}

async function prepareViewer() {
    if (!config?.ready || !container) {
        showError('Открытка не найдена или срок её хранения завершён.');
        return;
    }

    engine = await createMindarViewer({
        container,
        modelUrl: config.model,
        media: config.media,
        mindar: { target: config.target },
        assets: config.assets,
        animation: config.animation,
    });

    spinner.hidden = true;
    status.hidden = true;
    startButton.hidden = false;
}

startButton.addEventListener('click', async () => {
    if (!engine) return;

    startButton.disabled = true;
    try {
        await engine.start();
        overlay.classList.add('hidden');
    } catch (error) {
        console.error('MindAR could not be started.', error);
        startButton.disabled = false;
        showError('Не удалось получить доступ к камере. Разрешите доступ и попробуйте ещё раз.');
    }
});

window.addEventListener('pagehide', () => engine?.destroy());

prepareViewer().catch((error) => {
    console.error('AR viewer could not be prepared.', error);
    showError('Не удалось загрузить AR-открытку.');
});
