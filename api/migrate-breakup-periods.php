<?php
/**
 * Migration: 创建 breakup_periods 表并为 site_settings 添加 show_breakup_days 列
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/logger.php';

header('Content-Type: application/json');

ensureSession();

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => '需要管理员权限']]);
    exit;
}

// 仅允许 POST 且校验 CSRF：迁移是变更操作，GET 即变更可被 CSRF 利用
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => '方法不允许']]);
    exit;
}
verifyCSRF();

try {
    $db = getDB();
    $applied = [];

    $tables = $db->query("SHOW TABLES LIKE 'breakup_periods'")->fetchAll();
    if (empty($tables)) {
        $db->exec("CREATE TABLE `breakup_periods` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `start_date` DATE NOT NULL,
            `end_date` DATE NULL,
            `note` VARCHAR(100) NOT NULL DEFAULT '',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_start` (`start_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $applied[] = 'breakup_periods table';
    }

    $cols = $db->query("SHOW COLUMNS FROM site_settings LIKE 'show_breakup_days'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE `site_settings` ADD COLUMN `show_breakup_days` TINYINT(1) NOT NULL DEFAULT 0");
        $applied[] = 'site_settings.show_breakup_days column';
    }

    if (empty($applied)) {
        echo json_encode(['success' => true, 'message' => 'Migration already applied']);
        exit;
    }

    Logger::audit('Migration: breakup-periods schema', ['applied' => implode(', ', $applied)]);
    echo json_encode(['success' => true, 'message' => 'Migration completed: ' . implode(', ', $applied)]);
} catch (Throwable $e) {
    Logger::error('Migration failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => 'Migration failed']]);
}
