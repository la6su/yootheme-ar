// inc/ar-preview/ar-preview.js

import { createEngine } from "../ar-viewer/core/engine.js";

// ============================
// STATE
// ============================

let engine = null;
let initialized = false;

let resizeHandler = null;
// ============================
// INIT
// ============================

async function initThreePreview() {

    const container = document.getElementById("ar_preview_container");
    if (!container || !window.AR_PREVIEW) return;

    container.innerHTML = "";

    const type = window.AR_PREVIEW.type.includes("video") ? "video" : "image";

    engine = await createEngine({
        container,
        modelUrl: "/assets/gltf/tv-last-transformed.glb",
        media: {
            type,
            url: window.AR_PREVIEW.url
        },
        mode: "preview"
    });

    engine.start();
    resizeHandler = engine.resize;
    window.addEventListener("resize", resizeHandler);
}

// ============================
// DESTROY
// ============================

function destroyThreePreview() {

    if (engine) {
        engine.destroy();
        engine = null;
    }

    window.removeEventListener("resize", engine?.resize);

    const container = document.getElementById("ar_preview_container");
    if (container) container.innerHTML = "";
}

// ============================
// UIKIT EVENTS
// ============================

UIkit.util.on("#ar_modal", "shown", () => {

    setTimeout(async () => {

        if (!initialized) {
            await initThreePreview();
            initialized = true;
        }

        if (engine) engine.resize();

    }, 50);
});

UIkit.util.on("#ar_modal", "hidden", () => {

    destroyThreePreview();
    initialized = false;
});
