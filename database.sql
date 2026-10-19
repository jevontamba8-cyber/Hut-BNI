-- ==============================================================
-- BNI Anniversary Celebration - Database Schema & Initial Data
-- Database Name: bni_anniversary
-- Engine: InnoDB (Supports ACID transactions & row locking)
-- Charset: utf8mb4 / Collation: utf8mb4_unicode_ci
-- ==============================================================

CREATE DATABASE IF NOT EXISTS `bni_anniversary`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `bni_anniversary`;

-- -------------------------------------------------------------
-- Table: participants
-- Stores up to 1,200+ employees / participants
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `draw_results`;
DROP TABLE IF EXISTS `participants`;

CREATE TABLE `participants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nik` VARCHAR(16) NOT NULL,
  `nomor_undian` VARCHAR(50) NOT NULL,
  `nama_lengkap` VARCHAR(255) NOT NULL,
  `divisi` VARCHAR(150) NOT NULL,
  `cabang` VARCHAR(150) NOT NULL,
  `status_kehadiran` ENUM('BELUM HADIR', 'HADIR') NOT NULL DEFAULT 'BELUM HADIR',
  `waktu_checkin` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_participants_nik` (`nik`),
  UNIQUE KEY `idx_participants_nomor_undian` (`nomor_undian`),
  KEY `idx_participants_status` (`status_kehadiran`),
  KEY `idx_participants_nama` (`nama_lengkap`),
  KEY `idx_participants_divisi` (`divisi`),
  KEY `idx_participants_cabang` (`cabang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: prizes
-- Event prizes configuration
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `prizes`;

CREATE TABLE `prizes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_hadiah` VARCHAR(255) NOT NULL,
  `deskripsi` TEXT DEFAULT NULL,
  `jumlah_pemenang` INT UNSIGNED NOT NULL DEFAULT 1,
  `status_hadiah` ENUM('AKTIF', 'NONAKTIF', 'SELESAI') NOT NULL DEFAULT 'AKTIF',
  `urutan` INT UNSIGNED NOT NULL DEFAULT 1,
  `gambar` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prizes_status` (`status_hadiah`),
  KEY `idx_prizes_urutan` (`urutan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: draw_results
-- Official lottery draw results
-- -------------------------------------------------------------
CREATE TABLE `draw_results` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `participant_id` INT UNSIGNED NOT NULL,
  `prize_id` INT UNSIGNED NOT NULL,
  `draw_session_id` VARCHAR(64) NOT NULL,
  `waktu_undian` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status_hasil` ENUM('SAH', 'DIBATALKAN') NOT NULL DEFAULT 'SAH',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_draw_participant` (`participant_id`),
  KEY `idx_draw_prize` (`prize_id`),
  KEY `idx_draw_session` (`draw_session_id`),
  KEY `idx_draw_waktu` (`waktu_undian`),
  CONSTRAINT `fk_draw_participant` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_draw_prize` FOREIGN KEY (`prize_id`) REFERENCES `prizes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: admins
-- Admin & Committee access control with role-based auth
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `admins`;

CREATE TABLE `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_admin` VARCHAR(255) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('superadmin', 'admin', 'operator', 'panitia') NOT NULL DEFAULT 'admin',
  `status_akun` ENUM('AKTIF', 'NONAKTIF') NOT NULL DEFAULT 'AKTIF',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_admins_username` (`username`),
  KEY `idx_admins_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: audit_logs
-- Security & action logging
-- -------------------------------------------------------------
CREATE TABLE `audit_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT UNSIGNED DEFAULT NULL,
  `aktivitas` VARCHAR(255) NOT NULL,
  `detail_aktivitas` TEXT DEFAULT NULL,
  `waktu_aktivitas` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_admin` (`admin_id`),
  KEY `idx_audit_waktu` (`waktu_aktivitas`),
  CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: event_settings
-- Configurable event info & rules
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `event_settings`;

CREATE TABLE `event_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Default Event Settings
-- -------------------------------------------------------------
INSERT INTO `event_settings` (`setting_key`, `setting_value`, `description`) VALUES
('event_title', 'BNI Anniversary Celebration', 'Judul Acara Ulang Tahun'),
('event_tagline', 'Celebrating Together, Growing Stronger', 'Tagline resmi acara'),
('event_date', '2026-10-25', 'Tanggal pelaksanaan acara'),
('event_time', '08:30 - 16:00 WIB', 'Waktu pelaksanaan acara'),
('event_location', 'Grand Ballroom Hotel Indonesia Kempinski Jakarta', 'Lokasi venue acara'),
('event_theme', 'Synergy of Excellence & Digital Innovation', 'Tema perayaan HUT'),
('win_rule', 'one_win_only', 'Aturan kemenangan: one_win_only atau multiple_allowed'),
('qr_base_url', 'http://localhost/bni-anniversary/check-in.php', 'URL dasar untuk QR Code check-in'),
('company_name', 'PT Bank Negara Indonesia (Persero) Tbk', 'Nama Penyelenggara');

-- -------------------------------------------------------------
-- Default Admin Accounts
-- Default password for all: AdminBNI2024!
-- Hash generated via password_hash('AdminBNI2024!', PASSWORD_BCRYPT)
-- -------------------------------------------------------------
INSERT INTO `admins` (`nama_admin`, `username`, `password_hash`, `role`, `status_akun`) VALUES
('Super Administrator BNI', 'superadmin', '$2y$10$Oo9/11FYZQqqTL6Sl2NeveSxAoj15ZemPps./VA5jTuPGEPIFpeUW', 'superadmin', 'AKTIF'),
('Event Manager Panitia', 'admin', '$2y$10$Oo9/11FYZQqqTL6Sl2NeveSxAoj15ZemPps./VA5jTuPGEPIFpeUW', 'admin', 'AKTIF'),
('Operator Magic Wheel', 'operator', '$2y$10$mY/RwZvE77mOlSVGwLV7T.WaNpIq..Zp.4fPqCVKx1BjS4/f2bcUe', 'operator', 'AKTIF'),
('Petugas Meja Registrasi', 'panitia', '$2y$10$gjaevlmqC6rjP4j8Wk1U3e51bEsTEvR4xnbwCAoNDK6gCMORbbw1q', 'panitia', 'AKTIF');

-- -------------------------------------------------------------
-- Default Prizes
-- -------------------------------------------------------------
INSERT INTO `prizes` (`nama_hadiah`, `deskripsi`, `jumlah_pemenang`, `status_hadiah`, `urutan`) VALUES
('Grand Prize: Emas Logam Mulia Antam 50 Gram', 'Logam Mulia CertiEye Antam 50g Ed. Khusus HUT BNI', 1, 'AKTIF', 1),
('Hadiah Utama 1: Sepeda Motor Honda Vario 160 CBS', 'All New Honda Vario 160 OTR Jakarta + BPKB', 2, 'AKTIF', 2),
('Hadiah Utama 2: iPhone 16 Pro Max 256GB', 'Apple iPhone 16 Pro Max Resmi iBox Indonesia', 3, 'AKTIF', 3),
('Hadiah Favorit: Samsung Smart LED TV 55 Inch UHD 4K', 'Samsung Crystal UHD 4K Smart TV Series 2026', 5, 'AKTIF', 4),
('Hadiah Spesial: Tabungan BNI Taplus @Rp 5.000.000', 'Top-up langsung saldo rekening BNI Taplus', 10, 'AKTIF', 5),
('Door Prize: BNI TapCash @Rp 1.000.000', 'Kartu BNI TapCash edisi terbatas HUT BNI saldo 1jt', 25, 'AKTIF', 6);
