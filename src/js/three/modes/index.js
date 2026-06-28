// src/js/three/modes/index.js
// Фабрика режимов: standardPreview | mindarPreview

import { createStandardPreview } from './standardPreview.js';
import { createMindarPreview } from './mindarPreview.js';

/**
 * Выбирает нужный режим и возвращит engine API.
 * @param {Object} options
 */
export async function createEngine(options) {
    if (options.mode === 'mindar') {
        return createMindarPreview(options);
    }
    return createStandardPreview(options);
}
