// src/js/three/core/createLifecycle.js
// Управление жизненным циклом: resize + dispose.

/**
 * Подписывается на resize окна и обновляет renderer + camera.
 * @param {THREE.WebGLRenderer} renderer
 * @param {THREE.Camera} camera
 * @param {HTMLElement} container
 * @param {function} [onResize] - внешний обработчик resize
 */
export function onResize(renderer, camera, container, onResize) {
    const update = () => {
        const { width, height } = container.getBoundingClientRect();
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        renderer.setSize(width, height);
        if (onResize) onResize(width, height);
    };
    window.addEventListener('resize', update);
    update();
}

/**
 * Освобождает все ресурсы Three.js.
 * @param {THREE.Scene} scene
 * @param {THREE.WebGLRenderer} renderer
 * @param {Array} [extras] - дополнительные объекты для dispose
 */
export function dispose(scene, renderer, extras = []) {
    if (scene) scene.clear();
    if (renderer) {
        renderer.dispose();
        if (renderer.domElement.parentNode) {
            renderer.domElement.parentNode.removeChild(renderer.domElement);
        }
    }
    for (const ex of extras) {
        if (ex && typeof ex.dispose === 'function') ex.dispose();
    }
}
