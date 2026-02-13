<?php
/**
 * functions.php
 * Child Theme Functions
 */

// 1. Аватар (твой код без изменений)
add_action( 'woocommerce_account_content', 'storefront_myaccount_customer_avatar', 5 );
function storefront_myaccount_customer_avatar() {
     $current_user = wp_get_current_user();
     echo '<div class="myaccount_avatar">' . get_avatar( $current_user->user_email, 72, '', $current_user->display_name ) . '</div>';
}

/**
 * 🚀 GLOBAL OPTIMIZATION (NO JQUERY)
 */
add_action('wp_enqueue_scripts', 'optimize_site_assets', 100);
function optimize_site_assets() {
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('wc-blocks-style'); 
    wp_dequeue_style('classic-theme-styles');
    wp_dequeue_style('global-styles'); 

    if ( !is_page('contacts') ) {
        wp_dequeue_style('contact-form-7');
        wp_dequeue_script('contact-form-7');
        wp_dequeue_script('google-recaptcha');
    }

    if ( ! is_admin() && ! is_cart() && ! is_checkout() && ! is_account_page() && ! is_product() && ! is_shop() ) {
        wp_dequeue_script('woocommerce');
        wp_dequeue_script('wc-add-to-cart');
        wp_dequeue_script('wc-cart-fragments'); 
        wp_dequeue_script('wc-jquery-blockui'); 
        wp_dequeue_script('cmp-admin-script');
        wp_dequeue_script('cmp_admin_script'); 

        wp_deregister_script('jquery');
        wp_deregister_script('jquery-core');
        wp_deregister_script('jquery-migrate');
    }
}
remove_action('wp_head', 'rest_output_link_wp_head');
remove_action('wp_head', 'wp_oembed_add_discovery_links');
remove_action('wp_head', 'wp_generator');


/**
 * -----------------------------------------------------------
 * 🔮 AR MODULE: ASYNC UPLOAD (FIXED)
 * С твоими стилями + рабочая загрузка файла
 * -----------------------------------------------------------
 */

// 1. AJAX Обработчик (Нужен для работы загрузки)
add_action('wp_ajax_ar_async_upload', 'handle_ar_async_upload');
add_action('wp_ajax_nopriv_ar_async_upload', 'handle_ar_async_upload');

