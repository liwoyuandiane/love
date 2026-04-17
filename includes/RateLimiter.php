<?php
/**
 * 速率限制器
 *
 * 支持 APCu 和文件两种存储后端，APCu 优先
 */

class RateLimiter {
    private static int $maxAttempts = 5;
    private static int $windowSeconds = 60;
    private static bool $useApcu;

    public static function init(): void {
        self::$useApcu = extension_loaded('apcu') && apcu_enabled();
    }

    public static function check(string $identifier): bool {
        self::init();

        $key = 'rl_' . md5($identifier);
        $now = time();

        if (self::$useApcu) {
            return self::checkApcu($key, $now);
        }

        return self::checkFile($identifier, $now);
    }

    private static function checkApcu(string $key, int $now): bool {
        $data = apcu_fetch($key, $success);

        if (!$success) {
            $data = ['attempts' => [], 'locked_until' => null];
        }

        if ($data['locked_until'] && $data['locked_until'] > $now) {
            return false;
        }

        $data['attempts'] = array_values(array_filter(
            $data['attempts'],
            fn($t) => $t > $now - self::$windowSeconds
        ));

        if (count($data['attempts']) >= self::$maxAttempts) {
            $data['locked_until'] = $now + (self::$windowSeconds * 3);
            apcu_store($key, $data, self::$windowSeconds * 4);
            return false;
        }

        $data['attempts'][] = $now;
        apcu_store($key, $data, self::$windowSeconds * 4);

        return true;
    }

    private static function checkFile(string $identifier, int $now): bool {
        $cacheDir = sys_get_temp_dir() . '/rate_limit';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $file = $cacheDir . '/' . md5($identifier) . '.json';
        $lockFile = $file . '.lock';

        $fp = fopen($lockFile, 'c');
        if (!$fp || !flock($fp, LOCK_EX)) {
            return false;
        }

        $data = ['attempts' => [], 'locked_until' => null];

        if (file_exists($file)) {
            $content = file_get_contents($file);
            if ($content !== false) {
                $data = json_decode($content, true) ?: $data;
            }
        }

        if ($data['locked_until'] && $data['locked_until'] > $now) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return false;
        }

        $data['attempts'] = array_values(array_filter(
            $data['attempts'],
            fn($t) => $t > $now - self::$windowSeconds
        ));

        if (count($data['attempts']) >= self::$maxAttempts) {
            $data['locked_until'] = $now + (self::$windowSeconds * 3);
            file_put_contents($file, json_encode($data), LOCK_EX);
            flock($fp, LOCK_UN);
            fclose($fp);
            return false;
        }

        $data['attempts'][] = $now;
        file_put_contents($file, json_encode($data), LOCK_EX);
        flock($fp, LOCK_UN);
        fclose($fp);

        return true;
    }

    public static function getRemainingAttempts(string $identifier): int {
        self::init();

        $key = 'rl_' . md5($identifier);
        $now = time();

        if (self::$useApcu) {
            $data = apcu_fetch($key, $success);
            if (!$success) {
                return self::$maxAttempts;
            }

            if ($data['locked_until'] && $data['locked_until'] > $now) {
                return 0;
            }

            $validAttempts = array_values(array_filter(
                $data['attempts'] ?? [],
                fn($t) => $t > $now - self::$windowSeconds
            ));

            return max(0, self::$maxAttempts - count($validAttempts));
        }

        $cacheDir = sys_get_temp_dir() . '/rate_limit';
        $file = $cacheDir . '/' . md5($identifier) . '.json';

        if (!file_exists($file)) {
            return self::$maxAttempts;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return self::$maxAttempts;
        }

        $data = json_decode($content, true);
        if (!$data) {
            return self::$maxAttempts;
        }

        if ($data['locked_until'] && $data['locked_until'] > $now) {
            return 0;
        }

        $validAttempts = array_values(array_filter(
            $data['attempts'] ?? [],
            fn($t) => $t > $now - self::$windowSeconds
        ));

        return max(0, self::$maxAttempts - count($validAttempts));
    }

    public static function clear(string $identifier): void {
        self::init();

        $key = 'rl_' . md5($identifier);

        if (self::$useApcu) {
            apcu_delete($key);
            return;
        }

        $cacheDir = sys_get_temp_dir() . '/rate_limit';
        $file = $cacheDir . '/' . md5($identifier) . '.json';
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public static function getClientIp(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && isset($_SERVER['HTTP_X_REAL_IP'])) {
            $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $ips = array_map('trim', explode(',', $forwardedFor));
            $firstIp = filter_var($ips[0], FILTER_VALIDATE_IP);
            if ($firstIp !== false) {
                return $firstIp;
            }
        }

        return preg_replace('/[^0-9a-fA-F:.]/', '', $ip) ?: '0.0.0.0';
    }
}
