<?php
/**
 * 公共函数
 */

require_once __DIR__ . '/db.php';

function escapeHtml(string $text): string {
    if (!$text) return '';
    return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function formatDate(string $date = null): string {
    if (!$date) return '';
    return strpos($date, 'T') !== false ? explode('T', $date)[0] : $date;
}

function formatDateTime(string $date = null): string {
    if (!$date) return '';
    return date('Y-m-d H:i:s', strtotime($date));
}

function jsonResponse(bool $success, $data = null, string $message = '', string $errorCode = ''): void {
    header('Content-Type: application/json');
    $response = ['success' => $success];
    if ($message) {
        $response['message'] = $message;
    }
    if ($data !== null) {
        $response['data'] = $data;
    }
    if ($errorCode) {
        $response['error'] = ['code' => $errorCode, 'message' => $message];
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

function verifyCSRF(): void {
    require_once __DIR__ . '/csrf.php';
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (!CSRF::validate($token)) {
        http_response_code(403);
        jsonResponse(false, null, 'CSRF 验证失败，请刷新页面后重试', 'CSRF_ERROR');
    }
}
