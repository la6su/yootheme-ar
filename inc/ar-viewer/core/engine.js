import { createStandardPreview } from '../modes/standardPreview.js';
import { createMindarViewer } from '../modes/mindarViewer.js';

/**
 * Single public entry point for both 3D modes.
 */
export async function createEngine(options) {
    if (options.mode === 'mindar') {
        return createMindarViewer(options);
    }

    return createStandardPreview(options);
}
