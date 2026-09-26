<?php
/**
 * 日志记录系统
 */

class Logger {
    private static string $logDir;
    private static string $appLog;
    private static string $errorLog;
    private static string $auditLog;
    private const MAX_LOG_BYTES = 2097152;
    private const MAX_LOG_BACKUPS = 5;

    private static function init(): void {
        if (isset(self::$logDir)) return;

        self::$logDir = __DIR__ . '/../logs/';
        self::$appLog = self::$logDir . 'app.log';
        self::$errorLog = self::$logDir . 'error.log';
        self::$auditLog = self::$logDir . 'audit.log';

        if (!is_dir(self::$logDir)) {
            if (!@mkdir(self::$logDir, 0755, true) && !is_dir(self::$logDir)) {
                error_log('[logger] cannot create log dir: ' . self::$logDir);
            }
        } else {
            @chmod(self::$logDir, 0755);
        }
    }

    private static function rotateIfNeeded(string $logFile): void {
        if (!is_file($logFile)) return;
        $size = @filesize($logFile);
        if ($size === false || $size < self::MAX_LOG_BYTES) return;

        for ($i = self::MAX_LOG_BACKUPS - 1; $i >= 1; $i--) {
            $src = $logFile . '.' . $i;
            $dst = $logFile . '.' . ($i + 1);
            if (is_file($src)) {
                @rename($src, $dst);
            }
        }
        @rename($logFile, $logFile . '.1');
    }

    private static function write(string $level, string $message, string $type = 'app'): void {
        self::init();

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $username = $_SESSION['user_username'] ?? '';
        $user = $userId > 0 ? $userId : ($username ?: 'guest');
        $time = date('Y-m-d H:i:s');

        $logEntry = "[$time] [$level] [IP:$ip] [User:$user] " . str_replace(["\r", "\n"], ['\\r', '\\n'], $message) . PHP_EOL;

        $logFile = match($type) {
            'error' => self::$errorLog,
            'audit' => self::$auditLog,
            default => self::$appLog
        };

        self::rotateIfNeeded($logFile);

        if (@file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
            error_log('[logger] write failed: ' . $logFile . ' | ' . $message);
        }
    }
    
    public static function info(string $message): void {
        self::write('INFO', $message);
    }
    
    public static function warning(string $message): void {
        self::write('WARNING', $message);
    }
    
    public static function error(string $message): void {
        self::write('ERROR', $message, 'error');
        self::write('ERROR', $message);
    }
    
    public static function debug(string $message): void {
        if (getenv('APP_DEBUG') === 'true') {
            self::write('DEBUG', $message);
        }
    }
    
    public static function audit(string $action, ?array $details = null): void {
        $message = $action;
        if ($details) {
            $message .= ' | ' . json_encode($details, JSON_UNESCAPED_UNICODE);
        }
        self::write('AUDIT', $message, 'audit');
    }
}