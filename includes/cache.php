<?php
/**
 * 文件缓存工具
 */

class FileCache {
    private static string $cacheDir;

    public static function init(string $cacheDir = null): void {
        if ($cacheDir === null) {
            $cacheDir = sys_get_temp_dir() . '/love_site_cache';
        }
        self::$cacheDir = $cacheDir;
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }

    public static function get(string $key, int $ttl = 300): ?array {
        self::init();
        $file = self::getFilePath($key);

        if (!file_exists($file)) {
            return null;
        }

        if (filemtime($file) + $ttl < time()) {
            unlink($file);
            return null;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }

    public static function set(string $key, array $data): bool {
        self::init();
        $file = self::getFilePath($key);
        $content = json_encode($data, JSON_UNESCAPED_UNICODE);

        return file_put_contents($file, $content, LOCK_EX) !== false;
    }

    public static function delete(string $key): bool {
        self::init();
        $file = self::getFilePath($key);

        if (!file_exists($file)) {
            return true;
        }

        return unlink($file);
    }

    public static function clear(): void {
        self::init();
        $files = glob(self::$cacheDir . '/*.cache');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private static function getFilePath(string $key): string {
        $hash = md5($key);
        return self::$cacheDir . '/' . $hash . '.cache';
    }
}
