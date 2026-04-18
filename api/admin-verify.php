<?php
/**
 * API - 管理员验证
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/RateLimiter.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonResponse(false, null, '方法不允许', 'METHOD_NOT_ALLOWED');
}

$clientIp = RateLimiter::getClientIp();
$identifier = 'verify:' . $clientIp;

if (!RateLimiter::check($identifier)) {
    http_response_code(429);
    jsonResponse(false, null, '请求过于频繁，请稍后再试', 'RATE_LIMITED');
}

$data = json_decode(file_get_contents('php://input'), true);
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    jsonResponse(false, null, '请填写用户名和密码', 'VALIDATION_ERROR');
}

try {
    $result = login($username, $password);

    if ($result['success']) {
        RateLimiter::clear($identifier);
        Logger::audit('User login', ['username' => $username]);
        jsonResponse(true, ['username' => $_SESSION['user_username']], '登录成功');
    } else {
        Logger::warning('Failed login attempt', ['username' => $username]);
        jsonResponse(false, null, $result['message'], 'AUTH_FAILED');
    }
} catch (Exception $e) {
    Logger::error('Admin verify error: ' . $e->getMessage());
    http_response_code(500);
    jsonResponse(false, null, '登录失败', 'SERVER_ERROR');
}
