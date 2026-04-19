<?php
/**
 * API - 管理员登出
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/logger.php';

header('Content-Type: application/json');

ensureSession();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

verifyCSRF();

$username = $_SESSION['user_username'] ?? 'unknown';
Logger::audit('User logout', ['username' => $username]);

logout();

echo json_encode(['success' => true]);
