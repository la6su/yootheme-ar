<?php
/**
 * Template Name: AR-card Template
 */

defined('ABSPATH') || exit;

$ar_config = mospal_greeting_get_viewer_config();
$ar_base = '/arjs';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>AR-поздравление</title>
    <style>
        body { margin: 0; overflow: hidden; background: #000; font-family: sans-serif; }
        #container { position: relative; width: 100vw; height: 100vh; overflow: hidden; }
        #start-overlay {
            position: absolute; inset: 0; z-index: 10; display: flex; flex-direction: column;
            align-items: center; justify-content: center; padding: 24px; background: rgba(0, 0, 0, .86);
            color: #fff; text-align: center; transition: opacity .35s ease;
        }
        #start-overlay.hidden { opacity: 0; pointer-events: none; }
        .start-btn {
            padding: 15px 40px; border: 0; border-radius: 30px; background: linear-gradient(45deg, #6268cc, #9f79ff);
            color: #fff; font-size: 18px; font-weight: 700; cursor: pointer; text-transform: uppercase; letter-spacing: 1px;
        }
        .loader { width: 40px; height: 40px; margin-bottom: 20px; border: 4px solid #fff; border-bottom-color: transparent; border-radius: 50%; animation: rotation 1s linear infinite; }
        @keyframes rotation { to { transform: rotate(360deg); } }
    </style>
    <script async src="<?php echo esc_url($ar_base . '/es-module-shims.js'); ?>"></script>
    <script type="importmap">
        <?php echo wp_json_encode([
            'imports' => [
                'three' => $ar_base . '/three-js/three.module.min.js',
                'three/addons/' => $ar_base . '/three-js/examples/jsm/',
                'mindar-image-three' => $ar_base . '/mind-ar/mindar-image-three.prod.js',
            ],
        ], JSON_UNESCAPED_SLASHES); ?>
    </script>
</head>
<body>
    <div id="start-overlay">
        <div id="loading-spinner" class="loader"></div>
        <p id="status-text">Загрузка открытки…</p>
        <button id="start-btn" class="start-btn" type="button" hidden>Открыть открытку</button>
    </div>
    <div id="container"></div>
    <script>
        window.MOSPAL_AR_CONFIG = <?php echo wp_json_encode(
            $ar_config,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
        ); ?>;
    </script>
    <script type="module" src="<?php echo esc_url(get_stylesheet_directory_uri() . '/inc/ar-viewer/ar-viewer.js'); ?>"></script>
</body>
</html>
