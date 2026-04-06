<?php

remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 ); 

add_action('pre_get_posts', function($query) {

    if (!is_admin() && $query->is_main_query() && is_shop()) {

        $tax_query = [];
        $meta_query = [];

        if (!empty($_GET['color'])) {
            $tax_query[] = [
                'taxonomy' => 'pa_color',
                'field' => 'slug',
                'terms' => $_GET['color']
            ];
        }

        if (!empty($_GET['flower_type'])) {
            $tax_query[] = [
                'taxonomy' => 'pa_flower_type',
                'field' => 'slug',
                'terms' => $_GET['flower_type']
            ];
        }

        if (!empty($_GET['price'])) {
            $price = explode('-', $_GET['price']);

            $meta_query[] = [
                'key' => '_price',
                'value' => [$price[0], $price[1] ?? 999999],
                'compare' => 'BETWEEN',
                'type' => 'NUMERIC'
            ];
        }

        if ($tax_query) $query->set('tax_query', $tax_query);
        if ($meta_query) $query->set('meta_query', $meta_query);
    }

});

add_action('wp_enqueue_scripts', function() {

    if (is_shop() || is_product_category()) {

        wp_enqueue_script(
            'mospal-filter',
            get_stylesheet_directory_uri() . '/inc/product-filter/product-filter.js',
            [],
            '1.0',
            true
        );

        // передаём ajax url
        wp_localize_script('mospal-filter', 'mospal', [
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);
    }

});

add_action('wp_ajax_filter_products', 'filter_products');
add_action('wp_ajax_nopriv_filter_products', 'filter_products');
function filter_products() {

    $tax_query = [];
    $meta_query = [];

    if (!empty($_POST['color'])) {
        $tax_query[] = [
            'taxonomy' => 'pa_color',
            'field' => 'slug',
            'terms' => sanitize_text_field($_POST['color'])
        ];
    }

    if (!empty($_POST['flower_type'])) {
        $tax_query[] = [
            'taxonomy' => 'pa_flower_type',
            'field' => 'slug',
            'terms' => sanitize_text_field($_POST['flower_type'])
        ];
    }

    if (!empty($_POST['price'])) {
        $price = explode('-', $_POST['price']);

        $min = intval($price[0]);
        $max = isset($price[1]) ? intval($price[1]) : 999999;

        $meta_query[] = [
            'key' => '_price',
            'value' => [$min, $max],
            'compare' => 'BETWEEN',
            'type' => 'NUMERIC'
        ];
    }
    $order_by = isset($_GET['orderby']) ? wc_clean($_GET['orderby']) : '';
    
    $ordering_args = WC()->query->get_catalog_ordering_args($order_by);
    $args = [
        'post_type' => 'product',
        'posts_per_page' => 12,
        'post_status' => 'publish',
        'orderby' => $ordering_args['orderby'],
        'order' => $ordering_args['order'],
    ];
    
    if (!empty($ordering_args['meta_key'])) {
        $args['meta_key'] = $ordering_args['meta_key'];
    }
    if ($tax_query) $args['tax_query'] = $tax_query;
    if ($meta_query) $args['meta_query'] = $meta_query;

    $query = new WP_Query($args);

    if ($query->have_posts()) {

        woocommerce_product_loop_start();

        while ($query->have_posts()) {
            $query->the_post();
            wc_get_template_part('content', 'product');
        }

        woocommerce_product_loop_end();

    } else {
        echo '<p>Ничего не найдено</p>';
    }

    wp_reset_postdata();
    wp_die();
}