// core/loadModel.js

import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { DRACOLoader } from 'three/addons/loaders/DRACOLoader.js';

/**
 * Loads the model shared by the checkout preview and MindAR viewer.
 * Keep the complete glTF payload: animations live next to scene, not in scene.
 */
export async function loadModel(url, { dracoPath } = {}) {
    const loader = new GLTFLoader();
    const draco = new DRACOLoader();

    draco.setDecoderPath(dracoPath || '/arjs/three-js/examples/jsm/libs/draco/');
    loader.setDRACOLoader(draco);

    try {
        const gltf = await loader.loadAsync(url);
        const model = gltf.scene;
        let screen = null;

        model.traverse((object) => {
            if (object.isMesh && object.name.toLowerCase().includes('screen')) {
                screen = object;
            }
        });

        if (!screen) {
            throw new Error('The GLB model must contain a mesh named "Screen".');
        }

        const screenSize = new THREE.Box3().setFromObject(screen).getSize(new THREE.Vector3());

        return {
            gltf,
            model,
            screen,
            screenSize,
            animations: gltf.animations || [],
        };
    } finally {
        draco.dispose();
    }
}
