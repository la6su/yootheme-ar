export function disposeObject(object) {
    object.traverse((child) => {
        if (!child.isMesh) return;

        child.geometry?.dispose();

        const materials = Array.isArray(child.material) ? child.material : [child.material];
        materials.filter(Boolean).forEach((material) => {
            Object.values(material).forEach((value) => {
                if (value?.isTexture) value.dispose();
            });
            material.dispose();
        });
    });
}
