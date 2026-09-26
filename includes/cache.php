<?php
/**
 * 文件缓存类
 */

class Cache {
    private static string $cacheDir;
    private static int $defaultTTL = 300;
    private static bool $initialized = false;

    private static function ensureDir(): void {
        if (self::$initialized) return;

        self::$cacheDir = dirname(__DIR__) . '/cache';
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
        @chmod(self::$cacheDir, 0755);
        self::$initialized = true;
    }

    public static function get(string $key, int $ttl = null): mixed {
        self::ensureDir();
        $file = self::getFilePath($key);
        if (!file_exists($file)) return null;

        $ttl = $ttl ?? self::$defaultTTL;
        if (filemtime($file) + $ttl < time()) {
            @unlink($file);
            return null;
        }

        $content = file_get_contents($file);
        return $content ? json_decode($content, true) : null;
    }

    public static function set(string $key, mixed $value, int $ttl = null): bool {
        self::ensureDir();
        $file = self::getFilePath($key);
        $data = json_encode($value, JSON_UNESCAPED_UNICODE);
        $result = file_put_contents($file, $data, LOCK_EX);
        return $result !== false;
    }

    public static function delete(string $key): void {
        self::ensureDir();
        $file = self::getFilePath($key);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * 清除缓存。
     * - 空 / '*'：清空全部 .cache
     * - 非空：仅精确删除键为 $prefix 的文件（键文件名 key_md5hash.cache），避免 api_data_* 误删 api_data_xxx
     */
    public static function clear(string $prefix = ''): void {
        self::ensureDir();
        if ($prefix === '' || $prefix === '*') {
            $files = glob(self::$cacheDir . '/*.cache') ?: [];
            foreach ($files as $file) {
                if (is_file($file)) @unlink($file);
            }
            return;
        }
        $file = self::getFilePath($prefix);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private static function getFilePath(string $key): string {
        return self::$cacheDir . '/' . $key . '_' . md5($key) . '.cache';
    }
}