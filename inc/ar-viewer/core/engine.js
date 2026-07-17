/**
 * Single public entry point for both 3D modes.
 *
 * Modes are loaded lazily so the checkout preview never downloads or resolves
 * the MindAR dependency. Direct entry points may import a mode themselves.
 */
export async function createEngine(options) {
    if (options.mode === 'mindar') {
        const { createMindarViewer } = await import('../modes/mindarViewer.js');
        return createMindarViewer(options);
    }

    const { createStandardPreview } = await import('../modes/standardPreview.js');
    return createStandardPreview(options);
}
