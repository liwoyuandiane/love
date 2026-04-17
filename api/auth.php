<?php
/**
 * API - 认证
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/RateLimiter.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $action = $_GET['action'] ?? 'login';

    if ($action === 'login') {
        $clientIp = RateLimiter::getClientIp();
        $identifier = 'login:' . $clientIp;

        if (!RateLimiter::check($identifier)) {
            $remaining = RateLimiter::getRemainingAttempts($identifier);
            http_response_code(429);
            jsonResponse(false, null, '请求过于频繁，请稍后再试', 'RATE_LIMITED');
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            jsonResponse(false, null, '请填写用户名和密码', 'VALIDATION_ERROR');
        }

        $result = login($username, $password);

        if ($result['success']) {
            RateLimiter::clear($identifier);
            jsonResponse(true, ['username' => $_SESSION['user_username']], '登录成功');
        } else {
            jsonResponse(false, null, $result['message'], 'AUTH_FAILED');
        }
    } elseif ($action === 'logout') {
        logout();
        jsonResponse(true, null, '已退出登录');
    } elseif ($action === 'check') {
        if (isLoggedIn()) {
            jsonResponse(true, ['username' => $_SESSION['user_username']]);
        } else {
            jsonResponse(false, null, '未登录', 'UNAUTHORIZED');
        }
    }
} elseif ($method === 'GET') {
    if (isLoggedIn()) {
        echo json_encode([
            'success' => true,
            'data' => ['username' => $_SESSION['user_username']]
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => ['code' => 'UNAUTHORIZED', 'message' => '未登录']
        ]);
    }
}
