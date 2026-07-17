// core/mediaLoader.js

import * as THREE from 'three';

function waitForVideoMetadata(video) {
    return new Promise((resolve, reject) => {
        if (video.readyState >= HTMLMediaElement.HAVE_METADATA) {
            resolve();
            return;
        }

        const onLoaded = () => {
            cleanup();
            resolve();
        };
        const onError = () => {
            cleanup();
            reject(new Error('The greeting video could not be loaded.'));
        };
        const cleanup = () => {
            video.removeEventListener('loadedmetadata', onLoaded);
            video.removeEventListener('error', onError);
        };

        video.addEventListener('loadedmetadata', onLoaded, { once: true });
        video.addEventListener('error', onError, { once: true });
    });
}

export async function loadMedia({ type, url, videoEl = null }) {
    if (type === 'video') {
        const video = videoEl || document.createElement('video');

        video.crossOrigin = 'anonymous';
        video.muted = true;
        video.loop = true;
        video.playsInline = true;
        video.preload = 'metadata';

        if (!video.src) {
            video.src = url;
        }

        await waitForVideoMetadata(video);

        const texture = new THREE.VideoTexture(video);
        texture.colorSpace = THREE.SRGBColorSpace;

        return {
            texture,
            width: video.videoWidth,
            height: video.videoHeight,
            video,
        };
    }

    const loader = new THREE.TextureLoader();
    loader.setCrossOrigin('anonymous');
    const texture = await loader.loadAsync(url);
    texture.colorSpace = THREE.SRGBColorSpace;

    return {
        texture,
        width: texture.image.width,
        height: texture.image.height,
        video: null,
    };
}
