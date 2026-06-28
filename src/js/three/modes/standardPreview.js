// src/js/three/modes/standardPreview.js
// Режим 3D-превью: OrbitControls, HDR, модель + шейдер.

import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { RGBELoader } from 'three/addons/loaders/RGBELoader.js';

import { createModel } from '../core/createModel.js';
import { loadMedia } from '../core/mediaLoader.js';
import { applyScreenShader } from '../core/createMaterials.js';

/**
 * Создает стандартный 3D-превью.
 * @param {Object} options
 * @param {HTMLElement} options.container
 * @param {string} options.modelUrl
 * @param {Object} options.media
 */
export async function createStandardPreview(options) {
    const { container, modelUrl, media } = options;

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    const camera = new THREE.PerspectiveCamera(45, container.clientWidth / container.clientHeight, 0.1, 100);
    camera.position.set(-2, 2.5, 5);

    const scene = new THREE.Scene();

    // OrbitControls
    const controls = new OrbitControls(camera, renderer.domElement);
    controls.target.set(0, 1.5, 0);
    controls.minAzimuthAngle = -Math.PI / 6;
    controls.maxAzimuthAngle = Math.PI / 6;
    controls.minPolarAngle = Math.PI / 3;
    controls.maxPolarAngle = Math.PI / 2.2;
    controls.enablePan = false;
    controls.enableZoom = false;

    // Свет (cinematic)
    scene.add(new THREE.HemisphereLight(0xffffff, 0x444444, 0.6));
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

    // HDR
    try {
        const hdr = await new RGBELoader()
            .setPath('/assets/textures/equirectangular/')
            .loadAsync('venice_sunset_1k.hdr');
        hdr.mapping = THREE.EquirectangularReflectionMapping;
        scene.environment = hdr;
    } catch (e) {
        console.warn("HDR load failed", e);
    }

    // Модель
    const { model, screen, screenSize } = await createModel(modelUrl);
    model.scale.set(0.35, 0.35, 0.35);
    model.position.set(0, 0.5, 0);
    scene.add(model);

    // Медиа
    const { texture, width, height } = await loadMedia(media);
    const imageAspect = width / height;
    const screenAspect = screenSize.x / screenSize.y;

    // Шейдер
    const shaderController = applyScreenShader(screen.material, {
        texture,
        imageAspect
    });

    // Цикл
    let time = 0;
    const duration = 1.8;
    let animId = null;

    function tick() {
        animId = requestAnimationFrame(tick);
        const delta = 0.016;
        time += delta;
        const progress = Math.min(time / duration, 1);
        shaderController.update(time, progress, screenAspect);
        controls.update();
        renderer.render(scene, camera);
    }

    function start() {
        container.appendChild(renderer.domElement);
        tick();
    }

    function stop() {
        if (animId) cancelAnimationFrame(animId);
    }

    function destroy() {
        stop();
        if (media.video) media.video.pause();
        renderer.dispose();
        renderer.domElement.remove();
    }

    function resize() {
        const rect = container.getBoundingClientRect();
        camera.aspect = rect.width / rect.height;
        camera.updateProjectionMatrix();
        renderer.setSize(rect.width, rect.height);
    }

    return {
        scene,
        camera,
        renderer,
        start,
        stop,
        destroy,
        resize
    };
}
