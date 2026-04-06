# YOOtheme AR – WooCommerce Custom Checkout

Кастомная реализация checkout + AR-превью и AR-открыток для WooCommerce.

Проект реализует:
- multi-step checkout (UIkit)
- загрузку AR контента (изображение / видео)
- QR-код на открытку
- сохранение в заказе
- AR viewer (MindAR + Three.js)

---

## Структура проекта

```
wp-content/themes/yootheme-ar/
│
├── functions.php
│
├── inc/
│   ├── checkout/
│   │   ├── woocommerce-checkout.php   # Основная логика checkout (PHP)
│   │   ├── checkout-ar-ui.js          # UI логика AR (JS)
│   │   ├── delivery.php               # Дата/время доставки
│   │   └── libs/
│   │       └── flatpickr/             # Datepicker
│   │
│   ├── ar-preview/
│   │   ├── ar-preview.php             # AR backend + AJAX
│   │   └── ar-preview.js              # Preview логика (если используется)
│   │
│   ├── ar-viewer/ (рекомендуется)
│   │   ├── ar-viewer.js               # MindAR + Three.js viewer
│   │   └── shaders.js                # Shader код
│   │
│   └── ...
│
├── woocommerce/
│   └── checkout/
│       └── form-checkout.php          # Кастомный checkout шаблон
│
├── ar-card.php                        # AR viewer страница
│
└── style.css
```

---

## Архитектура

Проект разделён на 3 основных слоя:

### 1. Checkout (PHP + JS)
```
inc/checkout/
```

Отвечает за:
- поля checkout
- валидацию
- UI (JS)
- стоимость доставки
- сохранение данных

---

### 2. AR Upload Module
```
inc/ar-preview/
```

Отвечает за:
- загрузку файлов (AJAX)
- валидацию
- сохранение attachment ID
- связь с заказом

---

### 3. AR Viewer
```
ar-card.php
```

Отвечает за:
- отображение AR
- MindAR
- Three.js сцена
- воспроизведение контента

---

## Жизненный цикл

1. Пользователь оформляет заказ
2. Загружает AR контент
3. WooCommerce создаёт заказ
4. Данные сохраняются в meta
5. Генерируется AR ссылка
6. Пользователь открывает ar-card.php?id=ORDER_ID

---

## Данные заказа

### Meta поля:

| Поле | Описание |
|------|--------|
| _delivery_date | Дата доставки |
| _delivery_time | Время доставки |
| Зона доставки | MKAD / OUT_MKAD |
| AR_ATTACHMENT_ID | ID загруженного файла |

---

## Подключение модулей

В functions.php:

```php
require_once get_stylesheet_directory() . '/inc/core/loader.php';
```

---

## Важные моменты

### 1. WooCommerce AJAX
Используется:
```
updated_checkout
```

### 2. JS инициализация
Используется защита:
```
dataset.bound
```

---

## TODO / ROADMAP
### 0. Вынести ленивую загрузку three.js и ассетов
- [ ] Загружать сначала страницу checkout а потом лениво three.js и ассеты
- 
### 1. AR как продукт

- [ ] Добавить upsell:
  "Добавить AR поздравление +500₽"
- [ ] Toggle включения AR
- [ ] Динамическая цена
- [ ] Отображение в корзине

---

### 2. Личный кабинет

- [ ] Раздел "Мои AR открытки"
- [ ] Список заказов с AR
- [ ] Быстрый доступ к viewer
- [ ] Возможность повторного открытия

---

### 3. Архитектурный апгрейд ar-card.php

Текущая проблема:
- слишком большой файл
- смешаны PHP + HTML + JS

Решение:

```
inc/ar-viewer/
```

Вынести:
- JS → ar-viewer.js
- shaders → shaders.js

В ar-card.php оставить:
- только PHP + HTML
- CONFIG
- подключение JS

---

## Принципы проекта

- модульность
- разделение ответственности
- минимальная связность
- устойчивость к AJAX

---

## Итог

Проект уже является:
- кастомным checkout решением
- AR системой и QR-кодом на открытку
- базой для коммерческого продукта

