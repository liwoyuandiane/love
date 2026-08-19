<?php
/**
 * 静态资源版本号统一入口
 *
 * 所有页面（前台/后台/安装向导）都通过 asset() 引用静态资源，
 * 版本号自动跟随文件 mtime，避免 nginx immutable 缓存导致样式/脚本不更新。
 *
 * 用法：echo asset('assets/css/admin.css');
 *       // => /assets/css/admin.css?v=1787025126
 */
function asset(string $path): string {
    static $cache = [];

    if (!isset($cache[$path])) {
        $file = dirname(__DIR__) . '/' . ltrim($path, '/');
        $mtime = is_file($file) ? filemtime($file) : false;
        $cache[$path] = $mtime !== false ? $mtime : (defined('APP_VERSION') ? APP_VERSION : '1');
    }

    return '/' . ltrim($path, '/') . '?v=' . $cache[$path];
}
