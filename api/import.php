<?php
/**
 * API - 数据导入
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/cache.php';

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

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($contentType, 'multipart/form-data') !== false) {
    if (!isset($_FILES['json_file']) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(false, null, '请选择要导入的文件', 'VALIDATION_ERROR');
    }
    
    if ($_FILES['json_file']['size'] > 10 * 1024 * 1024) {
        jsonResponse(false, null, '文件大小不能超过 10MB', 'VALIDATION_ERROR');
    }
    
    $importData = json_decode(file_get_contents($_FILES['json_file']['tmp_name']), true);
} else {
    $importData = json_decode(file_get_contents('php://input'), true);
}

if (json_last_error() !== JSON_ERROR_NONE) {
    jsonResponse(false, null, 'JSON 格式无效', 'VALIDATION_ERROR');
}

if (!isset($importData['data']) || !isset($importData['version'])) {
    jsonResponse(false, null, '无效的备份文件结构', 'VALIDATION_ERROR');
}

try {
    $db = getDB();
    $data = $importData['data'];
    
    $db->beginTransaction();
    
    if (!empty($data['coupleInfo'])) {
        $c = $data['coupleInfo'];
        $stmt = $db->prepare("UPDATE couple_info SET name1 = ?, name2 = ?, anniversary = ? WHERE id = 1");
        $stmt->execute([
            $c['name1'] ?? '',
            $c['name2'] ?? '',
            $c['anniversary'] ?? date('Y-m-d')
        ]);
    }
    
    if (!empty($data['anniversaries']) && is_array($data['anniversaries'])) {
        $db->exec("DELETE FROM anniversaries");
        $stmt = $db->prepare("INSERT INTO anniversaries (id, title, date, description, type, reminder_days, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $allowedTypes = ['anniversary', 'birthday', 'wedding', 'other'];
        foreach ($data['anniversaries'] as $a) {
            $type = in_array($a['type'] ?? '', $allowedTypes) ? $a['type'] : 'anniversary';
            $stmt->execute([
                max(1, min(65535, intval($a['id'] ?? 0))),
                mb_substr($a['title'] ?? '', 0, 200),
                $a['date'] ?? null,
                mb_substr($a['description'] ?? '', 0, 500),
                $type,
                max(0, min(365, intval($a['reminder_days'] ?? 0))),
                $a['created_at'] ?? date('Y-m-d H:i:s'),
                $a['updated_at'] ?? date('Y-m-d H:i:s')
            ]);
        }
    }
    
    if (!empty($data['wishlists']) && is_array($data['wishlists'])) {
        $db->exec("DELETE FROM wishlists");
        $stmt = $db->prepare("INSERT INTO wishlists (id, title, description, date, completed, completed_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($data['wishlists'] as $w) {
            $stmt->execute([
                max(1, min(65535, intval($w['id'] ?? 0))),
                mb_substr($w['title'] ?? '', 0, 200),
                mb_substr($w['description'] ?? '', 0, 500),
                $w['date'] ?? null,
                !empty($w['completed']) ? 1 : 0,
                $w['completed_at'] ?? null,
                $w['created_at'] ?? date('Y-m-d H:i:s'),
                $w['updated_at'] ?? date('Y-m-d H:i:s')
            ]);
        }
    }
    
    if (!empty($data['explores']) && is_array($data['explores'])) {
        $db->exec("DELETE FROM explores");
        $stmt = $db->prepare("INSERT INTO explores (id, title, description, date, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($data['explores'] as $e) {
            $stmt->execute([
                max(1, min(65535, intval($e['id'] ?? 0))),
                mb_substr($e['title'] ?? '', 0, 200),
                mb_substr($e['description'] ?? '', 0, 500),
                $e['date'] ?? null,
                $e['created_at'] ?? date('Y-m-d H:i:s'),
                $e['updated_at'] ?? date('Y-m-d H:i:s')
            ]);
        }
    }
    
    if (!empty($data['photos']) && is_array($data['photos'])) {
        $db->exec("DELETE FROM photos");
        $stmt = $db->prepare("INSERT INTO photos (id, url, caption, source_type, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
        $allowedSourceTypes = ['local', 'url'];
        foreach ($data['photos'] as $p) {
            $sourceType = in_array($p['source_type'] ?? '', $allowedSourceTypes) ? $p['source_type'] : 'url';
            $stmt->execute([
                max(1, min(65535, intval($p['id'] ?? 0))),
                mb_substr($p['url'] ?? '', 0, 500),
                mb_substr($p['caption'] ?? '', 0, 200),
                $sourceType,
                $p['created_at'] ?? date('Y-m-d H:i:s'),
                $p['updated_at'] ?? date('Y-m-d H:i:s')
            ]);
        }
    }
    
    if (!empty($data['music'])) {
        $m = $data['music'];
        $allowedSourceTypes = ['url', 'local'];
        $sourceType = in_array($m['source_type'] ?? '', $allowedSourceTypes) ? $m['source_type'] : 'url';
        $stmt = $db->prepare("UPDATE music SET source_type = ?, source_url = ?, backup_url = ?, title = ?, artist = ? WHERE id = 1");
        $stmt->execute([
            $sourceType,
            mb_substr($m['source_url'] ?? '', 0, 500),
            mb_substr($m['backup_url'] ?? '', 0, 500),
            mb_substr($m['title'] ?? '', 0, 200),
            mb_substr($m['artist'] ?? '', 0, 100)
        ]);
    }
    
    $db->commit();

    Cache::clear('api_data');
    jsonResponse(true, null, '数据导入成功');
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Import API Error: ' . $e->getMessage());
    http_response_code(500);
    jsonResponse(false, null, '导入失败', 'SERVER_ERROR');
}
