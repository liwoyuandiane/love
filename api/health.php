<?php
/**
 * API - 健康检查
 * 返回服务状态、数据库连接、版本信息
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$health = [
    'status' => 'ok',
    'timestamp' => date('Y-m-d H:i:s'),
    'version' => '2.0.0',
    'checks' => []
];

try {
    $db = getDB();
    $db->query("SELECT 1");
    $health['checks']['database'] = ['status' => 'ok'];
} catch (Exception $e) {
    $health['checks']['database'] = ['status' => 'error', 'message' => '数据库连接失败'];
    $health['status'] = 'degraded';
}

$cacheDir = dirname(__DIR__) . '/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}
@chmod($cacheDir, 0755);
$health['checks']['cache'] = is_dir($cacheDir) && is_writable($cacheDir)
    ? ['status' => 'ok']
    : ['status' => 'warning', 'message' => '缓存目录不可写'];

$logsDir = dirname(__DIR__) . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}
@chmod($logsDir, 0755);
$health['checks']['logs'] = is_dir($logsDir) && is_writable($logsDir)
    ? ['status' => 'ok']
    : ['status' => 'warning', 'message' => '日志目录不可写'];

$uploadDir = dirname(__DIR__) . '/assets/uploads';
$health['checks']['uploads'] = is_dir($uploadDir) && is_writable($uploadDir)
    ? ['status' => 'ok']
    : ['status' => 'warning', 'message' => '上传目录不可写'];

$health['checks']['php'] = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'file_uploads' => ini_get('file_uploads') ? 'on' : 'off',
    'max_execution_time' => ini_get('max_execution_time'),
    'config_status' => (ini_get('upload_max_filesize') >= 20 * 1024 * 1024) ? 'ok' : '需要调整'
];

$uploadsSize = 0;
if (is_dir($uploadDir)) {
    $files = glob($uploadDir . '/*');
    $uploadsSize = array_sum(array_map('filesize', array_filter($files, 'is_file')));
}
$health['checks']['storage'] = [
    'status' => 'ok',
    'uploads_size_bytes' => $uploadsSize,
    'uploads_size_human' => formatBytes($uploadsSize)
];

http_response_code($health['status'] === 'ok' ? 200 : 503);
echo json_encode($health, JSON_UNESCAPED_UNICODE);

function formatBytes(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 2) . ' KB';
    if ($bytes < 1024 * 1024 * 1024) return round($bytes / (1024 * 1024), 2) . ' MB';
    return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
}