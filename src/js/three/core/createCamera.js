// src/js/three/core/createCamera.js
// Конфигурация камеры Perspective.

/**
 * Создаёт PerspectiveCamera.
 * @param {number} [width]
 * @param {number} [height]
 * @param {THREE.PerspectiveCamera} [externalCamera]
 */
export function createCamera(width = 800, height = 600, externalCamera = null) {
    if (externalCamera) return externalCamera;

    const aspect = width / height;
    const camera = new THREE.PerspectiveCamera(45, aspect, 0.1, 100);
    camera.position.set(-2, 2.5, 5);

    return camera;
}
