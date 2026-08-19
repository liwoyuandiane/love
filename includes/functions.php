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

/**
 * SSRF 防护：判断 URL 是否安全
 *
 * 解决 DNS rebinding TOCTOU 问题：
 * 1. 解析域名的所有 A/AAAA 记录，任一命中内网/保留地址即拒绝
 * 2. 只允许 http/https，拒绝带凭据的 URL
 * 3. 拒绝裸 IP 指向私网；IPv6 同时校验
 *
 * @param string $url
 * @return bool
 */
function isUrlSafe(string $url): bool {
    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) {
        return false;
    }

    $scheme = strtolower($parsed['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return false;
    }

    // 拒绝 URL 中携带用户名/密码（如 http://user:pass@host/）
    if (isset($parsed['user']) || isset($parsed['pass'])) {
        return false;
    }

    $host = strtolower($parsed['host']);
    // 去除 IPv6 字面量的方括号
    $rawHost = trim($host, '[]');

    // 1) 若主机名本身是 IP，直接校验
    if (filter_var($rawHost, FILTER_VALIDATE_IP)) {
        return isPublicIp($rawHost);
    }

    // 2) 裸主机名黑名单
    if (in_array($host, ['localhost', 'localhost.localdomain'], true)) {
        return false;
    }
    if (str_ends_with($host, '.localhost') || str_ends_with($host, '.internal') || str_ends_with($host, '.local')) {
        return false;
    }

    // 3) DNS 解析：取全部 A/AAAA 记录一起校验，
    //    避免 gethostbyname 只取一条时被攻击者轮换 DNS 记录绕过
    $ips = [];
    $records = @dns_get_record($rawHost, DNS_A | DNS_AAAA);
    if ($records === false || empty($records)) {
        return false;
    }
    foreach ($records as $r) {
        if (($r['type'] ?? '') === 'A' && isset($r['ip'])) {
            $ips[] = $r['ip'];
        } elseif (($r['type'] ?? '') === 'AAAA' && isset($r['ipv6'])) {
            $ips[] = $r['ipv6'];
        }
    }
    if (empty($ips)) {
        return false;
    }
    foreach ($ips as $ip) {
        if (!isPublicIp($ip)) {
            return false;
        }
    }
    return true;
}

/**
 * 判断 IP 是否为公网地址（拒绝私网/保留/链路本地/回环等）
 */
function isPublicIp(string $ip): bool {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        // FILTER_FLAG_NO_PRIV_RANGE 排除 10/8、172.16/12、192.168/16
        // FILTER_FLAG_NO_RES_RANGE 排除 127/8、169.254/16、0/8 等
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // IPv6：拒绝回环 ::1、链路本地 fe80::/10、唯一本地 fc00::/7、文档/保留地址
        $v6 = strtolower($ip);
        $forbiddenPrefixes = ['::1', '::', 'fe80:', 'fec0:', 'fc00:', 'fd00:', 'ff00:'];
        foreach ($forbiddenPrefixes as $prefix) {
            if (str_starts_with($v6, $prefix)) {
                return false;
            }
        }
        return true; // 其余 IPv6 按公网处理（CGN 等边界情况可再扩展）
    }
    return false;
}