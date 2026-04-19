<?php
/**
 * Router script for PHP built-in server
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// API routes with ID and action (e.g., /api/wishlists/123/toggle)
if (preg_match('#^/api/([^/]+)/(\d+)/([^/]+)$#', $uri, $matches)) {
    $apiFile = __DIR__ . '/api/' . $matches[1] . '-' . $matches[3] . '.php';
    if (file_exists($apiFile)) {
        $_GET['id'] = $matches[2];
        require $apiFile;
        return true;
    }
}

// API routes with ID (e.g., /api/photos/2) - DELETE, PUT
if (preg_match('#^/api/([^/]+)/(\d+)$#', $uri, $matches)) {
    $apiFile = __DIR__ . '/api/' . $matches[1] . '.php';
    if (file_exists($apiFile)) {
        $_GET['id'] = $matches[2];
        require $apiFile;
        return true;
    }
}

// API routes (e.g., /api/wishlists)
if (preg_match('#^/api/([^/]+)$#', $uri, $matches)) {
    $apiFile = __DIR__ . '/api/' . $matches[1] . '.php';
    if (file_exists($apiFile)) {
        require $apiFile;
        return true;
    }
}

// Install route
if ($uri === '/install/' || $uri === '/install') {
    require __DIR__ . '/install/index.php';
    return true;
}

// Static files
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
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