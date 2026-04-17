<?php
/**
 * API - 管理员验证
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/logger.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => '方法不允许']
    ]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'VALIDATION_ERROR', 'message' => '请填写用户名和密码']
    ]);
    exit;
}

$result = login($username, $password);

if ($result['success']) {
    Logger::audit('User login', ['username' => $username]);
    echo json_encode([
        'success' => true,
        'username' => $_SESSION['user_username']
    ]);
} else {
    Logger::warning('Failed login attempt', ['username' => $username]);
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'AUTH_FAILED', 'message' => $result['message']]
    ]);
}
