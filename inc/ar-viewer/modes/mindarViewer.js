import * as THREE from 'three';
import { MindARThree } from 'mindar-image-three';

import { createAnimationController } from '../core/animationController.js';
import { disposeObject } from '../core/dispose.js';
import { loadMedia } from '../core/mediaLoader.js';
import { loadModel } from '../core/loadModel.js';
import { applyScreenShader } from '../core/createScreenMaterial.js';

export async function createMindarViewer({
    container,
    modelUrl,
    media,
    mindar,
    assets = {},
    animation = {},
}) {
    if (!mindar?.target) {
        throw new Error('MindAR target is required.');
    }

    const mindarThree = new MindARThree({
        container,
        imageTargetSrc: mindar.target,
        uiLoading: 'no',
        uiScanning: 'yes',
        filterMinCF: 0.0001,
        filterBeta: 0.001,
    });

    const { renderer, scene, camera } = mindarThree;
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    const anchor = mindarThree.addAnchor(0);
    scene.add(new THREE.AmbientLight(0xffffff, 1.2));

    const { model, screen, screenSize, animations } = await loadModel(modelUrl, assets);
    const { texture, width, height, video } = await loadMedia(media);
    const animationController = createAnimationController(model, animations, animation);
    const shader = applyScreenShader(screen.material, {
        texture,
        imageAspect: width / height,
    });
    const screenAspect = screenSize.x / screenSize.y;

    model.scale.set(0.42, 0.42, 0.42);
    model.position.set(0, -1, 0.24);

    const group = new THREE.Group();
    group.scale.set(0.36, 0.36, 0.36);
    group.add(model);
    anchor.group.add(group);

    let running = false;
    let targetFound = false;
    let greetingStarted = false;
    let lastFrameAt = null;
    let revealElapsed = 0;

    function resetGreeting() {
        revealElapsed = 0;
        animationController.playFromStart();

        if (video) {
            video.currentTime = 0;
            video.play().catch(() => {});
        }
    }

    anchor.onTargetFound = () => {
        targetFound = true;
        if (!greetingStarted) {
            greetingStarted = true;
            resetGreeting();
        }
    };

    anchor.onTargetLost = () => {
        targetFound = false;
    };

    function renderFrame(time) {
        const delta = lastFrameAt === null ? 0 : Math.min((time - lastFrameAt) / 1000, 0.1);
        lastFrameAt = time;

        if (greetingStarted) {
            revealElapsed += delta;
            shader.update(revealElapsed, Math.min(revealElapsed / 1.8, 1), screenAspect);
            animationController.update(delta);
        }

        renderer.render(scene, camera);
    }

    return {
        animation: animationController,
        anchor,
        camera,
        renderer,
        scene,
        async start() {
            if (running) return;

            running = true;
            await mindarThree.start();
            renderer.setAnimationLoop(renderFrame);
        },
        async stop() {
            if (!running) return;

            running = false;
            renderer.setAnimationLoop(null);
            animationController.pause();
            video?.pause();
            await mindarThree.stop();
        },
        destroy() {
            renderer.setAnimationLoop(null);
            animationController.destroy();
            video?.pause();
            disposeObject(model);
            texture.dispose();
            renderer.dispose();
        },
    };
}
