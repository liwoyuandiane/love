<?php
/**
 * 文件缓存类
 */

class Cache {
    private static string $cacheDir;
    private static int $defaultTTL = 300;

    public static function init(): void {
        self::$cacheDir = dirname(__DIR__) . '/cache';
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
        @chmod(self::$cacheDir, 0755);
    }

    public static function get(string $key, int $ttl = null): mixed {
        self::init();
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
        self::init();
        $file = self::getFilePath($key);
        $data = json_encode($value, JSON_UNESCAPED_UNICODE);
        $result = file_put_contents($file, $data, LOCK_EX);
        return $result !== false;
    }

    public static function delete(string $key): void {
        self::init();
        $file = self::getFilePath($key);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    public static function clear(string $prefix = ''): void {
        self::init();
        if (empty($prefix) || $prefix === '*') {
            $files = glob(self::$cacheDir . '/*.cache');
        } else {
            $prefix = rtrim($prefix, '*');
            $files = glob(self::$cacheDir . '/' . $prefix . '_*.cache');
        }
        foreach ($files as $file) {
            if (is_file($file)) @unlink($file);
        }
    }

    private static function getFilePath(string $key): string {
        return self::$cacheDir . '/' . $key . '_' . md5($key) . '.cache';
    }
}