-- ================================================
-- DATABASE: lab_document_management
-- Sistem Manajemen Dokumen Laboratorium DLH
-- Created: 2026-01-19
-- ================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+07:00";

-- Drop tables jika sudah ada (untuk fresh install)
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `review_items`;
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `document_versions`;
DROP TABLE IF EXISTS `documents`;
DROP TABLE IF EXISTS `document_counter`;
DROP TABLE IF EXISTS `users`;

-- ================================================
-- TABEL 1: users
-- ================================================
CREATE TABLE `users` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `role` ENUM('uploader','reviewer','admin') NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default users
-- Password untuk semua user: admin123
INSERT INTO `users` (`username`, `password`, `full_name`, `email`, `role`, `is_active`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator Sistem', 'admin@dlh.go.id', 'admin', 1),
('uploader1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Petugas Lab 1', 'uploader1@dlh.go.id', 'uploader', 1),
('uploader2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Petugas Lab 2', 'uploader2@dlh.go.id', 'uploader', 1),
('reviewer1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Kepala Laboratorium', 'reviewer1@dlh.go.id', 'reviewer', 1),
('reviewer2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Quality Control', 'reviewer2@dlh.go.id', 'reviewer', 1);

-- ================================================
-- TABEL 2: documents
-- ================================================
CREATE TABLE `documents` (
  `document_id` INT(11) NOT NULL AUTO_INCREMENT,
  `doc_code` VARCHAR(20) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `description` TEXT,
  `uploader_id` INT(11) NOT NULL,
  `current_version_id` INT(11) DEFAULT NULL,
  `status` ENUM('draft','pending','revision','approved','archived') DEFAULT 'draft',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`document_id`),
  UNIQUE KEY `doc_code` (`doc_code`),
  KEY `idx_doc_code` (`doc_code`),
  KEY `idx_status` (`status`),
  KEY `idx_uploader` (`uploader_id`),
  CONSTRAINT `fk_doc_uploader` FOREIGN KEY (`uploader_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TABEL 3: document_versions
-- ================================================
CREATE TABLE `document_versions` (
  `version_id` INT(11) NOT NULL AUTO_INCREMENT,
  `document_id` INT(11) NOT NULL,
  `version_number` DECIMAL(3,1) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` INT(11) NOT NULL,
  `file_hash` VARCHAR(64) NOT NULL,
  `uploaded_by` INT(11) NOT NULL,
  `upload_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `is_current` TINYINT(1) DEFAULT 1,
  `notes` TEXT,
  PRIMARY KEY (`version_id`),
  UNIQUE KEY `unique_doc_version` (`document_id`,`version_number`),
  KEY `idx_document` (`document_id`),
  KEY `idx_current` (`is_current`),
  KEY `fk_version_uploader` (`uploaded_by`),
  CONSTRAINT `fk_version_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_version_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TABEL 4: reviews
-- ================================================
CREATE TABLE `reviews` (
  `review_id` INT(11) NOT NULL AUTO_INCREMENT,
  `version_id` INT(11) NOT NULL,
  `reviewer_id` INT(11) NOT NULL,
  `status` ENUM('pending','approved','needs_revision') NOT NULL,
  `review_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT,
  PRIMARY KEY (`review_id`),
  KEY `idx_version` (`version_id`),
  KEY `idx_reviewer` (`reviewer_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_review_version` FOREIGN KEY (`version_id`) REFERENCES `document_versions` (`version_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TABEL 5: review_items
-- ================================================
CREATE TABLE `review_items` (
  `item_id` INT(11) NOT NULL AUTO_INCREMENT,
  `review_id` INT(11) NOT NULL,
  `section` VARCHAR(100) DEFAULT NULL,
  `issue_type` ENUM('typo','data','format','content','other') NOT NULL,
  `description` TEXT NOT NULL,
  `priority` ENUM('low','medium','high') DEFAULT 'medium',
  `is_resolved` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`item_id`),
  KEY `idx_review` (`review_id`),
  CONSTRAINT `fk_item_review` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`review_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TABEL 6: activity_logs
-- ================================================
CREATE TABLE `activity_logs` (
  `log_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `document_id` INT(11) DEFAULT NULL,
  `details` TEXT,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_date` (`created_at`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_log_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TABEL 7: document_counter
-- ================================================
CREATE TABLE `document_counter` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `year` INT(4) NOT NULL,
  `counter` INT(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_year` (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert tahun berjalan
INSERT INTO `document_counter` (`year`, `counter`) VALUES (2026, 1);

-- ================================================
-- Foreign Key untuk current_version_id
-- ================================================
ALTER TABLE `documents`
  ADD CONSTRAINT `fk_doc_current_version` FOREIGN KEY (`current_version_id`) REFERENCES `document_versions` (`version_id`) ON DELETE SET NULL;

-- ================================================
-- SELESAI
-- ================================================
-- Default users:
-- Username: admin      | Password: admin123 | Role: admin
-- Username: uploader1  | Password: admin123 | Role: uploader
-- Username: uploader2  | Password: admin123 | Role: uploader
-- Username: reviewer1  | Password: admin123 | Role: reviewer
-- Username: reviewer2  | Password: admin123 | Role: reviewer
-- ================================================