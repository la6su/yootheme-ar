<?php
/**
 * Public 3D preview for YOOtheme Builder pages.
 *
 * Usage: [mospal_ar_preview video="https://example.com/video.mp4"]
 * The video attribute also accepts a WordPress attachment ID.
 */

defined('ABSPATH') || exit;

function mospal_ar_showcase_video_url(string $value): string {
    $value = trim($value);

    if ($value !== '' && ctype_digit($value)) {
        return (string) wp_get_attachment_url((int) $value);
    }

    return esc_url_raw($value);
}

function mospal_ar_showcase_enqueue_assets(): void {
    static $enqueued = false;

    if ($enqueued) {
        return;
    }
    $enqueued = true;

    $directory = get_stylesheet_directory() . '/inc/ar-showcase/';
    $uri = get_stylesheet_directory_uri() . '/inc/ar-showcase/';

    wp_enqueue_style(
        'mospal-ar-showcase',
        $uri . 'ar-showcase.css',
        [],
        filemtime($directory . 'ar-showcase.css')
    );
    wp_enqueue_script(
        'mospal-ar-showcase',
        $uri . 'ar-showcase.js',
        [],
        filemtime($directory . 'ar-showcase.js'),
        true
    );

    add_filter('script_loader_tag', function (string $tag, string $handle): string {
        if ($handle === 'mospal-ar-showcase') {
            return str_replace('<script ', '<script type="module" ', $tag);
        }

        return $tag;
    }, 10, 2);
}

function mospal_ar_showcase_import_map(): string {
    static $rendered = false;

    if ($rendered) {
        return '';
    }
    $rendered = true;

    return '<script type="importmap">' . wp_json_encode([
        'imports' => [
            'three' => '/arjs/three-js/three.module.min.js',
            'three/addons/' => '/arjs/three-js/examples/jsm/',
        ],
    ], JSON_UNESCAPED_SLASHES) . '</script>';
}

function mospal_ar_showcase_shortcode(array $attributes = []): string {
    $attributes = shortcode_atts([
        'video' => '',
        'height' => '520',
        'class' => '',
    ], $attributes, 'mospal_ar_preview');

    $video_url = mospal_ar_showcase_video_url((string) $attributes['video']);
    $height = min(1000, max(280, absint($attributes['height']) ?: 520));
    $assets = mospal_greeting_assets_config();
    $classes = array_filter(array_map('sanitize_html_class', preg_split('/\s+/', (string) $attributes['class']) ?: []));

    mospal_ar_showcase_enqueue_assets();

    $config = [
        'model' => $assets['model'],
        'assets' => $assets['assets'],
        'animation' => $assets['animation'],
        'media' => [
            'type' => 'video',
            'url' => $video_url,
        ],
    ];

    return mospal_ar_showcase_import_map()
        . '<div class="mospal-ar-showcase ' . esc_attr(implode(' ', $classes)) . '"'
        . ' data-mospal-ar-showcase="' . esc_attr(wp_json_encode($config)) . '"'
        . ' style="height:' . esc_attr((string) $height) . 'px">'
        . '<p class="mospal-ar-showcase__status uk-text-meta">Загружаем 3D-открытку…</p>'
        . '</div>';
}
add_shortcode('mospal_ar_preview', 'mospal_ar_showcase_shortcode');
