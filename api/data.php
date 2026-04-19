<?php
/**
 * API - 获取所有数据
 *
 * 使用文件缓存减少数据库访问
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/cache.php';

header('Content-Type: application/json');

ensureSession();

try {
    $cacheKey = 'api_data';
    $cached = Cache::get($cacheKey, 300);

    if ($cached) {
        echo json_encode([
            'success' => true,
            'data' => $cached,
            'cached' => true
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db = getDB();

    $stmt = $db->query("SELECT id, name1, name2, anniversary, created_at, updated_at FROM couple_info WHERE id = 1");
    $coupleInfo = $stmt->fetch();

    $stmt = $db->query("SELECT id, title, date, description, type, reminder_days, created_at, updated_at FROM anniversaries ORDER BY date DESC");
    $anniversaries = $stmt->fetchAll();

    $stmt = $db->query("SELECT id, title, description, date, completed, completed_at, created_at, updated_at FROM wishlists ORDER BY completed ASC, created_at DESC");
    $wishlists = $stmt->fetchAll();

    $stmt = $db->query("SELECT id, title, description, date, created_at, updated_at FROM explores ORDER BY created_at DESC");
    $explores = $stmt->fetchAll();

    $stmt = $db->query("SELECT id, url, caption, source_type, created_at, updated_at FROM photos ORDER BY created_at DESC");
    $photos = $stmt->fetchAll();

    $stmt = $db->query("SELECT source_type, source_url, backup_url, title, artist FROM music WHERE id = 1");
    $music = $stmt->fetch();

    $stmt = $db->query("SELECT icp_code, police_record_code, site_name, timezone FROM site_settings WHERE id = 1");
    $settings = $stmt->fetch();
    if (!$settings) {
        $settings = ['icp_code' => '', 'police_record_code' => '', 'site_name' => '', 'timezone' => 'Asia/Shanghai'];
    }

    $data = [
        'coupleInfo' => $coupleInfo,
        'anniversaries' => $anniversaries,
        'wishlists' => $wishlists,
        'explores' => $explores,
        'photos' => $photos,
        'music' => $music,
        'settings' => $settings
    ];

    Cache::set($cacheKey, $data, 300);

    echo json_encode([
        'success' => true,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Data API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'SERVER_ERROR', 'message' => '数据加载失败']
    ]);
}
