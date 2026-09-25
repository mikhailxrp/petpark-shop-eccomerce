<?php

declare(strict_types=1);

/**
 * Установка БД — создание таблиц.
 * Запускать один раз через браузер: /install-temp.php
 * После выполнения — удалить install-temp.php из public/
 *
 * Схема соответствует .docs/database.md — при добавлении своей таблицы
 * сначала опиши её там, потом продублируй сюда в порядке зависимостей
 * (родитель раньше потомка).
 */

require_once dirname(__DIR__) . '/config/config.php';

$pdo = getPdo();

// ─── users ──────────────────────────────────────────────────────────────

$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        name          VARCHAR(100) NOT NULL,
        email         VARCHAR(150) NOT NULL UNIQUE,
        phone         VARCHAR(20) NULL,
        password_hash VARCHAR(255) NOT NULL,
        role          ENUM('customer', 'specialist', 'shift_admin', 'content_editor', 'owner')
                          NOT NULL DEFAULT 'customer',
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── categories ─────────────────────────────────────────────────────────

$pdo->exec("
    CREATE TABLE IF NOT EXISTS categories (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        parent_id       INT NULL,
        name            VARCHAR(150) NOT NULL,
        slug            VARCHAR(160) NOT NULL UNIQUE,
        sort_order      INT NOT NULL DEFAULT 0,
        seo_title       VARCHAR(70) NULL,
        seo_description VARCHAR(160) NULL,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_categories_parent (parent_id),
        CONSTRAINT fk_categories_parent
            FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── brands ─────────────────────────────────────────────────────────────

$pdo->exec("
    CREATE TABLE IF NOT EXISTS brands (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(120) NOT NULL UNIQUE,
        slug       VARCHAR(140) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── products ───────────────────────────────────────────────────────────
// Цена/остаток/артикул — у product_variants, не здесь (ADR-002).

$pdo->exec("
    CREATE TABLE IF NOT EXISTS products (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        category_id     INT NOT NULL,
        brand_id        INT NULL,
        name            VARCHAR(200) NOT NULL,
        slug            VARCHAR(220) NOT NULL UNIQUE,
        description     TEXT NULL,
        is_active       TINYINT(1) NOT NULL DEFAULT 1,
        seo_title       VARCHAR(70) NULL,
        seo_description VARCHAR(160) NULL,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_products_category_active (category_id, is_active),
        KEY idx_products_brand (brand_id),
        FULLTEXT KEY ft_products_name_description (name, description),
        CONSTRAINT fk_products_category
            FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT,
        CONSTRAINT fk_products_brand
            FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── product_secondary_categories ──────────────────────────────────────
// Вторая (необязательная) категория товара — ADR-002.

$pdo->exec("
    CREATE TABLE IF NOT EXISTS product_secondary_categories (
        product_id  INT NOT NULL,
        category_id INT NOT NULL,
        PRIMARY KEY (product_id),
        KEY idx_product_secondary_categories_category (category_id),
        CONSTRAINT fk_product_secondary_categories_product
            FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
        CONSTRAINT fk_product_secondary_categories_category
            FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── product_attributes ─────────────────────────────────────────────────
// Характеристики Товара, общие для всех Вариантов (вид животного, возраст, назначение).

$pdo->exec("
    CREATE TABLE IF NOT EXISTS product_attributes (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        attr_name  VARCHAR(60) NOT NULL,
        attr_value VARCHAR(150) NOT NULL,
        KEY idx_product_attributes_product (product_id),
        KEY idx_product_attributes_name_value (attr_name, attr_value),
        CONSTRAINT fk_product_attributes_product
            FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── product_images ─────────────────────────────────────────────────────

$pdo->exec("
    CREATE TABLE IF NOT EXISTS product_images (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        path       VARCHAR(255) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_main    TINYINT(1) NOT NULL DEFAULT 0,
        KEY idx_product_images_product (product_id),
        CONSTRAINT fk_product_images_product
            FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── product_variants ───────────────────────────────────────────────────
// Ключевая сущность — цена/остаток/артикул принадлежат Варианту (ADR-002).

$pdo->exec("
    CREATE TABLE IF NOT EXISTS product_variants (
        id                 INT AUTO_INCREMENT PRIMARY KEY,
        product_id         INT NOT NULL,
        sku                VARCHAR(64) NOT NULL UNIQUE,
        price              DECIMAL(10, 2) NOT NULL,
        discount_price     DECIMAL(10, 2) NULL,
        stock_quantity     INT NOT NULL DEFAULT 0,
        reserved_quantity  INT NOT NULL DEFAULT 0,
        is_active          TINYINT(1) NOT NULL DEFAULT 1,
        moysklad_synced_at TIMESTAMP NULL,
        created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_product_variants_product (product_id),
        KEY idx_product_variants_price (price),
        CONSTRAINT fk_product_variants_product
            FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── product_variant_attributes ─────────────────────────────────────────
// Признаки вариативности (вес упаковки, вкус) — ADR-005.

$pdo->exec("
    CREATE TABLE IF NOT EXISTS product_variant_attributes (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        variant_id INT NOT NULL,
        attr_name  VARCHAR(60) NOT NULL,
        attr_value VARCHAR(150) NOT NULL,
        KEY idx_product_variant_attributes_variant (variant_id),
        CONSTRAINT fk_product_variant_attributes_variant
            FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── cart_items ─────────────────────────────────────────────────────────
// В корзину кладут конкретный Вариант товара, не Товар.

$pdo->exec("
    CREATE TABLE IF NOT EXISTS cart_items (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(64) NULL,
        user_id    INT NULL,
        variant_id INT NOT NULL,
        quantity   INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_cart_items_session (session_id),
        KEY idx_cart_items_user (user_id),
        KEY idx_cart_items_variant (variant_id),
        CONSTRAINT fk_cart_items_user
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT fk_cart_items_variant
            FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── orders ─────────────────────────────────────────────────────────────
// Статусы/переходы — tz.md §6.3, ADR-007.

$pdo->exec("
    CREATE TABLE IF NOT EXISTS orders (
        id                 INT AUTO_INCREMENT PRIMARY KEY,
        user_id            INT NULL,
        created_by_user_id INT NULL,
        status             ENUM('new', 'confirmed', 'assembled', 'shipped', 'ready_for_pickup',
                                 'delivered', 'picked_up', 'cancelled') NOT NULL DEFAULT 'new',
        payment_status     ENUM('unpaid', 'paid', 'refunded') NOT NULL DEFAULT 'unpaid',
        delivery_method    ENUM('pickup', 'courier') NOT NULL,
        payment_method     ENUM('card_online', 'cash_or_card_on_delivery') NOT NULL,
        delivery_cost      DECIMAL(10, 2) NOT NULL DEFAULT 0,
        delivery_address   VARCHAR(255) NULL,
        reserved_until     TIMESTAMP NULL,
        amocrm_id          VARCHAR(64) NULL,
        amocrm_synced_at   TIMESTAMP NULL,
        total              DECIMAL(10, 2) NOT NULL,
        created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_orders_user (user_id),
        KEY idx_orders_status (status),
        KEY idx_orders_created (created_at),
        KEY idx_orders_status_reserved_until (status, reserved_until),
        CONSTRAINT fk_orders_user
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
        CONSTRAINT fk_orders_created_by_user
            FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── order_items ────────────────────────────────────────────────────────
// product_name/variant_label/price — снэпшот на момент заказа, дублируются намеренно.

$pdo->exec("
    CREATE TABLE IF NOT EXISTS order_items (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        order_id      INT NOT NULL,
        variant_id    INT NULL,
        product_name  VARCHAR(200) NOT NULL,
        variant_label VARCHAR(150) NOT NULL,
        price         DECIMAL(10, 2) NOT NULL,
        quantity      INT NOT NULL,
        KEY idx_order_items_order (order_id),
        KEY idx_order_items_variant (variant_id),
        CONSTRAINT fk_order_items_order
            FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
        CONSTRAINT fk_order_items_variant
            FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── payment_logs ───────────────────────────────────────────────────────

$pdo->exec("
    CREATE TABLE IF NOT EXISTS payment_logs (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        order_id        INT NOT NULL,
        provider        VARCHAR(50) NOT NULL,
        signature_valid TINYINT(1) NOT NULL,
        payload         TEXT NOT NULL,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_payment_logs_order (order_id),
        CONSTRAINT fk_payment_logs_order
            FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── favorites ──────────────────────────────────────────────────────────
// Избранное (FR-CARD-006, FR-ACC-004) — ADR-012.

$pdo->exec("
    CREATE TABLE IF NOT EXISTS favorites (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        variant_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_favorites_user_variant (user_id, variant_id),
        KEY idx_favorites_variant (variant_id),
        CONSTRAINT fk_favorites_user
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT fk_favorites_variant
            FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── content_pages ──────────────────────────────────────────────────────

$pdo->exec("
    CREATE TABLE IF NOT EXISTS content_pages (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        slug            VARCHAR(160) NOT NULL UNIQUE,
        title           VARCHAR(200) NOT NULL,
        body            TEXT NOT NULL,
        seo_title       VARCHAR(70) NULL,
        seo_description VARCHAR(160) NULL,
        updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── content_page_images ────────────────────────────────────────────────
// ADR-008 — редактор изображений статических страниц в Админке.

$pdo->exec("
    CREATE TABLE IF NOT EXISTS content_page_images (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        content_page_id INT NOT NULL,
        path            VARCHAR(255) NOT NULL,
        sort_order      INT NOT NULL DEFAULT 0,
        KEY idx_content_page_images_page (content_page_id),
        CONSTRAINT fk_content_page_images_page
            FOREIGN KEY (content_page_id) REFERENCES content_pages (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── pets ───────────────────────────────────────────────────────────────

$pdo->exec("
    CREATE TABLE IF NOT EXISTS pets (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        name       VARCHAR(60) NOT NULL,
        species    VARCHAR(50) NOT NULL,
        breed      VARCHAR(80) NULL,
        weight     DECIMAL(5, 2) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_pets_user (user_id),
        CONSTRAINT fk_pets_user
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── services ───────────────────────────────────────────────────────────
// ADR-013: строки заводятся сидом при разработке, отдельного FR-ADM/FR-MGR на CRUD нет.

$pdo->exec("
    CREATE TABLE IF NOT EXISTS services (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        name              VARCHAR(150) NOT NULL,
        duration_minutes  INT NOT NULL,
        price             DECIMAL(10, 2) NOT NULL,
        deposit_amount    DECIMAL(10, 2) NULL,
        is_active         TINYINT(1) NOT NULL DEFAULT 1,
        created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── specialists ────────────────────────────────────────────────────────
// ADR-013: график заводится сидом при разработке (тот же принцип, что services).

$pdo->exec("
    CREATE TABLE IF NOT EXISTS specialists (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL UNIQUE,
        work_start TIME NOT NULL DEFAULT '10:00:00',
        work_end   TIME NOT NULL DEFAULT '20:00:00',
        day_off    TINYINT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_specialists_user
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── specialist_services ────────────────────────────────────────────────
// M:N — не каждый Специалист оказывает каждую Услугу.

$pdo->exec("
    CREATE TABLE IF NOT EXISTS specialist_services (
        specialist_id INT NOT NULL,
        service_id    INT NOT NULL,
        PRIMARY KEY (specialist_id, service_id),
        KEY idx_specialist_services_service (service_id),
        CONSTRAINT fk_specialist_services_specialist
            FOREIGN KEY (specialist_id) REFERENCES specialists (id) ON DELETE CASCADE,
        CONSTRAINT fk_specialist_services_service
            FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── specialist_time_off ────────────────────────────────────────────────

$pdo->exec("
    CREATE TABLE IF NOT EXISTS specialist_time_off (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        specialist_id INT NOT NULL,
        date_from     DATE NOT NULL,
        date_to       DATE NOT NULL,
        reason        VARCHAR(200) NULL,
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_specialist_time_off_specialist_dates (specialist_id, date_from, date_to),
        CONSTRAINT fk_specialist_time_off_specialist
            FOREIGN KEY (specialist_id) REFERENCES specialists (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── bookings ───────────────────────────────────────────────────────────
// Резерв с истечением — ADR-006; статусы — tz.md §6.3.

$pdo->exec("
    CREATE TABLE IF NOT EXISTS bookings (
        id                    INT AUTO_INCREMENT PRIMARY KEY,
        user_id               INT NOT NULL,
        created_by_user_id    INT NULL,
        pet_id                INT NOT NULL,
        specialist_id         INT NOT NULL,
        scheduled_at          DATETIME NOT NULL,
        status                ENUM('slot_selected', 'confirmed', 'slot_released', 'cancelled',
                                    'no_show', 'completed') NOT NULL DEFAULT 'slot_selected',
        slot_hold_expires_at  TIMESTAMP NULL,
        deposit_amount        DECIMAL(10, 2) NULL,
        deposit_status        ENUM('none', 'held', 'returned', 'forfeited') NOT NULL DEFAULT 'none',
        amocrm_id             VARCHAR(64) NULL,
        amocrm_synced_at      TIMESTAMP NULL,
        created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_bookings_specialist_scheduled (specialist_id, scheduled_at),
        KEY idx_bookings_user (user_id),
        KEY idx_bookings_status_slot_hold (status, slot_hold_expires_at),
        CONSTRAINT fk_bookings_user
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT fk_bookings_created_by_user
            FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
        CONSTRAINT fk_bookings_pet
            FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE,
        CONSTRAINT fk_bookings_specialist
            FOREIGN KEY (specialist_id) REFERENCES specialists (id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── booking_services ───────────────────────────────────────────────────
// M:N со снэпшотом — Запись назначена на 1 или несколько Услуг подряд.

$pdo->exec("
    CREATE TABLE IF NOT EXISTS booking_services (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        booking_id       INT NOT NULL,
        service_id       INT NULL,
        service_name     VARCHAR(150) NOT NULL,
        price            DECIMAL(10, 2) NOT NULL,
        duration_minutes INT NOT NULL,
        sort_order       INT NOT NULL DEFAULT 0,
        KEY idx_booking_services_booking (booking_id),
        CONSTRAINT fk_booking_services_booking
            FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE,
        CONSTRAINT fk_booking_services_service
            FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── order_returns ──────────────────────────────────────────────────────

$pdo->exec("
    CREATE TABLE IF NOT EXISTS order_returns (
        id                   INT AUTO_INCREMENT PRIMARY KEY,
        order_id             INT NOT NULL UNIQUE,
        reason               TEXT NOT NULL,
        status               ENUM('submitted', 'in_review', 'approved', 'rejected', 'completed')
                                 NOT NULL DEFAULT 'submitted',
        resolved_by_user_id  INT NULL,
        created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_order_returns_status (status),
        CONSTRAINT fk_order_returns_order
            FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
        CONSTRAINT fk_order_returns_resolved_by_user
            FOREIGN KEY (resolved_by_user_id) REFERENCES users (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── order_return_photos ────────────────────────────────────────────────

$pdo->exec("
    CREATE TABLE IF NOT EXISTS order_return_photos (
        id        INT AUTO_INCREMENT PRIMARY KEY,
        return_id INT NOT NULL,
        path      VARCHAR(255) NOT NULL,
        KEY idx_order_return_photos_return (return_id),
        CONSTRAINT fk_order_return_photos_return
            FOREIGN KEY (return_id) REFERENCES order_returns (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── conversations ──────────────────────────────────────────────────────
// Демо: CHANNELS — UI-заглушка (ADR-001), таблица нужна независимо от этого.

$pdo->exec("
    CREATE TABLE IF NOT EXISTS conversations (
        id                        INT AUTO_INCREMENT PRIMARY KEY,
        channel                   ENUM('max', 'telegram', 'vk', 'avito') NOT NULL,
        external_conversation_id  VARCHAR(120) NULL,
        user_id                   INT NULL,
        contact_identifier        VARCHAR(120) NULL,
        order_id                  INT NULL,
        created_at                TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at                TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_conversations_user (user_id),
        KEY idx_conversations_channel (channel),
        KEY idx_conversations_order (order_id),
        CONSTRAINT fk_conversations_user
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
        CONSTRAINT fk_conversations_order
            FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── conversation_messages ──────────────────────────────────────────────

$pdo->exec("
    CREATE TABLE IF NOT EXISTS conversation_messages (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        direction       ENUM('in', 'out') NOT NULL,
        body            TEXT NOT NULL,
        sent_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_conversation_messages_conversation_sent (conversation_id, sent_at),
        CONSTRAINT fk_conversation_messages_conversation
            FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── reviews ─────────────────────────────────────────────────────────────
// Модерация обязательна (Q-048, ADR-011) — новый отзыв всегда 'pending'.

$pdo->exec("
    CREATE TABLE IF NOT EXISTS reviews (
        id                    INT AUTO_INCREMENT PRIMARY KEY,
        product_id            INT NOT NULL,
        user_id               INT NULL,
        author_name           VARCHAR(100) NOT NULL,
        author_email          VARCHAR(150) NOT NULL,
        rating                TINYINT UNSIGNED NOT NULL,
        body                  TEXT NOT NULL,
        status                ENUM('pending', 'published', 'rejected') NOT NULL DEFAULT 'pending',
        moderated_by_user_id  INT NULL,
        created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        moderated_at          TIMESTAMP NULL,
        KEY idx_reviews_product_status (product_id, status),
        KEY idx_reviews_status (status),
        CONSTRAINT fk_reviews_product
            FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
        CONSTRAINT fk_reviews_user
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
        CONSTRAINT fk_reviews_moderated_by_user
            FOREIGN KEY (moderated_by_user_id) REFERENCES users (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ─── marketplace_listings ───────────────────────────────────────────────
// Демо: MARKET — UI-заглушка (ADR-001).

$pdo->exec("
    CREATE TABLE IF NOT EXISTS marketplace_listings (
        id                 INT AUTO_INCREMENT PRIMARY KEY,
        variant_id         INT NOT NULL,
        marketplace        ENUM('wildberries', 'ozon') NOT NULL,
        marketplace_price  DECIMAL(10, 2) NOT NULL,
        synced_at          TIMESTAMP NULL,
        UNIQUE KEY uq_marketplace_listings_variant_marketplace (variant_id, marketplace),
        CONSTRAINT fk_marketplace_listings_variant
            FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Добавляй свои таблицы здесь (после базовых, с учётом их FK):
// $pdo->exec("CREATE TABLE IF NOT EXISTS ...");

echo "✅ Таблицы созданы успешно.\n";
