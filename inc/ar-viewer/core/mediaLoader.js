// core/mediaLoader.js

import * as THREE from "three";

export async function loadMedia({ type, url, videoEl }) {

    let texture, width, height;

    if (type === "video") {

        let video = videoEl;

        if (!video) {
            video = document.createElement("video");
            video.src = url;
            video.muted = true;
            video.loop = true;
            video.playsInline = true;
        }

        await new Promise(resolve => {
            if (video.readyState >= 1) resolve();
            else video.onloadedmetadata = () => resolve();
        });

        await video.play().catch(() => {});

        texture = new THREE.VideoTexture(video);

        width = video.videoWidth;
        height = video.videoHeight;

        return { texture, width, height, video };

    } else {

        const tex = await new THREE.TextureLoader().loadAsync(url);
        tex.colorSpace = THREE.SRGBColorSpace;

        width = tex.image.width;
        height = tex.image.height;

        return { texture: tex, width, height };
    }
}
