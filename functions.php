<?php
/**
 * functions.php
 * Child Theme Functions
 */

// 1. Аватар 
add_action( 'woocommerce_account_content', 'storefront_myaccount_customer_avatar', 5 );
function storefront_myaccount_customer_avatar() {
     $current_user = wp_get_current_user();
     echo '<div class="myaccount_avatar">' . get_avatar( $current_user->user_email, 72, '', $current_user->display_name ) . '</div>';
}
// 2. Удаляем неиспользуемое 
remove_action('wp_head', 'rest_output_link_wp_head');
remove_action('wp_head', 'wp_oembed_add_discovery_links');
remove_action('wp_head', 'wp_generator');

// 3. Загружаем модули
require_once get_stylesheet_directory() . '/inc/core/loader.php';