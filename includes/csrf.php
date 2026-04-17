<?php
/**
 * CSRF 防护
 */

class CSRF {
    private static string $tokenKey = 'csrf_token';
    
    public static function generate(): string {
        ensureSession();
        if (empty($_SESSION[self::$tokenKey])) {
            $_SESSION[self::$tokenKey] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::$tokenKey];
    }
    
    public static function validate(string $token): bool {
        ensureSession();
        if (empty($_SESSION[self::$tokenKey]) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION[self::$tokenKey], $token);
    }
    
    public static function getInput(): string {
        $token = self::generate();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
}
