<?php
/**
 * CSRF 防护
 * token 附带生成时间戳，超过 2 小时视为过期
 */

class CSRF {
    private static string $tokenKey = 'csrf_token';
    private static string $timeKey = 'csrf_token_time';
    private const TTL = 7200; // 2 hours

    public static function generate(bool $force = false): string {
        ensureSession();
        $expired = empty($_SESSION[self::$timeKey])
            || (time() - (int)$_SESSION[self::$timeKey]) > self::TTL;
        if ($force || empty($_SESSION[self::$tokenKey]) || $expired) {
            $_SESSION[self::$tokenKey] = bin2hex(random_bytes(32));
            $_SESSION[self::$timeKey] = time();
        }
        return $_SESSION[self::$tokenKey];
    }

    public static function rotate(): string {
        return self::generate(true);
    }

    public static function validate(string $token): bool {
        ensureSession();
        if (empty($_SESSION[self::$tokenKey]) || empty($token)) {
            return false;
        }
        if (empty($_SESSION[self::$timeKey])
            || (time() - (int)$_SESSION[self::$timeKey]) > self::TTL) {
            unset($_SESSION[self::$tokenKey], $_SESSION[self::$timeKey]);
            return false;
        }
        return hash_equals($_SESSION[self::$tokenKey], $token);
    }

    public static function getInput(): string {
        $token = self::generate();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
}
