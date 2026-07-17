<?php
/**
 * Mospal flower subscriptions.
 *
 * A lightweight, GPL-compatible subscription layer for the four Mospal plans.
 * It deliberately uses regular WooCommerce products and orders: payments stay
 * in WooCommerce/PayKeeper, while subscription preferences and fulfilment live
 * in project-owned records.
 */

defined('ABSPATH') || exit;

const MOSPAL_SUBSCRIPTION_POST_TYPE = 'mospal_subscription';
const MOSPAL_DELIVERY_POST_TYPE = 'mospal_delivery';
const MOSPAL_SUBSCRIPTION_ACTION_GROUP = 'mospal-subscriptions';

/**
 * Stable business configuration. Product IDs may differ between staging and
 * production, so products are resolved by SKU rather than by post ID.
 */
function mospal_subscription_plans(): array {
    return [
        'SUB-BUD-M' => [
            'name' => 'Бутоны на районе',
            'billing' => 'month',
            'deliveries' => 'monthly',
        ],
        'SUB-BAZ-M' => [
            'name' => 'Цветочный базар',
            'billing' => 'month',
            'deliveries' => 'weekly-four',
        ],
        'SUB-SEZ-Y' => [
            'name' => 'Сезоны большого города',
            'billing' => 'year',
            'deliveries' => 'monthly-twelve',
        ],
        'SUB-MAG-Y' => [
            'name' => 'Праздничная магия',
            'billing' => 'year',
            'deliveries' => 'holidays',
        ],
    ];
}

function mospal_subscription_plan_for_product($product): ?array {
    if (!$product instanceof WC_Product) {
        return null;
    }

    $sku = strtoupper(trim((string) $product->get_sku()));
    $plans = mospal_subscription_plans();

    if (isset($plans[$sku])) {
        return $plans[$sku] + [
            'sku' => $sku,
            'product_id' => $product->get_id(),
        ];
    }

    // Products may already exist without the recommended SKU. Keep checkout
    // functional by matching the four fixed public plan names as a fallback.
    $product_name = mospal_subscription_normalize_name($product->get_name());
    foreach ($plans as $plan_sku => $plan) {
        $plan_name = mospal_subscription_normalize_name($plan['name']);
        if ($product_name === $plan_name || strpos($product_name, $plan_name) !== false) {
            return $plan + [
                'sku' => $plan_sku,
                'product_id' => $product->get_id(),
            ];
        }
    }

    return null;
}

