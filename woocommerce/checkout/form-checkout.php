<style>
.woocommerce-checkout-review-order button {
    width: auto;
}
@media (min-width: 1200px) {
    #order_review_heading, .woocommerce-checkout-review-order {
        width: 100%;
        float: unset;
    }
}
.woocommerce-invalid input,
.woocommerce-invalid select {
    border-color: #e53935;
}
</style>

<form name="checkout" method="post"
      class="checkout woocommerce-checkout"
      action="<?php echo esc_url( wc_get_checkout_url() ); ?>"
      enctype="multipart/form-data">

<div class="uk-container uk-container-small">

    <!-- NAV -->
    <ul class="uk-subnav uk-subnav-pill uk-flex-center uk-margin-large-bottom"
        uk-switcher="connect: #checkout-switcher">

        <li class="uk-active"><a href="#">Контакты</a></li>
        <li><a href="#">Доставка</a></li>
        <li><a href="#">AR</a></li>
        <li><a href="#">Оплата</a></li>

    </ul>

    <!-- SWITCHER -->
    <ul id="checkout-switcher" class="uk-switcher uk-margin">

        <!-- STEP 0: BILLING -->
        <li>
            <?php do_action( 'woocommerce_checkout_billing' ); ?>
            <!-- CHECKBOX -->
            <p class="form-row form-row-wide uk-margin-top">
                <label>
                    <input type="checkbox"
                           id="ship-to-different-address-checkbox"
                           name="ship_to_different_address"
                           value="1">
                    Доставить на другой адрес?
                </label>
            </p>
            <button type="button"
                    class="uk-button uk-button-primary uk-margin-medium-top"
                    data-next>
                Далее
            </button>
        </li>

        <!-- STEP 1: DELIVERY -->
        <li>



            <!-- SHIPPING (INLINE) -->
            <div id="shipping-fields" style="display:none;">
                <?php do_action( 'woocommerce_checkout_shipping' ); ?>
            </div>

            <!-- SHIPPING METHODS -->
            <div class="uk-margin-top">
                <?php wc_cart_totals_shipping_html(); ?>
            </div>
            
            <div uk-grid>

                <!-- DELIVERY DATE -->
                <div>
                    <div class="uk-inline uk-width-medium">
                        <span class="uk-form-icon uk-form-icon-flip" uk-icon="icon: calendar"></span>
                        <input type="text"
                           placeholder="Дата доставки"
                           name="delivery_date"
                           id="delivery_date"
                           class="uk-input validate-required">
                    </div>
                </div>
    
                <!-- DELIVERY TIME -->
                <div class="uk-width-medium">
                    <select name="delivery_time" class="uk-select validate-required">
                        <option value="">Время доставки</option>
                        <option value="10-14">10:00 – 14:00</option>
                        <option value="14-18">14:00 – 18:00</option>
                        <option value="18-21">18:00 – 21:00</option>
                    </select>
                </div>
                
            </div>
            
            <div class="uk-margin-medium-top">
                <button type="button" class="uk-button uk-button-default" data-prev>
                    Назад
                </button>

                <button type="button" class="uk-button uk-button-primary" data-next>
                    Далее
                </button>
            </div>
        </li>

        <!-- STEP 2: AR -->
        <li>
            <?php if (function_exists('ar_render_checkout')) {
                ar_render_checkout();
            } ?>

            <div class="uk-margin-medium-top">
                <button type="button" class="uk-button uk-button-default" data-prev>
                    Назад
                </button>

                <button type="button" class="uk-button uk-button-primary" data-next>
                    Далее
                </button>
            </div>
        </li>

        <!-- STEP 3: REVIEW -->
        <li>

            <h3>Ваш заказ</h3>

            <div id="order_review" class="woocommerce-checkout-review-order">
                <?php do_action( 'woocommerce_checkout_order_review' ); ?>
            </div>

            <div class="uk-margin-medium-top">
                <button type="button" class="uk-button uk-button-default" data-prev>
                    Назад
                </button>
            </div>

        </li>

    </ul>

</div>
</form>
<script>
document.addEventListener("DOMContentLoaded", function () {

    const switcher = UIkit.switcher('.uk-subnav[uk-switcher]');

    function getStep() {
        return switcher.index();
    }

    function getCheckbox() {
        return document.querySelector('[name="ship_to_different_address"]');
    }

    // =========================
    // SHIPPING INLINE
    // =========================

    const shippingFields = document.getElementById('shipping-fields');

    function updateShipping() {
        const checkbox = getCheckbox();
        if (!checkbox || !shippingFields) return;

        shippingFields.style.display = checkbox.checked ? 'block' : 'none';
    }

    document.body.addEventListener('change', function(e) {
        if (e.target.name === 'ship_to_different_address') {
            updateShipping();
        }
    });

    jQuery(document.body).on('updated_checkout', updateShipping);

    // =========================
    // VALIDATION
    // =========================

    function getFieldValue(input) {

        if (input.type === 'radio') {
            const checked = document.querySelector(`[name="${input.name}"]:checked`);
            return checked ? checked.value : '';
        }

        if (input.type === 'checkbox') {
            return input.checked ? input.value : '';
        }

        return input.value || '';
    }

    function validateField(input) {

        if (!input.name) return true;

        let field =
            document.getElementById(input.name.replace('[]','') + '_field') ||
            input.closest('.form-row');

        if (!field) return true;

        // skip hidden shipping
        if (field.closest('#shipping-fields') && !getCheckbox()?.checked) {
            return true;
        }

        let value = getFieldValue(input);
        let valid = true;

        if (field.classList.contains('validate-required')) {
            if (!value) valid = false;
        }

        if (valid && field.classList.contains('validate-email')) {
            valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        }

        field.classList.toggle('woocommerce-invalid', !valid);
        field.classList.toggle('woocommerce-validated', valid);

        return valid;
    }

    function validateStep(step) {

        let inputs = [];

        if (step === 0) {
            inputs = document.querySelectorAll('[name^="billing_"]');
        }

        if (step === 1) {
            inputs = document.querySelectorAll(
                '[name^="shipping_"], [name="delivery_date"], [name="delivery_time"]'
            );
        }

        let valid = true;

        inputs.forEach(input => {
            if (!validateField(input)) valid = false;
        });

        return valid;
    }

    function scrollToError() {
        const err = document.querySelector('.woocommerce-invalid');
        if (err) {
            err.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    // =========================
    // NAVIGATION (ПРОСТО И НАДЁЖНО)
    // =========================

    function goNext() {

        const step = getStep();

        if (!validateStep(step)) {
            scrollToError();
            return;
        }

        switcher.show(step + 1);
    }

    function goPrev() {
        const step = getStep();
        switcher.show(step - 1);
    }

    document.body.addEventListener('click', function(e) {

        if (e.target.closest('[data-next]')) {
            e.preventDefault();
            goNext();
        }

        if (e.target.closest('[data-prev]')) {
            e.preventDefault();
            goPrev();
        }

    });

    // =========================
    // FLATPICKR
    // =========================

    if (typeof flatpickr !== "undefined") {
        flatpickr("#delivery_date", {
            locale: "ru",
            dateFormat: "Y-m-d",
            minDate: "today"
        });
    }

    // =========================
    // INIT
    // =========================

    setTimeout(updateShipping, 200);

});
</script>