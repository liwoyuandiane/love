<?php
/**
 * API - 用户状态检查
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

ensureSession();

if (isLoggedIn()) {
    echo json_encode([
        'success' => true,
        'isAdmin' => isAdmin(),
        'username' => $_SESSION['user_username'] ?? '',
        'role' => getCurrentUserRole()
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'isAdmin' => false,
        'error' => ['code' => 'UNAUTHORIZED', 'message' => '未登录']
    ]);
}
