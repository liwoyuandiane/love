<?php
/**
 * API - 数据同步
 *
 * 直接操作数据库，前端应每60秒调用一次
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

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

verifyCSRF();

$action = $_GET['action'] ?? 'status';

try {
    match($action) {
        'status' => handleStatus(),
        default => handleStatus()
    };
} catch (Exception $e) {
    error_log('Sync API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'SERVER_ERROR', 'message' => '同步失败']
    ]);
}

function handleStatus(): void {
    try {
        $db = getDB();

        $allowedTables = ['anniversaries', 'wishlists', 'explores', 'photos', 'music', 'couple_info'];
        $stats = [];

        foreach ($allowedTables as $table) {
            try {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM `$table`");
                $stmt->execute();
                $result = $stmt->fetch();
                $stats[$table] = [
                    'count' => intval($result['count']),
                    'status' => 'ok'
                ];
            } catch (Exception $e) {
                $stats[$table] = [
                    'count' => 0,
                    'status' => 'error'
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'tables' => $stats,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
    } catch (Exception $e) {
        error_log('handleStatus error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => ['code' => 'SERVER_ERROR', 'message' => '状态检查失败']
        ]);
    }
}
