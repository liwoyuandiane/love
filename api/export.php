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

    $breakupPeriods = [];
    if (!empty($db->query("SHOW TABLES LIKE 'breakup_periods'")->fetchAll())) {
        $stmt = $db->query("SELECT id, start_date, end_date, note, created_at, updated_at FROM breakup_periods ORDER BY start_date ASC");
        $breakupPeriods = $stmt->fetchAll();
    }

    $hasBreakupSetting = !empty($db->query("SHOW COLUMNS FROM site_settings LIKE 'show_breakup_days'")->fetchAll());
    $settingsSelect = $hasBreakupSetting
        ? "SELECT icp_code, police_record_code, site_name, timezone, show_breakup_days, updated_at FROM site_settings WHERE id = 1"
        : "SELECT icp_code, police_record_code, site_name, timezone, updated_at FROM site_settings WHERE id = 1";
    $stmt = $db->query($settingsSelect);
    $siteSettings = $stmt->fetch();
    if ($siteSettings) {
        $siteSettings['show_breakup_days'] = intval($siteSettings['show_breakup_days'] ?? 0);
    }

    $exportData = [
        'exportDate' => date('c'),
        'version' => APP_VERSION,
        'data' => [
            'coupleInfo' => $coupleInfo,
            'anniversaries' => $anniversaries,
            'wishlists' => $wishlists,
            'explores' => $explores,
            'photos' => $photos,
            'music' => $music,
            'breakupPeriods' => $breakupPeriods,
            'siteSettings' => $siteSettings
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
