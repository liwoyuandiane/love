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

// 流式扫描，避免 file()+array_reverse 一次性加载并翻转全量日志
$matchCount = 0;
$wantedFromEnd = ($page - 1) * $perPage;
$windowStart = $wantedFromEnd;
$windowEnd = $wantedFromEnd + $perPage;
$window = [];

$fp = fopen($logFile, 'r');
if ($fp === false) {
    jsonResponse(true, ['logs' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage]);
}
while (($line = fgets($fp)) !== false) {
    $line = rtrim($line, "\r\n");
    if ($line === '') continue;
    if ($level && strpos($line, "[$level]") === false) continue;
    if ($search && stripos($line, $search) === false) continue;
    $idxFromOldest = $matchCount;
    $matchCount++;
    // 最新在前：从末尾计数 windowStart..windowEnd-1
    $idxFromEnd = $matchCount; // 1-based from oldest after include
    if ($idxFromEnd > $windowStart && $idxFromEnd <= $windowEnd) {
        $window[] = $line;
    }
}
fclose($fp);

$total = $matchCount;
// $window 是旧→新；UI 需要新→旧
$paginatedLogs = array_reverse($window);

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