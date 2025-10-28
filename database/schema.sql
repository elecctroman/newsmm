-- phpMyAdmin SQL Dump
--
-- Database: lisansonay

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('owner', 'manager', 'staff') NOT NULL DEFAULT 'manager',
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO admin_users (name, email, password_hash, role) VALUES
('Sistem Yöneticisi', 'admin@lisansonay.com', '$2y$12$RX1YfQX95A.2iFP6/K4NHuC0uOg7AaHgF1IvoI8/VLc9iw.Phfv16', 'owner')
ON DUPLICATE KEY UPDATE name = VALUES(name);

CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(160) NOT NULL UNIQUE,
    group_name VARCHAR(80) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_user_roles (
    admin_user_id INT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (admin_user_id, role_id),
    CONSTRAINT fk_admin_user_roles_user FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_admin_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(40) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    balance_cents INT NOT NULL DEFAULT 0,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(120) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value) VALUES
('company_name', 'Lisansonay'),
('support_email', 'destek@lisansonay.com'),
('support_phone', '+90 555 555 55 55'),
('whatsapp_link', 'https://wa.me/905555555555')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id INT UNSIGNED NULL DEFAULT NULL,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    description TEXT NULL,
    emoji VARCHAR(16) NULL,
    icon_url VARCHAR(255) NULL,
    hero_image VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('draft', 'review', 'published', 'archived') NOT NULL DEFAULT 'published',
    published_at TIMESTAMP NULL DEFAULT NULL,
    meta_title VARCHAR(160) DEFAULT NULL,
    meta_description VARCHAR(320) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_categories_parent (parent_id),
    INDEX idx_categories_status (status),
    INDEX idx_categories_featured (is_featured),
    UNIQUE KEY uniq_categories_parent_name (parent_id, name),
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    subcategory_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(160) NOT NULL,
    short_description VARCHAR(280) DEFAULT NULL,
    description_html MEDIUMTEXT DEFAULT NULL,
    price_label VARCHAR(60) NOT NULL,
    price_cents INT UNSIGNED NOT NULL DEFAULT 0,
    old_price_cents INT UNSIGNED DEFAULT NULL,
    discount_percent DECIMAL(5,2) DEFAULT NULL,
    stock_status ENUM('in_stock', 'limited', 'preorder', 'out_of_stock') NOT NULL DEFAULT 'in_stock',
    primary_image_url VARCHAR(255) DEFAULT NULL,
    gallery_json JSON DEFAULT NULL,
    badge_json JSON DEFAULT NULL,
    note VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('draft', 'review', 'published', 'archived') NOT NULL DEFAULT 'published',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    meta_title VARCHAR(160) DEFAULT NULL,
    meta_description VARCHAR(320) DEFAULT NULL,
    published_at TIMESTAMP NULL DEFAULT NULL,
    available_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    CONSTRAINT fk_products_subcategory_category FOREIGN KEY (subcategory_id) REFERENCES categories(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_products_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_attributes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    attribute_key VARCHAR(120) NOT NULL,
    attribute_value VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_attributes_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS related_products (
    product_id INT UNSIGNED NOT NULL,
    related_id INT UNSIGNED NOT NULL,
    relation_type ENUM('related', 'upsell', 'cross_sell') NOT NULL DEFAULT 'related',
    PRIMARY KEY (product_id, related_id, relation_type),
    CONSTRAINT fk_related_product_base FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_related_product_related FOREIGN KEY (related_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_badges (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    badge_label VARCHAR(60) NOT NULL,
    badge_style VARCHAR(30) NOT NULL DEFAULT 'info',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_badges_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED DEFAULT NULL,
    order_no VARCHAR(40) NOT NULL UNIQUE,
    customer_name VARCHAR(150) NOT NULL,
    customer_email VARCHAR(150) DEFAULT NULL,
    customer_phone VARCHAR(40) DEFAULT NULL,
    status ENUM('pending', 'processing', 'completed', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending',
    total_cents INT UNSIGNED NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'TRY',
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customer_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED DEFAULT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price_cents INT UNSIGNED NOT NULL DEFAULT 0,
    total_cents INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cart_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_customer_product (customer_id, product_id),
    CONSTRAINT fk_cart_items_customer FOREIGN KEY (customer_id) REFERENCES customer_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(60) NOT NULL,
    entity VARCHAR(60) DEFAULT NULL,
    entity_id INT UNSIGNED DEFAULT NULL,
    message VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    ticket_no VARCHAR(40) NOT NULL UNIQUE,
    subject VARCHAR(200) NOT NULL,
    status ENUM('open', 'customer_reply', 'staff_reply', 'closed') NOT NULL DEFAULT 'open',
    priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
    last_reply_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tickets_customer FOREIGN KEY (customer_id) REFERENCES customer_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    author_type ENUM('customer', 'staff') NOT NULL,
    customer_id INT UNSIGNED DEFAULT NULL,
    staff_id INT UNSIGNED DEFAULT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_messages_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_customer FOREIGN KEY (customer_id) REFERENCES customer_users(id) ON DELETE SET NULL,
    CONSTRAINT fk_messages_staff FOREIGN KEY (staff_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    type ENUM('deposit', 'purchase', 'refund', 'adjustment', 'withdrawal') NOT NULL,
    amount_cents INT NOT NULL,
    balance_after INT NOT NULL,
    reference_type VARCHAR(60) DEFAULT NULL,
    reference_id INT UNSIGNED DEFAULT NULL,
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_wallet_transactions_customer FOREIGN KEY (customer_id) REFERENCES customer_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_topups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    reference_code VARCHAR(40) NOT NULL UNIQUE,
    amount_cents INT UNSIGNED NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    payment_channel VARCHAR(60) DEFAULT NULL,
    proof_url VARCHAR(255) DEFAULT NULL,
    notes TEXT,
    admin_notes TEXT,
    processed_by INT UNSIGNED DEFAULT NULL,
    processed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_wallet_topups_customer FOREIGN KEY (customer_id) REFERENCES customer_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_wallet_topups_admin FOREIGN KEY (processed_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS home_slider_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    image_url VARCHAR(255) NOT NULL,
    headline VARCHAR(180) DEFAULT NULL,
    link_url VARCHAR(255) DEFAULT NULL,
    starts_at TIMESTAMP NULL DEFAULT NULL,
    ends_at TIMESTAMP NULL DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS home_strip_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(200) DEFAULT NULL,
    image_url VARCHAR(255) DEFAULT NULL,
    link_url VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS home_blocks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('category', 'custom') NOT NULL DEFAULT 'category',
    category_id INT UNSIGNED DEFAULT NULL,
    title_override VARCHAR(160) DEFAULT NULL,
    product_limit TINYINT UNSIGNED NOT NULL DEFAULT 6,
    show_only_instock TINYINT(1) NOT NULL DEFAULT 1,
    pinned_product_ids JSON DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_home_blocks_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(160) NOT NULL UNIQUE,
    image_url VARCHAR(255) DEFAULT NULL,
    excerpt TEXT DEFAULT NULL,
    body_html MEDIUMTEXT DEFAULT NULL,
    status ENUM('draft', 'review', 'published', 'archived') NOT NULL DEFAULT 'draft',
    published_at TIMESTAMP NULL DEFAULT NULL,
    meta_title VARCHAR(160) DEFAULT NULL,
    meta_description VARCHAR(320) DEFAULT NULL,
    author_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_blog_posts_author FOREIGN KEY (author_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    path VARCHAR(255) NOT NULL,
    filename VARCHAR(180) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    width INT UNSIGNED DEFAULT NULL,
    height INT UNSIGNED DEFAULT NULL,
    alt_text VARCHAR(160) DEFAULT NULL,
    tags JSON DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_media_files_user FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menus (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(120) NOT NULL,
    location VARCHAR(80) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_menus_location (location)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    menu_id INT UNSIGNED NOT NULL,
    label VARCHAR(160) NOT NULL,
    link_url VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_menu_items_menu FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS redirects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_path VARCHAR(255) NOT NULL,
    to_path VARCHAR(255) NOT NULL,
    redirect_type ENUM('301', '302') NOT NULL DEFAULT '301',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_redirects_from (from_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_jobs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_type ENUM('category', 'product') NOT NULL,
    status ENUM('pending', 'running', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    filename VARCHAR(180) DEFAULT NULL,
    summary_json JSON DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_import_jobs_user FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name, slug, is_system, description) VALUES
('Süper Yönetici', 'super-admin', 1, 'Tüm izinlere sahip kullanıcı'),
('İçerik Yöneticisi', 'content-manager', 1, 'Kategori, ürün ve vitrin içeriklerini yönetir'),
('Editör', 'editor', 1, 'İçerik oluşturur ve taslakları yönetir'),
('Satış Operasyon', 'sales-operator', 1, 'Sipariş ve stok süreçlerini yönetir'),
('Gözlemci', 'observer', 1, 'Sadece görüntüleme yetkisi')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO permissions (name, slug, group_name, description) VALUES
('Kontrol Paneli Görüntüleme', 'dashboard.view', 'dashboard', 'Kontrol panelini görüntüleyebilir'),
('Kategori Görüntüleme', 'catalog.categories.view', 'catalog', 'Kategori listesini görüntüleyebilir'),
('Kategori Yönetimi', 'catalog.categories.manage', 'catalog', 'Kategori ekleyip düzenleyebilir'),
('Kategori Yayınlama', 'catalog.categories.publish', 'catalog', 'Kategori yayın durumunu değiştirebilir'),
('Ürün Görüntüleme', 'catalog.products.view', 'catalog', 'Ürün listesini görüntüleyebilir'),
('Ürün Yönetimi', 'catalog.products.manage', 'catalog', 'Ürün ekleyip düzenleyebilir'),
('Ürün Yayınlama', 'catalog.products.publish', 'catalog', 'Ürün yayın durumunu değiştirebilir'),
('Ürün Fiyat/Stok Yönetimi', 'catalog.products.pricing', 'catalog', 'Ürün fiyat ve stok bilgisini güncelleyebilir'),
('Anasayfa İçerikleri', 'content.home.manage', 'content', 'Slider, strip ve blokları yönetebilir'),
('Blog Yönetimi', 'content.blog.manage', 'content', 'Blog içeriklerini yönetebilir'),
('Medya Yönetimi', 'content.media.manage', 'content', 'Medya kütüphanesini yönetebilir'),
('Menü Yönetimi', 'content.menu.manage', 'content', 'Menüleri düzenleyebilir'),
('Yönlendirme Yönetimi', 'content.redirects.manage', 'content', '301/302 yönlendirmelerini yönetebilir'),
('Sipariş Görüntüleme', 'sales.orders.view', 'sales', 'Siparişleri görüntüleyebilir'),
('Sipariş Yönetimi', 'sales.orders.manage', 'sales', 'Sipariş durumlarını güncelleyebilir'),
('Bakiye Yönetimi', 'sales.wallet.manage', 'sales', 'Bakiye işlemlerini yönetebilir'),
('Destek Görüntüleme', 'support.view', 'support', 'Destek taleplerini görüntüleyebilir'),
('Destek Yönetimi', 'support.manage', 'support', 'Destek taleplerine yanıt verebilir'),
('Ayarlar Yönetimi', 'system.settings.manage', 'system', 'Sistem ayarlarını güncelleyebilir'),
('Kullanıcı Yönetimi', 'system.users.manage', 'system', 'Yönetici kullanıcıları ve rollerini yönetebilir'),
('Audit Log Görüntüleme', 'system.audit.view', 'system', 'Audit log kayıtlarını görüntüleyebilir'),
('Import/Export Kullanımı', 'system.import.manage', 'system', 'İçeri aktarma/dışa aktarma işlemlerini yönetebilir')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.slug = 'super-admin'
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.slug IN (
    'dashboard.view',
    'catalog.categories.view', 'catalog.categories.manage', 'catalog.categories.publish',
    'catalog.products.view', 'catalog.products.manage', 'catalog.products.publish', 'catalog.products.pricing',
    'content.home.manage', 'content.blog.manage', 'content.media.manage', 'content.menu.manage',
    'content.redirects.manage', 'system.import.manage', 'system.audit.view'
)
WHERE r.slug = 'content-manager'
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.slug IN (
    'dashboard.view',
    'catalog.categories.view', 'catalog.products.view',
    'catalog.categories.manage', 'catalog.products.manage',
    'content.home.manage', 'content.blog.manage',
    'content.media.manage'
)
WHERE r.slug = 'editor'
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.slug IN (
    'dashboard.view',
    'catalog.products.view', 'catalog.products.pricing',
    'sales.orders.view', 'sales.orders.manage',
    'sales.wallet.manage',
    'system.import.manage'
)
WHERE r.slug = 'sales-operator'
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.slug IN (
    'dashboard.view',
    'catalog.categories.view',
    'catalog.products.view',
    'sales.orders.view',
    'support.view',
    'system.audit.view'
)
WHERE r.slug = 'observer'
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO admin_user_roles (admin_user_id, role_id)
SELECT au.id, r.id
FROM admin_users au
CROSS JOIN roles r
WHERE au.email = 'admin@lisansonay.com' AND r.slug = 'super-admin'
ON DUPLICATE KEY UPDATE admin_user_id = admin_user_id;

INSERT INTO categories (slug, name, description, emoji, sort_order)
VALUES
('lisans', 'Lisans Hizmetleri', 'Aradığınız Tüm Lisanslar', '🧩', 1),
('twitter', 'Twitter Hizmetleri', '2007–2025 Hesaplar', '🐦', 2),
('instagram', 'Instagram Hizmetleri', 'Takipçili Takipçisiz Eski Hesaplar', '📸', 3)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    emoji = VALUES(emoji),
    sort_order = VALUES(sort_order);

INSERT INTO products (category_id, name, price_label, price_cents, note, sort_order) VALUES
(1, '14 Günlük Semrush Guru', '₺100', 10000, NULL, 1),
(1, 'Office 365 Pro Plus (6 Ay Garantili Kişiye Özel)', '₺100', 10000, NULL, 2),
(1, '1 Aylık Kişisel ChatGPT', '₺200', 20000, NULL, 3),
(1, 'JetBrains (Kişisel Hesap)', '₺299', 29900, NULL, 4),
(1, 'Canva Okul Hesabı (Tüm Yönetim Sizde)', '₺299', 29900, NULL, 5),
(1, 'Envato - Freepik Tasarımcı Paketi (2 Aylık Garantili Kişisel Özel Panel)', '₺279', 27900, NULL, 6),
(1, 'Kaspersky Premium (Mail Devex, 365 Gün)', '₺150', 15000, NULL, 7),
(1, 'Autodesk Tüm Uygulamalar (1 Yıl, Kişisel Hesap)', '₺299', 29900, NULL, 8),
(1, 'CapCut PRO (Ortak Hesap, 7/24 Garantili)', '₺149', 14900, 'Mail ile sipariş & teslim', 9),
(1, 'Windows 10 Home', '₺75', 7500, NULL, 10),
(1, 'Windows 10 PRO', '₺75', 7500, NULL, 11),
(1, 'Windows 11 Home', '₺75', 7500, NULL, 12),
(1, 'Windows 11 PRO', '₺75', 7500, NULL, 13),
(1, 'Windows 10/11 Home (OEM)', '₺100', 10000, NULL, 14),
(1, 'Windows 10/11 PRO (OEM)', '₺100', 10000, NULL, 15),
(1, 'Home’dan PRO’ya Geçiş', '₺75', 7500, NULL, 16),
(1, 'Office 2016', '₺75', 7500, NULL, 17),
(1, 'Office 2019', '₺75', 7500, NULL, 18),
(1, 'Office 2021', '₺100', 10000, NULL, 19),
(1, 'Office 365 Windows', '₺75', 7500, NULL, 20),
(1, 'Office 365 Mac', '₺75', 7500, NULL, 21),
(1, 'Office 365 Kişisel-Şirket', '₺250', 25000, NULL, 22),
(1, 'Office 2024 Pro Plus (Ömür Boyu)', '₺400', 40000, NULL, 23),
(1, 'Grammarly 1 Aylık', '₺75', 7500, NULL, 24),
(1, 'Grammarly 3 Aylık', '₺210', 21000, NULL, 25),
(1, 'Grammarly 6 Aylık', '₺350', 35000, NULL, 26),
(1, 'Grammarly 12 Aylık', '₺475', 47500, NULL, 27),
(1, 'Visio 2016', '₺150', 15000, NULL, 28),
(1, 'Visio 2019', '₺150', 15000, NULL, 29),
(1, 'Visio 2021', '₺200', 20000, NULL, 30),
(1, 'Adobe Creative Cloud (14 Günlük Hesap Seçilebilir)', '₺75', 7500, NULL, 31),
(1, 'Shutterstock (İndirme Başı)', '₺25', 2500, NULL, 32),
(1, 'Adobe Stock', '₺75', 7500, NULL, 33),
(1, 'Canva Edu', '₺60', 6000, NULL, 34),
(1, 'Canva Öğretmen', '₺200', 20000, NULL, 35),
(1, 'Figma Pro', '₺300', 30000, NULL, 36),
(1, 'Envato Elements 30 Gün', '₺75', 7500, 'Günlük 100 indirme', 37),
(1, 'Envato Elements 90 Gün', '₺199', 19900, 'Günlük 100 indirme', 38),
(1, 'Envato Elements 6 Ay', '₺399', 39900, 'Günlük 100 indirme', 39),
(1, 'Envato Elements 12 Ay', '₺600', 60000, 'Günlük 100 indirme', 40),
(1, 'Freepik 30 Gün', '₺75', 7500, 'Günlük 25 indirme', 41),
(1, 'Freepik 90 Gün', '₺199', 19900, 'Günlük 25 indirme', 42),
(1, 'Freepik 6 Ay', '₺399', 39900, 'Günlük 25 indirme', 43),
(1, 'Freepik 12 Ay', '₺600', 60000, 'Günlük 25 indirme', 44),
(1, 'Motion Array 30 Gün', '₺75', 7500, 'Günlük 20 indirme', 45),
(1, 'Motion Array 90 Gün', '₺199', 19900, 'Günlük 20 indirme', 46),
(1, 'Motion Array 6 Ay', '₺399', 39900, 'Günlük 20 indirme', 47),
(1, 'Motion Array 12 Ay', '₺600', 60000, 'Günlük 20 indirme', 48),
(1, 'Semrush Pro', '₺150', 15000, NULL, 49),
(1, 'Semrush Guru', '₺150', 15000, NULL, 50),
(1, 'Kaspersky Total Premium Security', '₺150', 15000, NULL, 51),
(1, 'NordVPN (1 Yıl, Ortak Hesap)', '₺200', 20000, NULL, 52),
(1, 'WP Rocket', '₺200', 20000, NULL, 53),
(1, 'WP Elementor Pro', '₺200', 20000, NULL, 54),
(1, 'WP Rank Math Pro', '₺200', 20000, NULL, 55),
(1, 'Perfmatters', '₺200', 20000, NULL, 56),
(1, 'SEOPress', '₺200', 20000, NULL, 57),
(1, 'WP Imagify Pro', '₺200', 20000, NULL, 58),
(1, 'WPML Pro', '₺200', 20000, NULL, 59),
(1, 'WP Schema Pro', '₺200', 20000, NULL, 60),
(1, 'WP Automatic', '₺200', 20000, NULL, 61),
(1, 'Croco Block PRO', '₺200', 20000, NULL, 62),
(1, 'Ultimate Addons for Elementor', '₺200', 20000, NULL, 63),
(1, 'Bricks', '₺200', 20000, NULL, 64),
(1, 'Prime Slider', '₺200', 20000, NULL, 65),
(1, 'The Plus Addons for Elementor', '₺200', 20000, NULL, 66),
(1, 'WP Portfolio', '₺200', 20000, NULL, 67),
(1, 'Ultimate Addons for Beaver Builder', '₺200', 20000, NULL, 68),
(1, 'Convert Pro', '₺200', 20000, NULL, 69),
(1, 'PostX', '₺200', 20000, NULL, 70),
(1, 'ProductX', '₺200', 20000, NULL, 71),
(1, 'WholesaleX', '₺200', 20000, NULL, 72),
(1, 'Publisher Pro', '₺400', 40000, NULL, 73),
(1, 'GeneratePress Pro', '₺400', 40000, NULL, 74),
(1, 'Astra Pro', '₺400', 40000, NULL, 75),
(1, 'Kadence Pro', '₺400', 40000, NULL, 76),
(1, 'Blocksy', '₺400', 40000, NULL, 77),
(1, 'WPXPO', '₺400', 40000, NULL, 78),
(1, 'Duolingo Öğrenci', '₺75', 7500, NULL, 79),
(1, 'Duolingo Öğretmen', '₺200', 20000, NULL, 80),
(1, 'EDU Mail', '₺75', 7500, NULL, 81),
(2, '2010 Tarihli Twitter Hesabı', '₺40', 4000, 'POPÜLER', 1),
(2, '2025 Tarihli Twitter Hesabı (Mail + Şifre ile Teslimat)', '₺5', 500, NULL, 2),
(2, '2009 Tarihli Twitter Hesabı', '₺60', 6000, 'POPÜLER', 3),
(2, '2007 Tarihli Twitter Hesabı', '₺450', 45000, 'POPÜLER', 4),
(2, '+30 Takipçili Twitter Hesabı (2007–2017 Tarihli)', '₺70', 7000, NULL, 5),
(2, '2008 Tarihli Twitter Hesabı', '₺200', 20000, NULL, 6),
(3, 'Instagram Eski Tarihli Gmailli Hesap', '₺60', 6000, NULL, 1),
(3, 'Instagram 2024 Tarihli 2Falı Hesap', '₺40', 4000, NULL, 2),
(3, 'Instagram Eski Gönderili Hesap', '₺99', 9900, NULL, 3),
(3, 'Asla Onay İstemeyen 2012–2020 Eski Instagram Hesapları', '₺59,99', 5999, NULL, 4),
(3, 'Eski 2024 Instagram Hesapları (2FA Ekli)', '₺30', 3000, NULL, 5),
(3, 'VIP | Premium Gönderimli Eski Instagram Hesapları (2012–2020)', '₺99', 9900, NULL, 6),
(3, 'VIP | 2025 Tarihli Instagram Hesapları', '₺19,99', 1999, NULL, 7),
(3, '100 Takipçili Eski Instagram Hesapları (2012–2022)', '₺90', 9000, NULL, 8),
(3, '2012–2020 IG Hesapları – Telefon Onayı İster', '₺24,99', 2499, NULL, 9),
(3, '(Takipçi) 1000 Adet Garantisiz', '₺100', 10000, NULL, 10),
(3, '(Takipçi) 1000 Adet Garantili (90 Gün)', '₺250', 25000, NULL, 11),
(3, '(Takipçi) 1000 Türk Takipçi (30 Gün Garantili)', '₺400', 40000, NULL, 12)
ON DUPLICATE KEY UPDATE name = VALUES(name), price_label = VALUES(price_label), price_cents = VALUES(price_cents), note = VALUES(note), sort_order = VALUES(sort_order);

INSERT INTO orders (order_no, customer_name, customer_email, customer_phone, status, total_cents, currency, notes) VALUES
('LS-20240101-0001', 'Ali Veli', 'ali@example.com', '+90 555 111 22 33', 'processing', 29900, 'TRY', 'JetBrains lisansı teslim edildi.'),
('LS-20240115-0002', 'Ayşe Yılmaz', 'ayse@example.com', '+90 555 444 55 66', 'completed', 47500, 'TRY', 'Grammarly 12 aylık abonelik tamamlandı.')
ON DUPLICATE KEY UPDATE customer_name = VALUES(customer_name), status = VALUES(status), total_cents = VALUES(total_cents), notes = VALUES(notes);

INSERT INTO activity_logs (user_id, action, entity, entity_id, message) VALUES
(1, 'seed', 'system', NULL, 'Başlangıç verileri yüklendi')
ON DUPLICATE KEY UPDATE message = VALUES(message);
