<?php
/**
 * API - 数据导出
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/logger.php';

if (!isLoggedIn()) {
    http_response_code(401);
    jsonResponse(false, null, '请先登录', 'UNAUTHORIZED');
}

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonResponse(false, null, '方法不允许', 'METHOD_NOT_ALLOWED');
}

verifyCSRF();

try {
    $db = getDB();

    $stmt = $db->query("SELECT id, name1, name2, anniversary, created_at, updated_at FROM couple_info WHERE id = 1");
    $coupleInfo = $stmt->fetch();

    $stmt = $db->query("SELECT id, title, date, description, type, reminder_days, created_at, updated_at FROM anniversaries");
    $anniversaries = $stmt->fetchAll();

    $stmt = $db->query("SELECT id, title, description, date, completed, completed_at, created_at, updated_at FROM wishlists");
    $wishlists = $stmt->fetchAll();

    $stmt = $db->query("SELECT id, title, description, date, created_at, updated_at FROM explores");
    $explores = $stmt->fetchAll();

    $stmt = $db->query("SELECT id, url, caption, source_type, created_at, updated_at FROM photos");
    $photos = $stmt->fetchAll();

    $stmt = $db->query("SELECT id, source_type, source_url, backup_url, title, artist, updated_at FROM music WHERE id = 1");
    $music = $stmt->fetch();

    $exportData = [
        'exportDate' => date('c'),
        'version' => '2.0.0',
        'data' => [
            'coupleInfo' => $coupleInfo,
            'anniversaries' => $anniversaries,
            'wishlists' => $wishlists,
            'explores' => $explores,
            'photos' => $photos,
            'music' => $music
        ]
    ];

    $json = json_encode($exportData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $filename = 'love-backup-' . date('Y-m-d') . '.json';

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($json));

    echo $json;

} catch (Exception $e) {
    Logger::error('Export API error: ' . $e->getMessage());
    http_response_code(500);
    jsonResponse(false, null, '导出失败', 'SERVER_ERROR');
}
