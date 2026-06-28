// src/js/three/core/createLoop.js
// Запускает/останавливает анимационный цикл.

/**
 * Запускает render loop.
 * @param {function} renderFn - (time) => void
 * @param {function} [stopFn] - (time) => void для очистки
 * @returns {Object} {start, stop}
 */
export function createLoop() {
    let rafId = null;
    let animFn = null;

    function tick(time) {
        if (animFn) animFn(time);
        rafId = requestAnimationFrame(tick);
    }

    return {
        start(onFrame) {
            animFn = onFrame;
            rafId = requestAnimationFrame(tick);
        },
        stop() {
            if (rafId) {
                cancelAnimationFrame(rafId);
                rafId = null;
            }
            animFn = null;
        }
    };
}
