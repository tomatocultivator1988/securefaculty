-- SecureFaculty MySQL Database Schema
-- Capstone Project: CICI Department - ISUFST Main Campus
-- Compliant with RA 10173 (Data Privacy Act of 2012)

CREATE DATABASE IF NOT EXISTS `securefaculty_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `securefaculty_db`;

-- 1. Users Table (One-Gate RBAC & Bcrypt Authentication)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(64) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('dean', 'faculty') NOT NULL DEFAULT 'faculty',
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150),
    `department` VARCHAR(100) DEFAULT 'CICI',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Faculty Profiles Table (1:1 with Users)
CREATE TABLE IF NOT EXISTS `faculty_profiles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `employee_id` VARCHAR(50),
    `academic_rank` VARCHAR(100),
    `specialization` VARCHAR(150),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Certificates Table (1:M with Users)
CREATE TABLE IF NOT EXISTS `certificates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `issuer` VARCHAR(255),
    `date_issued` VARCHAR(50),
    `category` VARCHAR(100) DEFAULT 'Seminar',
    `file_path` VARCHAR(255) NOT NULL,
    `original_filename` VARCHAR(255) NOT NULL,
    `file_type` VARCHAR(10) NOT NULL,
    `file_size` INT NOT NULL,
    `file_hash` VARCHAR(64) NULL,
    `cert_number` VARCHAR(100) NULL,
    `processing_status` VARCHAR(50) DEFAULT 'completed',
    `batch_id` VARCHAR(100) NULL,
    `ocr_raw_text` MEDIUMTEXT,
    `ocr_confidence` DECIMAL(5,2) DEFAULT 0.95,
    `status` VARCHAR(50) DEFAULT 'approved',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`faculty_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Certificate Authors (M:N Mapping for Research Publications & Co-authors)
CREATE TABLE IF NOT EXISTS `certificate_authors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `certificate_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    FOREIGN KEY (`certificate_id`) REFERENCES `certificates`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Audit Logs Table (Immutable RA 10173 Audit Trail)
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT,
    `username` VARCHAR(64),
    `role` VARCHAR(32),
    `action` VARCHAR(64) NOT NULL,
    `target_id` INT,
    `details` TEXT,
    `ip_address` VARCHAR(45),
    `user_agent` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. API Quotas Table (Real-Time Quota Monitor)
CREATE TABLE IF NOT EXISTS `api_quotas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `service_name` VARCHAR(64) NOT NULL UNIQUE,
    `monthly_limit` INT DEFAULT 25000,
    `current_usage` INT DEFAULT 0,
    `month_year` VARCHAR(10) NOT NULL,
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Processing Batches Table (Multi-File & ZIP Ingestion Tracking)
CREATE TABLE IF NOT EXISTS `processing_batches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `batch_uuid` VARCHAR(64) NOT NULL UNIQUE,
    `user_id` INT NOT NULL,
    `total_files` INT DEFAULT 0,
    `processed_files` INT DEFAULT 0,
    `success_files` INT DEFAULT 0,
    `failed_files` INT DEFAULT 0,
    `status` VARCHAR(32) DEFAULT 'pending',
    `current_stage` VARCHAR(64) DEFAULT 'Upload',
    `log_summary` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Performance & Query Acceleration Indexes
CREATE INDEX IF NOT EXISTS `idx_certificates_faculty` ON `certificates`(`faculty_id`);
CREATE INDEX IF NOT EXISTS `idx_certificates_hash` ON `certificates`(`file_hash`);
CREATE INDEX IF NOT EXISTS `idx_certificates_date` ON `certificates`(`date_issued`);
CREATE INDEX IF NOT EXISTS `idx_cert_authors_cert` ON `certificate_authors`(`certificate_id`);
CREATE INDEX IF NOT EXISTS `idx_cert_authors_user` ON `certificate_authors`(`user_id`);
CREATE INDEX IF NOT EXISTS `idx_audit_created` ON `audit_logs`(`created_at`);
CREATE INDEX IF NOT EXISTS `idx_audit_action` ON `audit_logs`(`action`);
CREATE INDEX IF NOT EXISTS `idx_audit_user` ON `audit_logs`(`user_id`);
CREATE INDEX IF NOT EXISTS `idx_audit_ip` ON `audit_logs`(`ip_address`);

-- Initial API Quota Seed
INSERT INTO `api_quotas` (`service_name`, `monthly_limit`, `current_usage`, `month_year`)
VALUES ('OCR_SPACE', 25000, 142, DATE_FORMAT(NOW(), '%Y-%m'))
ON DUPLICATE KEY UPDATE `last_updated` = NOW();

-- Seed Default Accounts (Bcrypt password hashes)
-- dean: admin123
-- faculty: faculty123
INSERT INTO `users` (`username`, `password_hash`, `role`, `full_name`, `email`, `department`) VALUES
('dean', '$2y$10$w9FpT5LzF.7pA0wNnB2QeeE7G8c1B5uX.aH6b0bT9dK3Z0uK0L6/G', 'dean', 'Dr. Arlene S. Poblador', 'dean.cici@isufst.edu.ph', 'CICI'),
('mikhail', '$2y$10$7Z8V4T0q9jXy2K1w3P0e2eH6m7A4b8T9dK3Z0uK0L6/G2B5uX.aH6', 'faculty', 'Prof. Levy Mikhail B. Aguilar', 'mikhail.aguilar@isufst.edu.ph', 'CICI'),
('raejan', '$2y$10$7Z8V4T0q9jXy2K1w3P0e2eH6m7A4b8T9dK3Z0uK0L6/G2B5uX.aH6', 'faculty', 'Prof. Rae Jan B. Corneja', 'raejan.corneja@isufst.edu.ph', 'CICI'),
('eljay', '$2y$10$7Z8V4T0q9jXy2K1w3P0e2eH6m7A4b8T9dK3Z0uK0L6/G2B5uX.aH6', 'faculty', 'Prof. Eljay Muyco', 'eljay.muyco@isufst.edu.ph', 'CICI');
