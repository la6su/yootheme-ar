# YOOtheme AR — checkout и AR-открытки для Mospal

Дочерняя тема YOOtheme Pro для `mospal.ru`. В ней находятся интерфейс checkout и AR-открытки; стандартные механики WooCommerce и YOOtheme не изменяются.

## Что реализовано

- многошаговый checkout на UIkit;
- загрузка персонального изображения или видео к заказу;
- isolated media storage: `wp-content/uploads/greetings/YYYY/MM/`;
- случайные имена файлов, привязка к сессии checkout и заказу;
- автоматическая очистка: брошенная загрузка через 48 часов, оплаченная открытка через 30 дней после доставки;
- 3D preview в checkout и MindAR viewer используют один Three.js runtime;
- GLTF animation: первый клип запускается один раз в preview и один раз при первой находке маркера. Потеря маркера не останавливает клип; при следующей находке видна финальная поза;
- публичная AR-ссылка использует случайный токен, а не номер заказа.
- бесплатный модуль цветочных подписок на обычных WooCommerce-товарах: персональные настройки, график доставок, пауза и ручное продление через обычный заказ.

## Структура

```text
inc/
├── greetings/greetings.php       # upload, retention, tokens, order binding
├── ar-preview/                   # checkout UI + standard Three.js preview
├── ar-viewer/
│   ├── ar-viewer.js              # MindAR page entrypoint
│   ├── core/                     # shared model/media/animation lifecycle
│   └── modes/                    # standardPreview + mindarViewer
├── subscriptions/subscriptions.php # subscription preferences, delivery schedule, renewals
└── checkout/                     # delivery fields and checkout UI
ar-card.php                       # minimal AR page template
```

`src/js/three/` is an old experimental refactor and is not loaded by WordPress. The canonical runtime is `inc/ar-viewer/`; new changes must go there until a real build pipeline is introduced.

## Подписка на цветы

Подписки не требуют платного WooCommerce Subscriptions. Обычные товары определяются по SKU: `SUB-BUD-M`, `SUB-BAZ-M`, `SUB-SEZ-Y`, `SUB-MAG-Y`. Убедитесь, что эти артикулы указаны у четырёх тарифов.

В checkout показываются только нужные тарифу данные: стиль и первая дата для ежемесячных/еженедельных букетов; даты рождения и годовщины — только для «Праздничной магии». После первой оплаченной покупки создаются отдельные записи подписки и будущих доставок в меню WooCommerce. Следующее продление создаёт обычный ожидающий оплаты заказ и отправляет покупателю ссылку на оплату. Это совместимо с PayKeeper; автоматическое повторное списание можно добавить позднее, не меняя данные подписок.

Клиент управляет паузой из «Мой аккаунт → Подписки на цветы». Для появления нового пункта меню после развёртывания один раз сохраните постоянные ссылки в `Настройки → Постоянные ссылки`.

## Требования к GLB

- mesh экрана должен называться `Screen` (регистр не важен);
- анимации должны быть экспортированы внутрь GLB как glTF clips;
- первый clip используется автоматически;
- для выбора конкретного клипа добавьте в дочернюю тему или mini-plugin:

```php
add_filter('mospal_greeting_animation_clip', fn () => 'OpenTV');
add_filter('mospal_greeting_animation_loop', fn () => 'once');
```

Для новой модели можно заменить путь без редактирования runtime:

```php
add_filter('mospal_greeting_model_url', fn () => '/arjs/gltf/tv-animated.glb');
```

## Хранение greeting-файлов

Новые загрузки проходят через `mospal_greeting_upload` и получают мета-признаки `_mospal_greeting`, сессионный токен и время удаления. Attachment нельзя подставить из другого checkout.

Фоновая задача `mospal_greeting_cleanup_expired` запускается раз в сутки через Action Scheduler; если он недоступен, используется WP-Cron. На production для точного расписания нужен системный cron, вызывающий WordPress cron регулярно.

## AR-ссылка и QR

После создания заказа генерируется `_mospal_greeting_token`. Получить безопасный URL можно так:

```php
$url = mospal_greeting_viewer_url($order);
```

Он имеет вид `/ar-card/?token=...` и готов для QR-кода. Старый запрос по ID доступен только менеджеру WooCommerce.

## Форматы

- изображения: JPG, PNG, до 10 МБ;
- видео: MP4, MOV, до 50 МБ.

Для максимальной совместимости мобильных браузеров предпочтителен MP4 с H.264/AAC. MOV не перекодируется автоматически.
