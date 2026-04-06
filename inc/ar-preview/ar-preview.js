import * as THREE from "three";
import { GLTFLoader } from "three/addons/loaders/GLTFLoader.js";
import { DRACOLoader } from "three/addons/loaders/DRACOLoader.js";

// ============================
// GLOBALS
// ============================

let renderer, scene, camera, model, videoEl, animationId;

function getContainerSize(container) {
    const rect = container.getBoundingClientRect();
    return {
        width: rect.width,
        height: rect.height,
    };
}

// ============================
// INIT
// ============================
async function initThreePreview() {
    const container = document.getElementById("ar_preview_container");
    if (!container || !window.AR_PREVIEW) return;
    container.innerHTML = "";

    // SCENE
    scene = new THREE.Scene();
    scene.background = null;
    const { width, height } = getContainerSize(container);
    camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 100);
    camera.position.set(0, 0.5, 3);
    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setSize(width, height);
    renderer.setPixelRatio(window.devicePixelRatio);
    container.appendChild(renderer.domElement);

    // LIGHT
    scene.add(new THREE.AmbientLight(0xffffff, 0.7));
    const light1 = new THREE.DirectionalLight(0xffffff, 1);
    light1.position.set(3, 3, 3);
    scene.add(light1);
    const light2 = new THREE.DirectionalLight(0xffffff, 0.5);
    light2.position.set(-2, 2, 1);
    scene.add(light2);

    // LOAD MODEL
    const loader = new GLTFLoader();
    const draco = new DRACOLoader();
    draco.setDecoderPath("/arjs/three-js/examples/jsm/libs/draco/");
    loader.setDRACOLoader(draco);
    
    const gltf = await loader.loadAsync("/arjs/gltf/tv.glb");
    
    model = gltf.scene;
    model.scale.set(0.35, 0.35, 0.35);
    model.position.set(0, -0.35, 0);

    // SCREEN
    let screen = null;
    
    model.traverse((o) => {
        if (o.isMesh && o.name.toLowerCase().includes("screen")) {
            screen = o;
        }
    });


    // TEXTURE
    let texture;
    
    if (window.AR_PREVIEW.type.includes("video")) {
        videoEl = document.createElement("video");
        videoEl.src = window.AR_PREVIEW.url;
        videoEl.muted = true;
        videoEl.loop = true;
        videoEl.playsInline = true;
        await videoEl.play().catch(() => {});
        texture = new THREE.VideoTexture(videoEl);
        
    } else {
        texture = new THREE.TextureLoader().load(window.AR_PREVIEW.url);
    }
    
    texture.flipY = false;
    
    if (screen) {
        screen.material.map = texture;
        screen.material.emissive = new THREE.Color(0xffffff);
        screen.material.emissiveMap = texture;
        screen.material.emissiveIntensity = 1;
        screen.material.needsUpdate = true;
    }

    // ADD
    scene.add(model);

    // START RENDER
    animate();
}

function onResize() {
    const container = document.getElementById("ar_preview_container");
    if (!container || !renderer || !camera) return;
    const rect = container.getBoundingClientRect();
    if (rect.width === 0 || rect.height === 0) return;
    camera.aspect = rect.width / rect.height;
    camera.updateProjectionMatrix();
    renderer.setSize(rect.width, rect.height);
}

// ============================
// ANIMATE
// ============================

function animate() {
    animationId = requestAnimationFrame(animate);
    // if (model) {
    //     // лёгкое вращение (приятный UX)
    //     model.rotation.y += 0.005;
    // }
    renderer.render(scene, camera);
}

// ============================
// DESTROY
// ============================

function destroyThreePreview() {
    cancelAnimationFrame(animationId);
    if (renderer) {
        renderer.dispose();
        renderer.domElement.remove();
        renderer = null;
    }
    if (videoEl) {
        videoEl.pause();
        videoEl = null;
    }
    const container = document.getElementById("ar_preview_container");
    if (container) container.innerHTML = "";
}

// ============================
// UIKIT EVENTS
// ============================

let threeInitialized = false;

UIkit.util.on("#ar_modal", "shown", () => {
    // важно: сначала resize listener
    window.addEventListener("resize", onResize);
    // ждём пока модалка реально появится
    setTimeout(() => {
        if (!threeInitialized) {
            initThreePreview();
            threeInitialized = true;
        }
        onResize(); // гарантируем корректный размер
    }, 50);
});

UIkit.util.on("#ar_modal", "hidden", () => {
    destroyThreePreview();
    threeInitialized = false;
    window.removeEventListener("resize", onResize);
});
