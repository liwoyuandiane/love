<?php
/**
 * 认证模块
 */

require_once __DIR__ . '/db.php';

define('SALT_ROUNDS', 12);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 15 * 60);

// bcrypt 只使用前 72 字节，超长密码会被静默截断
define('MAX_PASSWORD_BYTES', 72);

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['user_username'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'admin'
    ];
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /admin.php?error=请先登录');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['user_role'] ?? 'admin') !== 'admin') {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => '需要管理员权限']], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function getCurrentUserRole(): string {
    return $_SESSION['user_role'] ?? 'admin';
}

function isAdmin(): bool {
    return isLoggedIn() && getCurrentUserRole() === 'admin';
}

function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => SALT_ROUNDS]);
}

function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

function isPasswordStrong(string $password): bool {
    if (strlen($password) < 8) {
        return false;
    }
    // bcrypt 只取前 72 字节，超长密码会被静默截断导致语义不一致
    if (strlen($password) > MAX_PASSWORD_BYTES) {
        return false;
    }
    if (!preg_match('/[A-Za-z]/', $password)) {
        return false;
    }
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }
    return true;
}

function getLoginAttempts(PDO $pdo, string $username): array {
    $stmt = $pdo->prepare("SELECT login_attempts, locked_until FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch() ?: ['login_attempts' => 0, 'locked_until' => null];
}

function incrementLoginAttempts(PDO $pdo, string $username): void {
    $stmt = $pdo->prepare("UPDATE admin_users SET login_attempts = login_attempts + 1 WHERE username = ?");
    $stmt->execute([$username]);
}

function clearLoginAttempts(PDO $pdo, string $username): void {
    $stmt = $pdo->prepare("UPDATE admin_users SET login_attempts = 0, locked_until = NULL WHERE username = ?");
    $stmt->execute([$username]);
}

function setLockout(PDO $pdo, string $username, int $until): void {
    // 必须用本地时区写入：锁定时间在 login() 中通过 strtotime() 按本地时区解析。
    // 若用 gmdate 写 UTC，strtotime 会按 Asia/Shanghai 解析，锁定会提前 8 小时判定过期而失效。
    $datetime = date('Y-m-d H:i:s', $until);
    $stmt = $pdo->prepare("UPDATE admin_users SET locked_until = ? WHERE username = ?");
    $stmt->execute([$datetime, $username]);
}

function login(string $username, string $password): array {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'message' => '用户名或密码错误'];
    }
    
    if ($user['locked_until']) {
        $lockedTime = strtotime($user['locked_until']);
        if ($lockedTime === false && strtotime($user['locked_until'] . ' UTC') !== false) {
            // 兼容旧版本用 UTC 写入的锁定时间
            $lockedTime = strtotime($user['locked_until'] . ' UTC');
        }
        if ($lockedTime !== false && $lockedTime > time()) {
            $remaining = ceil(($lockedTime - time()) / 60);
            return ['success' => false, 'message' => "账号已锁定，请 {$remaining} 分钟后再试"];
        }
    }
    
    if (!verifyPassword($password, $user['password'])) {
        $attempts = $user['login_attempts'] + 1;
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $lockUntil = time() + LOCKOUT_DURATION;
            setLockout($db, $username, $lockUntil);
            return ['success' => false, 'message' => '登录失败次数过多，账号已锁定 15 分钟'];
        }
        incrementLoginAttempts($db, $username);
        $remaining = MAX_LOGIN_ATTEMPTS - $attempts;
        return ['success' => false, 'message' => "用户名或密码错误，剩余尝试次数: {$remaining}"];
    }
    
    clearLoginAttempts($db, $username);

    // 哈希成本低于当前配置时自动重哈希升级，保证登录速度与一致性
    if (password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => SALT_ROUNDS])) {
        $stmt = $db->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
        $stmt->execute([hashPassword($password), $user['id']]);
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'] ?? 'admin';

    Logger::audit('User login success', ['username' => $username]);

    return ['success' => true, 'message' => '登录成功'];
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function ensureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        logout();
        return;
    }
    $_SESSION['last_activity'] = time();
}