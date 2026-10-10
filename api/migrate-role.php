<?php
/**
 * Migration: 添加 role 字段到 admin_users 表
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

    $cols = $db->query("SHOW COLUMNS FROM admin_users LIKE 'role'")->fetchAll();
    if (!empty($cols)) {
        echo json_encode(['success' => true, 'message' => 'Migration already applied (role column exists)']);
        exit;
    }

    $db->exec("ALTER TABLE admin_users ADD COLUMN role ENUM('admin', 'user') DEFAULT 'admin'");
    $db->exec("UPDATE admin_users SET role = 'admin' WHERE role IS NULL OR role = ''");

    Logger::audit('Migration: add role column', ['user_id' => $_SESSION['user_id'] ?? 0]);
    echo json_encode(['success' => true, 'message' => 'Migration completed successfully']);
} catch (Throwable $e) {
    Logger::error('Migration failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => 'Migration failed']]);
}