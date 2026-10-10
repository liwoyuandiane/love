<?php
/**
 * API - 管理员验证
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/RateLimiter.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonResponse(false, null, '方法不允许', 'METHOD_NOT_ALLOWED');
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    $data = [];
}
// is_string 校验：客户端传数组等非标量时 trim() 会抛 TypeError，
// 未捕获的 TypeError 会在 display_errors=On 的环境泄露绝对路径与堆栈
$username = is_string($data['username'] ?? null) ? trim($data['username']) : '';
$password = is_string($data['password'] ?? null) ? $data['password'] : '';

if ($username === '' || $password === '') {
    jsonResponse(false, null, '请填写用户名和密码', 'VALIDATION_ERROR');
}

$clientIp = RateLimiter::getClientIp();
$identifier = 'verify:' . $clientIp;

if (!RateLimiter::check($identifier)) {
    http_response_code(429);
    jsonResponse(false, null, '请求过于频繁，请稍后再试', 'RATE_LIMITED');
}

try {
    $result = login($username, $password);

    if ($result['success']) {
        RateLimiter::clear($identifier);
        Logger::audit('User login', ['username' => $username]);
        jsonResponse(true, [
            'username' => $_SESSION['user_username'],
            // 返回角色：前端据此过滤管理员专属页签，避免普通用户看到后提交才被拒
            'role' => $_SESSION['user_role'] ?? 'admin',
            'csrf_token' => CSRF::generate()
        ], '登录成功');
    } else {
        Logger::warning('Failed login attempt', ['username' => $username]);
        jsonResponse(false, null, $result['message'], 'AUTH_FAILED');
    }
} catch (Throwable $e) {
    Logger::error('Admin verify error: ' . $e->getMessage());
    http_response_code(500);
    jsonResponse(false, null, '登录失败', 'SERVER_ERROR');
}
