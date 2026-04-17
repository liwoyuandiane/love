-- 情侣纪念网站数据库建表 SQL
-- 参照 love-hansenning 项目结构

-- 1. 情侣信息表
CREATE TABLE IF NOT EXISTS `couple_info` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name1` VARCHAR(50) NOT NULL DEFAULT '',
    `name2` VARCHAR(50) NOT NULL DEFAULT '',
    `anniversary` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. 纪念日表
CREATE TABLE IF NOT EXISTS `anniversaries` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `title` VARCHAR(200) NOT NULL,
    `date` DATE,
    `description` TEXT,
    `type` ENUM('anniversary', 'birthday', 'wedding', 'other') DEFAULT 'anniversary',
    `reminder_days` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_date` (`date`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. 愿望清单表
CREATE TABLE IF NOT EXISTS `wishlists` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `date` DATE,
    `completed` TINYINT(1) DEFAULT 0,
    `completed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. 探索地点表
CREATE TABLE IF NOT EXISTS `explores` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `date` DATE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. 记忆墙照片表
CREATE TABLE IF NOT EXISTS `photos` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `url` VARCHAR(500) NOT NULL,
    `caption` VARCHAR(200),
    `source_type` ENUM('local', 'url') NOT NULL DEFAULT 'url',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. 音乐配置表
CREATE TABLE IF NOT EXISTS `music` (
    `id` INT PRIMARY KEY DEFAULT 1,
    `source_type` ENUM('local', 'url') NOT NULL DEFAULT 'url',
    `source_url` VARCHAR(500) NOT NULL,
    `backup_url` VARCHAR(500),
    `title` VARCHAR(200),
    `artist` VARCHAR(100),
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. 管理员账号表
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` VARCHAR(20) NOT NULL DEFAULT 'user',
    `login_attempts` INT DEFAULT 0,
    `locked_until` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 初始化情侣信息
INSERT IGNORE INTO `couple_info` (`id`, `name1`, `name2`, `anniversary`) VALUES (1, '', '', CURDATE());

-- 初始化音乐配置
INSERT IGNORE INTO `music` (`id`, `source_type`, `source_url`, `title`) VALUES (1, 'url', '', '背景音乐');
