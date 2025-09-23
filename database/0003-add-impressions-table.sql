-- Create impressions table for incremental migrations
CREATE TABLE IF NOT EXISTS `impressions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `program_id` INT UNSIGNED NOT NULL,
    `tracking_code` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
    `url` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `ip_address` VARCHAR(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `user_agent` TEXT COLLATE utf8mb4_unicode_ci,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `program_id` (`program_id`),
    KEY `idx_impressions_tracking_code` (`tracking_code`),
    CONSTRAINT `impressions_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
