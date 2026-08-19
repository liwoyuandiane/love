#!/bin/sh
set -e

# ============================================================
# 情侣纪念网站 - 容器启动脚本（v3.2.0 一键启动）
#
# 设计目标：宿主机只需
#   mkdir -p love && cd love
#   docker run -d --name love -p 8000:80 -v "$PWD":/data ghcr.io/liwoyuandiane/love:latest
#
# 本脚本自动完成：
#   1. 自动创建数据子目录 uploads/cache/logs/sessions（无需手动 mkdir）
#   2. 自动检测 .env：love/ 下有 .env → 直接使用；没有 → 进入安装向导
#   3. 数据目录映射到应用路径（symlink），容器重建数据不丢
# ============================================================

DATA_DIR="${DATA_DIR:-/data}"
APP_DIR=/var/www/html

echo "[love] 数据目录: $DATA_DIR"
echo "[love] 应用目录: $APP_DIR"

# ------------------------------------------------------------
# 1) 自动创建数据子目录（bind mount 缺失的目录由 Docker 以 root 创建，这里补齐并修正所有权）
# ------------------------------------------------------------
mkdir -p "$DATA_DIR/uploads" "$DATA_DIR/cache" "$DATA_DIR/logs" "$DATA_DIR/sessions"
echo "[love] 数据子目录就绪: uploads/ cache/ logs/ sessions/"

# ------------------------------------------------------------
# 2) .env 检测：
#    - love/ 下有 .env → 同步到应用目录，直接使用该数据库配置
#    - 没有 .env       → 确保应用目录无 .env，浏览器访问自动进入安装向导
#      （安装向导会把 .env 写入 $DATA_DIR/.env，即宿主机 love/ 目录，容器重建配置不丢）
# ------------------------------------------------------------
if [ -f "$DATA_DIR/.env" ]; then
    echo "[love] 检测到 .env → 使用现有数据库配置"
    cp -f "$DATA_DIR/.env" "$APP_DIR/.env"
    chown www-data:www-data "$APP_DIR/.env" 2>/dev/null || true
    chmod 600 "$APP_DIR/.env"
else
    echo "[love] 未检测到 .env → 首次启动将进入安装向导"
    echo "[love] 请打开浏览器访问: http://<服务器IP>:<端口>/install/"
    rm -f "$APP_DIR/.env"
fi

# ------------------------------------------------------------
# 3) 数据目录映射到应用位置（symlink：uploads/cache/logs）
# ------------------------------------------------------------
rm -rf "$APP_DIR/cache" "$APP_DIR/logs" "$APP_DIR/assets/uploads"
ln -sfn "$DATA_DIR/cache"   "$APP_DIR/cache"
ln -sfn "$DATA_DIR/logs"    "$APP_DIR/logs"
ln -sfn "$DATA_DIR/uploads" "$APP_DIR/assets/uploads"
echo "[love] 数据目录已映射: uploads/cache/logs"

# ------------------------------------------------------------
# 4) PHP 会话目录（重建容器后登录态不丢失）
# ------------------------------------------------------------
rm -rf /var/lib/php/sessions
ln -sfn "$DATA_DIR/sessions" /var/lib/php/sessions
echo "[love] 会话目录已映射: sessions"

# ------------------------------------------------------------
# 5) 权限：数据目录归 www-data（PHP-FPM 运行用户）
#    - 数据目录根：安装向导需要在此创建 .env
#    - 数据子目录：照片/缓存/日志/会话读写
# ------------------------------------------------------------
chown www-data:www-data "$DATA_DIR" 2>/dev/null || true
chown -R www-data:www-data "$DATA_DIR/uploads" "$DATA_DIR/cache" "$DATA_DIR/logs" "$DATA_DIR/sessions" 2>/dev/null || true
chown -R www-data:www-data /var/lib/php/sessions 2>/dev/null || true

# ------------------------------------------------------------
# 6) 启动 PHP-FPM（后台）与 Nginx（前台，保持容器存活）
# ------------------------------------------------------------
php-fpm -D

exec "$@"
