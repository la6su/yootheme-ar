<?php
/**
 * Mospal greeting media lifecycle.
 *
 * Every upload is isolated from the regular media library, bound to the current
 * checkout browser and removed automatically after its retention period.
 */

defined('ABSPATH') || exit;

const MOSPAL_GREETING_ATTACHMENT_FLAG = '_mospal_greeting';
const MOSPAL_GREETING_SESSION_META = '_mospal_greeting_session';
const MOSPAL_GREETING_EXPIRES_META = '_mospal_greeting_expires_at';
const MOSPAL_GREETING_ORDER_META = '_mospal_greeting_order_id';
const MOSPAL_GREETING_TOKEN_META = '_mospal_greeting_token';
const MOSPAL_GREETING_ATTACHMENT_META = '_mospal_greeting_attachment_id';
const MOSPAL_GREETING_TYPE_META = '_mospal_greeting_type';
const MOSPAL_GREETING_ACTIVE_META = '_mospal_greeting_active';
const MOSPAL_GREETING_CHUNK_SIZE = 512 * KB_IN_BYTES;

const MOSPAL_GREETING_LIMITS = [
    'image_max' => 10 * MB_IN_BYTES,
    'video_max' => 50 * MB_IN_BYTES,
    'image_types' => ['image/jpeg', 'image/png'],
    'video_types' => ['video/mp4', 'video/quicktime'],
];

function mospal_greeting_assets_config(): array {
    $base = '/arjs';

    return [
        'model' => apply_filters('mospal_greeting_model_url', $base . '/gltf/tv.glb'),
        'target' => apply_filters('mospal_greeting_target_url', $base . '/card-target.mind'),
        'assets' => [
            'dracoPath' => $base . '/three-js/examples/jsm/libs/draco/',
            'hdrPath' => $base . '/three-js/examples/jsm/textures/equirectangular/venice_sunset_1k.hdr',
        ],
        'animation' => [
            'clipName' => apply_filters('mospal_greeting_animation_clip', null),
            'loop' => apply_filters('mospal_greeting_animation_loop', 'once'),
        ],
    ];
}

