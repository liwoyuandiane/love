<?php
/**
 * Migration: 添加 role 字段到 admin_users 表
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

try {
    $db = getDB();
    
    // Check if role column exists
    $cols = $db->query("SHOW COLUMNS FROM admin_users LIKE 'role'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE admin_users ADD COLUMN role ENUM('admin', 'user') DEFAULT 'admin'");
    }
    
    // Set all existing users to admin
    $db->exec("UPDATE admin_users SET role = 'admin' WHERE role IS NULL OR role = ''");
    
    echo json_encode(['success' => true, 'message' => 'Migration completed']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
