<?php
/**
 * API - 认证
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/RateLimiter.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $action = $_GET['action'] ?? 'login';

    if ($action === 'login') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $data = [];
        }
        // is_string 校验：客户端传数组等非标量时 trim() 会抛 TypeError，
        // 未捕获的 TypeError 会在 display_errors=On 的环境泄露绝对路径与堆栈
        $username = is_string($data['username'] ?? null) ? trim($data['username']) : '';
        $password = is_string($data['password'] ?? null) ? $data['password'] : '';

        if ($username === '' || $password === '') {
            http_response_code(400);
            jsonResponse(false, null, '请填写用户名和密码', 'VALIDATION_ERROR');
        }

        $clientIp = RateLimiter::getClientIp();
        $identifier = 'login:' . $clientIp;

        if (!RateLimiter::check($identifier)) {
            http_response_code(429);
            jsonResponse(false, null, '请求过于频繁，请稍后再试', 'RATE_LIMITED');
        }

        try {
            $result = login($username, $password);

            if ($result['success']) {
                RateLimiter::clear($identifier);
                jsonResponse(true, [
                    'username' => $_SESSION['user_username'],
                    'csrf_token' => CSRF::generate()
                ], '登录成功');
            } else {
                http_response_code(401);
                jsonResponse(false, null, $result['message'], 'AUTH_FAILED');
            }
        } catch (Throwable $e) {
            Logger::error('Login error: ' . $e->getMessage());
            http_response_code(500);
            jsonResponse(false, null, '登录失败', 'SERVER_ERROR');
        }
    } elseif ($action === 'logout') {
        verifyCSRF();
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
        jsonResponse(true, ['username' => $_SESSION['user_username']]);
    } else {
        http_response_code(401);
        jsonResponse(false, null, '未登录', 'UNAUTHORIZED');
    }
}
