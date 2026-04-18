<?php
/**
 * API - 审计日志查询
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

ensureSession();

if (!isAdmin()) {
    http_response_code(403);
    jsonResponse(false, null, '需要管理员权限', 'FORBIDDEN');
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    jsonResponse(false, null, '方法不允许', 'METHOD_NOT_ALLOWED');
}

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = min(100, max(10, intval($_GET['per_page'] ?? 50)));
$level = $_GET['level'] ?? '';
$search = trim($_GET['search'] ?? '');

$logFile = dirname(__DIR__) . '/logs/audit.log';

if (!file_exists($logFile)) {
    jsonResponse(true, ['logs' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage]);
}

$lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$logs = array_reverse($lines);

$filtered = [];
foreach ($logs as $line) {
    if ($level && strpos($line, "[$level]") === false) continue;
    if ($search && stripos($line, $search) === false) continue;
    $filtered[] = $line;
}

$total = count($filtered);
$offset = ($page - 1) * $perPage;
$paginatedLogs = array_slice($filtered, $offset, $perPage);

$parsedLogs = [];
foreach ($paginatedLogs as $line) {
    $parsed = parseLogLine($line);
    if ($parsed) $parsedLogs[] = $parsed;
}

jsonResponse(true, [
    'logs' => $parsedLogs,
    'total' => $total,
    'page' => $page,
    'per_page' => $perPage,
    'total_pages' => ceil($total / $perPage)
]);

function parseLogLine(string $line): ?array {
    if (preg_match('/^\[(.+?)\] \[(.+?)\] \[IP:(.+?)\] \[User:(.+?)\] (.+)$/', $line, $m)) {
        return [
            'time' => $m[1],
            'level' => $m[2],
            'ip' => $m[3],
            'user' => $m[4],
            'message' => $m[5]
        ];
    }
    return null;
}