#!/bin/sh
set -e

# 卷挂载会覆盖镜像内权限，这里在启动前修正可写目录的所有者
chown -R www-data:www-data /var/www/html/cache /var/www/html/logs /var/www/html/assets/uploads 2>/dev/null || true
# 会话持久卷（容器重建后登录态不丢失）
chown -R www-data:www-data /var/lib/php/sessions 2>/dev/null || true

# 启动 PHP-FPM（后台）与 Nginx（前台，保持容器存活）
php-fpm -D

exec "$@"
