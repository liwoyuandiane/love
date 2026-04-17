<?php
/**
 * API - 数据导入
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

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
    
    $content = file_get_contents($_FILES['json_file']['tmp_name']);
} else {
    $data = json_decode(file_get_contents('php://input'), true);
    $content = $data['data'] ?? null;
    
    if (is_array($data) && !isset($data['exportDate']) && !isset($data['version'])) {
        $content = json_encode($data);
    }
}

$importData = json_decode($content, true);

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
        foreach ($data['anniversaries'] as $a) {
            $stmt->execute([
                $a['id'],
                $a['title'],
                $a['date'] ?? null,
                $a['description'] ?? '',
                $a['type'] ?? 'anniversary',
                $a['reminder_days'] ?? 0,
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
                $w['id'],
                $w['title'],
                $w['description'] ?? '',
                $w['date'] ?? null,
                $w['completed'] ? 1 : 0,
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
                $e['id'],
                $e['title'],
                $e['description'] ?? '',
                $e['date'] ?? null,
                $e['created_at'] ?? date('Y-m-d H:i:s'),
                $e['updated_at'] ?? date('Y-m-d H:i:s')
            ]);
        }
    }
    
    if (!empty($data['photos']) && is_array($data['photos'])) {
        $db->exec("DELETE FROM photos");
        $stmt = $db->prepare("INSERT INTO photos (id, url, caption, source_type, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($data['photos'] as $p) {
            $stmt->execute([
                $p['id'],
                $p['url'],
                $p['caption'] ?? '',
                $p['source_type'] ?? 'url',
                $p['created_at'] ?? date('Y-m-d H:i:s'),
                $p['updated_at'] ?? date('Y-m-d H:i:s')
            ]);
        }
    }
    
    if (!empty($data['music'])) {
        $m = $data['music'];
        $stmt = $db->prepare("UPDATE music SET source_type = ?, source_url = ?, backup_url = ?, title = ?, artist = ? WHERE id = 1");
        $stmt->execute([
            $m['source_type'] ?? 'url',
            $m['source_url'] ?? '',
            $m['backup_url'] ?? '',
            $m['title'] ?? '',
            $m['artist'] ?? ''
        ]);
    }
    
    $db->commit();
    
    jsonResponse(true, null, '数据导入成功');
    
} catch (Exception $e) {
    $db->rollBack();
    error_log('Import API Error: ' . $e->getMessage());
    http_response_code(500);
    jsonResponse(false, null, '导入失败', 'SERVER_ERROR');
}