function mospal_greeting_session_token(): string {
    $cookie_name = 'mospal_greeting_session';

    if (!empty($_COOKIE[$cookie_name]) && preg_match('/^[a-f0-9-]{36}$/', $_COOKIE[$cookie_name])) {
        return sanitize_text_field(wp_unslash($_COOKIE[$cookie_name]));
    }

    $token = wp_generate_uuid4();
    setcookie(
        $cookie_name,
        $token,
        [
            'expires' => time() + (2 * DAY_IN_SECONDS),
            'path' => COOKIEPATH ?: '/',
            'domain' => COOKIE_DOMAIN,
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
    $_COOKIE[$cookie_name] = $token;

    return $token;
}

function mospal_greeting_upload_is_active(): bool {
    return !empty($GLOBALS['mospal_greeting_upload_active']);
}

function mospal_greeting_upload_dir(array $directories): array {
    if (!mospal_greeting_upload_is_active()) {
        return $directories;
    }

    $directories['subdir'] = '/greetings' . $directories['subdir'];
    $directories['path'] = $directories['basedir'] . $directories['subdir'];
    $directories['url'] = $directories['baseurl'] . $directories['subdir'];

    return $directories;
}
add_filter('upload_dir', 'mospal_greeting_upload_dir');

function mospal_greeting_randomize_filename(array $file): array {
    if (!mospal_greeting_upload_is_active() || empty($file['name'])) {
        return $file;
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $file['name'] = wp_generate_uuid4() . ($extension ? '.' . $extension : '');

    return $file;
}
add_filter('wp_handle_upload_prefilter', 'mospal_greeting_randomize_filename');
add_filter('wp_handle_sideload_prefilter', 'mospal_greeting_randomize_filename');

function mospal_greeting_extract_checkout_data(): array {
    $form_data = [];

    if (!empty($_POST['post_data'])) {
        parse_str(wp_unslash($_POST['post_data']), $form_data);
    }

    return array_merge(wp_unslash($_POST), $form_data);
}

function mospal_greeting_attachment_belongs_to_session(int $attachment_id): bool {
    return (int) get_post_meta($attachment_id, MOSPAL_GREETING_ATTACHMENT_FLAG, true) === 1
        && hash_equals(
            (string) get_post_meta($attachment_id, MOSPAL_GREETING_SESSION_META, true),
            mospal_greeting_session_token()
        )
        && !(int) get_post_meta($attachment_id, MOSPAL_GREETING_ORDER_META, true);
}

function mospal_greeting_handle_upload(): void {
    check_ajax_referer('mospal_greeting_upload', 'nonce');

    if (empty($_FILES['ar_file_upload']) || !is_array($_FILES['ar_file_upload'])) {
        wp_send_json_error(['message' => 'Файл не передан.'], 400);
    }

    $file = $_FILES['ar_file_upload'];

    if (!empty($file['error'])) {
        wp_send_json_error(['message' => 'Не удалось принять файл.'], 400);
    }

    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        wp_send_json_error(['message' => 'Загрузка файла не подтверждена.'], 400);
    }

    $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
    $mime = $check['type'] ?? '';
    $is_image = in_array($mime, MOSPAL_GREETING_LIMITS['image_types'], true);
    $is_video = in_array($mime, MOSPAL_GREETING_LIMITS['video_types'], true);

    if (!$is_image && !$is_video) {
        wp_send_json_error(['message' => 'Разрешены только JPG, PNG, MP4 и MOV.'], 400);
    }

    if (($is_image && $file['size'] > MOSPAL_GREETING_LIMITS['image_max'])
        || ($is_video && $file['size'] > MOSPAL_GREETING_LIMITS['video_max'])) {
        wp_send_json_error(['message' => $is_image ? 'Изображение больше 10 МБ.' : 'Видео больше 50 МБ.'], 400);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $GLOBALS['mospal_greeting_upload_active'] = true;
    try {
        $attachment_id = media_handle_upload('ar_file_upload', 0);
    } finally {
        $GLOBALS['mospal_greeting_upload_active'] = false;
    }

    if (is_wp_error($attachment_id)) {
        wp_send_json_error(['message' => $attachment_id->get_error_message()], 400);
    }

    update_post_meta($attachment_id, MOSPAL_GREETING_ATTACHMENT_FLAG, 1);
    update_post_meta($attachment_id, MOSPAL_GREETING_SESSION_META, mospal_greeting_session_token());
    update_post_meta($attachment_id, MOSPAL_GREETING_EXPIRES_META, time() + (2 * DAY_IN_SECONDS));

    wp_send_json_success([
        'id' => $attachment_id,
        'url' => wp_get_attachment_url($attachment_id),
        'type' => $mime,
    ]);
}
add_action('wp_ajax_mospal_greeting_upload', 'mospal_greeting_handle_upload');
add_action('wp_ajax_nopriv_mospal_greeting_upload', 'mospal_greeting_handle_upload');

function mospal_greeting_chunk_directory(string $upload_id): string {
    $session = hash('sha256', mospal_greeting_session_token());
    return trailingslashit(get_temp_dir()) . 'mospal-greetings/' . $session . '/' . $upload_id;
}

function mospal_greeting_remove_chunk_directory(string $directory): void {
    if (!is_dir($directory)) {
        return;
    }
    foreach (glob(trailingslashit($directory) . '*.part') ?: [] as $part) {
        wp_delete_file($part);
    }
    @rmdir($directory);
}

function mospal_greeting_cleanup_abandoned_chunks(): void {
    $root = trailingslashit(get_temp_dir()) . 'mospal-greetings';
    if (!is_dir($root)) {
        return;
    }

    $cutoff = time() - DAY_IN_SECONDS;
    foreach (glob(trailingslashit($root) . '*', GLOB_ONLYDIR) ?: [] as $session_directory) {
        foreach (glob(trailingslashit($session_directory) . '*', GLOB_ONLYDIR) ?: [] as $upload_directory) {
            $modified = filemtime($upload_directory);
            if ($modified !== false && $modified <= $cutoff) {
                mospal_greeting_remove_chunk_directory($upload_directory);
            }
        }
        @rmdir($session_directory);
    }
    @rmdir($root);
}

function mospal_greeting_handle_chunk_upload(): void {
    check_ajax_referer('mospal_greeting_upload', 'nonce');

    $upload_id = sanitize_text_field(wp_unslash($_POST['upload_id'] ?? ''));
    $chunk_index = isset($_POST['chunk_index']) ? absint($_POST['chunk_index']) : -1;
    $total_chunks = isset($_POST['total_chunks']) ? absint($_POST['total_chunks']) : 0;
    $file_name = sanitize_file_name(wp_unslash($_POST['file_name'] ?? ''));
    $file_type = sanitize_mime_type(wp_unslash($_POST['file_type'] ?? ''));
    $file_size = isset($_POST['file_size']) ? absint($_POST['file_size']) : 0;

    if (!preg_match('/^[a-f0-9-]{36}$/', $upload_id) || !$file_name || !$file_size) {
        wp_send_json_error(['message' => 'Некорректные параметры загрузки.'], 400);
    }

    $is_image = in_array($file_type, MOSPAL_GREETING_LIMITS['image_types'], true);
    $is_video = in_array($file_type, MOSPAL_GREETING_LIMITS['video_types'], true);
    $max_size = $is_image ? MOSPAL_GREETING_LIMITS['image_max'] : MOSPAL_GREETING_LIMITS['video_max'];
    if ((!$is_image && !$is_video) || $file_size > $max_size) {
        wp_send_json_error(['message' => $is_image ? 'Изображение больше 10 МБ.' : 'Видео больше 50 МБ или имеет неверный формат.'], 400);
    }

    $expected_chunks = (int) ceil($file_size / MOSPAL_GREETING_CHUNK_SIZE);
    if ($total_chunks !== $expected_chunks || $chunk_index < 0 || $chunk_index >= $total_chunks || $total_chunks > 100) {
        wp_send_json_error(['message' => 'Нарушена последовательность частей файла.'], 400);
    }

    if (empty($_FILES['ar_file_chunk']) || !is_array($_FILES['ar_file_chunk'])) {
        wp_send_json_error(['message' => 'Часть файла не передана.'], 400);
    }
    $chunk = $_FILES['ar_file_chunk'];
    if (!empty($chunk['error']) || empty($chunk['tmp_name']) || !is_uploaded_file($chunk['tmp_name'])) {
        wp_send_json_error(['message' => 'Не удалось принять часть файла.'], 400);
    }
    if ((int) $chunk['size'] > MOSPAL_GREETING_CHUNK_SIZE) {
        wp_send_json_error(['message' => 'Часть файла превышает допустимый размер.'], 400);
    }

    $directory = mospal_greeting_chunk_directory($upload_id);
    if (!wp_mkdir_p($directory)) {
        wp_send_json_error(['message' => 'Не удалось подготовить временное хранилище.'], 500);
    }
    $part_path = trailingslashit($directory) . sprintf('%05d.part', $chunk_index);
    if (!move_uploaded_file($chunk['tmp_name'], $part_path)) {
        wp_send_json_error(['message' => 'Не удалось сохранить часть файла.'], 500);
    }

    $received = count(glob(trailingslashit($directory) . '*.part') ?: []);
    if ($received < $total_chunks) {
        wp_send_json_success([
            'complete' => false,
            'received' => $received,
            'total' => $total_chunks,
        ]);
    }

    $assembled_path = wp_tempnam($file_name);
    $output = $assembled_path ? fopen($assembled_path, 'wb') : false;
    if (!$assembled_path || !$output) {
        mospal_greeting_remove_chunk_directory($directory);
        wp_send_json_error(['message' => 'Не удалось собрать файл.'], 500);
    }

    $assembled = true;
    for ($index = 0; $index < $total_chunks; $index++) {
        $source_path = trailingslashit($directory) . sprintf('%05d.part', $index);
        $source = is_file($source_path) ? fopen($source_path, 'rb') : false;
        if (!$source) {
            $assembled = false;
            break;
        }
        if (stream_copy_to_stream($source, $output) === false) {
            $assembled = false;
        }
        fclose($source);
        if (!$assembled) break;
    }
    fclose($output);
    mospal_greeting_remove_chunk_directory($directory);

    if (!$assembled || !is_file($assembled_path) || filesize($assembled_path) !== $file_size) {
        if ($assembled_path) wp_delete_file($assembled_path);
        wp_send_json_error(['message' => 'Файл собран не полностью. Повторите загрузку.'], 400);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $check = wp_check_filetype_and_ext($assembled_path, $file_name);
    $mime = $check['type'] ?? '';
    if (!in_array($mime, array_merge(MOSPAL_GREETING_LIMITS['image_types'], MOSPAL_GREETING_LIMITS['video_types']), true)) {
        wp_delete_file($assembled_path);
        wp_send_json_error(['message' => 'Разрешены только JPG, PNG, MP4 и MOV.'], 400);
    }

    $sideload = [
        'name' => $file_name,
        'type' => $mime,
        'tmp_name' => $assembled_path,
        'error' => 0,
        'size' => $file_size,
    ];
    $GLOBALS['mospal_greeting_upload_active'] = true;
    try {
        $attachment_id = media_handle_sideload($sideload, 0);
    } finally {
        $GLOBALS['mospal_greeting_upload_active'] = false;
    }
    if (is_wp_error($attachment_id)) {
        if (is_file($assembled_path)) wp_delete_file($assembled_path);
        wp_send_json_error(['message' => $attachment_id->get_error_message()], 400);
    }

    update_post_meta($attachment_id, MOSPAL_GREETING_ATTACHMENT_FLAG, 1);
    update_post_meta($attachment_id, MOSPAL_GREETING_SESSION_META, mospal_greeting_session_token());
    update_post_meta($attachment_id, MOSPAL_GREETING_EXPIRES_META, time() + (2 * DAY_IN_SECONDS));

    wp_send_json_success([
        'complete' => true,
        'id' => $attachment_id,
        'url' => wp_get_attachment_url($attachment_id),
        'type' => $mime,
    ]);
}
add_action('wp_ajax_mospal_greeting_upload_chunk', 'mospal_greeting_handle_chunk_upload');
add_action('wp_ajax_nopriv_mospal_greeting_upload_chunk', 'mospal_greeting_handle_chunk_upload');

function mospal_greeting_bind_to_order(WC_Order $order, array $data): void {
    $is_active = !empty($data['ar_active']);
    if (!$is_active) {
        return;
    }

    $attachment_id = isset($data['ar_attachment_id']) ? absint($data['ar_attachment_id']) : 0;
    if (!$attachment_id || !mospal_greeting_attachment_belongs_to_session($attachment_id)) {
        return;
    }

    $mime = (string) get_post_mime_type($attachment_id);
    $type = in_array($mime, MOSPAL_GREETING_LIMITS['video_types'], true) ? 'video' : 'image';

    $order->update_meta_data(MOSPAL_GREETING_ACTIVE_META, 'yes');
    $order->update_meta_data(MOSPAL_GREETING_ATTACHMENT_META, $attachment_id);
    $order->update_meta_data(MOSPAL_GREETING_TYPE_META, $type);
    $order->update_meta_data(MOSPAL_GREETING_TOKEN_META, wp_generate_password(32, false, false));
    $order->update_meta_data(MOSPAL_GREETING_EXPIRES_META, time() + (30 * DAY_IN_SECONDS));
}
add_action('woocommerce_checkout_create_order', function (WC_Order $order) {
    mospal_greeting_bind_to_order($order, mospal_greeting_extract_checkout_data());
}, 30);

function mospal_greeting_attach_order(WC_Order $order): void {
    $attachment_id = absint($order->get_meta(MOSPAL_GREETING_ATTACHMENT_META));
    if (!$attachment_id || !get_post_meta($attachment_id, MOSPAL_GREETING_ATTACHMENT_FLAG, true)) {
        return;
    }

    update_post_meta($attachment_id, MOSPAL_GREETING_ORDER_META, $order->get_id());
    update_post_meta($attachment_id, MOSPAL_GREETING_EXPIRES_META, (int) $order->get_meta(MOSPAL_GREETING_EXPIRES_META));
}
add_action('woocommerce_checkout_order_created', 'mospal_greeting_attach_order', 30);

function mospal_greeting_extend_retention(int $order_id): void {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $attachment_id = absint($order->get_meta(MOSPAL_GREETING_ATTACHMENT_META));
    if (!$attachment_id) return;

    $expiry = time() + (30 * DAY_IN_SECONDS);
    $delivery_date = (string) $order->get_meta('_delivery_date');

    if ($delivery_date) {
        try {
            $delivery = new DateTimeImmutable($delivery_date, wp_timezone());
            $expiry = max($expiry, $delivery->modify('+30 days')->getTimestamp());
        } catch (Exception $exception) {
            // Keep the default 30 day retention when an old delivery value cannot be parsed.
        }
    }

    $order->update_meta_data(MOSPAL_GREETING_EXPIRES_META, $expiry);
    $order->save();
    update_post_meta($attachment_id, MOSPAL_GREETING_EXPIRES_META, $expiry);
}
add_action('woocommerce_payment_complete', 'mospal_greeting_extend_retention');
add_action('woocommerce_order_status_processing', 'mospal_greeting_extend_retention');
add_action('woocommerce_order_status_completed', 'mospal_greeting_extend_retention');

function mospal_greeting_find_order_by_token(string $token): ?WC_Order {
    $orders = wc_get_orders([
        'limit' => 1,
        'meta_key' => MOSPAL_GREETING_TOKEN_META,
        'meta_value' => $token,
        'return' => 'objects',
    ]);

    return $orders[0] ?? null;
}

function mospal_greeting_viewer_url(WC_Order $order): string {
    $token = (string) $order->get_meta(MOSPAL_GREETING_TOKEN_META);
    if (!$token) return '';

    $page = get_page_by_path('ar-card');
    $url = $page ? get_permalink($page) : home_url('/ar-card/');

    return add_query_arg('token', rawurlencode($token), $url);
}

function mospal_greeting_get_viewer_config(): array {
    $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
    $order = $token ? mospal_greeting_find_order_by_token($token) : null;

    // Legacy lookup is deliberately limited to WooCommerce managers; new QR codes use a token.
    if (!$order && isset($_GET['id']) && current_user_can('manage_woocommerce')) {
        $order = wc_get_order(absint($_GET['id']));
    }

    $assets = mospal_greeting_assets_config();
    $config = [
        'ready' => false,
        'model' => $assets['model'],
        'target' => $assets['target'],
        'assets' => $assets['assets'],
        'animation' => $assets['animation'],
        'media' => ['type' => 'image', 'url' => ''],
    ];

    if (!$order || $order->get_meta(MOSPAL_GREETING_ACTIVE_META) !== 'yes') {
        return $config;
    }

    $expires_at = (int) $order->get_meta(MOSPAL_GREETING_EXPIRES_META);
    $attachment_id = absint($order->get_meta(MOSPAL_GREETING_ATTACHMENT_META));
    $url = $attachment_id ? wp_get_attachment_url($attachment_id) : false;

    if (!$attachment_id || !$url || ($expires_at && $expires_at < time())) {
        return $config;
    }

    $config['ready'] = true;
    $config['media'] = [
        'type' => $order->get_meta(MOSPAL_GREETING_TYPE_META) === 'video' ? 'video' : 'image',
        'url' => $url,
    ];

    return $config;
}

function mospal_greeting_cleanup_expired_uploads(): void {
    mospal_greeting_cleanup_abandoned_chunks();

    $attachments = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'any',
        'posts_per_page' => 50,
        'fields' => 'ids',
        'meta_query' => [
            'relation' => 'AND',
            [
                'key' => MOSPAL_GREETING_ATTACHMENT_FLAG,
                'value' => 1,
            ],
            [
                'key' => MOSPAL_GREETING_EXPIRES_META,
                'value' => time(),
                'compare' => '<=',
                'type' => 'NUMERIC',
            ],
        ],
    ]);

    foreach ($attachments as $attachment_id) {
        $order_id = absint(get_post_meta($attachment_id, MOSPAL_GREETING_ORDER_META, true));
        if ($order = wc_get_order($order_id)) {
            $order->update_meta_data(MOSPAL_GREETING_EXPIRES_META, time());
            $order->save();
        }
        wp_delete_attachment($attachment_id, true);
    }
}
add_action('mospal_greeting_cleanup_expired', 'mospal_greeting_cleanup_expired_uploads');

add_action('init', function () {
    if (function_exists('as_next_scheduled_action')) {
        if (!as_next_scheduled_action('mospal_greeting_cleanup_expired', [], 'mospal-greetings')) {
            as_schedule_recurring_action(time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, 'mospal_greeting_cleanup_expired', [], 'mospal-greetings');
        }
        return;
    }

    if (!wp_next_scheduled('mospal_greeting_cleanup_expired')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'mospal_greeting_cleanup_expired');
    }
});
