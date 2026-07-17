<?php

add_action('wp_enqueue_scripts', function() {

    if (!is_checkout() || is_admin()) return;

    // CSS
    wp_enqueue_style(
        'flatpickr-css',
        get_stylesheet_directory_uri() . '/inc/checkout/libs/flatpickr/flatpickr.min.css',
        [],
        '4.6.13'
    );

    // JS
    wp_enqueue_script(
        'flatpickr-js',
        get_stylesheet_directory_uri() . '/inc/checkout/libs/flatpickr/flatpickr.min.js',
        [],
        '4.6.13',
        true
    );

    // RU locale
    wp_enqueue_script(
        'flatpickr-ru',
        get_stylesheet_directory_uri() . '/inc/checkout/libs/flatpickr/ru.js',
        ['flatpickr-js'],
        '4.6.13',
        true
    );

}, 20);

add_action('woocommerce_checkout_create_order', function(WC_Order $order, array $data) {

    if (!empty($data['delivery_date'])) {
        $order->update_meta_data('_delivery_date', sanitize_text_field($data['delivery_date']));
    }

    if (!empty($data['delivery_time'])) {
        $order->update_meta_data('_delivery_time', sanitize_text_field($data['delivery_time']));
    }

}, 20, 2);

add_action('woocommerce_admin_order_data_after_order_details', function($order){

    $date = $order->get_meta('_delivery_date');
    $time = $order->get_meta('_delivery_time');

    if (!$date && !$time) return;

    echo '<div style=" display: inline-block; margin-top:20px">';
    echo '<h3 style="margin-bottom: 20px">Время и дата доставки</h3>';

    if ($date) {
        echo '<div><strong>Дата:</strong> ' . esc_html($date) . '</div>';
    }

    if ($time) {
        echo '<div><strong>Время:</strong> ' . esc_html($time) . '</div>';
    }

    echo '</div>';
});

add_action('woocommerce_thankyou', function($order_id){

    if (!$order_id) return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    $date = $order->get_meta('_delivery_date');
    $time = $order->get_meta('_delivery_time');

    if (!$date && !$time) return;

    echo '<div class="uk-margin-top">';
    

    echo '<h3 class="uk-card-title">Доставка</h3>';

    if ($date) {
        echo '<p class="uk-margin-small-bottom"><strong>Дата:</strong> ' . esc_html($date) . '</p>';
    }

    if ($time) {
        echo '<p class="uk-margin-small-top"><strong>Время:</strong> ' . esc_html($time) . '</p>';
    }

    echo '</div>';

}, 20);
