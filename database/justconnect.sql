-- ============================================================
--  JUSTCONNECT — Smarter Legal Decisions Powered by NLP
--  MySQL Schema for XAMPP (MySQL 5.7+ / MariaDB 10.3+)
--  Import via phpMyAdmin or: mysql -u root -p < justconnect.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `justconnect`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `justconnect`;

-- ──────────────────────────────────────────
-- USERS
-- ──────────────────────────────────────────
CREATE TABLE `users` (
  `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `first_name`      VARCHAR(80)      NOT NULL,
  `last_name`       VARCHAR(80)      NOT NULL,
  `email`           VARCHAR(191)     NOT NULL,
  `password`        VARCHAR(255)     NOT NULL,
  `organisation`    VARCHAR(191)     NULL DEFAULT NULL,
  `role`            ENUM('Legal Professional','Law Student','Researcher','Business Owner','Other')
                    NOT NULL DEFAULT 'Legal Professional',
  `email_verified_at` TIMESTAMP      NULL DEFAULT NULL,
  `otp_code`        VARCHAR(6)       NULL DEFAULT NULL,
  `otp_expires_at`  TIMESTAMP        NULL DEFAULT NULL,
  `remember_token`  VARCHAR(100)     NULL DEFAULT NULL,
  `created_at`      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────
-- DOCUMENTS (uploaded legal files)
-- ──────────────────────────────────────────
CREATE TABLE `documents` (
  `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED     NOT NULL,
  `original_name`   VARCHAR(255)     NOT NULL,
  `stored_name`     VARCHAR(255)     NOT NULL,
  `mime_type`       VARCHAR(100)     NOT NULL DEFAULT 'application/pdf',
  `file_size`       INT UNSIGNED     NOT NULL DEFAULT 0 COMMENT 'bytes',
  `page_count`      SMALLINT         NULL DEFAULT NULL,
  `word_count`      INT UNSIGNED     NULL DEFAULT NULL,
  `extracted_text`  LONGTEXT         NULL DEFAULT NULL,
  `summary_type`    VARCHAR(40)      NULL DEFAULT NULL,
  `status`          ENUM('pending','processing','done','failed')
                    NOT NULL DEFAULT 'pending',
  `created_at`      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `documents_user_id_index` (`user_id`),
  CONSTRAINT `fk_documents_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────
-- SUMMARIES (NLP analysis results)
-- ──────────────────────────────────────────
CREATE TABLE `summaries` (
  `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `document_id`         INT UNSIGNED  NOT NULL,
  `user_id`             INT UNSIGNED  NOT NULL,
  `summary_type`        VARCHAR(40)   NOT NULL DEFAULT 'general_user',
  `document_type`       VARCHAR(100)  NULL DEFAULT NULL,
  `case_number`         VARCHAR(100)  NULL DEFAULT NULL,
  `parties`             JSON          NULL DEFAULT NULL,
  `date_of_document`    VARCHAR(60)   NULL DEFAULT NULL,
  `court`               VARCHAR(150)  NULL DEFAULT NULL,
  `judge`               VARCHAR(150)  NULL DEFAULT NULL,
  `executive_summary`   TEXT          NULL DEFAULT NULL,
  `professional_summary` TEXT         NULL DEFAULT NULL,
  `citizen_summary`     TEXT          NULL DEFAULT NULL,
  `key_findings`        TEXT          NULL DEFAULT NULL,
  `key_obligations`     JSON          NULL DEFAULT NULL,
  `legal_principles`    TEXT          NULL DEFAULT NULL,
  `outcome`             TEXT          NULL DEFAULT NULL,
  `practical_implications` TEXT       NULL DEFAULT NULL,
  `result_cards`        JSON          NULL DEFAULT NULL,
  `structured_panels`   JSON          NULL DEFAULT NULL,
  `supporting_passages` JSON          NULL DEFAULT NULL,
  `source_map`          JSON          NULL DEFAULT NULL,
  `semantic_profile`    JSON          NULL DEFAULT NULL,
  -- NLP-specific fields
  `nlp_entities`        JSON          NULL DEFAULT NULL COMMENT 'Named entities: persons, orgs, dates',
  `nlp_keywords`        JSON          NULL DEFAULT NULL COMMENT 'Top TF-IDF keywords',
  `nlp_sentiment`       VARCHAR(20)   NULL DEFAULT NULL COMMENT 'positive/neutral/negative',
  `nlp_readability`     FLOAT         NULL DEFAULT NULL COMMENT 'Flesch score',
  `nlp_language`        VARCHAR(10)   NULL DEFAULT 'en',
  `nlp_legal_categories` JSON         NULL DEFAULT NULL,
  `ai_provider`         VARCHAR(30)   NULL DEFAULT NULL COMMENT 'NLP_BART analysis provider key',
  `processing_ms`       INT UNSIGNED  NULL DEFAULT NULL,
  `pdf_path`            VARCHAR(255)  NULL DEFAULT NULL COMMENT 'generated PDF summary',
  `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `summaries_document_id_index` (`document_id`),
  KEY `summaries_user_id_index` (`user_id`),
  CONSTRAINT `fk_summaries_document` FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_summaries_user`     FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────
-- DOWNLOADS  (audit trail of PDF downloads)
-- ──────────────────────────────────────────
CREATE TABLE `downloads` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED  NOT NULL,
  `summary_id`   INT UNSIGNED  NOT NULL,
  `downloaded_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `downloads_user_id_index`    (`user_id`),
  KEY `downloads_summary_id_index` (`summary_id`),
  CONSTRAINT `fk_downloads_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)     ON DELETE CASCADE,
  CONSTRAINT `fk_downloads_summary` FOREIGN KEY (`summary_id`) REFERENCES `summaries`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────
-- SESSIONS  (Laravel-compatible)
-- ──────────────────────────────────────────
CREATE TABLE `sessions` (
  `id`            VARCHAR(255)  NOT NULL,
  `user_id`       INT UNSIGNED  NULL DEFAULT NULL,
  `ip_address`    VARCHAR(45)   NULL DEFAULT NULL,
  `user_agent`    TEXT          NULL DEFAULT NULL,
  `payload`       LONGTEXT      NOT NULL,
  `last_activity` INT           NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index`       (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────
-- CACHE  (optional, for rate limiting etc.)
-- ──────────────────────────────────────────
CREATE TABLE `cache` (
  `key`        VARCHAR(255)  NOT NULL,
  `value`      MEDIUMTEXT    NOT NULL,
  `expiration` INT           NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache_locks` (
  `key`        VARCHAR(255)  NOT NULL,
  `owner`      VARCHAR(255)  NOT NULL,
  `expiration` INT           NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────
-- QUEUE  (background analysis pipeline)
-- ──────────────────────────────────────────
CREATE TABLE `jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` VARCHAR(255) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL,
  `reserved_at` INT UNSIGNED DEFAULT NULL,
  `available_at` INT UNSIGNED NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `failed_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` VARCHAR(255) NOT NULL,
  `connection` TEXT NOT NULL,
  `queue` TEXT NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `exception` LONGTEXT NOT NULL,
  `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────
-- DEMO SEED  (optional — remove in production)
-- ──────────────────────────────────────────
-- Password = "Admin@2024!" (bcrypt hash)
INSERT INTO `users` (`first_name`,`last_name`,`email`,`password`,`organisation`,`role`,`email_verified_at`)
VALUES (
  'Demo','User','demo@justconnect.zw',
  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.ucrm3a.ye',
  'JustConnect Legal','Legal Professional',NOW()
);
