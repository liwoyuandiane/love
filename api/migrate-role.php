<?php
/**
 * Migration: 添加 role 字段到 admin_users 表
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/logger.php';

header('Content-Type: application/json');

ensureSession();

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '需要管理员权限']);
    exit;
}

try {
    $db = getDB();

    $cols = $db->query("SHOW COLUMNS FROM admin_users LIKE 'role'")->fetchAll();
    if (!empty($cols)) {
        echo json_encode(['success' => true, 'message' => 'Migration already applied (role column exists)']);
        exit;
    }

    $db->exec("ALTER TABLE admin_users ADD COLUMN role ENUM('admin', 'user') DEFAULT 'admin'");
    $db->exec("UPDATE admin_users SET role = 'admin' WHERE role IS NULL OR role = ''");

    Logger::audit('Migration: add role column', ['user_id' => $_SESSION['user_id'] ?? 0]);
    echo json_encode(['success' => true, 'message' => 'Migration completed successfully']);
} catch (Exception $e) {
    Logger::error('Migration failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Migration failed']);
}