function handle_ar_async_upload() {
    check_ajax_referer('ar_upload_nonce', 'nonce'); 

    if (empty($_FILES['ar_file_upload'])) {
        wp_send_json_error('Файл не передан');
    }
    
    // Подключаем функции WP для работы с файлами
    if ( ! function_exists( 'media_handle_upload' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
    }

    $attachment_id = media_handle_upload('ar_file_upload', 0);

    if (is_wp_error($attachment_id)) {
        wp_send_json_error($attachment_id->get_error_message());
    } else {
        wp_send_json_success([
            'id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id)
        ]);
    }
}


// 2. Вывод полей в Checkout (Твои стили + AJAX логика)
add_action( 'woocommerce_after_order_notes', 'render_ar_checkout_section' );

function render_ar_checkout_section() {
    // Подготовка переменных для JS
    $ajax_url = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce('ar_upload_nonce');
    ?>
    
    <div class="uk-card uk-card-default uk-card-small uk-margin-top uk-border-rounded ar-checkout-card">
        <div class="uk-card-header uk-background-muted uk-border-rounded">
            <h3 class="uk-card-title uk-text-default uk-flex uk-flex-middle">
                <span uk-icon="icon: magic; ratio: 1.2" class="uk-margin-small-right uk-text-primary"></span>
                Добавить AR-поздравление?
            </h3>
        </div>

        <div class="uk-card-body">
            <!-- Главный чекбокс -->
            <div class="uk-margin">
                <label class="uk-flex uk-flex-middle" style="cursor: pointer;">
                    <input class="uk-checkbox uk-margin-small-right" type="checkbox" name="ar_active" id="ar_active_trigger" value="1">
                    <span class="">Да, хочу добавить "живую" открытку</span>
                </label>
                <p class="uk-text-meta uk-margin-small-top uk-margin-remove-bottom">
                    Получатель сможет оживить открытку, наведя на неё камеру телефона.
                </p>
            </div>

            <!-- Контейнер настроек -->
            <div id="ar_settings_container" hidden>
                <hr class="uk-divider">

                <!-- Тип -->
                <div class="uk-margin">
                    <label class="uk-form-label">Выберите тип контента:</label>
                    <div class="uk-margin-small-top uk-grid-small uk-child-width-auto uk-grid">
                        <label><input class="uk-radio" type="radio" name="ar_type" value="image" checked> Фото</label>
                        <label><input class="uk-radio" type="radio" name="ar_type" value="video"> Видео</label>
                    </div>
                </div>

                <!-- Формат -->
                <div class="uk-margin">
                    <label class="uk-form-label">Формат:</label>
                    <div class="uk-margin-small-top uk-grid-small uk-child-width-auto uk-grid">
                        <label><input class="uk-radio" type="radio" name="ar_format" value="portrait" checked> Вертикальное (Сторис)</label>
                        <label><input class="uk-radio" type="radio" name="ar_format" value="horizont"> Горизонтальное</label>
                    </div>
                </div>

                <!-- Загрузка (С АВТО-ЗАГРУЗКОЙ) -->
                <div class="uk-margin">
                    <label class="uk-form-label" id="ar_file_label">Загрузите изображение:</label>
                    
                    <div class="uk-width-1-1 uk-margin-small-top">
                        <!-- Видимый инпут (не отправляется формой заказа, только для выбора) -->
                        <input type="file" id="ar_file_input" accept="image/png, image/jpeg, image/jpg" class="uk-input" style="padding: 5px;">
                        
                        <!-- СКРЫТОЕ ПОЛЕ: Сюда упадет ID после загрузки (Это поле уходит в заказ) -->
                        <input type="hidden" name="ar_attachment_id" id="ar_attachment_id">
                    </div>
                    
                    <!-- Статус загрузки -->
                    <div id="ar_upload_status" class="uk-margin-small-top uk-text-small"></div>

                    <p class="uk-text-meta uk-text-small" id="ar_file_desc">
                        Форматы: JPG, PNG. Макс 10Мб.
                    </p>
                </div>

                <!-- КНОПКА ПРЕДПРОСМОТРА (Твои стили) -->
                <div class="uk-margin-top">
                    <button type="button" id="ar_preview_btn" class="uk-button uk-button-large uk-button-default uk-border-rounded" uk-toggle="target: #modal-preview" disabled>
                        <span uk-icon="icon: play-circle" class="uk-margin-small-right"></span>
                        Предпросмотр AR
                    </button>
                    <p class="uk-text-small uk-text-muted uk-margin-small-top">
                        Кнопка станет активной после выбора файла
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- JS Логика (AJAX Upload) -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const trigger = document.getElementById('ar_active_trigger');
        const container = document.getElementById('ar_settings_container');
        const typeRadios = document.querySelectorAll('input[name="ar_type"]');
        const fileInput = document.getElementById('ar_file_input');
        const hiddenInput = document.getElementById('ar_attachment_id');
        const statusBox = document.getElementById('ar_upload_status');
        const previewBtn = document.getElementById('ar_preview_btn');
        const fileLabel = document.getElementById('ar_file_label');
        const fileDesc = document.getElementById('ar_file_desc');

        // Данные для AJAX из PHP
        const AJAX_URL = "<?php echo $ajax_url; ?>";
        const NONCE = "<?php echo $nonce; ?>";

        if(trigger && container) {
            // 1. Показать/Скрыть
            trigger.addEventListener('change', function() {
                container.hidden = !this.checked;
                if(!this.checked) {
                    hiddenInput.value = ''; // Очищаем ID если выключили
                }
            });

            // 2. Смена типа
            typeRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    fileInput.value = ''; 
                    hiddenInput.value = '';
                    statusBox.innerHTML = '';
                    previewBtn.disabled = true;

                    if (this.value === 'video') {
                        fileLabel.innerText = "Загрузите видео-поздравление:";
                        fileInput.setAttribute('accept', 'video/mp4, video/mov, video/quicktime');
                        fileDesc.innerText = "Форматы: MP4, MOV. Макс 50Мб.";
                    } else {
                        fileLabel.innerText = "Загрузите изображение:";
                        fileInput.setAttribute('accept', 'image/png, image/jpeg, image/jpg');
                        fileDesc.innerText = "Форматы: JPG, PNG. Макс 10Мб.";
                    }
                });
            });

            // 3. АВТО-ЗАГРУЗКА ФАЙЛА (Самая важная часть)
            fileInput.addEventListener('change', function() {
                if (!this.files || !this.files[0]) return;

                const file = this.files[0];
                const formData = new FormData();
                formData.append('action', 'ar_async_upload');
                formData.append('ar_file_upload', file);
                formData.append('nonce', NONCE);

                // UI: Лоадер
                statusBox.innerHTML = '<span class="uk-text-primary" uk-spinner="ratio: 0.5"></span> Загрузка на сервер...';
                previewBtn.disabled = true;
                fileInput.disabled = true; // Блокируем, чтобы не спамили

                fetch(AJAX_URL, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        // УСПЕХ: Сохраняем полученный ID в скрытое поле
                        hiddenInput.value = data.data.id; 
                        statusBox.innerHTML = '<span class="uk-text-success" uk-icon="icon: check"></span> Файл успешно загружен!';
                        previewBtn.disabled = false;
                        previewBtn.classList.remove('uk-button-disabled');
                    } else {
                        statusBox.innerHTML = '<span class="uk-text-danger">Ошибка: ' + data.data + '</span>';
                    }
                })
                .catch(error => {
                    console.error(error);
                    statusBox.innerHTML = '<span class="uk-text-danger">Ошибка сети.</span>';
                })
                .finally(() => {
                    fileInput.disabled = false;
                });
            });
        }
    });
    </script>
    <?php
}


