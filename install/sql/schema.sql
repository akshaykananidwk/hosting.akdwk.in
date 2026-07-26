-- =====================================================================
-- FILE: /install/sql/schema.sql
-- AK CLOUD — aaPanel-powered Multi-Tenant SaaS Hosting Platform
-- Full database schema + seed data  (MySQL 8 / MariaDB 10.6+)
-- Charset: utf8mb4_unicode_ci | Engine: InnoDB
-- ---------------------------------------------------------------------
-- NOTE: imported in chunks by the installer (Module 1). Each statement
--       ends with `;` — the importer splits on that delimiter.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET time_zone = '+00:00';
SET sql_mode = 'NO_ENGINE_SUBSTITUTION';

-- =====================================================================
-- 1. SYSTEM / MIGRATIONS
-- =====================================================================

CREATE TABLE IF NOT EXISTS `migrations` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration`  VARCHAR(255) NOT NULL,
  `batch`      INT NOT NULL DEFAULT 1,
  `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_migration` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 2. MULTI-TENANT CORE
-- =====================================================================

CREATE TABLE IF NOT EXISTS `tenants` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(150) NOT NULL,
  `company`       VARCHAR(200) DEFAULT NULL,
  `subdomain`     VARCHAR(100) DEFAULT NULL,
  `custom_domain` VARCHAR(191) DEFAULT NULL,
  `email`         VARCHAR(191) NOT NULL,
  `phone`         VARCHAR(20) DEFAULT NULL,
  `plan`          ENUM('trial','starter','business','enterprise') NOT NULL DEFAULT 'trial',
  `status`        ENUM('active','suspended','pending','closed') NOT NULL DEFAULT 'active',
  `white_label`   TINYINT(1) NOT NULL DEFAULT 0,
  `api_enabled`   TINYINT(1) NOT NULL DEFAULT 0,
  `max_clients`   INT NOT NULL DEFAULT 50,
  `max_servers`   INT NOT NULL DEFAULT 1,
  `wa_monthly_limit` INT NOT NULL DEFAULT 1000,
  `logo`          VARCHAR(255) DEFAULT NULL,
  `theme_color`   VARCHAR(20) DEFAULT '#2563eb',
  `trial_ends_at` DATE DEFAULT NULL,
  `suspended_at`  TIMESTAMP NULL DEFAULT NULL,
  `retention_until` DATE DEFAULT NULL,
  `is_system`     TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subdomain` (`subdomain`),
  UNIQUE KEY `uq_custom_domain` (`custom_domain`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_subscriptions` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`    BIGINT UNSIGNED NOT NULL,
  `plan`         ENUM('trial','starter','business','enterprise') NOT NULL,
  `amount`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `billing_cycle` ENUM('monthly','quarterly','half_yearly','yearly') NOT NULL DEFAULT 'monthly',
  `status`       ENUM('active','past_due','cancelled','expired') NOT NULL DEFAULT 'active',
  `starts_at`    DATE NOT NULL,
  `renews_at`    DATE DEFAULT NULL,
  `cancelled_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_tsub_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 3. AUTH / RBAC
-- =====================================================================

CREATE TABLE IF NOT EXISTS `roles` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  BIGINT UNSIGNED DEFAULT NULL,
  `name`       VARCHAR(80) NOT NULL,
  `slug`       VARCHAR(80) NOT NULL,
  `scope`      ENUM('super_admin','tenant_owner','reseller','client','staff') NOT NULL DEFAULT 'staff',
  `is_system`  TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `slug`       VARCHAR(120) NOT NULL,
  `group`      VARCHAR(60) NOT NULL DEFAULT 'general',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_perm_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id`       BIGINT UNSIGNED NOT NULL,
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_perm` (`role_id`,`permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`     BIGINT UNSIGNED DEFAULT NULL,
  `role_id`       BIGINT UNSIGNED DEFAULT NULL,
  `name`          VARCHAR(150) NOT NULL,
  `email`         VARCHAR(191) NOT NULL,
  `mobile`        VARCHAR(20) DEFAULT NULL,
  `password`      VARCHAR(255) NOT NULL,
  `type`          ENUM('super_admin','tenant_owner','reseller','client','staff') NOT NULL DEFAULT 'client',
  `status`        ENUM('active','inactive','suspended','pending') NOT NULL DEFAULT 'active',
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `mobile_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `two_factor_secret`  VARCHAR(255) DEFAULT NULL,
  `two_factor_method`  ENUM('totp','whatsapp') DEFAULT NULL,
  `remember_token`     VARCHAR(100) DEFAULT NULL,
  `reset_token`        VARCHAR(100) DEFAULT NULL,
  `reset_expires_at`   TIMESTAMP NULL DEFAULT NULL,
  `otp_code`           VARCHAR(10) DEFAULT NULL,
  `otp_expires_at`     TIMESTAMP NULL DEFAULT NULL,
  `failed_logins`      INT NOT NULL DEFAULT 0,
  `locked_until`       TIMESTAMP NULL DEFAULT NULL,
  `last_login_at`      TIMESTAMP NULL DEFAULT NULL,
  `last_login_ip`      VARCHAR(45) DEFAULT NULL,
  `language`           VARCHAR(5) NOT NULL DEFAULT 'gu',
  `avatar`             VARCHAR(255) DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_email` (`tenant_id`,`email`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  KEY `idx_role` (`role_id`),
  CONSTRAINT `fk_user_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `tenant_id`   BIGINT UNSIGNED DEFAULT NULL,
  `session_id`  VARCHAR(128) NOT NULL,
  `ip_address`  VARCHAR(45) DEFAULT NULL,
  `user_agent`  VARCHAR(255) DEFAULT NULL,
  `device`      VARCHAR(120) DEFAULT NULL,
  `payload`     TEXT DEFAULT NULL,
  `last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_session` (`session_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_sess_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_logs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED DEFAULT NULL,
  `tenant_id`  BIGINT UNSIGNED DEFAULT NULL,
  `email`      VARCHAR(191) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `status`     ENUM('success','failed','locked','2fa_pending') NOT NULL DEFAULT 'success',
  `reason`     VARCHAR(191) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED DEFAULT NULL,
  `user_id`     BIGINT UNSIGNED DEFAULT NULL,
  `action`      VARCHAR(120) NOT NULL,
  `model_type`  VARCHAR(120) DEFAULT NULL,
  `model_id`    BIGINT UNSIGNED DEFAULT NULL,
  `old_values`  JSON DEFAULT NULL,
  `new_values`  JSON DEFAULT NULL,
  `ip_address`  VARCHAR(45) DEFAULT NULL,
  `user_agent`  VARCHAR(255) DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_model` (`model_type`,`model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 4. CLIENTS
-- =====================================================================

CREATE TABLE IF NOT EXISTS `clients` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `user_id`     BIGINT UNSIGNED DEFAULT NULL,
  `reseller_id` BIGINT UNSIGNED DEFAULT NULL,
  `client_code` VARCHAR(30) DEFAULT NULL,
  `company`     VARCHAR(200) DEFAULT NULL,
  `first_name`  VARCHAR(100) NOT NULL,
  `last_name`   VARCHAR(100) DEFAULT NULL,
  `email`       VARCHAR(191) NOT NULL,
  `phone`       VARCHAR(20) DEFAULT NULL,
  `gstin`       VARCHAR(20) DEFAULT NULL,
  `address`     VARCHAR(255) DEFAULT NULL,
  `city`        VARCHAR(100) DEFAULT NULL,
  `state`       VARCHAR(100) DEFAULT NULL,
  `state_code`  VARCHAR(5) DEFAULT NULL,
  `country`     VARCHAR(100) NOT NULL DEFAULT 'India',
  `postcode`    VARCHAR(15) DEFAULT NULL,
  `status`      ENUM('active','inactive','suspended','closed') NOT NULL DEFAULT 'active',
  `credit_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `notes`       TEXT DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_reseller` (`reseller_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_client_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_client_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `client_contacts` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  BIGINT UNSIGNED NOT NULL,
  `client_id`  BIGINT UNSIGNED NOT NULL,
  `name`       VARCHAR(150) NOT NULL,
  `email`      VARCHAR(191) DEFAULT NULL,
  `phone`      VARCHAR(20) DEFAULT NULL,
  `type`       ENUM('billing','technical','admin','general') NOT NULL DEFAULT 'general',
  `receives_invoices` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client` (`client_id`),
  CONSTRAINT `fk_contact_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 5. SERVERS (aaPanel)
-- =====================================================================

CREATE TABLE IF NOT EXISTS `servers` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED DEFAULT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `hostname`    VARCHAR(191) DEFAULT NULL,
  `panel_url`   VARCHAR(191) NOT NULL,
  `api_key`     TEXT NOT NULL,
  `ip_address`  VARCHAR(45) DEFAULT NULL,
  `ssh_port`    INT NOT NULL DEFAULT 22,
  `type`        ENUM('aapanel','custom') NOT NULL DEFAULT 'aapanel',
  `verify_ssl`  TINYINT(1) NOT NULL DEFAULT 0,
  `default_php` VARCHAR(5) NOT NULL DEFAULT '82',
  `max_accounts` INT NOT NULL DEFAULT 150,
  `active_accounts` INT NOT NULL DEFAULT 0,
  `status`      ENUM('online','offline','maintenance','disabled') NOT NULL DEFAULT 'online',
  `is_default`  TINYINT(1) NOT NULL DEFAULT 0,
  `auto_assign` TINYINT(1) NOT NULL DEFAULT 1,
  `nameserver1` VARCHAR(191) DEFAULT NULL,
  `nameserver2` VARCHAR(191) DEFAULT NULL,
  `last_health_at` TIMESTAMP NULL DEFAULT NULL,
  `cpu_percent` DECIMAL(5,2) DEFAULT NULL,
  `ram_percent` DECIMAL(5,2) DEFAULT NULL,
  `disk_percent` DECIMAL(5,2) DEFAULT NULL,
  `load_avg`    VARCHAR(30) DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `server_health_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `server_id`   BIGINT UNSIGNED NOT NULL,
  `cpu_percent` DECIMAL(5,2) DEFAULT NULL,
  `ram_percent` DECIMAL(5,2) DEFAULT NULL,
  `disk_percent` DECIMAL(5,2) DEFAULT NULL,
  `load_avg`    VARCHAR(30) DEFAULT NULL,
  `ram_total`   BIGINT DEFAULT NULL,
  `ram_used`    BIGINT DEFAULT NULL,
  `disk_total`  BIGINT DEFAULT NULL,
  `disk_used`   BIGINT DEFAULT NULL,
  `net_up`      BIGINT DEFAULT NULL,
  `net_down`    BIGINT DEFAULT NULL,
  `status`      ENUM('online','offline') NOT NULL DEFAULT 'online',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_server` (`server_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_health_server` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `aapanel_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `server_id`   BIGINT UNSIGNED DEFAULT NULL,
  `tenant_id`   BIGINT UNSIGNED DEFAULT NULL,
  `service_id`  BIGINT UNSIGNED DEFAULT NULL,
  `endpoint`    VARCHAR(191) NOT NULL,
  `method`      VARCHAR(60) DEFAULT NULL,
  `params`      TEXT DEFAULT NULL,
  `response`    MEDIUMTEXT DEFAULT NULL,
  `http_code`   INT DEFAULT NULL,
  `status`      ENUM('success','failed','error') NOT NULL DEFAULT 'success',
  `duration_ms` INT DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_server` (`server_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 6. PRODUCTS / PLANS
-- =====================================================================

CREATE TABLE IF NOT EXISTS `product_groups` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  BIGINT UNSIGNED NOT NULL,
  `name`       VARCHAR(120) NOT NULL,
  `slug`       VARCHAR(120) NOT NULL,
  `type`       ENUM('shared','reseller','vps','domain','ssl','other') NOT NULL DEFAULT 'shared',
  `description` TEXT DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `status`     ENUM('active','hidden') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_pgroup_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`    BIGINT UNSIGNED NOT NULL,
  `group_id`     BIGINT UNSIGNED NOT NULL,
  `name`         VARCHAR(150) NOT NULL,
  `slug`         VARCHAR(150) NOT NULL,
  `description`  TEXT DEFAULT NULL,
  `type`         ENUM('shared','reseller','vps','domain','ssl','other') NOT NULL DEFAULT 'shared',
  `disk_mb`      INT NOT NULL DEFAULT 1024,
  `bandwidth_mb` INT NOT NULL DEFAULT 20480,
  `is_bw_unlimited` TINYINT(1) NOT NULL DEFAULT 0,
  `max_sites`    INT NOT NULL DEFAULT 1,
  `max_addon_domains` INT NOT NULL DEFAULT 0,
  `max_subdomains` INT NOT NULL DEFAULT 0,
  `max_databases` INT NOT NULL DEFAULT 1,
  `max_ftp`      INT NOT NULL DEFAULT 1,
  `max_email`    INT NOT NULL DEFAULT 0,
  `php_version`  VARCHAR(5) NOT NULL DEFAULT '82',
  `php_selectable` TINYINT(1) NOT NULL DEFAULT 1,
  `free_ssl`     TINYINT(1) NOT NULL DEFAULT 1,
  `backup_freq`  ENUM('none','daily','weekly','monthly') NOT NULL DEFAULT 'daily',
  `setup_fee`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `auto_provision` TINYINT(1) NOT NULL DEFAULT 1,
  `server_id`    BIGINT UNSIGNED DEFAULT NULL,
  `stock_control` TINYINT(1) NOT NULL DEFAULT 0,
  `stock_qty`    INT DEFAULT NULL,
  `sort_order`   INT NOT NULL DEFAULT 0,
  `status`       ENUM('active','hidden','retired') NOT NULL DEFAULT 'active',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_group` (`group_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_product_group` FOREIGN KEY (`group_id`) REFERENCES `product_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_pricing` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `product_id`  BIGINT UNSIGNED NOT NULL,
  `currency`    VARCHAR(5) NOT NULL DEFAULT 'INR',
  `monthly`     DECIMAL(12,2) DEFAULT NULL,
  `quarterly`   DECIMAL(12,2) DEFAULT NULL,
  `half_yearly` DECIMAL(12,2) DEFAULT NULL,
  `yearly`      DECIMAL(12,2) DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `fk_pricing_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 7. ORDERS & SERVICES
-- =====================================================================

CREATE TABLE IF NOT EXISTS `orders` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`    BIGINT UNSIGNED NOT NULL,
  `client_id`    BIGINT UNSIGNED NOT NULL,
  `order_number` VARCHAR(40) NOT NULL,
  `status`       ENUM('pending','active','fraud','cancelled','completed') NOT NULL DEFAULT 'pending',
  `subtotal`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tax`          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `currency`     VARCHAR(5) NOT NULL DEFAULT 'INR',
  `coupon_id`    BIGINT UNSIGNED DEFAULT NULL,
  `payment_method` VARCHAR(60) DEFAULT NULL,
  `ip_address`   VARCHAR(45) DEFAULT NULL,
  `notes`        TEXT DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_number` (`order_number`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_order_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `order_id`    BIGINT UNSIGNED NOT NULL,
  `product_id`  BIGINT UNSIGNED DEFAULT NULL,
  `type`        ENUM('hosting','domain','ssl','addon','setup') NOT NULL DEFAULT 'hosting',
  `description` VARCHAR(255) NOT NULL,
  `domain`      VARCHAR(191) DEFAULT NULL,
  `billing_cycle` ENUM('monthly','quarterly','half_yearly','yearly','one_time') NOT NULL DEFAULT 'monthly',
  `qty`         INT NOT NULL DEFAULT 1,
  `unit_price`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `setup_fee`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  CONSTRAINT `fk_oitem_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `services` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`     BIGINT UNSIGNED NOT NULL,
  `client_id`     BIGINT UNSIGNED NOT NULL,
  `order_id`      BIGINT UNSIGNED DEFAULT NULL,
  `product_id`    BIGINT UNSIGNED DEFAULT NULL,
  `server_id`     BIGINT UNSIGNED DEFAULT NULL,
  `reseller_id`   BIGINT UNSIGNED DEFAULT NULL,
  `domain`        VARCHAR(191) NOT NULL,
  `aapanel_site_id` INT DEFAULT NULL,
  `site_path`     VARCHAR(255) DEFAULT NULL,
  `username`      VARCHAR(32) DEFAULT NULL,
  `password_enc`  TEXT DEFAULT NULL,
  `billing_cycle` ENUM('monthly','quarterly','half_yearly','yearly','one_time') NOT NULL DEFAULT 'monthly',
  `first_payment` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `recurring_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status`        ENUM('pending','active','suspended','terminated','cancelled','fraud') NOT NULL DEFAULT 'pending',
  `suspend_reason` VARCHAR(255) DEFAULT NULL,
  `disk_limit_mb` INT NOT NULL DEFAULT 1024,
  `bandwidth_limit_mb` INT NOT NULL DEFAULT 20480,
  `is_bw_unlimited` TINYINT(1) NOT NULL DEFAULT 0,
  `disk_used_mb`  INT NOT NULL DEFAULT 0,
  `bandwidth_used_mb` INT NOT NULL DEFAULT 0,
  `php_version`   VARCHAR(5) NOT NULL DEFAULT '82',
  `reg_date`      DATE DEFAULT NULL,
  `next_due_date` DATE DEFAULT NULL,
  `termination_date` DATE DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_server` (`server_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due` (`next_due_date`),
  KEY `idx_domain` (`domain`),
  CONSTRAINT `fk_service_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_service_server` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `service_details` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `service_id`  BIGINT UNSIGNED NOT NULL,
  `db_name`     VARCHAR(120) DEFAULT NULL,
  `db_user`     VARCHAR(120) DEFAULT NULL,
  `db_pass_enc` TEXT DEFAULT NULL,
  `db_host`     VARCHAR(120) DEFAULT '127.0.0.1',
  `ftp_user`    VARCHAR(120) DEFAULT NULL,
  `ftp_pass_enc` TEXT DEFAULT NULL,
  `ftp_host`    VARCHAR(120) DEFAULT NULL,
  `ftp_port`    INT DEFAULT 21,
  `fpm_pool`    VARCHAR(120) DEFAULT NULL,
  `linux_user`  VARCHAR(120) DEFAULT NULL,
  `ssl_status`  ENUM('none','pending','active','expired','failed') NOT NULL DEFAULT 'none',
  `ssl_expires_at` DATE DEFAULT NULL,
  `isolation_applied` TINYINT(1) NOT NULL DEFAULT 0,
  `meta`        JSON DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_service` (`service_id`),
  CONSTRAINT `fk_sdetail_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `provisioning_queue` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `service_id`  BIGINT UNSIGNED DEFAULT NULL,
  `order_id`    BIGINT UNSIGNED DEFAULT NULL,
  `action`      ENUM('create','suspend','unsuspend','terminate','change_password','change_plan','install_ssl','retry') NOT NULL DEFAULT 'create',
  `payload`     JSON DEFAULT NULL,
  `status`      ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `attempts`    INT NOT NULL DEFAULT 0,
  `max_attempts` INT NOT NULL DEFAULT 3,
  `last_error`  TEXT DEFAULT NULL,
  `available_at` TIMESTAMP NULL DEFAULT NULL,
  `reserved_at` TIMESTAMP NULL DEFAULT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_service` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `provisioning_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `service_id`  BIGINT UNSIGNED DEFAULT NULL,
  `queue_id`    BIGINT UNSIGNED DEFAULT NULL,
  `step`        VARCHAR(80) NOT NULL,
  `status`      ENUM('info','success','warning','error') NOT NULL DEFAULT 'info',
  `message`     TEXT DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service` (`service_id`),
  KEY `idx_queue` (`queue_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 8. QUOTA & BANDWIDTH (aaPanel gaps solved by AK Cloud)
-- =====================================================================

CREATE TABLE IF NOT EXISTS `disk_usage` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `service_id`  BIGINT UNSIGNED NOT NULL,
  `files_bytes` BIGINT NOT NULL DEFAULT 0,
  `db_bytes`    BIGINT NOT NULL DEFAULT 0,
  `total_bytes` BIGINT NOT NULL DEFAULT 0,
  `percent`     DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `checked_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service` (`service_id`),
  KEY `idx_checked` (`checked_at`),
  CONSTRAINT `fk_disk_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bandwidth_usage` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `service_id`  BIGINT UNSIGNED NOT NULL,
  `usage_date`  DATE NOT NULL,
  `bytes`       BIGINT NOT NULL DEFAULT 0,
  `log_offset`  BIGINT NOT NULL DEFAULT 0,
  `log_inode`   BIGINT DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_service_date` (`service_id`,`usage_date`),
  KEY `idx_date` (`usage_date`),
  CONSTRAINT `fk_bw_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quota_warnings` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `service_id`  BIGINT UNSIGNED NOT NULL,
  `type`        ENUM('disk','bandwidth') NOT NULL DEFAULT 'disk',
  `threshold`   ENUM('80','90','100') NOT NULL,
  `notified_at` TIMESTAMP NULL DEFAULT NULL,
  `grace_until` DATE DEFAULT NULL,
  `resolved`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service` (`service_id`),
  CONSTRAINT `fk_qwarn_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 9. DOMAINS
-- =====================================================================

CREATE TABLE IF NOT EXISTS `domains` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `client_id`   BIGINT UNSIGNED NOT NULL,
  `service_id`  BIGINT UNSIGNED DEFAULT NULL,
  `domain`      VARCHAR(191) NOT NULL,
  `type`        ENUM('register','transfer','own') NOT NULL DEFAULT 'own',
  `registrar`   VARCHAR(60) DEFAULT NULL,
  `status`      ENUM('pending','active','expired','cancelled','transferred') NOT NULL DEFAULT 'active',
  `auto_renew`  TINYINT(1) NOT NULL DEFAULT 1,
  `whois_privacy` TINYINT(1) NOT NULL DEFAULT 0,
  `nameservers` JSON DEFAULT NULL,
  `reg_date`    DATE DEFAULT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_expiry` (`expiry_date`),
  CONSTRAINT `fk_domain_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `domain_dns` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `domain_id`   BIGINT UNSIGNED NOT NULL,
  `type`        ENUM('A','AAAA','CNAME','MX','TXT','SRV','NS') NOT NULL,
  `name`        VARCHAR(191) NOT NULL,
  `content`     VARCHAR(500) NOT NULL,
  `priority`    INT DEFAULT NULL,
  `ttl`         INT NOT NULL DEFAULT 3600,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_domain` (`domain_id`),
  CONSTRAINT `fk_dns_domain` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 10. BILLING
-- =====================================================================

CREATE TABLE IF NOT EXISTS `invoices` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`    BIGINT UNSIGNED NOT NULL,
  `client_id`    BIGINT UNSIGNED NOT NULL,
  `invoice_number` VARCHAR(40) NOT NULL,
  `status`       ENUM('draft','unpaid','paid','overdue','cancelled','refunded') NOT NULL DEFAULT 'unpaid',
  `subtotal`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cgst`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `sgst`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `igst`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tax_total`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `credit_used`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `late_fee`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `currency`     VARCHAR(5) NOT NULL DEFAULT 'INR',
  `place_of_supply` VARCHAR(5) DEFAULT NULL,
  `issue_date`   DATE NOT NULL,
  `due_date`     DATE NOT NULL,
  `paid_at`      TIMESTAMP NULL DEFAULT NULL,
  `notes`        TEXT DEFAULT NULL,
  `pdf_path`     VARCHAR(255) DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoice_number` (`invoice_number`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due` (`due_date`),
  CONSTRAINT `fk_invoice_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `invoice_id`  BIGINT UNSIGNED NOT NULL,
  `service_id`  BIGINT UNSIGNED DEFAULT NULL,
  `description` VARCHAR(255) NOT NULL,
  `hsn_sac`     VARCHAR(15) DEFAULT NULL,
  `qty`         INT NOT NULL DEFAULT 1,
  `unit_price`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tax_rate`    DECIMAL(5,2) NOT NULL DEFAULT 18.00,
  `amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `period_start` DATE DEFAULT NULL,
  `period_end`  DATE DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_invoice` (`invoice_id`),
  CONSTRAINT `fk_iitem_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transactions` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `invoice_id`  BIGINT UNSIGNED DEFAULT NULL,
  `client_id`   BIGINT UNSIGNED DEFAULT NULL,
  `gateway`     VARCHAR(60) NOT NULL DEFAULT 'manual',
  `transaction_id` VARCHAR(191) DEFAULT NULL,
  `gateway_ref` VARCHAR(191) DEFAULT NULL,
  `type`        ENUM('payment','refund','credit','chargeback') NOT NULL DEFAULT 'payment',
  `amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `fee`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `currency`    VARCHAR(5) NOT NULL DEFAULT 'INR',
  `status`      ENUM('pending','success','failed','refunded') NOT NULL DEFAULT 'pending',
  `utr`         VARCHAR(60) DEFAULT NULL,
  `meta`        JSON DEFAULT NULL,
  `paid_at`     TIMESTAMP NULL DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_invoice` (`invoice_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_gateway` (`gateway`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `credits` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `client_id`   BIGINT UNSIGNED NOT NULL,
  `amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `type`        ENUM('add','deduct') NOT NULL DEFAULT 'add',
  `description` VARCHAR(255) DEFAULT NULL,
  `invoice_id`  BIGINT UNSIGNED DEFAULT NULL,
  `admin_id`    BIGINT UNSIGNED DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client` (`client_id`),
  CONSTRAINT `fk_credit_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coupons` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `code`        VARCHAR(60) NOT NULL,
  `type`        ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  `value`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `applies_to`  ENUM('all','product','group') NOT NULL DEFAULT 'all',
  `applies_id`  BIGINT UNSIGNED DEFAULT NULL,
  `max_uses`    INT DEFAULT NULL,
  `used_count`  INT NOT NULL DEFAULT 0,
  `per_client`  INT NOT NULL DEFAULT 1,
  `min_amount`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `starts_at`   DATE DEFAULT NULL,
  `expires_at`  DATE DEFAULT NULL,
  `status`      ENUM('active','disabled') NOT NULL DEFAULT 'active',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_code` (`tenant_id`,`code`),
  CONSTRAINT `fk_coupon_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coupon_usage` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `coupon_id`   BIGINT UNSIGNED NOT NULL,
  `client_id`   BIGINT UNSIGNED NOT NULL,
  `order_id`    BIGINT UNSIGNED DEFAULT NULL,
  `discount`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_coupon` (`coupon_id`),
  CONSTRAINT `fk_cusage_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_gateways` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `driver`      VARCHAR(60) NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `mode`        ENUM('test','live') NOT NULL DEFAULT 'test',
  `is_active`   TINYINT(1) NOT NULL DEFAULT 0,
  `is_default`  TINYINT(1) NOT NULL DEFAULT 0,
  `config`      TEXT DEFAULT NULL,
  `sort_order`  INT NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_gw_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 11. SUPPORT TICKETS
-- =====================================================================

CREATE TABLE IF NOT EXISTS `ticket_departments` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `email`       VARCHAR(191) DEFAULT NULL,
  `sla_hours`   INT NOT NULL DEFAULT 24,
  `sort_order`  INT NOT NULL DEFAULT 0,
  `status`      ENUM('active','hidden') NOT NULL DEFAULT 'active',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_dept_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tickets` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`    BIGINT UNSIGNED NOT NULL,
  `client_id`    BIGINT UNSIGNED DEFAULT NULL,
  `department_id` BIGINT UNSIGNED DEFAULT NULL,
  `service_id`   BIGINT UNSIGNED DEFAULT NULL,
  `assigned_to`  BIGINT UNSIGNED DEFAULT NULL,
  `ticket_number` VARCHAR(30) NOT NULL,
  `subject`      VARCHAR(255) NOT NULL,
  `priority`     ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `status`       ENUM('open','answered','customer_reply','on_hold','closed') NOT NULL DEFAULT 'open',
  `sla_due_at`   TIMESTAMP NULL DEFAULT NULL,
  `sla_breached` TINYINT(1) NOT NULL DEFAULT 0,
  `rating`       TINYINT DEFAULT NULL,
  `last_reply_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_number` (`ticket_number`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ticket_replies` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `ticket_id`   BIGINT UNSIGNED NOT NULL,
  `user_id`     BIGINT UNSIGNED DEFAULT NULL,
  `author_type` ENUM('client','staff','system') NOT NULL DEFAULT 'client',
  `message`     MEDIUMTEXT NOT NULL,
  `is_internal` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ticket` (`ticket_id`),
  CONSTRAINT `fk_treply_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ticket_attachments` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `ticket_id`   BIGINT UNSIGNED NOT NULL,
  `reply_id`    BIGINT UNSIGNED DEFAULT NULL,
  `filename`    VARCHAR(255) NOT NULL,
  `stored_name` VARCHAR(255) NOT NULL,
  `path`        VARCHAR(500) NOT NULL,
  `mime`        VARCHAR(120) DEFAULT NULL,
  `size`        BIGINT DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ticket` (`ticket_id`),
  CONSTRAINT `fk_tattach_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 12. RESELLERS / WALLETS / AFFILIATES
-- =====================================================================

CREATE TABLE IF NOT EXISTS `resellers` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `user_id`     BIGINT UNSIGNED DEFAULT NULL,
  `client_id`   BIGINT UNSIGNED DEFAULT NULL,
  `company`     VARCHAR(200) DEFAULT NULL,
  `brand_name`  VARCHAR(120) DEFAULT NULL,
  `brand_domain` VARCHAR(191) DEFAULT NULL,
  `logo`        VARCHAR(255) DEFAULT NULL,
  `markup_type` ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  `markup_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `wallet_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status`      ENUM('pending','active','suspended','rejected') NOT NULL DEFAULT 'pending',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_reseller_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reseller_pricing` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `reseller_id` BIGINT UNSIGNED NOT NULL,
  `product_id`  BIGINT UNSIGNED NOT NULL,
  `monthly`     DECIMAL(12,2) DEFAULT NULL,
  `quarterly`   DECIMAL(12,2) DEFAULT NULL,
  `half_yearly` DECIMAL(12,2) DEFAULT NULL,
  `yearly`      DECIMAL(12,2) DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reseller` (`reseller_id`),
  CONSTRAINT `fk_rpricing_reseller` FOREIGN KEY (`reseller_id`) REFERENCES `resellers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wallets` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `client_id`   BIGINT UNSIGNED DEFAULT NULL,
  `reseller_id` BIGINT UNSIGNED DEFAULT NULL,
  `balance`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `currency`    VARCHAR(5) NOT NULL DEFAULT 'INR',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_reseller` (`reseller_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `wallet_id`   BIGINT UNSIGNED NOT NULL,
  `type`        ENUM('credit','debit') NOT NULL,
  `amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `balance_after` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `description` VARCHAR(255) DEFAULT NULL,
  `reference`   VARCHAR(120) DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wallet` (`wallet_id`),
  CONSTRAINT `fk_wtx_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `affiliates` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `client_id`   BIGINT UNSIGNED NOT NULL,
  `code`        VARCHAR(60) NOT NULL,
  `visits`      INT NOT NULL DEFAULT 0,
  `signups`     INT NOT NULL DEFAULT 0,
  `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  `balance`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_earned` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `min_payout`  DECIMAL(12,2) NOT NULL DEFAULT 1000.00,
  `status`      ENUM('active','disabled') NOT NULL DEFAULT 'active',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aff_code` (`tenant_id`,`code`),
  KEY `idx_client` (`client_id`),
  CONSTRAINT `fk_aff_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `affiliate_commissions` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `affiliate_id` BIGINT UNSIGNED NOT NULL,
  `client_id`   BIGINT UNSIGNED DEFAULT NULL,
  `invoice_id`  BIGINT UNSIGNED DEFAULT NULL,
  `amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status`      ENUM('pending','approved','paid','rejected') NOT NULL DEFAULT 'pending',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_affiliate` (`affiliate_id`),
  CONSTRAINT `fk_affcomm_affiliate` FOREIGN KEY (`affiliate_id`) REFERENCES `affiliates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 13. NOTIFICATIONS — EMAIL
-- =====================================================================

CREATE TABLE IF NOT EXISTS `email_templates` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED DEFAULT NULL,
  `slug`        VARCHAR(120) NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `subject`     VARCHAR(255) NOT NULL,
  `body`        MEDIUMTEXT NOT NULL,
  `language`    VARCHAR(5) NOT NULL DEFAULT 'gu',
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_queue` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED DEFAULT NULL,
  `to_email`    VARCHAR(191) NOT NULL,
  `to_name`     VARCHAR(150) DEFAULT NULL,
  `subject`     VARCHAR(255) NOT NULL,
  `body`        MEDIUMTEXT NOT NULL,
  `attachments` JSON DEFAULT NULL,
  `status`      ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts`    INT NOT NULL DEFAULT 0,
  `error`       TEXT DEFAULT NULL,
  `sent_at`     TIMESTAMP NULL DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED DEFAULT NULL,
  `to_email`    VARCHAR(191) NOT NULL,
  `subject`     VARCHAR(255) DEFAULT NULL,
  `status`      ENUM('sent','failed') NOT NULL DEFAULT 'sent',
  `error`       TEXT DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 14. NOTIFICATIONS — WHATSAPP
-- =====================================================================

CREATE TABLE IF NOT EXISTS `whatsapp_templates` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED DEFAULT NULL,
  `slug`        VARCHAR(120) NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `body`        MEDIUMTEXT NOT NULL,
  `language`    VARCHAR(5) NOT NULL DEFAULT 'gu',
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `whatsapp_queue` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED DEFAULT NULL,
  `number`      VARCHAR(20) NOT NULL,
  `message`     MEDIUMTEXT NOT NULL,
  `media_url`   VARCHAR(500) DEFAULT NULL,
  `campaign_id` BIGINT UNSIGNED DEFAULT NULL,
  `status`      ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts`    INT NOT NULL DEFAULT 0,
  `error`       TEXT DEFAULT NULL,
  `scheduled_at` TIMESTAMP NULL DEFAULT NULL,
  `sent_at`     TIMESTAMP NULL DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_scheduled` (`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `whatsapp_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED DEFAULT NULL,
  `number`      VARCHAR(20) NOT NULL,
  `message`     MEDIUMTEXT DEFAULT NULL,
  `media_url`   VARCHAR(500) DEFAULT NULL,
  `status`      ENUM('sent','failed') NOT NULL DEFAULT 'sent',
  `response`    TEXT DEFAULT NULL,
  `event`       VARCHAR(80) DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_number` (`number`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `whatsapp_campaigns` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `message`     MEDIUMTEXT NOT NULL,
  `media_url`   VARCHAR(500) DEFAULT NULL,
  `filter`      JSON DEFAULT NULL,
  `total`       INT NOT NULL DEFAULT 0,
  `sent`        INT NOT NULL DEFAULT 0,
  `failed`      INT NOT NULL DEFAULT 0,
  `status`      ENUM('draft','scheduled','running','completed','cancelled') NOT NULL DEFAULT 'draft',
  `scheduled_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_wacamp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 15. CONTENT — ANNOUNCEMENTS / KB
-- =====================================================================

CREATE TABLE IF NOT EXISTS `announcements` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `title`       VARCHAR(200) NOT NULL,
  `body`        MEDIUMTEXT NOT NULL,
  `audience`    ENUM('all','clients','resellers') NOT NULL DEFAULT 'all',
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `published_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_announce_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kb_categories` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `slug`        VARCHAR(150) NOT NULL,
  `parent_id`   BIGINT UNSIGNED DEFAULT NULL,
  `sort_order`  INT NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `knowledgebase` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `category_id` BIGINT UNSIGNED DEFAULT NULL,
  `title`       VARCHAR(255) NOT NULL,
  `slug`        VARCHAR(255) NOT NULL,
  `body`        MEDIUMTEXT NOT NULL,
  `views`       INT NOT NULL DEFAULT 0,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 16. SYSTEM — SETTINGS / CRON / BACKUPS / UPDATES / API
-- =====================================================================

CREATE TABLE IF NOT EXISTS `settings` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED DEFAULT NULL,
  `group`       VARCHAR(60) NOT NULL DEFAULT 'general',
  `key`         VARCHAR(120) NOT NULL,
  `value`       MEDIUMTEXT DEFAULT NULL,
  `is_encrypted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_key` (`tenant_id`,`group`,`key`),
  KEY `idx_group` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cron_jobs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(120) NOT NULL,
  `slug`        VARCHAR(120) NOT NULL,
  `schedule`    VARCHAR(60) NOT NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `last_run_at` TIMESTAMP NULL DEFAULT NULL,
  `next_run_at` TIMESTAMP NULL DEFAULT NULL,
  `last_status` ENUM('success','failed','running','idle') NOT NULL DEFAULT 'idle',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cron_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cron_job_id` BIGINT UNSIGNED DEFAULT NULL,
  `slug`        VARCHAR(120) DEFAULT NULL,
  `status`      ENUM('success','failed','skipped') NOT NULL DEFAULT 'success',
  `output`      MEDIUMTEXT DEFAULT NULL,
  `duration_ms` INT DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slug` (`slug`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `backups` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED DEFAULT NULL,
  `service_id`  BIGINT UNSIGNED DEFAULT NULL,
  `type`        ENUM('full','files','database','system') NOT NULL DEFAULT 'full',
  `destination` ENUM('local','gdrive','dropbox','s3','ftp') NOT NULL DEFAULT 'local',
  `filename`    VARCHAR(255) DEFAULT NULL,
  `path`        VARCHAR(500) DEFAULT NULL,
  `size`        BIGINT DEFAULT NULL,
  `status`      ENUM('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
  `error`       TEXT DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_service` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `update_history` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_version` VARCHAR(30) DEFAULT NULL,
  `to_version`  VARCHAR(30) DEFAULT NULL,
  `commit_hash` VARCHAR(60) DEFAULT NULL,
  `status`      ENUM('success','failed','rolled_back') NOT NULL DEFAULT 'success',
  `backup_path` VARCHAR(500) DEFAULT NULL,
  `log`         MEDIUMTEXT DEFAULT NULL,
  `admin_id`    BIGINT UNSIGNED DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_keys` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED DEFAULT NULL,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `token_hash`  VARCHAR(191) NOT NULL,
  `prefix`      VARCHAR(20) NOT NULL,
  `scopes`      JSON DEFAULT NULL,
  `rate_limit`  INT NOT NULL DEFAULT 60,
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  `expires_at`  TIMESTAMP NULL DEFAULT NULL,
  `status`      ENUM('active','revoked') NOT NULL DEFAULT 'active',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token_hash` (`token_hash`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_apikey_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED DEFAULT NULL,
  `api_key_id`  BIGINT UNSIGNED DEFAULT NULL,
  `method`      VARCHAR(10) NOT NULL,
  `endpoint`    VARCHAR(191) NOT NULL,
  `ip_address`  VARCHAR(45) DEFAULT NULL,
  `status_code` INT DEFAULT NULL,
  `duration_ms` INT DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_key` (`api_key_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED DEFAULT NULL,
  `user_id`     BIGINT UNSIGNED DEFAULT NULL,
  `type`        VARCHAR(80) NOT NULL,
  `title`       VARCHAR(200) NOT NULL,
  `body`        TEXT DEFAULT NULL,
  `link`        VARCHAR(255) DEFAULT NULL,
  `icon`        VARCHAR(60) DEFAULT NULL,
  `is_read`     TINYINT(1) NOT NULL DEFAULT 0,
  `read_at`     TIMESTAMP NULL DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- =====================================================================
--                            SEED DATA
-- =====================================================================
-- =====================================================================

-- ---- System tenant (AK Cloud platform owner) ------------------------
INSERT INTO `tenants`
  (`id`,`name`,`company`,`subdomain`,`email`,`phone`,`plan`,`status`,`white_label`,`api_enabled`,`max_clients`,`max_servers`,`wa_monthly_limit`,`is_system`)
VALUES
  (1,'AK Cloud','AK Cloud Hosting','app','support@akdwk.in','7016034943','enterprise','active',1,1,999999,99,999999,1);

-- ---- Roles ----------------------------------------------------------
INSERT INTO `roles` (`id`,`tenant_id`,`name`,`slug`,`scope`,`is_system`) VALUES
  (1,NULL,'Super Admin','super-admin','super_admin',1),
  (2,1,'Tenant Owner','tenant-owner','tenant_owner',1),
  (3,1,'Reseller','reseller','reseller',1),
  (4,1,'Client','client','client',1),
  (5,1,'Staff','staff','staff',1);

-- ---- Permissions (grouped) -----------------------------------------
INSERT INTO `permissions` (`name`,`slug`,`group`) VALUES
  ('View Dashboard','dashboard.view','general'),
  ('Manage Clients','clients.manage','clients'),
  ('View Clients','clients.view','clients'),
  ('Impersonate Client','clients.impersonate','clients'),
  ('Manage Services','services.manage','services'),
  ('Provision Services','services.provision','services'),
  ('Manage Servers','servers.manage','servers'),
  ('Manage Products','products.manage','products'),
  ('Manage Invoices','invoices.manage','billing'),
  ('Manage Transactions','transactions.manage','billing'),
  ('Manage Coupons','coupons.manage','billing'),
  ('Manage Tickets','tickets.manage','support'),
  ('Manage Domains','domains.manage','domains'),
  ('Manage Resellers','resellers.manage','resellers'),
  ('Manage Templates','templates.manage','settings'),
  ('Manage Settings','settings.manage','settings'),
  ('View Reports','reports.view','reports'),
  ('View Logs','logs.view','settings'),
  ('Run Updates','updates.run','settings'),
  ('Manage Backups','backups.manage','settings'),
  ('Manage Staff','staff.manage','settings'),
  ('Use API','api.use','settings'),
  ('Broadcast WhatsApp','whatsapp.broadcast','support');

-- ---- Super Admin gets every permission ------------------------------
INSERT INTO `role_permissions` (`role_id`,`permission_id`)
  SELECT 1, `id` FROM `permissions`;

-- ---- Product group + the 4 suggested plans for this VPS -------------
INSERT INTO `product_groups` (`id`,`tenant_id`,`name`,`slug`,`type`,`description`,`sort_order`) VALUES
  (1,1,'Shared Hosting','shared-hosting','shared','SSD shared hosting plans',1),
  (2,1,'Reseller Hosting','reseller-hosting','reseller','White-label reseller plans',2);

INSERT INTO `products`
  (`id`,`tenant_id`,`group_id`,`name`,`slug`,`type`,`disk_mb`,`bandwidth_mb`,`is_bw_unlimited`,`max_sites`,`max_databases`,`max_ftp`,`php_version`,`free_ssl`,`backup_freq`,`auto_provision`,`sort_order`)
VALUES
  (1,1,1,'Starter','starter','shared',1024,20480,0,1,1,1,'82',1,'weekly',1,1),
  (2,1,1,'Business','business','shared',5120,102400,0,3,3,3,'82',1,'daily',1,2),
  (3,1,1,'Professional','professional','shared',15360,0,1,10,10,10,'82',1,'daily',1,3),
  (4,1,2,'Reseller','reseller','reseller',51200,0,1,50,50,50,'82',1,'daily',1,4);

INSERT INTO `product_pricing`
  (`tenant_id`,`product_id`,`currency`,`monthly`,`quarterly`,`half_yearly`,`yearly`)
VALUES
  (1,1,'INR',99.00,282.00,534.00,999.00),
  (1,2,'INR',249.00,708.00,1344.00,2499.00),
  (1,3,'INR',599.00,1707.00,3234.00,5999.00),
  (1,4,'INR',1499.00,4272.00,8094.00,14999.00);

-- ---- Payment gateways (drivers, disabled until keys added) ----------
INSERT INTO `payment_gateways` (`tenant_id`,`driver`,`name`,`mode`,`is_active`,`is_default`,`sort_order`) VALUES
  (1,'razorpay','Razorpay','test',0,1,1),
  (1,'payu','PayU','test',0,0,2),
  (1,'cashfree','Cashfree','test',0,0,3),
  (1,'phonepe','PhonePe','test',0,0,4),
  (1,'upi_manual','UPI (Manual QR + UTR)','live',0,0,5),
  (1,'bank_transfer','Bank Transfer','live',0,0,6),
  (1,'stripe','Stripe','test',0,0,7),
  (1,'paypal','PayPal','test',0,0,8);

-- ---- Ticket departments --------------------------------------------
INSERT INTO `ticket_departments` (`tenant_id`,`name`,`email`,`sla_hours`,`sort_order`) VALUES
  (1,'Sales','sales@akdwk.in',12,1),
  (1,'Technical Support','support@akdwk.in',6,2),
  (1,'Billing','billing@akdwk.in',24,3);

-- ---- KB category ----------------------------------------------------
INSERT INTO `kb_categories` (`tenant_id`,`name`,`slug`,`sort_order`) VALUES
  (1,'Getting Started','getting-started',1),
  (1,'Billing & Payments','billing-payments',2),
  (1,'Hosting & Domains','hosting-domains',3);

-- ---- Cron jobs (Module 17 — single entry, many jobs) ---------------
INSERT INTO `cron_jobs` (`name`,`slug`,`schedule`) VALUES
  ('Provisioning Worker','provisioning_worker','*/5 * * * *'),
  ('WhatsApp Queue Worker','whatsapp_queue_worker','* * * * *'),
  ('Email Queue Worker','email_queue_worker','*/5 * * * *'),
  ('Disk Quota Check','quota_check','*/30 * * * *'),
  ('Bandwidth Parser','bandwidth_parser','0 * * * *'),
  ('Server Health Check','server_health_check','*/10 * * * *'),
  ('Generate Invoices','generate_invoices','30 0 * * *'),
  ('Payment Reminders','payment_reminders','0 9 * * *'),
  ('Suspend Overdue','suspend_overdue','0 1 * * *'),
  ('Unsuspend Paid','unsuspend_paid','*/15 * * * *'),
  ('SSL Auto Renew','ssl_auto_renew','0 2 * * *'),
  ('Domain Expiry Check','domain_expiry_check','0 10 * * *'),
  ('Auto Backup','auto_backup','0 3 * * *'),
  ('Cleanup Logs','cleanup_logs','0 4 * * 0'),
  ('GitHub Update Check','github_update_check','0 4 * * *'),
  ('Daily Summary WhatsApp','daily_summary_whatsapp','0 21 * * *');

-- ---- Default settings (non-secret; secrets set in installer) --------
INSERT INTO `settings` (`tenant_id`,`group`,`key`,`value`,`is_encrypted`) VALUES
  (NULL,'general','company_name','AK Cloud',0),
  (NULL,'general','app_url','',0),
  (NULL,'general','timezone','Asia/Kolkata',0),
  (NULL,'general','currency','INR',0),
  (NULL,'general','currency_symbol','₹',0),
  (NULL,'general','default_language','en',0),
  (NULL,'general','maintenance_mode','0',0),
  (NULL,'tax','gst_enabled','1',0),
  (NULL,'tax','gst_percent','18',0),
  (NULL,'tax','company_gstin','',0),
  (NULL,'tax','company_state_code','24',0),
  (NULL,'billing','invoice_prefix','AKC-',0),
  (NULL,'billing','invoice_generate_days','7',0),
  (NULL,'billing','grace_days','3',0),
  (NULL,'billing','late_fee','0',0),
  (NULL,'quota','disk_grace_days','3',0),
  (NULL,'quota','use_setquota','0',0),
  (NULL,'quota','warn_thresholds','80,90,100',0),
  (NULL,'isolation','enabled','1',0),
  (NULL,'isolation','use_aapanel_builtin','0',0),
  (NULL,'isolation','disabled_functions','exec,shell_exec,system,passthru,proc_open,popen,symlink,link,putenv,pcntl_exec,dl',0),
  (NULL,'whatsapp','api_url','https://bulk.akdwk.in/api.php',0),
  (NULL,'whatsapp','api_key','',1),
  (NULL,'whatsapp','session_id','',1),
  (NULL,'whatsapp','hourly_max','200',0),
  (NULL,'whatsapp','daily_max','1000',0),
  (NULL,'whatsapp','delay_min','3',0),
  (NULL,'whatsapp','delay_max','8',0),
  (NULL,'updates','repo','',0),
  (NULL,'updates','branch','main',0),
  (NULL,'updates','github_token','',1),
  (NULL,'updates','auto_check','1',0),
  (NULL,'smtp','host','',0),
  (NULL,'smtp','port','587',0),
  (NULL,'smtp','username','',0),
  (NULL,'smtp','password','',1),
  (NULL,'smtp','encryption','tls',0),
  (NULL,'smtp','from_email','',0),
  (NULL,'smtp','from_name','AK Cloud',0);

-- ---- WhatsApp templates (Gujarati) — Module 9 events ---------------
INSERT INTO `whatsapp_templates` (`tenant_id`,`slug`,`name`,`body`,`language`) VALUES
  (NULL,'welcome','Registration Welcome','Hello {client_name} 👋\nWelcome to {company_name}! Your account is ready.\nPanel: {panel_url}','en'),
  (NULL,'otp','OTP Verification','Your verification code is: *{otp}*\nValid for 10 minutes. Do not share it with anyone.','en'),
  (NULL,'order_placed','Order Placed','{client_name}, we have received your order ✅\nInvoice: {invoice_no} | Amount: ₹{amount}\nYour hosting will be set up automatically once payment is confirmed.','en'),
  (NULL,'invoice_generated','Invoice Generated','Hi {client_name}, a new invoice has been generated.\nInvoice: {invoice_no} | Amount: ₹{amount}\nDue date: {due_date}\nPay here: {panel_url}','en'),
  (NULL,'payment_received','Payment Received','Thank you {client_name} 🙏\nWe have received your payment of ₹{amount}. Invoice {invoice_no} is now paid.','en'),
  (NULL,'reminder','Payment Reminder','Hi {client_name}, a friendly reminder that invoice {invoice_no} (₹{amount}) is due on {due_date}. Please pay on time to avoid interruption.','en'),
  (NULL,'overdue','Overdue Warning','⚠️ {client_name}, invoice {invoice_no} (₹{amount}) is overdue. Please pay soon to avoid suspension of your service.','en'),
  (NULL,'suspended','Service Suspended','🔴 {client_name}, your service ({domain}) has been suspended. Please complete payment to restore it: {panel_url}','en'),
  (NULL,'unsuspended','Service Unsuspended','🟢 {client_name}, your service ({domain}) is active again. Thank you!','en'),
  (NULL,'hosting_ready','Hosting Ready','🎉 {client_name}, your hosting is ready!\n\n🌐 Domain: {domain}\n👤 Username: {username}\n🔑 Password: {password}\n\n📂 FTP Host: {ftp_host}\nFTP User: {ftp_user}\nFTP Password: {ftp_pass}\n\n🗄️ DB Name: {db_name}\nDB User: {db_user}\nDB Password: {db_pass}\n\nControl panel: {panel_url}','en'),
  (NULL,'renewal_success','Renewal Success','✅ {client_name}, {domain} has been renewed successfully. New expiry date: {expiry_date}.','en'),
  (NULL,'expiring','Service Expiring','⏰ {client_name}, {domain} expires on {expiry_date}. Renew here: {panel_url}','en'),
  (NULL,'disk_warning','Disk Usage Warning','⚠️ {client_name}, disk usage for {domain} has reached {disk_used} of {disk_limit}. Please free up some space.','en'),
  (NULL,'bandwidth_warning','Bandwidth Warning','⚠️ {client_name}, bandwidth usage for {domain} is close to your monthly limit.','en'),
  (NULL,'ticket_opened','Ticket Opened','{client_name}, your support ticket #{ticket_id} has been created. Our team will respond shortly.','en'),
  (NULL,'ticket_replied','Ticket Replied','{client_name}, there is a new reply on your ticket #{ticket_id}. View it here: {panel_url}','en'),
  (NULL,'ticket_closed','Ticket Closed','{client_name}, your ticket #{ticket_id} has been closed. Thank you!','en'),
  (NULL,'ssl_installed','SSL Installed','🔒 {client_name}, an SSL certificate has been installed successfully for {domain}.','en'),
  (NULL,'server_down','Server Down (Admin)','🚨 ALERT: A server appears to be down. Please check immediately.','en'),
  (NULL,'provisioning_failed','Provisioning Failed (Admin)','🚨 ALERT: Provisioning failed for {domain}. Manual retry required.','en'),
  (NULL,'new_order_admin','New Order (Admin)','🛒 New order: {client_name} — {plan_name} (₹{amount}).','en'),
  (NULL,'daily_summary','Daily Sales Summary (Admin)','📊 Daily summary:\nNew orders: {amount}\nSee the dashboard for full collection figures.','en');

-- ---- Email templates (minimal defaults) -----------------------------
INSERT INTO `email_templates` (`tenant_id`,`slug`,`name`,`subject`,`body`,`language`) VALUES
  (NULL,'welcome','Welcome Email','Welcome to {company_name}','<p>Hello {client_name},</p><p>Welcome to {company_name}. Your account is ready to use.</p><p>Control panel: <a href="{panel_url}">{panel_url}</a></p>','en'),
  (NULL,'invoice','Invoice Email','Invoice {invoice_no} — {company_name}','<p>Hello {client_name},</p><p>Your invoice {invoice_no} for ₹{amount} has been generated. Due date: {due_date}.</p>','en'),
  (NULL,'hosting_ready','Hosting Ready Email','Your hosting is ready — {domain}','<p>Hello {client_name},</p><p>Your hosting for {domain} is ready. Login details have been sent to you on WhatsApp.</p>','en'),
  (NULL,'password_reset','Password Reset','Password Reset — {company_name}','<p>Hello {client_name},</p><p>Click the link below to reset your password: <a href="{panel_url}">Reset password</a></p>','en');
