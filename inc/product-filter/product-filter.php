<?php

defined('ABSPATH') || exit;

remove_action('woocommerce_before_shop_loop', 'woocommerce_result_count', 20);
remove_action('woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30);

/**
 * Attribute terms used by published products. These values are the single
 * source of truth for the filter controls rendered in archive-product.php.
 */
function mospal_product_filter_terms(string $taxonomy): array {
    if (!taxonomy_exists($taxonomy)) {
        return [];
    }

    $args = [
        'taxonomy' => $taxonomy,
        'hide_empty' => true,
        'orderby' => 'name',
        'order' => 'ASC',
    ];

    // On category and attribute archives only offer values that are assigned
    // to products in the current archive, not unrelated catalogue terms.
    $queried_object = get_queried_object();
    if ($queried_object instanceof WP_Term && is_object_in_taxonomy('product', $queried_object->taxonomy)) {
        $object_ids = get_objects_in_term($queried_object->term_id, $queried_object->taxonomy);
        if (is_wp_error($object_ids) || !$object_ids) {
            return [];
        }
        $args['object_ids'] = array_map('absint', $object_ids);
    }

    $terms = get_terms($args);

    return is_wp_error($terms) ? [] : $terms;
}

function mospal_product_filter_value(array $source, string $key): string {
    return isset($source[$key]) && is_scalar($source[$key])
        ? sanitize_title(wp_unslash((string) $source[$key]))
        : '';
}

function mospal_product_filter_price_range(array $source): ?array {
    $value = isset($source['price']) && is_scalar($source['price'])
        ? sanitize_text_field(wp_unslash((string) $source['price']))
        : '';

    if (!$value || !preg_match('/^(\d+)-(\d*)$/', $value, $matches)) {
        return null;
    }

    $min = (int) $matches[1];
    $max = $matches[2] === '' ? 999999 : (int) $matches[2];

    return $max >= $min ? [$min, $max] : null;
}

function mospal_product_filter_queries(array $source): array {
    $tax_query = [];
    $meta_query = [];

    foreach (['color' => 'pa_color', 'flower_type' => 'pa_flower_type'] as $key => $taxonomy) {
        $value = mospal_product_filter_value($source, $key);
        if ($value && taxonomy_exists($taxonomy)) {
            $tax_query[] = [
                'taxonomy' => $taxonomy,
                'field' => 'slug',
                'terms' => [$value],
            ];
        }
    }

    if ($price = mospal_product_filter_price_range($source)) {
        $meta_query[] = [
            'key' => '_price',
            'value' => $price,
            'compare' => 'BETWEEN',
            'type' => 'NUMERIC',
        ];
    }

    return [$tax_query, $meta_query];
}

add_action('pre_get_posts', function(WP_Query $query): void {
    if (is_admin() || !$query->is_main_query() || (!is_shop() && !is_product_taxonomy())) {
        return;
    }

    [$filter_tax_query, $filter_meta_query] = mospal_product_filter_queries($_GET);

    if ($filter_tax_query) {
        $query->set('tax_query', array_merge((array) $query->get('tax_query'), $filter_tax_query));
    }
    if ($filter_meta_query) {
        $query->set('meta_query', array_merge((array) $query->get('meta_query'), $filter_meta_query));
    }
});

add_action('wp_enqueue_scripts', function(): void {

    if (is_shop() || is_product_taxonomy()) {

        $script_path = get_stylesheet_directory() . '/inc/product-filter/product-filter.js';
        $queried_object = get_queried_object();
        $context_taxonomy = '';
        $context_term = '';

        if ($queried_object instanceof WP_Term && is_object_in_taxonomy('product', $queried_object->taxonomy)) {
            $context_taxonomy = $queried_object->taxonomy;
            $context_term = $queried_object->slug;
        }

        wp_enqueue_script(
            'mospal-filter',
            get_stylesheet_directory_uri() . '/inc/product-filter/product-filter.js',
            [],
            file_exists($script_path) ? (string) filemtime($script_path) : null,
            true
        );

        wp_localize_script('mospal-filter', 'mospal', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mospal_product_filter'),
            'contextTaxonomy' => $context_taxonomy,
            'contextTerm' => $context_term,
        ]);
    }
});

add_action('wp_ajax_filter_products', 'mospal_filter_products_ajax');
add_action('wp_ajax_nopriv_filter_products', 'mospal_filter_products_ajax');

function mospal_filter_products_ajax(): void {
    check_ajax_referer('mospal_product_filter', 'nonce');

    [$tax_query, $meta_query] = mospal_product_filter_queries($_POST);

    $context_taxonomy = mospal_product_filter_value($_POST, 'context_taxonomy');
    $context_term = mospal_product_filter_value($_POST, 'context_term');
    if ($context_taxonomy && $context_term && taxonomy_exists($context_taxonomy)
        && is_object_in_taxonomy('product', $context_taxonomy)) {
        $tax_query[] = [
            'taxonomy' => $context_taxonomy,
            'field' => 'slug',
            'terms' => [$context_term],
        ];
    }

    $order_by = isset($_POST['orderby']) && is_scalar($_POST['orderby'])
        ? wc_clean(wp_unslash((string) $_POST['orderby']))
        : '';
    
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
    $args['tax_query'] = array_merge(WC()->query->get_tax_query(), $tax_query);
    $args['meta_query'] = array_merge(WC()->query->get_meta_query(), $meta_query);

    $query = new WP_Query($args);

    if ($query->have_posts()) {

        woocommerce_product_loop_start();

        while ($query->have_posts()) {
            $query->the_post();
            wc_get_template_part('content', 'product');
        }

        woocommerce_product_loop_end();

    } else {
        echo '<p class="woocommerce-info">' . esc_html__('Ничего не найдено', 'yootheme-ar') . '</p>';
    }

    wp_reset_postdata();
    wp_die();
}