function mospal_subscription_normalize_name(string $name): string {
    $name = html_entity_decode(wp_strip_all_tags($name), ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
    $name = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    $name = trim($name, " \t\n\r\0\x0B«»\"'“”");
    return (string) preg_replace('/\s+/u', ' ', $name);
}

/** Keep the billing period clear while products remain standard Woo products. */
function mospal_subscription_price_period_html(WC_Product $product): string {
    $plan = mospal_subscription_plan_for_product($product);
    if (!$plan) {
        return '';
    }

    if ($plan['billing'] === 'month') {
        return '<small class="mospal-subscription-price-period">/ месяц</small>';
    }

    $monthly_equivalent = (int) floor((float) $product->get_price() / 12);
    return '<small class="mospal-subscription-price-period">/ год · от ' . wp_kses_post(wc_price($monthly_equivalent)) . ' / месяц</small>';
}

add_filter('woocommerce_get_price_html', function (string $price_html, WC_Product $product): string {
    if ((is_admin() && !wp_doing_ajax()) || $price_html === '') {
        return $price_html;
    }
    return $price_html . ' ' . mospal_subscription_price_period_html($product);
}, 20, 2);

/** The period must stay visible in cart, checkout, emails and order details. */
add_filter('woocommerce_cart_item_name', function (string $name, array $cart_item): string {
    $product = $cart_item['data'] ?? null;
    return $product instanceof WC_Product ? $name . ' ' . mospal_subscription_price_period_html($product) : $name;
}, 20, 2);

add_filter('woocommerce_order_item_name', function (string $name, WC_Order_Item $item): string {
    $product = $item->get_product();
    return $product instanceof WC_Product ? $name . ' ' . mospal_subscription_price_period_html($product) : $name;
}, 20, 2);

function mospal_subscription_cart_plans(): array {
    if (!function_exists('WC') || !WC()->cart) {
        return [];
    }

    $plans = [];
    foreach (WC()->cart->get_cart() as $item) {
        $plan = mospal_subscription_plan_for_product($item['data'] ?? null);
        if ($plan) {
            $plans[$plan['sku']] = $plan;
        }
    }

    return array_values($plans);
}

function mospal_subscription_cart_plan(): ?array {
    $plans = mospal_subscription_cart_plans();
    return count($plans) === 1 ? $plans[0] : null;
}

function mospal_is_subscription_cart(): bool {
    return (bool) mospal_subscription_cart_plan();
}

function mospal_subscription_keep_only_cart_item(string $keep_key): void {
    if (!function_exists('WC') || !WC()->cart) {
        return;
    }
    foreach (WC()->cart->get_cart() as $key => $item) {
        if ($key !== $keep_key && mospal_subscription_plan_for_product($item['data'] ?? null)) {
            WC()->cart->remove_cart_item($key);
        }
    }
}

/** A newly selected plan replaces any older flower plan in the cart. */
add_action('woocommerce_add_to_cart', function (string $cart_item_key): void {
    if (!function_exists('WC') || !WC()->cart) {
        return;
    }

    $added_item = WC()->cart->get_cart_item($cart_item_key);
    $added_plan = mospal_subscription_plan_for_product($added_item['data'] ?? null);
    if (!$added_plan) {
        return;
    }

    mospal_subscription_keep_only_cart_item($cart_item_key);
}, 20, 1);

/** Repair carts saved before the one-plan rule was introduced. */
add_action('woocommerce_cart_loaded_from_session', function (WC_Cart $cart): void {
    $subscription_keys = [];
    foreach ($cart->get_cart() as $key => $item) {
        if (mospal_subscription_plan_for_product($item['data'] ?? null)) {
            $subscription_keys[] = $key;
        }
    }
    if (count($subscription_keys) > 1) {
        mospal_subscription_keep_only_cart_item((string) end($subscription_keys));
    }
}, 20);

function mospal_subscription_styles(): array {
    return [
        'minimalism' => 'Минимализм',
        'eclectic'   => 'Эклектика',
        'pastel'     => 'Пастельные оттенки',
    ];
}

function mospal_subscription_time_options(): array {
    return [
        ''      => 'Время доставки',
        '10-14' => '10:00–14:00',
        '14-18' => '14:00–18:00',
        '18-21' => '18:00–21:00',
    ];
}

function mospal_subscription_checkout_value(string $key): string {
    if (function_exists('WC') && WC()->checkout()) {
        return (string) WC()->checkout()->get_value($key);
    }

    return '';
}

function mospal_subscription_next_fixed_date(int $month, int $day): string {
    $today = new DateTimeImmutable('today', wp_timezone());
    $date = $today->setDate((int) $today->format('Y'), $month, $day);
    if ($date < $today) {
        $date = $date->modify('+1 year');
    }
    return $date->format('Y-m-d');
}

function mospal_subscription_next_easter_date(): string {
    $today = new DateTimeImmutable('today', wp_timezone());
    $date = mospal_subscription_orthodox_easter((int) $today->format('Y'));
    if ($date < $today) {
        $date = mospal_subscription_orthodox_easter((int) $today->format('Y') + 1);
    }
    return $date->format('Y-m-d');
}

function mospal_subscription_schedule_template(array $plan): array {
    if ($plan['deliveries'] === 'holidays') {
        return [
            ['kind' => 'valentine', 'label' => '14 февраля', 'date' => mospal_subscription_next_fixed_date(2, 14), 'fixed' => true],
            ['kind' => 'march-8', 'label' => '8 марта', 'date' => mospal_subscription_next_fixed_date(3, 8), 'fixed' => true],
            ['kind' => 'easter', 'label' => 'Пасха', 'date' => mospal_subscription_next_easter_date(), 'fixed' => true],
            ['kind' => 'birthday', 'label' => 'День рождения', 'date' => '', 'fixed' => false],
            ['kind' => 'new-year', 'label' => 'Новый год', 'date' => mospal_subscription_next_fixed_date(12, 31), 'fixed' => true],
            ['kind' => 'anniversary', 'label' => 'Важная дата / годовщина', 'date' => '', 'fixed' => false],
        ];
    }

    $count = 1;
    $unit = 'month';
    $step = 1;
    $kind = 'monthly';
    if ($plan['deliveries'] === 'weekly-four') {
        $count = 4;
        $unit = 'day';
        $step = 7;
        $kind = 'weekly';
    } elseif ($plan['deliveries'] === 'monthly-twelve') {
        $count = 12;
        $kind = 'seasonal';
    }

    $schedule = [];
    for ($i = 0; $i < $count; $i++) {
        $schedule[] = [
            'kind' => $kind . '-' . ($i + 1),
            'label' => $count === 1 ? 'Доставка' : 'Доставка ' . ($i + 1),
            'date' => '',
            'fixed' => false,
            'offset' => $i * $step,
            'unit' => $unit,
        ];
    }
    return $schedule;
}

function mospal_subscription_posted_schedule(array $plan): array {
    $template = mospal_subscription_schedule_template($plan);
    $raw = isset($_POST['mospal_subscription_schedule']) && is_array($_POST['mospal_subscription_schedule'])
        ? wp_unslash($_POST['mospal_subscription_schedule'])
        : [];

    foreach ($template as $index => &$delivery) {
        $posted = isset($raw[$index]) && is_array($raw[$index]) ? $raw[$index] : [];
        if (empty($delivery['fixed'])) {
            $delivery['date'] = sanitize_text_field($posted['date'] ?? $delivery['date']);
        }
        $delivery['time'] = sanitize_text_field($posted['time'] ?? '');
    }
    unset($delivery);

    return $template;
}

/** Render only the preferences needed by the selected plan. */
function mospal_subscription_render_checkout_fields(): void {
    $plan = mospal_subscription_cart_plan();
    if (!$plan) {
        return;
    }

    $style = mospal_subscription_checkout_value('mospal_subscription_style');
    $schedule = mospal_subscription_posted_schedule($plan);
    ?>
    <section class="uk-card uk-card-default uk-card-small uk-padding-small uk-margin-bottom" aria-labelledby="mospal-subscription-title">
        <h3 id="mospal-subscription-title" class="uk-card-title uk-margin-small-bottom">
            Подписка «<?php echo esc_html($plan['name']); ?>»
        </h3>

        <div class="uk-margin-small-bottom uk-text-meta">
            <?php if ($plan['deliveries'] === 'weekly-four') : ?>
                Четыре еженедельные доставки. Выбери первую дату — остальные заполнятся автоматически, но их можно изменить.
            <?php elseif ($plan['deliveries'] === 'monthly-twelve') : ?>
                Двенадцать сезонных доставок. Даты заполнятся от первой и останутся доступными для изменения.
            <?php elseif ($plan['deliveries'] === 'holidays') : ?>
                Шесть праздничных доставок с отдельными датами и интервалами.
            <?php else : ?>
                Один свежий букет каждый месяц.
            <?php endif; ?>
        </div>

        <div class="uk-margin-small form-row validate-required">
            <label class="uk-form-label" for="mospal_subscription_style">Стиль букета</label>
            <select id="mospal_subscription_style" name="mospal_subscription_style" class="uk-select validate-required">
                <option value="">Выберите стиль</option>
                <?php foreach (mospal_subscription_styles() as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($style, $value); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mospal-subscription-schedule uk-margin-top">
            <?php foreach ($schedule as $index => $delivery) : ?>
                <?php $date_id = 'mospal_subscription_delivery_' . $index . '_date'; ?>
                <div class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-small-bottom">
                    <h4 class="uk-margin-small-bottom"><?php echo esc_html($delivery['label']); ?></h4>
                    <div class="uk-grid-small" uk-grid>
                        <div class="uk-width-1-2@s form-row validate-required">
                            <label class="uk-form-label" for="<?php echo esc_attr($date_id); ?>">Дата</label>
                            <input
                                type="text"
                                id="<?php echo esc_attr($date_id); ?>"
                                name="mospal_subscription_schedule[<?php echo esc_attr($index); ?>][date]"
                                value="<?php echo esc_attr($delivery['date']); ?>"
                                class="uk-input validate-required"
                                placeholder="Дата доставки"
                                data-mospal-delivery-date
                                data-offset="<?php echo esc_attr($delivery['offset'] ?? 0); ?>"
                                data-offset-unit="<?php echo esc_attr($delivery['unit'] ?? 'day'); ?>"
                                <?php echo !empty($delivery['fixed']) ? 'readonly data-fixed="1"' : ''; ?>
                            >
                        </div>
                        <div class="uk-width-1-2@s form-row validate-required">
                            <label class="uk-form-label" for="mospal_subscription_delivery_<?php echo esc_attr($index); ?>_time">Интервал</label>
                            <select
                                id="mospal_subscription_delivery_<?php echo esc_attr($index); ?>_time"
                                name="mospal_subscription_schedule[<?php echo esc_attr($index); ?>][time]"
                                class="uk-select validate-required"
                                data-mospal-delivery-time
                            >
                                <?php foreach (mospal_subscription_time_options() as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($delivery['time'], $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}
add_action('mospal_checkout_subscription_fields', 'mospal_subscription_render_checkout_fields');

function mospal_subscription_posted_value(string $key): string {
    return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
}

function mospal_subscription_valid_date(string $date, bool $must_be_future = false): bool {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
    $errors = DateTimeImmutable::getLastErrors();
    if (!$parsed || ($errors && ($errors['warning_count'] || $errors['error_count']))) {
        return false;
    }

    return !$must_be_future || $parsed >= (new DateTimeImmutable('today', wp_timezone()));
}

add_filter('woocommerce_checkout_registration_required', function (bool $required): bool {
    return $required || mospal_is_subscription_cart();
});

add_action('woocommerce_after_checkout_validation', function (array $data, WP_Error $errors): void {
    $plans = mospal_subscription_cart_plans();
    if (count($plans) > 1) {
        $errors->add('mospal_subscription_multiple_plans', 'В одном заказе можно оформить только одну цветочную подписку.');
        return;
    }

    $plan = mospal_subscription_cart_plan();
    if (!$plan) {
        return;
    }

    $style = mospal_subscription_posted_value('mospal_subscription_style');
    if (!isset(mospal_subscription_styles()[$style])) {
        $errors->add('mospal_subscription_style', 'Выберите стиль букета для подписки.');
    }

    $schedule = mospal_subscription_posted_schedule($plan);
    $previous_date = null;
    foreach ($schedule as $index => $delivery) {
        if (!mospal_subscription_valid_date($delivery['date'], true)) {
            $errors->add('mospal_subscription_delivery_date_' . $index, 'Укажите будущую дату для «' . $delivery['label'] . '».');
            continue;
        }
        if (!isset(mospal_subscription_time_options()[$delivery['time']]) || $delivery['time'] === '') {
            $errors->add('mospal_subscription_delivery_time_' . $index, 'Выберите интервал для «' . $delivery['label'] . '».');
        }
        $date = new DateTimeImmutable($delivery['date'], wp_timezone());
        if ($plan['deliveries'] !== 'holidays' && $previous_date && $date <= $previous_date) {
            $errors->add('mospal_subscription_delivery_order_' . $index, 'Даты доставок должны идти по порядку.');
        }
        $previous_date = $date;
    }
}, 20, 2);

function mospal_subscription_order_plan(WC_Order $order): ?array {
    foreach ($order->get_items('line_item') as $item) {
        $plan = mospal_subscription_plan_for_product($item->get_product());
        if ($plan) {
            return $plan;
        }
    }

    return null;
}

function mospal_subscription_save_order_preferences(WC_Order $order): void {
    $plan = mospal_subscription_cart_plan();
    if (!$plan) {
        return;
    }

    $order->update_meta_data('_mospal_subscription_plan', $plan['sku']);
    $order->update_meta_data('_mospal_subscription_style', mospal_subscription_posted_value('mospal_subscription_style'));
    $schedule = mospal_subscription_posted_schedule($plan);
    $order->update_meta_data('_mospal_subscription_schedule', wp_json_encode($schedule, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    if (!empty($schedule[0]['time'])) {
        $order->update_meta_data('_mospal_subscription_delivery_time', $schedule[0]['time']);
    }
    if ($plan['deliveries'] !== 'holidays' && !empty($schedule[0]['date'])) {
        $order->update_meta_data('_mospal_subscription_first_delivery_date', $schedule[0]['date']);
    }
    foreach ($schedule as $delivery) {
        if (in_array($delivery['kind'], ['birthday', 'anniversary'], true)) {
            $order->update_meta_data('_mospal_subscription_' . $delivery['kind'], $delivery['date']);
        }
    }
}
add_action('woocommerce_checkout_create_order', function (WC_Order $order): void {
    mospal_subscription_save_order_preferences($order);
}, 40);

function mospal_subscription_register_post_types(): void {
    register_post_type(MOSPAL_SUBSCRIPTION_POST_TYPE, [
        'labels' => [
            'name' => 'Подписки на цветы',
            'singular_name' => 'Подписка на цветы',
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => 'woocommerce',
        'supports' => ['title'],
        'capability_type' => 'shop_order',
        'map_meta_cap' => true,
    ]);

    register_post_type(MOSPAL_DELIVERY_POST_TYPE, [
        'labels' => [
            'name' => 'Доставки по подписке',
            'singular_name' => 'Доставка по подписке',
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => 'woocommerce',
        'supports' => ['title'],
        'capability_type' => 'shop_order',
        'map_meta_cap' => true,
    ]);
}
add_action('init', 'mospal_subscription_register_post_types');

function mospal_subscription_meta(int $subscription_id, string $key, string $default = ''): string {
    return (string) get_post_meta($subscription_id, $key, true) ?: $default;
}

function mospal_subscription_add_interval(DateTimeImmutable $date, string $billing): DateTimeImmutable {
    return $date->modify($billing === 'year' ? '+1 year' : '+1 month');
}

function mospal_subscription_create_delivery(int $subscription_id, int $order_id, DateTimeImmutable $date, string $time, string $kind): int {
    $existing = get_posts([
        'post_type' => MOSPAL_DELIVERY_POST_TYPE,
        'post_status' => 'any',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_query' => [
            ['key' => '_mospal_subscription_id', 'value' => $subscription_id],
            ['key' => '_mospal_delivery_date', 'value' => $date->format('Y-m-d')],
            ['key' => '_mospal_delivery_kind', 'value' => $kind],
        ],
    ]);
    if ($existing) {
        return (int) $existing[0];
    }

    $delivery_id = wp_insert_post([
        'post_type' => MOSPAL_DELIVERY_POST_TYPE,
        'post_status' => 'publish',
        'post_title' => sprintf('%s — %s', $date->format('d.m.Y'), $kind),
    ]);
    if (is_wp_error($delivery_id) || !$delivery_id) {
        return 0;
    }

    update_post_meta($delivery_id, '_mospal_subscription_id', $subscription_id);
    update_post_meta($delivery_id, '_mospal_order_id', $order_id);
    update_post_meta($delivery_id, '_mospal_delivery_date', $date->format('Y-m-d'));
    update_post_meta($delivery_id, '_mospal_delivery_time', $time);
    update_post_meta($delivery_id, '_mospal_delivery_kind', $kind);
    update_post_meta($delivery_id, '_mospal_delivery_status', 'scheduled');

    return (int) $delivery_id;
}

/** Orthodox Easter date in the Gregorian calendar. */
function mospal_subscription_orthodox_easter(int $year): DateTimeImmutable {
    $a = $year % 19;
    $b = $year % 7;
    $c = $year % 4;
    $d = (19 * $a + 15) % 30;
    $e = (2 * $b + 4 * $c - $d + 34) % 7;
    $month = intdiv($d + $e + 114, 31);
    $day = (($d + $e + 114) % 31) + 1;

    return (new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), new DateTimeZone('UTC')))
        ->modify('+13 days')
        ->setTimezone(wp_timezone());
}

function mospal_subscription_personal_date(string $date, int $year): ?DateTimeImmutable {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
    return $parsed ? $parsed->setDate($year, (int) $parsed->format('m'), (int) $parsed->format('d')) : null;
}

function mospal_subscription_saved_schedule(int $subscription_id): array {
    $json = mospal_subscription_meta($subscription_id, '_mospal_subscription_schedule');
    $schedule = $json ? json_decode($json, true) : null;
    return is_array($schedule) ? $schedule : [];
}

function mospal_subscription_shift_delivery_date(
    array $delivery,
    DateTimeImmutable $base_cycle,
    DateTimeImmutable $target_cycle,
    string $billing
): ?DateTimeImmutable {
    if (empty($delivery['date']) || !mospal_subscription_valid_date((string) $delivery['date'])) {
        return null;
    }

    $date = new DateTimeImmutable((string) $delivery['date'], wp_timezone());
    if ($billing === 'year') {
        $years = (int) $target_cycle->format('Y') - (int) $base_cycle->format('Y');
        if (($delivery['kind'] ?? '') === 'easter') {
            return mospal_subscription_orthodox_easter((int) $date->format('Y') + $years);
        }
        return $years > 0 ? $date->modify('+' . $years . ' years') : $date;
    }

    $months = ((int) $target_cycle->format('Y') - (int) $base_cycle->format('Y')) * 12
        + ((int) $target_cycle->format('n') - (int) $base_cycle->format('n'));
    return $months > 0 ? $date->modify('+' . $months . ' months') : $date;
}

function mospal_subscription_create_cycle_deliveries(int $subscription_id, int $order_id, DateTimeImmutable $cycle_start): void {
    $sku = mospal_subscription_meta($subscription_id, '_mospal_subscription_plan');
    $plan = mospal_subscription_plans()[$sku] ?? null;
    if (!$plan) {
        return;
    }

    $schedule = mospal_subscription_saved_schedule($subscription_id);
    if ($schedule) {
        $base_cycle_value = mospal_subscription_meta($subscription_id, '_mospal_subscription_cycle_start');
        $base_cycle = $base_cycle_value && mospal_subscription_valid_date($base_cycle_value)
            ? new DateTimeImmutable($base_cycle_value, wp_timezone())
            : $cycle_start;
        foreach ($schedule as $delivery) {
            $date = mospal_subscription_shift_delivery_date($delivery, $base_cycle, $cycle_start, $plan['billing']);
            $time = sanitize_text_field((string) ($delivery['time'] ?? ''));
            $kind = sanitize_key((string) ($delivery['kind'] ?? 'delivery'));
            if ($date && $time) {
                mospal_subscription_create_delivery($subscription_id, $order_id, $date, $time, $kind);
            }
        }
        return;
    }

    // Backward-compatible fallback for subscriptions created before individual
    // delivery dates were stored.
    $time = mospal_subscription_meta($subscription_id, '_mospal_subscription_delivery_time');
    if ($plan['deliveries'] === 'monthly') {
        mospal_subscription_create_delivery($subscription_id, $order_id, $cycle_start, $time, 'monthly');
        return;
    }
    if ($plan['deliveries'] === 'weekly-four') {
        for ($i = 0; $i < 4; $i++) {
            mospal_subscription_create_delivery($subscription_id, $order_id, $cycle_start->modify('+' . (7 * $i) . ' days'), $time, 'weekly');
        }
        return;
    }
    if ($plan['deliveries'] === 'monthly-twelve') {
        for ($i = 0; $i < 12; $i++) {
            mospal_subscription_create_delivery($subscription_id, $order_id, $cycle_start->modify('+' . $i . ' months'), $time, 'seasonal');
        }
        return;
    }

    $birthday = mospal_subscription_meta($subscription_id, '_mospal_subscription_birthday');
    $anniversary = mospal_subscription_meta($subscription_id, '_mospal_subscription_anniversary');
    $end = $cycle_start->modify('+1 year');
    $dates = [];
    foreach ([$cycle_start->format('Y'), $end->format('Y')] as $year) {
        $year = (int) $year;
        $candidates = [
            'valentine' => new DateTimeImmutable($year . '-02-14', wp_timezone()),
            'march-8' => new DateTimeImmutable($year . '-03-08', wp_timezone()),
            'easter' => mospal_subscription_orthodox_easter($year),
            'new-year' => new DateTimeImmutable($year . '-12-31', wp_timezone()),
        ];
        if ($birthday_date = mospal_subscription_personal_date($birthday, $year)) {
            $candidates['birthday'] = $birthday_date;
        }
        if ($anniversary_date = mospal_subscription_personal_date($anniversary, $year)) {
            $candidates['anniversary'] = $anniversary_date;
        }
        foreach ($candidates as $kind => $date) {
            if ($date >= $cycle_start && $date < $end) {
                $dates[$kind . '-' . $date->format('Y')] = [$date, $kind];
            }
        }
    }
    usort($dates, static fn($a, $b) => $a[0] <=> $b[0]);
    foreach ($dates as [$date, $kind]) {
        mospal_subscription_create_delivery($subscription_id, $order_id, $date, $time, $kind);
    }
}

function mospal_subscription_schedule_renewal(int $subscription_id, int $timestamp): void {
    if (function_exists('as_schedule_single_action')) {
        if (!as_next_scheduled_action('mospal_subscription_renewal_due', [$subscription_id], MOSPAL_SUBSCRIPTION_ACTION_GROUP)) {
            as_schedule_single_action($timestamp, 'mospal_subscription_renewal_due', [$subscription_id], MOSPAL_SUBSCRIPTION_ACTION_GROUP);
        }
        return;
    }

    if (!wp_next_scheduled('mospal_subscription_renewal_due', [$subscription_id])) {
        wp_schedule_single_event($timestamp, 'mospal_subscription_renewal_due', [$subscription_id]);
    }
}

function mospal_subscription_unschedule_renewal(int $subscription_id): void {
    if (function_exists('as_unschedule_all_actions')) {
        as_unschedule_all_actions('mospal_subscription_renewal_due', [$subscription_id], MOSPAL_SUBSCRIPTION_ACTION_GROUP);
    }
    wp_clear_scheduled_hook('mospal_subscription_renewal_due', [$subscription_id]);
}

function mospal_subscription_create_from_paid_order(int $order_id): void {
    $order = wc_get_order($order_id);
    if (!$order || !$order->is_paid()) {
        return;
    }

    if ($subscription_id = (int) $order->get_meta('_mospal_subscription_id')) {
        if ($order->get_meta('_mospal_subscription_renewal') === 'yes') {
            mospal_subscription_complete_renewal($subscription_id, $order);
        }
        return;
    }
    if ((int) $order->get_meta('_mospal_subscription_created')) {
        return;
    }

    $plan = mospal_subscription_order_plan($order);
    if (!$plan) {
        return;
    }

    $cycle_start_value = (string) $order->get_meta('_mospal_subscription_first_delivery_date');
    $cycle_start = $cycle_start_value && mospal_subscription_valid_date($cycle_start_value)
        ? new DateTimeImmutable($cycle_start_value, wp_timezone())
        : new DateTimeImmutable('today', wp_timezone());
    $subscription_id = wp_insert_post([
        'post_type' => MOSPAL_SUBSCRIPTION_POST_TYPE,
        'post_status' => 'publish',
        'post_title' => sprintf('%s — заказ №%d', $plan['name'], $order->get_id()),
    ]);
    if (is_wp_error($subscription_id) || !$subscription_id) {
        return;
    }

    $subscription_id = (int) $subscription_id;
    $meta = [
        '_mospal_subscription_status' => 'active',
        '_mospal_subscription_plan' => $plan['sku'],
        '_mospal_subscription_product_id' => $plan['product_id'],
        '_mospal_subscription_customer_id' => $order->get_customer_id(),
        '_mospal_subscription_parent_order_id' => $order->get_id(),
        '_mospal_subscription_delivery_time' => (string) $order->get_meta('_mospal_subscription_delivery_time'),
        '_mospal_subscription_style' => (string) $order->get_meta('_mospal_subscription_style'),
        '_mospal_subscription_cycle_start' => $cycle_start->format('Y-m-d'),
        '_mospal_subscription_schedule' => (string) $order->get_meta('_mospal_subscription_schedule'),
    ];
    foreach (['birthday', 'anniversary'] as $field) {
        $value = (string) $order->get_meta('_mospal_subscription_' . $field);
        if ($value) {
            $meta['_mospal_subscription_' . $field] = $value;
        }
    }
    $next_renewal = mospal_subscription_add_interval($cycle_start, $plan['billing']);
    $meta['_mospal_subscription_next_renewal'] = $next_renewal->format('Y-m-d H:i:s');
    foreach ($meta as $key => $value) {
        update_post_meta($subscription_id, $key, $value);
    }

    $order->update_meta_data('_mospal_subscription_id', $subscription_id);
    $order->update_meta_data('_mospal_subscription_created', 'yes');
    $order->save();
    mospal_subscription_create_cycle_deliveries($subscription_id, $order->get_id(), $cycle_start);
    mospal_subscription_schedule_renewal($subscription_id, $next_renewal->getTimestamp());
}
add_action('woocommerce_payment_complete', 'mospal_subscription_create_from_paid_order');
add_action('woocommerce_order_status_processing', 'mospal_subscription_create_from_paid_order');
add_action('woocommerce_order_status_completed', 'mospal_subscription_create_from_paid_order');

function mospal_subscription_complete_renewal(int $subscription_id, WC_Order $order): void {
    if (!in_array(mospal_subscription_meta($subscription_id, '_mospal_subscription_status'), ['active', 'payment_due'], true)) {
        return;
    }
    if ($order->get_meta('_mospal_subscription_renewal_completed')) {
        return;
    }

    $sku = mospal_subscription_meta($subscription_id, '_mospal_subscription_plan');
    $plan = mospal_subscription_plans()[$sku] ?? null;
    $cycle_value = (string) $order->get_meta('_mospal_subscription_cycle_start');
    $cycle_start = $cycle_value && mospal_subscription_valid_date($cycle_value)
        ? new DateTimeImmutable($cycle_value, wp_timezone())
        : new DateTimeImmutable('today', wp_timezone());
    if (!$plan) {
        return;
    }

    mospal_subscription_create_cycle_deliveries($subscription_id, $order->get_id(), $cycle_start);
    $next_renewal = mospal_subscription_add_interval($cycle_start, $plan['billing']);
    update_post_meta($subscription_id, '_mospal_subscription_status', 'active');
    update_post_meta($subscription_id, '_mospal_subscription_next_renewal', $next_renewal->format('Y-m-d H:i:s'));
    $order->update_meta_data('_mospal_subscription_renewal_completed', 'yes');
    $order->save();
    mospal_subscription_schedule_renewal($subscription_id, $next_renewal->getTimestamp());
}

function mospal_subscription_create_renewal_order(int $subscription_id): ?WC_Order {
    $customer_id = (int) mospal_subscription_meta($subscription_id, '_mospal_subscription_customer_id');
    $product_id = (int) mospal_subscription_meta($subscription_id, '_mospal_subscription_product_id');
    $parent_order = wc_get_order((int) mospal_subscription_meta($subscription_id, '_mospal_subscription_parent_order_id'));
    $product = wc_get_product($product_id);
    if (!$customer_id || !$parent_order || !$product) {
        return null;
    }

    $order = wc_create_order(['customer_id' => $customer_id]);
    $order->add_product($product, 1);
    $order->set_address($parent_order->get_address('billing'), 'billing');
    $order->set_address($parent_order->get_address('shipping'), 'shipping');
    $order->update_meta_data('_mospal_subscription_id', $subscription_id);
    $order->update_meta_data('_mospal_subscription_renewal', 'yes');
    $cycle_start = new DateTimeImmutable(mospal_subscription_meta($subscription_id, '_mospal_subscription_next_renewal'), wp_timezone());
    $order->update_meta_data('_mospal_subscription_cycle_start', $cycle_start->format('Y-m-d'));
    $order->add_order_note('Создано автоматическое продление цветочной подписки.');
    $order->calculate_totals();
    $order->save();

    return $order;
}

add_action('mospal_subscription_renewal_due', function (int $subscription_id): void {
    if (mospal_subscription_meta($subscription_id, '_mospal_subscription_status') !== 'active') {
        return;
    }
    if ($order = mospal_subscription_create_renewal_order($subscription_id)) {
        update_post_meta($subscription_id, '_mospal_subscription_status', 'payment_due');
        update_post_meta($subscription_id, '_mospal_subscription_pending_order_id', $order->get_id());
        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();
        if (!empty($emails['WC_Email_Customer_Invoice'])) {
            $emails['WC_Email_Customer_Invoice']->trigger($order->get_id());
        }
    }
});

function mospal_subscription_account_endpoint(): void {
    add_rewrite_endpoint('mospal-subscriptions', EP_ROOT | EP_PAGES);
}
add_action('init', 'mospal_subscription_account_endpoint');
add_action('after_switch_theme', static function (): void {
    mospal_subscription_account_endpoint();
    flush_rewrite_rules();
});

add_filter('woocommerce_account_menu_items', function (array $items): array {
    $logout = $items['customer-logout'] ?? null;
    unset($items['customer-logout']);
    $items['mospal-subscriptions'] = 'Подписки на цветы';
    if ($logout !== null) {
        $items['customer-logout'] = $logout;
    }
    return $items;
});

function mospal_subscription_change_status_from_account(): void {
    if (empty($_POST['mospal_subscription_action']) || !is_user_logged_in()) {
        return;
    }
    $subscription_id = isset($_POST['subscription_id']) ? absint($_POST['subscription_id']) : 0;
    if (!$subscription_id || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'mospal_subscription_' . $subscription_id)) {
        wc_add_notice('Не удалось подтвердить действие с подпиской.', 'error');
        return;
    }
    if ((int) mospal_subscription_meta($subscription_id, '_mospal_subscription_customer_id') !== get_current_user_id()) {
        wc_add_notice('Эта подписка принадлежит другому аккаунту.', 'error');
        return;
    }

    $action = sanitize_key(wp_unslash($_POST['mospal_subscription_action']));
    if ($action === 'pause') {
        update_post_meta($subscription_id, '_mospal_subscription_status', 'paused');
        mospal_subscription_unschedule_renewal($subscription_id);
        wc_add_notice('Подписка поставлена на паузу. Менеджер свяжется с вами для переноса ближайшей доставки.', 'success');
    } elseif ($action === 'resume') {
        update_post_meta($subscription_id, '_mospal_subscription_status', 'active');
        $next = mospal_subscription_meta($subscription_id, '_mospal_subscription_next_renewal');
        $timestamp = $next ? (new DateTimeImmutable($next, wp_timezone()))->getTimestamp() : time();
        mospal_subscription_schedule_renewal($subscription_id, max(time() + MINUTE_IN_SECONDS, $timestamp));
        wc_add_notice('Подписка снова активна.', 'success');
    }
    wp_safe_redirect(wc_get_account_endpoint_url('mospal-subscriptions'));
    exit;
}
add_action('template_redirect', 'mospal_subscription_change_status_from_account');

add_action('woocommerce_account_mospal-subscriptions_endpoint', function (): void {
    $subscriptions = get_posts([
        'post_type' => MOSPAL_SUBSCRIPTION_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_key' => '_mospal_subscription_customer_id',
        'meta_value' => get_current_user_id(),
    ]);
    if (!$subscriptions) {
        echo '<p>Активных цветочных подписок пока нет.</p>';
        return;
    }
    foreach ($subscriptions as $subscription) {
        $id = $subscription->ID;
        $sku = mospal_subscription_meta($id, '_mospal_subscription_plan');
        $plan = mospal_subscription_plans()[$sku] ?? ['name' => get_the_title($id)];
        $status = mospal_subscription_meta($id, '_mospal_subscription_status');
        $next = mospal_subscription_meta($id, '_mospal_subscription_next_renewal');
        echo '<section class="uk-card uk-card-default uk-card-small uk-padding-small uk-margin-bottom">';
        echo '<h3 class="uk-card-title">' . esc_html($plan['name']) . '</h3>';
        echo '<p>Статус: ' . esc_html($status === 'paused' ? 'На паузе' : ($status === 'payment_due' ? 'Ожидает оплаты' : 'Активна')) . '</p>';
        if ($next) {
            echo '<p>Следующее продление: ' . esc_html(wp_date('d.m.Y', (new DateTimeImmutable($next, wp_timezone()))->getTimestamp())) . '</p>';
        }
        echo '<form method="post">';
        wp_nonce_field('mospal_subscription_' . $id);
        echo '<input type="hidden" name="subscription_id" value="' . esc_attr($id) . '">';
        $action = $status === 'paused' ? 'resume' : 'pause';
        $label = $status === 'paused' ? 'Возобновить' : 'Поставить на паузу';
        echo '<button class="uk-button uk-button-default" type="submit" name="mospal_subscription_action" value="' . esc_attr($action) . '">' . esc_html($label) . '</button>';
        echo '</form></section>';
    }
});
