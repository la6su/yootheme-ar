<?php 
/* Template Name: ar-card Template */ 

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
// Путь к корню сайта (чтобы importmap работал от корня)
$site_url = get_site_url(); 
// Базовый путь к папке arjs
$ar_base = '/arjs'; 

$ar_config = [
    'ready'  => false,
    'type'   => 'image',
    'format' => 'portrait',
    'src'    => '',
    // Модели и таргеты берем относительно корня
    'model'  => $ar_base . '/gltf/abstract.glb', 
    'target' => $ar_base . '/card-target.mind', 
    // Пути для importmap
    'paths'  => [
        'three_module' => $ar_base . '/three-js/three.module.min.js',
        'three_addons' => $ar_base . '/three-js/examples/jsm/', // Важно: папка jsm
        'mindar'       => $ar_base . '/mind-ar/mindar-image-three.prod.js',
        'draco'        => $ar_base . '/three-js/examples/jsm/libs/draco/'
    ]
];

if ($order_id > 0) {
    if ( get_field('type_card', $order_id) || get_field('ar_card', $order_id) ) {
        $type = get_field('type', $order_id) ?: 'image';
        $format = get_field('video_format', $order_id) ?: 'portrait';
        $file_id = ($type === 'video') ? get_field('video_loader', $order_id) : get_field('image_loader', $order_id);
        $file_url = is_numeric($file_id) ? wp_get_attachment_url($file_id) : $file_id;

        if ($file_url) {
            $ar_config['ready']  = true;
            $ar_config['type']   = $type;
            $ar_config['format'] = $format;
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

    <!-- Import Maps: Самая важная часть -->
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

    <script type="module">
        import * as THREE from 'three';
        import { MindARThree } from 'mindar-image-three';
        // Импортируем через алиас three/addons/
        import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
        import { DRACOLoader } from 'three/addons/loaders/DRACOLoader.js';
        import { RGBELoader } from 'three/addons/loaders/RGBELoader.js';

        const CONFIG = <?php echo json_encode($ar_config); ?>;

        if (!CONFIG.ready) {
            document.getElementById('status-text').innerText = "Ошибка: Данные не найдены";
            document.getElementById('loading-spinner').style.display = 'none';
            throw new Error("No AR Data");
        }
        
        // ... (дальше твой код без изменений до setupScene) ...
        const startBtn = document.getElementById('start-btn');
        const overlay = document.getElementById('start-overlay');
        const spinner = document.getElementById('loading-spinner');
        const statusText = document.getElementById('status-text');

        const mindarThree = new MindARThree({
            container: document.querySelector("#container"),
            imageTargetSrc: CONFIG.target,
            uiLoading: "no", 
            uiScanning: "yes", 
            filterMinCF: 0.0001,
            filterBeta: 0.001
        });

        const { renderer, scene, camera } = mindarThree;
        const anchor = mindarThree.addAnchor(0);

        const setupScene = () => {
            const ambientLight = new THREE.AmbientLight(0xffffff, 1.2);
            scene.add(ambientLight);
            const dirLight = new THREE.DirectionalLight(0xffffff, 1.5);
            dirLight.position.set(5, 10, 7);
            scene.add(dirLight);

            // Загрузка HDR через правильный алиас
            new RGBELoader().setPath('<?php echo $ar_config['paths']['three_addons']; ?>textures/equirectangular/')
                .load('blouberg_sunrise_2_1k.hdr', (texture) => {
                    texture.mapping = THREE.EquirectangularReflectionMapping;
                    scene.environment = texture;
                });
        };

        const loadContent = async () => {
            const group = new THREE.Group();
            
            let width = (CONFIG.format === 'horizont') ? 1.92 : 1.08;
            let height = (CONFIG.format === 'horizont') ? 1.08 : 1.92;

            const geometry = new THREE.PlaneGeometry(width, height);
            let texture = null;

            if (CONFIG.type === 'video') {
                const video = document.getElementById('ar-video');
                texture = new THREE.VideoTexture(video);
                texture.minFilter = THREE.LinearFilter;
                
                anchor.onTargetFound = () => { if (video.paused) video.play(); }
                anchor.onTargetLost = () => { video.pause(); }
            } else {
                const img = document.getElementById('ar-image');
                texture = new THREE.TextureLoader().load(img.src);
                texture.colorSpace = THREE.SRGBColorSpace;
            }

            const material = new THREE.MeshBasicMaterial({ map: texture });
            const screenMesh = new THREE.Mesh(geometry, material);
            
            const frameGeo = new THREE.BoxGeometry(width, height, 0.05);
            const frameMat = new THREE.MeshStandardMaterial({ color: 0x9f79ff, roughness: 0.2, metalness: 0.8 });
            const frameMesh = new THREE.Mesh(frameGeo, frameMat);
            frameMesh.position.z = -0.03;
            screenMesh.add(frameMesh);
            group.add(screenMesh);

            // DRACO LOADER
            const loader = new GLTFLoader();
            const dracoLoader = new DRACOLoader();
            // Указываем путь к декодерам через конфиг
            dracoLoader.setDecoderPath('<?php echo $ar_config['paths']['draco']; ?>');
            loader.setDRACOLoader(dracoLoader);

            try {
                const gltf = await loader.loadAsync(CONFIG.model);
                const model = gltf.scene;
                
                model.traverse((child) => {
                    if (child.isMesh) child.material = frameMat; 
                });

                model.scale.set(0.5, 0.5, 0.5);
                model.position.set(-1.2, -2.5, 0.3);
                model.rotation.y = Math.PI / 2;
                group.add(model);
                
            } catch (err) {
                console.warn("Model load failed", err);
            }

            const ringGeo = new THREE.RingGeometry(2, 2.4, 32);
            const ringMat = new THREE.MeshStandardMaterial({ 
                color: 0xFFD700, metalness: 1, roughness: 0.1, side: THREE.DoubleSide 
            });
            const ring = new THREE.Mesh(ringGeo, ringMat);
            ring.position.set(-1, 0.4, -0.7);
            ring.scale.set(0.58, 0.58, 0.58);
            group.add(ring);

            group.scale.set(0.36, 0.36, 0.36); 
            anchor.group.add(group);
        };
        
        // ... (init и остальное без изменений) ...
        const init = async () => {
            setupScene();
            await loadContent(); 
            spinner.style.display = 'none';
            statusText.style.display = 'none';
            startBtn.style.display = 'block';

            startBtn.addEventListener('click', async () => {
                if (CONFIG.type === 'video') document.getElementById('ar-video').muted = false;
                overlay.classList.add('hidden');
                await mindarThree.start();
                renderer.setAnimationLoop(() => {
                    renderer.render(scene, camera);
                });
            });
        };
        init();
    </script>
</body>
</html>

