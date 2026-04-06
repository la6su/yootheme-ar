<?php
/**
 * -----------------------------------------------------------
 * AR MODULE: CHECKOUT + ASYNC UPLOAD + PREVIEW (STABLE)
 * -----------------------------------------------------------
 */

defined('ABSPATH') || exit;

/**
 * -----------------------------------------------------------
 * CONFIG
 * -----------------------------------------------------------
 */

const AR_FIELDS = [
    'active' => 'field_698ea7aa65985',
    'type'   => 'field_698ea86d65986',
    'video'  => 'field_698ea91065988',
    'image'  => 'field_698ea9466598a'
];

const AR_LIMITS = [
    'image_max' => 10 * 1024 * 1024,   // 10MB
    'video_max' => 50 * 1024 * 1024,  // 50MB
    'image_types' => ['image/jpeg','image/png'],
    'video_types' => ['video/mp4','video/quicktime']
];

add_action('wp_head', function() {

    if (!is_checkout()) return;
    if (is_admin() || wp_doing_ajax()) return;

?>

<script type="importmap">
<?php echo json_encode([
    "imports" => [
        "three" => "/arjs/three-js/three.module.min.js",
        "three/addons/" => "/arjs/three-js/examples/jsm/"
    ]
], JSON_UNESCAPED_SLASHES); ?>
</script>
<?php
}, 1);

add_action('wp_enqueue_scripts', function () {

    if (!is_checkout() || is_admin() || wp_doing_ajax()) return;


    // MAIN SCRIPT
    wp_enqueue_script(
        'ar-preview',
        get_stylesheet_directory_uri() . '/inc/ar-preview/ar-preview.js',
        [],
        '1.1',
        true
    );

    // MODULE FIX
    add_filter('script_loader_tag', function($tag, $handle) {
        if ($handle === 'ar-preview') {
            return str_replace('<script ', '<script type="module" ', $tag);
        }
        return $tag;
    }, 10, 2);


}, 20);
/**
 * -----------------------------------------------------------
 * 1. AJAX UPLOAD (SAFE)
 * -----------------------------------------------------------
 */

add_action('wp_ajax_ar_async_upload', 'ar_handle_upload');
add_action('wp_ajax_nopriv_ar_async_upload', 'ar_handle_upload');

function ar_handle_upload() {

    check_ajax_referer('ar_upload_nonce', 'nonce');

    if (empty($_FILES['ar_file_upload'])) {
        wp_send_json_error('Файл не передан');
    }

    $file = $_FILES['ar_file_upload'];

    // определяем реальный mime
    $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);

    if (!$check['type']) {
        wp_send_json_error('Неверный тип файла');
    }

    $mime = $check['type'];

    $is_image = in_array($mime, AR_LIMITS['image_types']);
    $is_video = in_array($mime, AR_LIMITS['video_types']);

    if (!$is_image && !$is_video) {
        wp_send_json_error('Разрешены только JPG, PNG, MP4, MOV');
    }

    // размер
    if ($is_image && $file['size'] > AR_LIMITS['image_max']) {
        wp_send_json_error('Изображение больше 10MB');
    }

    if ($is_video && $file['size'] > AR_LIMITS['video_max']) {
        wp_send_json_error('Видео больше 50MB');
    }

    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attachment_id = media_handle_upload('ar_file_upload', 0);

    if (is_wp_error($attachment_id)) {
        wp_send_json_error($attachment_id->get_error_message());
    }

    wp_send_json_success([
        'id'   => $attachment_id,
        'url'  => wp_get_attachment_url($attachment_id),
        'type' => $mime
    ]);
}

/**
 * -----------------------------------------------------------
 * 2. CHECKOUT UI (UIKIT + UX)
 * -----------------------------------------------------------
 */


