<?php
/**
 * API - 管理员登出
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/logger.php';

header('Content-Type: application/json');

$username = $_SESSION['user_username'] ?? 'unknown';
logout();

Logger::audit('User logout', ['username' => $username]);
echo json_encode(['success' => true]);
