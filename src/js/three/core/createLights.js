// src/js/three/core/createLights.js
// Настройка освещения (cinematic): Ambient + 3 directional + point.

/**
 * Добавляет освещение в сцену.
 * @param {THREE.Scene} scene
 */
export function createLights(scene) {
    scene.add(new THREE.AmbientLight(0xffffff, 1.2));

    const keyLight = new THREE.DirectionalLight(0xffffff, 1.6);
    keyLight.position.set(5, 8, 5);
    scene.add(keyLight);

    const fillLight = new THREE.DirectionalLight(0xffffff, 0.6);
    fillLight.position.set(-4, 3, 2);
    scene.add(fillLight);

    const rimLight = new THREE.DirectionalLight(0xffffff, 1.2);
    rimLight.position.set(0, 5, -6);
    scene.add(rimLight);

    const screenLight = new THREE.PointLight(0x88ccff, 0.25, 5);
    screenLight.position.set(0, 3.2, 2.5);
    scene.add(screenLight);
}
