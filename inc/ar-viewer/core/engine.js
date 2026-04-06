// inc/ar-viewer/core/engine.js

import * as THREE from "three";
import { OrbitControls } from "three/addons/controls/OrbitControls.js";
import { RGBELoader } from "three/addons/loaders/RGBELoader.js";

import { loadModel } from "./loadModel.js";
import { loadMedia } from "./mediaLoader.js";
import { applyScreenShader } from "./createScreenMaterial.js";

export async function createEngine({
                                       container,
                                       modelUrl,
                                       media,
                                       mode = "preview",
                                       externalRenderer = null,
                                       externalCamera = null,
                                   }) {

    // ============================
    // RENDERER / SCENE / CAMERA
    // ============================

    let renderer = externalRenderer;
    let camera = externalCamera;

    const scene = new THREE.Scene();

    let controls = null;

    if (!renderer) {
        renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });

        const rect = container.getBoundingClientRect();
        renderer.setSize(rect.width, rect.height);
        renderer.setPixelRatio(window.devicePixelRatio);

        container.appendChild(renderer.domElement);
    }

    if (!camera) {
        const rect = container.getBoundingClientRect();
        camera = new THREE.PerspectiveCamera(45, rect.width / rect.height, 0.1, 100);
        camera.position.set(-2, 2.5, 5);

        // 🎯 OrbitControls (ограниченные)
        controls = new OrbitControls(camera, renderer.domElement);

        controls.target.set(0, 1.5, 0);

        // ограничение углов (~30°)
        controls.minAzimuthAngle = -Math.PI / 6;
        controls.maxAzimuthAngle = Math.PI / 6;

        controls.minPolarAngle = Math.PI / 3;
        controls.maxPolarAngle = Math.PI / 2.2;

        controls.enablePan = false;
        controls.enableZoom = false;

        controls.update();
    }

    // ============================
    // LIGHT (cinematic)
    // ============================

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

    // ============================
    // HDR
    // ============================

    try {
        const hdr = await new RGBELoader()
            .setPath('/assets/textures/equirectangular/')
            .loadAsync('venice_sunset_1k.hdr');

        hdr.mapping = THREE.EquirectangularReflectionMapping;
        scene.environment = hdr;
    } catch (e) {
        console.warn("HDR load failed", e);
    }

    // ============================
    // LOAD MODEL
    // ============================

    const { model, screen, screenSize } = await loadModel(modelUrl);

    model.scale.set(0.35, 0.35, 0.35);
    model.position.set(0, 0.5, 0);

    scene.add(model);

    // ============================
    // LOAD MEDIA
    // ============================

    const { texture, width, height, video } = await loadMedia(media);

    const imageAspect = width / height;
    const screenAspect = screenSize.x / screenSize.y;

    // ============================
    // APPLY SHADER
    // ============================

    const shaderController = applyScreenShader(screen.material, {
        texture,
        imageAspect
    });

    // ============================
    // ANIMATION
    // ============================

    let time = 0;
    const duration = 1.8;

    function update(delta) {

        time += delta;

        const progress = Math.min(time / duration, 1);

        shaderController.update(time, progress, screenAspect);

        if (controls) controls.update();
    }

    // ============================
    // RESIZE
    // ============================

    function resize() {
        if (!container || !renderer || !camera) return;

        const rect = container.getBoundingClientRect();
        if (rect.width === 0 || rect.height === 0) return;

        camera.aspect = rect.width / rect.height;
        camera.updateProjectionMatrix();
        renderer.setSize(rect.width, rect.height);
    }

    // ============================
    // LOOP
    // ============================

    let animationId = null;

    function start() {

        if (externalRenderer) {
            // MindAR loop
            externalRenderer.setAnimationLoop((t) => {
                update(0.016);
                externalRenderer.render(scene, camera);
            });
        } else {
            const clock = new THREE.Clock();

            const loop = () => {
                animationId = requestAnimationFrame(loop);

                const delta = clock.getDelta();
                update(delta);

                renderer.render(scene, camera);
            };

            loop();
        }
    }

    function stop() {
        if (animationId) cancelAnimationFrame(animationId);
        if (externalRenderer) externalRenderer.setAnimationLoop(null);
    }

    function destroy() {
        stop();

        if (video) video.pause();

        if (!externalRenderer && renderer) {
            renderer.dispose();
            renderer.domElement.remove();
        }
    }

    // ============================
    // API
    // ============================

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
