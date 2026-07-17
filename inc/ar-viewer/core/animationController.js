import * as THREE from 'three';

/**
 * A small wrapper around AnimationMixer used by both AR modes.
 * When clipName is not set, the first clip from the GLB is used.
 */
export function createAnimationController(model, clips = [], {
    clipName = null,
    loop = 'once',
} = {}) {
    const clip = clipName
        ? clips.find((candidate) => candidate.name === clipName)
        : clips[0];

    if (!clip) {
        return {
            available: false,
            clipNames: clips.map((candidate) => candidate.name),
            playFromStart() {},
            pause() {},
            update() {},
            destroy() {},
        };
    }

    const mixer = new THREE.AnimationMixer(model);
    const action = mixer.clipAction(clip);
    const shouldRepeat = loop === 'repeat';

    action.setLoop(shouldRepeat ? THREE.LoopRepeat : THREE.LoopOnce, shouldRepeat ? Infinity : 1);
    action.clampWhenFinished = !shouldRepeat;

    return {
        available: true,
        clipName: clip.name,
        clipNames: clips.map((candidate) => candidate.name),
        playFromStart() {
            action.reset();
            action.paused = false;
            action.enabled = true;
            action.play();
        },
        pause() {
            action.paused = true;
        },
        update(delta) {
            mixer.update(delta);
        },
        destroy() {
            mixer.stopAllAction();
            mixer.uncacheRoot(model);
        },
    };
}
