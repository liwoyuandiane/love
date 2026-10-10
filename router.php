<?php
/**
 * Router script for PHP built-in server
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

$isValidApiSegment = static fn(string $s): bool => $s !== '' && $s !== '.' && $s !== '..' && preg_match('/^[A-Za-z0-9_-]+$/', $s);

// API routes with ID and action (e.g., /api/wishlists/123/toggle)
if (preg_match('#^/api/([^/]+)/(\d+)/([^/]+)$#', $uri, $matches)) {
    if ($isValidApiSegment($matches[1]) && $isValidApiSegment($matches[3])) {
        $apiFile = __DIR__ . '/api/' . $matches[1] . '-' . $matches[3] . '.php';
        if (file_exists($apiFile)) {
            $_GET['id'] = $matches[2];
            require $apiFile;
            return true;
        }
    }
}

// API routes with ID (e.g., /api/photos/2) - DELETE, PUT
if (preg_match('#^/api/([^/]+)/(\d+)$#', $uri, $matches)) {
    if ($isValidApiSegment($matches[1])) {
        $apiFile = __DIR__ . '/api/' . $matches[1] . '.php';
        if (file_exists($apiFile)) {
            $_GET['id'] = $matches[2];
            require $apiFile;
            return true;
        }
    }
}

// API files addressed directly by filename (e.g., /api/admin-verify.php)
// 前端 admin.js/boot.js 直接按文件名调用这些端点，需在静态白名单之外显式放行
if (preg_match('#^/api/([A-Za-z0-9_-]+)\.php$#', $uri, $matches)) {
    $apiFile = __DIR__ . '/api/' . $matches[1] . '.php';
    if (file_exists($apiFile)) {
        require $apiFile;
        return true;
    }
}

// API routes (e.g., /api/wishlists)
if (preg_match('#^/api/([^/]+)$#', $uri, $matches)) {
    if ($isValidApiSegment($matches[1])) {
        $apiFile = __DIR__ . '/api/' . $matches[1] . '.php';
        if (file_exists($apiFile)) {
            require $apiFile;
            return true;
        }
    }
}

// Install route
if ($uri === '/install/' || $uri === '/install') {
    require __DIR__ . '/install/index.php';
    return true;
}

// Static files: allowlist approach — only serve from /assets/ and /favicon.ico.
// Serving "any existing file" would expose .env (DB credentials), .git/*, CLAUDE.md,
// logs/ and cache/ on the dev server; a denylist is easy to get wrong.
if ($uri !== '/' && strpos($uri, '..') === false) {
    $isAllowedStatic = str_starts_with($uri, '/assets/') || $uri === '/favicon.ico';
    if ($isAllowedStatic) {
        $staticPath = __DIR__ . $uri;
        $realPath = realpath($staticPath);
        $realRoot = realpath(__DIR__);
        if ($realPath !== false && $realRoot !== false && is_file($realPath)
            && strpos($realPath, $realRoot . DIRECTORY_SEPARATOR) === 0) {
            return false;
        }
    }
}

// Main entry points
if ($uri === '/' || $uri === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}

if ($uri === '/admin.php' || $uri === '/admin') {
    require __DIR__ . '/admin.php';
    return true;
}

// 404
http_response_code(404);
echo 'Not Found';
return true;