function ar_render_checkout() { 
?>

    <div class="uk-card uk-card-default uk-card-small uk-margin-top uk-border-rounded">
    
        <div class="uk-card-header uk-background-muted">
            <h3 class="uk-card-title uk-flex uk-flex-middle">
                <span uk-icon="icon: image"></span>
                <span class="uk-margin-small-left">AR-поздравление</span>
            </h3>
        </div>
    
        <div class="uk-card-body">
    
            <label class="uk-flex uk-flex-middle">
                <input type="checkbox" name="ar_active" id="ar_active" class="uk-checkbox uk-margin-small-right" value="1">
                Добавить AR-открытку
            </label>
    
            <div id="ar_box" hidden class="uk-margin-top">
    
                <div class="uk-margin-small">
                    <label><input type="radio" name="ar_type" value="image" checked> Фото</label>
                    <label class="uk-margin-left"><input type="radio" name="ar_type" value="video"> Видео</label>
                </div>
    
                <!-- UIKIT CUSTOM FILE -->
                <div class="uk-margin" uk-margin>
    
                    <div uk-form-custom="target: true">
                        <input type="file" id="ar_file" accept="image/*,video/*">
                        <input class="uk-input uk-form-width-medium" type="text" placeholder="Выберите файл" disabled>
                    </div>
    
                    <button type="button" id="ar_upload_btn" class="uk-button uk-button-primary">
                        Загрузить
                    </button>
    
                </div>
    
                <progress id="ar_progress" class="uk-progress" value="0" max="100" hidden></progress>
    
                <div id="ar_status" class="uk-text-small uk-margin-small-top"></div>
    
                <input type="hidden" name="ar_attachment_id" id="ar_attachment_id">
    
                <button type="button"
                        id="ar_preview_btn"
                        class="uk-button uk-button-default uk-margin-top"
                        uk-toggle="target: #ar_modal"
                        disabled>
                    Предпросмотр
                </button>
    
            </div>
        </div>
    </div>


<?php }

/**
 * -----------------------------------------------------------
 * 3. SAVE ORDER
 * -----------------------------------------------------------
 */

add_action('woocommerce_checkout_update_order_meta', 'ar_save_order', 20);

function ar_save_order($order_id) {

    if (!$order_id) return;

    $form_data = [];

    if (!empty($_POST['post_data'])) {
        parse_str($_POST['post_data'], $form_data);
    }

    $data = array_merge($_POST, $form_data);

    $is_active = !empty($data['ar_active']);

    update_field(AR_FIELDS['active'], $is_active ? 1 : 0, $order_id);

    if (!$is_active) return;

    $type = sanitize_text_field($data['ar_type'] ?? 'image');
    update_field(AR_FIELDS['type'], $type, $order_id);

    $att_id = isset($data['ar_attachment_id']) ? absint($data['ar_attachment_id']) : 0;

    if (!$att_id) return;

    if ($type === 'video') {
        update_field(AR_FIELDS['video'], $att_id, $order_id);
    } else {
        update_field(AR_FIELDS['image'], $att_id, $order_id);
    }
}

/**
 * -----------------------------------------------------------
 * 4. MODAL
 * -----------------------------------------------------------
 */

add_action('wp_footer', function () {
    if (!is_checkout()) return; ?>

<div id="ar_modal" class="uk-modal-container" uk-modal>
    <div class="uk-modal-dialog uk-modal-body">
        <button class="uk-modal-close-default" type="button" uk-close></button>
        <div id="ar_preview_container" class="ar-preview"></div>
    </div>
</div>

<style>
.ar-preview {
    width: 100%;
    aspect-ratio: 16 / 9; /* ключ */
    position: relative;
}

.ar-preview canvas {
    width: 100% !important;
    height: 100% !important;
    display: block;
}
.ar-preview video, .ar-preview img {display: none;}
</style>

<?php });

/**
 * -----------------------------------------------------------
 * 5. ADMIN VIEW
 * -----------------------------------------------------------
 */

add_action('woocommerce_admin_order_data_after_order_details', function($order){

    $id = $order->get_id();

    if (!get_field(AR_FIELDS['active'], $id)) return;

    $type  = get_field(AR_FIELDS['type'], $id);
    $video = get_field(AR_FIELDS['video'], $id);
    $image = get_field(AR_FIELDS['image'], $id);

    echo '<div style="margin-top:20px">';
    echo '<h3>AR поздравление</h3>';
    echo '<p><strong>Тип:</strong> '.esc_html($type).'</p>';

    if ($type === 'video' && $video) {
        echo '<video controls style="max-width:300px"><source src="'.esc_url($video).'"></video>';
    }

    if ($type === 'image' && $image) {
        echo '<img src="'.esc_url($image).'" style="max-width:300px">';
    }

    echo '</div>';
});