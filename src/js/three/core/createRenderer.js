// src/js/three/core/createRenderer.js
// Создаёт WebGLRenderer. Принимает container и внешние опции.

/**
 * Создаёт WebGLRenderer (или возвращает переданный).
 * @param {HTMLElement} container
 * @param {Object} [options]
 * @param {THREE.WebGLRenderer} [externalRenderer]
 */
export function createRenderer(container, options = {}) {
    const { externalRenderer = null } = options;

    if (externalRenderer) {
        return externalRenderer;
    }

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });

    const rect = container.getBoundingClientRect();
    renderer.setSize(rect.width, rect.height);
    renderer.setPixelRatio(window.devicePixelRatio);
    container.appendChild(renderer.domElement);

    return renderer;
}
