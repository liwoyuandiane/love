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
    private static bool $initialized = false;

    private static function ensureInit(): void {
        if (self::$initialized) return;
        self::$useApcu = extension_loaded('apcu') && apcu_enabled();
        self::$initialized = true;
    }

    public static function check(string $identifier): bool {
        self::ensureInit();

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
        @chmod($cacheDir, 0755);

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
        self::ensureInit();

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

        $data = json_decode((string)file_get_contents($file), true) ?: [];

        if (($data['locked_until'] ?? null) && $data['locked_until'] > $now) {
            return 0;
        }

        $validAttempts = array_values(array_filter(
            $data['attempts'] ?? [],
            fn($t) => $t > $now - self::$windowSeconds
        ));

        return max(0, self::$maxAttempts - count($validAttempts));
    }

    public static function clear(string $identifier): void {
        self::ensureInit();

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

    /**
     * 获取客户端真实 IP
     *
     * 默认只信任 REMOTE_ADDR。若部署在可信反向代理之后，
     * 可通过环境变量 TRUSTED_PROXIES 配置信任的代理来源地址
     * （逗号分隔，支持 IP/CIDR/精确网段，例如 "10.0.0.0/8,172.16.0.0/12"），
     * 只有来自可信代理的请求才使用 X-Forwarded-For / X-Real-IP 头，
     * 防止攻击者伪造请求头绕过限速。
     */
    public static function getClientIp(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ip = preg_replace('/[^0-9a-fA-F:.]/', '', $ip) ?: '0.0.0.0';

        // 判断来源是否为受信任代理
        if (!self::isTrustedProxy($ip)) {
            return $ip;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $ips = array_map('trim', explode(',', $forwardedFor));
            // 从右向左跳过同样受信任的代理，取最左侧真实客户端 IP
            for ($i = count($ips) - 1; $i >= 0; $i--) {
                $candidate = filter_var($ips[$i], FILTER_VALIDATE_IP);
                if ($candidate !== false && !self::isTrustedProxy($candidate)) {
                    return preg_replace('/[^0-9a-fA-F:.]/', '', $candidate) ?: $ip;
                }
            }
            $firstIp = filter_var($ips[0], FILTER_VALIDATE_IP);
            if ($firstIp !== false) {
                return preg_replace('/[^0-9a-fA-F:.]/', '', $firstIp) ?: $ip;
            }
        }

        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $realIp = trim($_SERVER['HTTP_X_REAL_IP']);
            $validatedIp = filter_var($realIp, FILTER_VALIDATE_IP);
            if ($validatedIp !== false && !self::isTrustedProxy($validatedIp)) {
                return preg_replace('/[^0-9a-fA-F:.]/', '', $validatedIp) ?: $ip;
            }
        }

        return $ip;
    }

    private static function isTrustedProxy(string $ip): bool {
        // 默认仅信任本机回环（本地开发的 php -S 等场景）
        $trusted = trim((string)getenv('TRUSTED_PROXIES'));
        if ($trusted === '') {
            $trusted = '127.0.0.1,::1';
        }

        $parts = array_map('trim', explode(',', $trusted));
        foreach ($parts as $part) {
            if ($part === '') continue;

            // CIDR 形式如 10.0.0.0/8、172.16.0.0/12
            if (str_contains($part, '/')) {
                if (self::ipInCidr($ip, $part)) {
                    return true;
                }
                continue;
            }
            if ($ip === $part) {
                return true;
            }
        }
        return false;
    }

    private static function ipInCidr(string $ip, string $cidr): bool {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
        if ($bits === null || !ctype_digit($bits)) {
            return false;
        }
        $bits = (int)$bits;

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        $len = strlen($ipBin);
        if ($len !== strlen($subnetBin)) {
            return false;
        }
        if ($bits > $len * 8) {
            $bits = $len * 8;
        }

        $fullBytes = intdiv($bits, 8);
        $remainBits = $bits % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainBits > 0) {
            $mask = 0xFF << (8 - $remainBits) & 0xFF;
            $ipByte = ord($ipBin[$fullBytes]);
            $subnetByte = ord($subnetBin[$fullBytes]);
            if (($ipByte & $mask) !== ($subnetByte & $mask)) {
                return false;
            }
        }

        return true;
    }
}