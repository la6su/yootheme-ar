import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { RGBELoader } from 'three/addons/loaders/RGBELoader.js';

import { createAnimationController } from '../core/animationController.js';
import { disposeObject } from '../core/dispose.js';
import { loadMedia } from '../core/mediaLoader.js';
import { loadModel } from '../core/loadModel.js';
import { applyScreenShader } from '../core/createScreenMaterial.js';

function addPreviewLights(scene) {
    scene.add(new THREE.HemisphereLight(0xffffff, 0x444444, 0.6));

    const key = new THREE.DirectionalLight(0xffffff, 1.6);
    key.position.set(5, 8, 5);
    scene.add(key);

    const fill = new THREE.DirectionalLight(0xffffff, 0.6);
    fill.position.set(-4, 3, 2);
    scene.add(fill);

    const rim = new THREE.DirectionalLight(0xffffff, 1.2);
    rim.position.set(0, 5, -6);
    scene.add(rim);
}

export async function createStandardPreview({
    container,
    modelUrl,
    media,
    assets = {},
    animation = {},
}) {
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(45, 16 / 9, 0.1, 100);
    camera.position.set(-2, 2.5, 5);

    const controls = new OrbitControls(camera, renderer.domElement);
    controls.target.set(0, 1.5, 0);
    controls.minAzimuthAngle = -Math.PI / 6;
    controls.maxAzimuthAngle = Math.PI / 6;
    controls.minPolarAngle = Math.PI / 3;
    controls.maxPolarAngle = Math.PI / 2.2;
    controls.enablePan = false;
    controls.enableZoom = false;
    controls.update();

    addPreviewLights(scene);

    if (assets.hdrPath) {
        try {
            const hdr = await new RGBELoader().loadAsync(assets.hdrPath);
            hdr.mapping = THREE.EquirectangularReflectionMapping;
            scene.environment = hdr;
        } catch (error) {
            console.warn('AR preview HDR could not be loaded.', error);
        }
    }

    const { model, screen, screenSize, animations } = await loadModel(modelUrl, assets);
    const { texture, width, height, video } = await loadMedia(media);
    const animationController = createAnimationController(model, animations, animation);

    model.scale.set(0.35, 0.35, 0.35);
    model.position.set(0, 0.5, 0);
    scene.add(model);

    const shader = applyScreenShader(screen.material, {
        texture,
        imageAspect: width / height,
    });

    const screenAspect = screenSize.x / screenSize.y;
    const clock = new THREE.Clock(false);
    let elapsed = 0;
    let frameId = null;
    let running = false;

    function resize() {
        const { width: containerWidth, height: containerHeight } = container.getBoundingClientRect();
        if (!containerWidth || !containerHeight) return;

        camera.aspect = containerWidth / containerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(containerWidth, containerHeight, false);
    }

    function renderFrame() {
        if (!running) return;

        frameId = requestAnimationFrame(renderFrame);
        const delta = Math.min(clock.getDelta(), 0.1);
        elapsed += delta;

        shader.update(elapsed, Math.min(elapsed / 1.8, 1), screenAspect);
        animationController.update(delta);
        controls.update();
        renderer.render(scene, camera);
    }

    function stop() {
        running = false;
        if (frameId) cancelAnimationFrame(frameId);
        frameId = null;
        animationController.pause();
        video?.pause();
    }

    return {
        animation: animationController,
        camera,
        renderer,
        scene,
        start() {
            if (running) return;

            running = true;
            elapsed = 0;
            resize();
            container.appendChild(renderer.domElement);
            animationController.playFromStart();
            video?.play().catch(() => {});
            clock.start();
            renderFrame();
        },
        stop,
        resize,
        destroy() {
            stop();
            animationController.destroy();
            controls.dispose();
            disposeObject(model);
            texture.dispose();
            renderer.dispose();
            renderer.domElement.remove();
        },
    };
}
