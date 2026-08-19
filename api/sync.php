<?php
/**
 * API - 会话保活
 *
 * 后台每 60 秒调用一次，用于维持 PHP 会话活跃（防止 30 分钟超时被登出）。
 * 早期版本在此对全部数据表做 COUNT(*) 统计，但并无实际用处，
 * 反而每 60 秒触发多次数据库查询，已移除。
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

ensureSession();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'UNAUTHORIZED', 'message' => '请先登录']
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => '方法不允许']
    ]);
    exit;
}

// 仅刷新会话活动时间即可，无需任何数据库查询
$_SESSION['last_activity'] = time();

echo json_encode([
    'success' => true,
    'data' => [
        'timestamp' => date('Y-m-d H:i:s')
    ]
]);