// 3. Сохранение данных (Упрощенное - берем готовый ID)
add_action( 'woocommerce_checkout_update_order_meta', 'save_ar_fields_native', 10, 2 );

function save_ar_fields_native( $order_id, $data ) {
    
    // Актуальные ключи
    $keys = [
        'active' => 'field_698ea7aa65985', 
        'type'   => 'field_698ea86d65986', 
        'format' => 'field_698ea8e865987', 
        'video'  => 'field_698ea91065988', 
        'image'  => 'field_698ea9466598a'  
    ];

    if ( ! empty( $_POST['ar_active'] ) ) {
        
        update_field( $keys['active'], 1, $order_id );
        
        if ( ! empty( $_POST['ar_type'] ) ) update_field( $keys['type'], sanitize_text_field( $_POST['ar_type'] ), $order_id );
        if ( ! empty( $_POST['ar_format'] ) ) update_field( $keys['format'], sanitize_text_field( $_POST['ar_format'] ), $order_id );

        // БЕРЕМ ID ИЗ СКРЫТОГО ПОЛЯ (ar_attachment_id)
        if ( ! empty( $_POST['ar_attachment_id'] ) ) {
            $att_id = intval($_POST['ar_attachment_id']);
            $type = $_POST['ar_type'] ?? 'image';
            
            if ( $type === 'video' ) {
                update_field( $keys['video'], $att_id, $order_id );
            } else {
                update_field( $keys['image'], $att_id, $order_id );
            }
        }
    } else {
        update_field( $keys['active'], 0, $order_id );
    }
}


// 4. Модальное окно предпросмотра (Footer)
add_action('wp_footer', 'render_ar_preview_modal');

function render_ar_preview_modal() {
    if ( !is_checkout() ) return;
    ?>
    <div id="modal-preview" uk-modal>
        <div class="uk-modal-dialog uk-modal-body uk-width-auto uk-margin-auto-vertical">
            <button class="uk-modal-close-default" type="button" uk-close></button>
            <h2 class="uk-modal-title uk-text-center">Предпросмотр</h2>
            <div id="container" style="width: 300px; height: 500px; background: #000; margin: 0 auto; border-radius: 10px; overflow: hidden; display:flex; align-items:center; justify-content:center; color:#fff;">
                3D Заглушка
            </div>
            <p class="uk-text-center uk-text-small uk-text-muted uk-margin-small-top">Покрутите модель</p>
        </div>
    </div>
    <?php
}
