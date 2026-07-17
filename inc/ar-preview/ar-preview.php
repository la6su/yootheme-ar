<?php
/**
 * Checkout UI and standard Three.js preview for greeting media.
 */

defined('ABSPATH') || exit;

function mospal_greeting_client_config(): array {
    $assets = mospal_greeting_assets_config();

    return [
        'model' => $assets['model'],
        'assets' => $assets['assets'],
        'animation' => $assets['animation'],
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mospal_greeting_upload'),
    ];
}

add_action('wp_head', function () {
    if (!is_checkout() || is_admin() || wp_doing_ajax()) return;
    ?>
    <script type="importmap">
        <?php echo wp_json_encode([
            'imports' => [
                'three' => '/arjs/three-js/three.module.min.js',
                'three/addons/' => '/arjs/three-js/examples/jsm/',
            ],
        ], JSON_UNESCAPED_SLASHES); ?>
    </script>
    <?php
}, 1);

add_action('wp_enqueue_scripts', function () {
    if (!is_checkout() || is_admin() || wp_doing_ajax()) return;

    $script_path = get_stylesheet_directory() . '/inc/ar-preview/ar-preview.js';
    wp_enqueue_script(
        'mospal-ar-preview',
        get_stylesheet_directory_uri() . '/inc/ar-preview/ar-preview.js',
        [],
        filemtime($script_path),
        true
    );
    wp_localize_script('mospal-ar-preview', 'MOSPAL_AR_CONFIG', mospal_greeting_client_config());

    add_filter('script_loader_tag', function (string $tag, string $handle): string {
        if ($handle === 'mospal-ar-preview') {
            return str_replace('<script ', '<script type="module" ', $tag);
        }
        return $tag;
    }, 10, 2);
}, 30);

function mospal_greeting_render_checkout(): void {
    ?>
    <section class="uk-card uk-card-default uk-card-small uk-margin-top uk-border-rounded" aria-labelledby="mospal-greeting-title">
        <div class="uk-card-header uk-background-muted">
            <h3 id="mospal-greeting-title" class="uk-card-title uk-flex uk-flex-middle">
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

                <div class="uk-margin" uk-margin>
                    <div uk-form-custom="target: true">
                        <input type="file" id="ar_file" accept="image/jpeg,image/png,video/mp4,video/quicktime">
                        <input class="uk-input uk-form-width-medium" type="text" placeholder="Выберите файл" disabled>
                    </div>
                </div>

                <progress id="ar_progress" class="uk-progress" value="0" max="100" hidden></progress>
                <div id="ar_status" class="uk-text-small uk-margin-small-top" aria-live="polite"></div>
                <p class="uk-text-meta uk-margin-small-top">JPG/PNG до 10 МБ, MP4/MOV до 50 МБ. Файл хранится 30 дней после доставки.</p>

                <input type="hidden" name="ar_attachment_id" id="ar_attachment_id">

                <button type="button" id="ar_preview_btn" class="uk-button uk-button-default uk-margin-top" uk-toggle="target: #ar_modal" disabled>
                    Предпросмотр
                </button>
            </div>
        </div>
    </section>
    <?php
}

add_action('woocommerce_after_checkout_validation', function (array $data, WP_Error $errors) {
    if (empty($data['ar_active'])) return;

    $attachment_id = isset($data['ar_attachment_id']) ? absint($data['ar_attachment_id']) : 0;
    if (!$attachment_id || !mospal_greeting_attachment_belongs_to_session($attachment_id)) {
        $errors->add('mospal_greeting_upload', 'Загрузите файл AR-открытки ещё раз перед оформлением заказа.');
    }
}, 20, 2);

add_action('wp_footer', function () {
    if (!is_checkout()) return;
    ?>
    <div id="ar_modal" class="uk-modal-container" uk-modal>
        <div class="uk-modal-dialog uk-modal-body">
            <button class="uk-modal-close-default" type="button" uk-close aria-label="Закрыть предпросмотр"></button>
            <div id="ar_preview_container" class="ar-preview"></div>
        </div>
    </div>
    <style>
        .ar-preview { width: 100%; aspect-ratio: 16 / 9; position: relative; }
        .ar-preview canvas { width: 100% !important; height: 100% !important; display: block; }
    </style>
    <?php
});

add_action('woocommerce_admin_order_data_after_order_details', function (WC_Order $order) {
    if ($order->get_meta(MOSPAL_GREETING_ACTIVE_META) !== 'yes') return;

    $attachment_id = absint($order->get_meta(MOSPAL_GREETING_ATTACHMENT_META));
    $type = $order->get_meta(MOSPAL_GREETING_TYPE_META);
    $expires_at = (int) $order->get_meta(MOSPAL_GREETING_EXPIRES_META);
    $url = $attachment_id ? wp_get_attachment_url($attachment_id) : false;
    $viewer_url = mospal_greeting_viewer_url($order);

    echo '<div style="margin-top:20px">';
    echo '<h3>AR-поздравление</h3>';
    echo '<p><strong>Тип:</strong> ' . esc_html($type === 'video' ? 'Видео' : 'Изображение') . '</p>';
    if ($expires_at) {
        echo '<p><strong>Хранится до:</strong> ' . esc_html(wp_date('d.m.Y H:i', $expires_at)) . '</p>';
    }
    if ($viewer_url) {
        echo '<p><a href="' . esc_url($viewer_url) . '" target="_blank" rel="noopener">Открыть AR-страницу</a></p>';
    }
    if ($type === 'video' && $url) {
        echo '<video controls playsinline style="max-width:300px"><source src="' . esc_url($url) . '" type="' . esc_attr((string) get_post_mime_type($attachment_id)) . '"></video>';
    } elseif ($url) {
        echo '<img src="' . esc_url($url) . '" style="max-width:300px" alt="">';
    } else {
        echo '<p>Файл удалён по истечении срока хранения.</p>';
    }
    echo '</div>';
});
