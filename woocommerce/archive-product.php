<?php

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

/**
 * Hook: woocommerce_before_main_content.
 *
 * @hooked woocommerce_output_content_wrapper - 10 (outputs opening divs for the content)
 * @hooked woocommerce_breadcrumb - 20
 * @hooked WC_Structured_Data::generate_website_data() - 30
 */
do_action( 'woocommerce_before_main_content' );

/**
 * Hook: woocommerce_shop_loop_header.
 *
 * @since 8.6.0
 *
 * @hooked woocommerce_product_taxonomy_archive_header - 10
 */
do_action( 'woocommerce_shop_loop_header' );

if ( woocommerce_product_loop() ) {

	/**
	 * Hook: woocommerce_before_shop_loop.
	 *
	 * @hooked woocommerce_output_all_notices - 10
	 * @hooked woocommerce_result_count - 20
	 * @hooked woocommerce_catalog_ordering - 30
	 */
	do_action( 'woocommerce_before_shop_loop' );
?>
<div id="catalog-filter" class="uk-grid-small uk-margin-bottom" uk-scrollspy="cls:uk-animation-fade" uk-grid>

  <!-- Вид цветка -->
  <div class="filter" data-filter="flower_type">

    <button class="filter-btn uk-button uk-button-small uk-button-default" type="button">
      Все цветы
    </button>

    <div uk-dropdown="mode: click; offset: 10">
      <ul class="uk-nav uk-dropdown-nav">

        <li>
          <button type="button" class="uk-button uk-button-text" data-value="">
            Все цветы
          </button>
        </li>

        <li>
          <button type="button" class="uk-button uk-button-text" data-value="roses">
            Розы
          </button>
        </li>

        <li>
          <button type="button" class="uk-button uk-button-text" data-value="peonies">
            Пионы
          </button>
        </li>

      </ul>
    </div>

  </div>

  <!-- Цвет -->
  <div class="filter" data-filter="color">

    <button class="filter-btn uk-button uk-button-small uk-button-default" type="button">
      Любой цвет
    </button>

    <div uk-dropdown="mode: click; offset: 10">
      <ul class="uk-nav uk-dropdown-nav">

        <li>
          <button type="button" class="uk-button uk-button-text" data-value="">Любой цвет</button>
        </li>

        <li>
          <button type="button" class="uk-button uk-button-text" data-value="red">Красный</button>
        </li>

        <li>
          <button type="button" class="uk-button uk-button-text" data-value="white">Белый</button>
        </li>

      </ul>
    </div>

  </div>

  <!-- Цена -->
  <div class="filter" data-filter="price">

    <button class="filter-btn uk-button uk-button-small uk-button-default" type="button">
      Любая цена
    </button>

    <div uk-dropdown="mode: click; offset: 10">
      <ul class="uk-nav uk-dropdown-nav">

        <li>
          <button type="button" class="uk-button uk-button-text" data-value="">Любая цена</button>
        </li>

        <li>
          <button type="button" class="uk-button uk-button-text" data-value="0-3000">До 3000</button>
        </li>

        <li>
          <button type="button" class="uk-button uk-button-text" data-value="3000-6000">3000–6000</button>
        </li>

        <li>
          <button type="button" class="uk-button uk-button-text" data-value="6000-10000">6000+</button>
        </li>

      </ul>
    </div>

  </div>

</div>
	
	<div id="products-container" uk-scrollspy="target: .product; cls: uk-animation-fade; delay: 120">	
<?php

	woocommerce_product_loop_start();

	if ( wc_get_loop_prop( 'total' ) ) {
		while ( have_posts() ) {
			the_post();

			/**
			 * Hook: woocommerce_shop_loop.
			 */
			do_action( 'woocommerce_shop_loop' );

			wc_get_template_part( 'content', 'product' );
		}
	}

	woocommerce_product_loop_end();
  ?>
</div>
<?php
	/**
	 * Hook: woocommerce_after_shop_loop.
	 *
	 * @hooked woocommerce_pagination - 10
	 */
	do_action( 'woocommerce_after_shop_loop' );
} else {
	/**
	 * Hook: woocommerce_no_products_found.
	 *
	 * @hooked wc_no_products_found - 10
	 */
	do_action( 'woocommerce_no_products_found' );
}

/**
 * Hook: woocommerce_after_main_content.
 *
 * @hooked woocommerce_output_content_wrapper_end - 10 (outputs closing divs for the content)
 */
do_action( 'woocommerce_after_main_content' );

/**
 * Hook: woocommerce_sidebar.
 *
 * @hooked woocommerce_get_sidebar - 10
 */
do_action( 'woocommerce_sidebar' );

get_footer( 'shop' );
