<?php
/**
 * WooCommerce Checkout кастомизация
 */
 
 
// ============================
// Загрузка Checkout UI скрипта
// ============================

add_action('wp_enqueue_scripts', function () {

    if (!is_checkout() || is_admin()) return;

    wp_enqueue_script(
        'checkout-ar-ui',
        get_stylesheet_directory_uri() . '/inc/checkout/checkout-ar-ui.js',
        ['jquery'],
        filemtime(get_stylesheet_directory() . '/inc/checkout/checkout-ar-ui.js'),
        true
    );

}, 30);

// =========================
// HELPERS
// =========================

function my_get_delivery_zone_label($zone) {
    $map = [
        'mkad'     => 'Москва (в пределах МКАД)',
        'out_mkad' => 'Москва (за МКАД)',
    ];

    return $map[$zone] ?? $zone;
}

// =========================
// CHECKOUT FIELDS
// =========================

add_filter('woocommerce_checkout_fields', function($fields) {

    // Удаляем лишние поля
    unset($fields['billing']['billing_state']);
    unset($fields['billing']['billing_country']);
    unset($fields['billing']['billing_postcode']);
    
    unset($fields['shipping']['shipping_country']);
    unset($fields['shipping']['shipping_state']);
    unset($fields['shipping']['shipping_postcode']);

    // Добавляем зону доставки
    $fields['billing']['delivery_zone'] = [
        'type'     => 'select',
        'label'    => 'Зона доставки',
        'required' => true,
        'class'    => ['form-row-wide'],
        'default'  => 'mkad',
        'priority' => 120,
        'options'  => [
            ''         => 'Выберите зону',
            'mkad'     => 'Москва (в пределах МКАД)',
            'out_mkad' => 'Москва (за МКАД)',
        ],
    ];

    return $fields;
});

// =========================
// DEFAULT COUNTRY
// =========================

add_filter('default_checkout_billing_country', function() {
    return 'RU';
});

add_filter('default_checkout_shipping_country', function() {
    return 'RU';
});

add_filter('woocommerce_countries_allowed_countries', function($countries) {
    return ['RU' => 'Russia'];
});

// =========================
// DELIVERY COST
// =========================

add_action('woocommerce_cart_calculate_fees', function($cart) {

    if (is_admin() && !defined('DOING_AJAX')) return;

    // правильно получаем значение
    $zone = WC()->checkout()->get_value('delivery_zone');

    if (!$zone) return;

    if ($zone === 'out_mkad') {
        $cart->add_fee('Доставка за МКАД', 500);
    }

    // mkad = бесплатно

});

// =========================
// SAVE ORDER META
// =========================

add_action('woocommerce_checkout_create_order', function($order, $data) {

    $zone = WC()->checkout()->get_value('delivery_zone');

    if ($zone) {
        $order->update_meta_data('Зона доставки', $zone);
    }

}, 10, 2);

// =========================
// SHOW IN ADMIN
// =========================

add_action('woocommerce_admin_order_data_after_billing_address', function($order){

    $zone = $order->get_meta('Зона доставки');

    if ($zone) {
        echo '<p><strong>Зона доставки:</strong> ' . esc_html(my_get_delivery_zone_label($zone)) . '</p>';
    }

});
