<?php
/**
 * 数据库升级脚本
 * 用于为已安装的网站添加 site_settings 表
 * 仅允许管理员访问
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

ensureSession();

if (!isAdmin()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "拒绝访问：仅允许管理员运行此脚本\n";
    exit;
}

require_once __DIR__ . '/includes/db.php';

$db = getDB();

try {
    $result = $db->query("SHOW TABLES LIKE 'site_settings'");
    if ($result->fetch()) {
        echo "site_settings 表已存在，无需升级。\n";
        exit;
    }

    $db->exec("CREATE TABLE `site_settings` (
        `id` INT PRIMARY KEY DEFAULT 1,
        `icp_code` VARCHAR(100) NOT NULL DEFAULT '',
        `police_record_code` VARCHAR(100) NOT NULL DEFAULT '',
        `site_name` VARCHAR(200) NOT NULL DEFAULT '',
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("INSERT INTO `site_settings` (`id`) VALUES (1)");

    echo "升级成功！site_settings 表已创建。\n";
} catch (Exception $e) {
    echo "升级失败：" . $e->getMessage() . "\n";
    exit(1);
}