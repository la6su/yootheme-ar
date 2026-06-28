// src/js/three/core/createMaterials.js
// Создание и управление материалами (шейдерами).

/**
 * Применяет shader к материалу.
 * @param {THREE.MeshStandardMaterial} material
 * @param {Object} options
 * @param {THREE.Texture} options.texture
 * @param {number} options.imageAspect
 */
export function applyScreenShader(material, { texture, imageAspect }) {
    let shaderRef = null;

    material.onBeforeCompile = (shader) => {
        shader.uniforms.uOverlay = { value: texture };
        shader.uniforms.uProgress = { value: 0 };
        shader.uniforms.uTime = { value: 0 };
        shader.uniforms.uImageAspect = { value: imageAspect };
        shader.uniforms.uScreenAspect = { value: 1 };

        shader.vertexShader = `varying vec2 vUvCustom;\n` + shader.vertexShader;

        shader.vertexShader = shader.vertexShader.replace(
            '#include <uv_vertex>',
            `#include <uv_vertex>\nvUvCustom = uv;`
        );

        shader.fragmentShader = `
            uniform sampler2D uOverlay;
            uniform float uProgress;
            uniform float uTime;
            uniform float uImageAspect;
            uniform float uScreenAspect;
            varying vec2 vUvCustom;
        ` + shader.fragmentShader;

        shader.fragmentShader = shader.fragmentShader.replace(
            '#include <emissivemap_fragment>',
            `
                #include <emissivemap_fragment>

                vec2 uv = vUvCustom;
                float p = clamp(uProgress, 0.0, 1.0);

                // чуть мягче, чтобы дать времени глазу
                p = pow(p, 0.8);

                vec2 center = uv - 0.5;

                // =========================
                // BALANCED PHASES
                // =========================
                float p_point  = smoothstep(0.0, 0.08, p);
                float p_line   = smoothstep(0.06, 0.22, p);
                float p_expand = smoothstep(0.15, 0.40, p);
                float p_fade   = smoothstep(0.30, 0.55, p);
                float p_reveal = smoothstep(0.35, 0.65, p);

                // =========================
                // CRT BEAM
                // =========================

                // точка
                float point = exp(-2400.0 * dot(center, center));

                // линия (чуть шире, чтобы читалась)
                float line = exp(-3600.0 * center.y * center.y);

                // точка -> линия
                float beam = mix(point, line, p_line);

                // =========================
                // EXPAND (экран)
                // =========================

                float radius = mix(0.0, 1.25, p_expand);
                float dist = length(center / vec2(1.0, 0.65));

                float mask = smoothstep(radius, radius - 0.18, dist);

                // CRT энергия
                float crt = beam * mask;

                // 👉 ВАЖНО: НЕ гасим сразу
                crt *= (1.0 - p_fade);

                // =========================
                // FLASH (усиливает старт)
                // =========================
                float flash = exp(-80.0 * abs(p - 0.06));
                crt += flash * 1.5;

                // =========================
                // CONTENT
                // =========================

                // scale
                float scale = mix(0.82, 1.0, p_reveal);
                vec2 scaledUv = center / scale + 0.5;

                // aspect fit
                float fit = step(uImageAspect, uScreenAspect);

                float sx = mix(1.0, uScreenAspect / uImageAspect, fit);
                float sy = mix(uImageAspect / uScreenAspect, 1.0, fit);

                vec2 texUv = (scaledUv - 0.5) * vec2(sx, sy) + 0.5;
                // flip Y
                texUv.y = 1.0 - texUv.y;
                vec2 clampedUv = clamp(texUv, 0.0, 1.0);

                float inside =
                    step(0.0, texUv.x) *
                    step(texUv.x, 1.0) *
                    step(0.0, texUv.y) *
                    step(texUv.y, 1.0);

                vec3 overlay = texture2D(uOverlay, clampedUv).rgb;

                // fade-in контента
                overlay *= p_reveal;

                // =========================
                // FINAL
                // =========================

                vec3 finalColor = vec3(crt) + overlay * inside;

                // scanlines только когда контент уже есть
                float scan = 0.98 + 0.02 * sin(uv.y * 280.0 + uTime * 10.0);
                finalColor *= mix(1.0, scan, p_reveal);

                totalEmissiveRadiance += finalColor;
            `
        );

        shaderRef = shader;
    };

    return {
        update: (time, progress, screenAspect) => {
            if (!shaderRef) return;
            shaderRef.uniforms.uTime.value = time;
            shaderRef.uniforms.uProgress.value = progress;
            shaderRef.uniforms.uScreenAspect.value = screenAspect;
        }
    };
}
