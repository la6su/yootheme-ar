// core/loadModel.js

import * as THREE from "three";
import { GLTFLoader } from "three/addons/loaders/GLTFLoader.js";
import { DRACOLoader } from "three/addons/loaders/DRACOLoader.js";

export async function loadModel(url) {

    const loader = new GLTFLoader();
    const draco = new DRACOLoader();
    draco.setDecoderPath('/assets/libs/draco/');
    loader.setDRACOLoader(draco);

    const gltf = await loader.loadAsync(url);

    const model = gltf.scene;

    let screen = null;

    model.traverse(o => {
        if (o.isMesh && o.name.toLowerCase().includes("screen")) {
            screen = o;
        }
    });

    if (!screen) throw new Error("Screen mesh not found");

    const box = new THREE.Box3().setFromObject(screen);
    const size = new THREE.Vector3();
    box.getSize(size);

    return { model, screen, screenSize: size };
}
