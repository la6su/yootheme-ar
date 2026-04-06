<?php 
/* Template Name: ar-card Template */ 

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
// Путь к корню сайта (чтобы importmap работал от корня)

// Базовый путь к папке arjs
$ar_base = '/arjs'; 

$ar_config = [
    'ready'  => false,
    'type'   => 'image',
    'src'    => '',
    // Модели и таргеты берем относительно корня
    'model'  => $ar_base . '/gltf/tv.glb', 
    'bgplane'  => $ar_base . '/bg-plane.jpg',
    'target' => $ar_base . '/card-target.mind', 
    // Пути для importmap
    'paths'  => [
        'three_module' => $ar_base . '/three-js/three.module.min.js',
        'three_addons' => $ar_base . '/three-js/examples/jsm/', 
        'mindar'       => $ar_base . '/mind-ar/mindar-image-three.prod.js',
        'draco'        => $ar_base . '/three-js/examples/jsm/libs/draco/'
    ]
];

if ($order_id > 0) {

    $is_active = get_field('type_card', $order_id);

    if ($is_active) {

        $type   = get_field('type', $order_id) ?: 'image';

        $file_url = ($type === 'video')
            ? get_field('video_loader', $order_id)
            : get_field('image_loader', $order_id);

        if ($file_url) {
            $ar_config['ready']  = true;
            $ar_config['type']   = $type;
            $ar_config['src']    = $file_url;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>AR Поздравление</title>
    <style>
        body { margin: 0; overflow: hidden; background: #000; font-family: sans-serif; }
        #start-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); z-index: 999;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            transition: opacity 0.5s;
        }
        #start-overlay.hidden { opacity: 0; pointer-events: none; }
        .start-btn {
            padding: 15px 40px; border-radius: 30px; border: none;
            background: linear-gradient(45deg, #6268cc, #9f79ff);
            color: white; font-size: 18px; font-weight: bold;
            cursor: pointer; box-shadow: 0 4px 15px rgba(98, 104, 204, 0.4);
            text-transform: uppercase; letter-spacing: 1px;
        }
        .loader {
            margin-bottom: 20px; width: 40px; height: 40px;
            border: 4px solid #fff; border-bottom-color: transparent;
            border-radius: 50%; animation: rotation 1s linear infinite;
        }
        @keyframes rotation { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        #media-storage { display: none; }
    </style>

    <!-- Import Maps -->
    <script async src="/arjs/es-module-shims.js"></script>
    <script type="importmap">
    {
        "imports": {
            "three": "<?php echo $ar_config['paths']['three_module']; ?>",
            "three/addons/": "<?php echo $ar_config['paths']['three_addons']; ?>", 
            "mindar-image-three": "<?php echo $ar_config['paths']['mindar']; ?>"
        }
    }
    </script>
</head>

<body>

    <!-- UI Overlay -->
    <div id="start-overlay">
        <div id="loading-spinner" class="loader"></div>
        <div id="status-text" style="color:white; margin-bottom:20px;">Загрузка...</div>
        <button id="start-btn" class="start-btn" style="display:none;">Открыть открытку</button>
    </div>

    <!-- Media Container -->
    <div id="media-storage">
        <?php if($ar_config['type'] === 'image'): ?>
            <img id="ar-image" src="<?php echo $ar_config['src']; ?>" crossorigin="anonymous">
        <?php else: ?>
            <!-- playsinline, muted - обязательно для автозапуска на iOS -->
            <video id="ar-video" loop muted playsinline crossorigin="anonymous">
                <source src="<?php echo $ar_config['src']; ?>" type="video/mp4">
            </video>
        <?php endif; ?>
    </div>

    <!-- App Container -->
    <div id="container" style="width: 100vw; height: 100vh; position: relative; overflow: hidden;"></div>

    <script>
    
    // ============================
    // CONFIG
    // ============================
    
    const CONFIG = <?php echo json_encode($ar_config); ?>;
    
    if (!CONFIG.ready) {
        document.getElementById('status-text').innerText = "Ошибка: Данные не найдены";
        document.getElementById('loading-spinner').style.display = 'none';
        throw new Error("No AR Data");
    }
    </script>
    
    <script type="module" src="/wp-content/themes/yootheme-ar/inc/ar-card-viewer/ar-viewer.js"></script>
</body>
</html>

