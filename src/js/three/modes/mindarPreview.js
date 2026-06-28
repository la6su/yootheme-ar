// src/js/three/modes/mindarPreview.js
// Режим AR: MindAR с анкером и ImageTarget.

import * as THREE from 'three';
import { MindARThree } from 'mindar-image-three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { DRACOLoader } from 'three/addons/loaders/DRACOLoader.js';

/**
 * Создает AR-сессию через MindAR.
 * @param {Object} options
 * @param {HTMLElement} options.container
 * @param {string} options.modelUrl
 * @param {Object} options.media
 * @param {Object} options.mindarConfig - параметры MindAR
 */
export async function createMindarPreview(options) {
    const { container, modelUrl, media, mindarConfig } = options;

    const mindarThree = new MindARThree({
        container,
        imageTargetSrc: mindarConfig.target,
        uiLoading: "no",
        uiScanning: "yes",
        filterMinCF: 0.0001,
        filterBeta: 0.001
    });

    const { renderer, scene, camera } = mindarThree;
    const anchor = mindarThree.addAnchor(0);

    // Свет
    scene.add(new THREE.AmbientLight(0xffffff, 1.2));

    const fitPlaneByHeight = (plane, w, h, baseH) => {
        const aspect = w / h;
        plane.scale.y = baseH;
        plane.scale.x = baseH * aspect;
    };

    // CONTENT
    const loadContent = async () => {
        const group = new THREE.Group();
        let texture, w, h;

        // ===== MEDIA =====
        if (media.type === 'video') {
            videoEl = document.getElementById('ar-video');
            await new Promise(resolve => {
                if (videoEl.readyState >= 1) resolve();
                else videoEl.onloadedmetadata = () => resolve();
            });
            texture = new THREE.VideoTexture(videoEl);
            w = videoEl.videoWidth;
            h = videoEl.videoHeight;
        } else {
            const img = document.getElementById('ar-image');
            await new Promise((resolve, reject) => {
                if (img.complete && img.naturalWidth > 0) resolve();
                else {
                    img.onload = () => resolve();
                    img.onerror = () => reject("Image load error");
                }
            });
            texture = new THREE.TextureLoader().load(img.src);
            texture.colorSpace = THREE.SRGBColorSpace;
            w = img.naturalWidth;
            h = img.naturalHeight;
        }

        const aspect = w / h;
        const WIDE_THRESHOLD = 0.15;
        const useBgPlane = Math.abs(aspect - (16 / 9)) > WIDE_THRESHOLD;

        // MODEL
        const loader = new GLTFLoader();
        const draco = new DRACOLoader();
        draco.setDecoderPath('/assets/libs/draco/');
        loader.setDRACOLoader(draco);

        const gltf = await loader.loadAsync(modelUrl);
        const model = gltf.scene;

        if (useBgPlane) {
            bgTexture = await new Promise((resolve, reject) => {
                new THREE.TextureLoader().load(CONFIG.bgplane, tex => resolve(tex), undefined, err => reject(err));
            });
            bgTexture.colorSpace = THREE.SRGBColorSpace;
        }

        model.traverse(c => {
            if (c.isMesh && c.name === 'Screen') {
                screen = c;
                originalMap = screen.material.map;
            }
        });

        const box = new THREE.Box3().setFromObject(screen);
        const size = new THREE.Vector3();
        box.getSize(size);
        const center = new THREE.Vector3();
        box.getCenter(center);

        // CONTENT PLANE
        contentPlane = new THREE.Mesh(
            new THREE.PlaneGeometry(1, 1),
            new THREE.MeshBasicMaterial({
                map: texture,
                transparent: true,
                opacity: 0
            })
        );
        fitPlaneByHeight(contentPlane, w, h, size.y);
        contentPlane.position.copy(center);
        contentPlane.position.z += 0.12;
        contentPlane.visible = false;
        contentBaseScale.copy(contentPlane.scale);
        contentPlane.scale.multiplyScalar(0.92);
        model.add(contentPlane);

        // CRT
        crtMaterial = new THREE.ShaderMaterial({
            uniforms: { uProgress: { value: 0 } },
            vertexShader: `varying vec2 vUv; void main(){ vUv = uv; gl_Position = projectionMatrix * modelViewMatrix * vec4(position,1.0); }`,
            fragmentShader: `precision highp float; varying vec2 vUv; uniform float uProgress; float verticalCollapse(vec2 uv, float t){ uv.y = uv.y * 2.0 - 1.0; uv.y = abs(uv.y); float edge = 1.0 - t; float d = uv.y - edge; float w = fwidth(d); return smoothstep(w, -w, d); } void main(){ float p = clamp(uProgress, 0.0, 1.0); float t = pow(p, 0.35); float mask = verticalCollapse(vUv, t); float flash = exp(-40.0 * abs(p - 0.15)); float finalMask = mask + flash * 0.6; gl_FragColor = vec4(vec3(finalMask), finalMask); }`,
            transparent: true,
            depthWrite: false
        });
        crtPlane = new THREE.Mesh(
            new THREE.PlaneGeometry(1, 1),
            crtMaterial
        );
        fitPlaneByHeight(crtPlane, size.x, size.y, size.y);
        crtPlane.position.copy(center);
        crtPlane.position.z += 0.1;
        crtPlane.visible = false;
        model.add(crtPlane);

        model.scale.set(0.42, 0.42, 0.42);
        model.position.set(0, -1, 0.24);
        group.add(model);
        group.scale.set(0.36, 0.36, 0.36);
        anchor.group.add(group);
    };

    // ANIM
    let crtStarted = false;
    let crtFinished = false;
    let screenSwitched = false;
    let startTime = 0;
    let contentBaseScale = new THREE.Vector3();
    let screen = null;
    let originalMap = null;
    let bgTexture = null;
    let crtMaterial = null;
    let crtPlane = null;
    let contentPlane = null;
    let videoEl = null;

    anchor.onTargetFound = () => {
        if (!crtStarted) {
            crtStarted = true;
            crtFinished = false;
            screenSwitched = false;
            crtPlane.visible = true;
            startTime = performance.now();
        }
        if (videoEl && videoEl.paused) {
            videoEl.play().catch(() => {});
        }
    };

    anchor.onTargetLost = () => {
        if (videoEl) videoEl.pause();
    };

    return {
        scene,
        camera,
        renderer,
        anchor,
        start() {
            renderer.setAnimationLoop((time) => {
                if (crtStarted && !crtFinished) {
                    const t = (time - startTime) / 480;
                    const progress = Math.min(t, 1.0);
                    crtMaterial.uniforms.uProgress.value = progress;
                    if (progress >= 1.0) {
                        crtFinished = true;
                        if (useBgPlane && !screenSwitched && screen && bgTexture) {
                            screen.material.map = bgTexture;
                            screen.material.emissive = new THREE.Color(0xffffff);
                            screen.material.emissiveMap = bgTexture;
                            screen.material.emissiveIntensity = 0.6;
                            screen.material.needsUpdate = true;
                            screenSwitched = true;
                        }
                        crtPlane.visible = false;
                        contentPlane.visible = true;
                    }
                }
                if (crtFinished && contentPlane) {
                    const mat = contentPlane.material;
                    mat.opacity += (1 - mat.opacity) * 0.08;
                    const currentFactor = contentPlane.scale.x / contentBaseScale.x;
                    const newFactor = currentFactor + (1.0 - currentFactor) * 0.08;
                    contentPlane.scale.set(
                        contentBaseScale.x * newFactor,
                        contentBaseScale.y * newFactor,
                        contentBaseScale.z * newFactor
                    );
                }
                renderer.render(scene, camera);
            });
        },
        destroy() {
            renderer.setAnimationLoop(null);
            if (videoEl) videoEl.pause();
        }
    };
}
