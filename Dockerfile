# syntax=docker/dockerfile:1

# PHP 基础版本（可通过 build-arg 覆盖，Actions 默认 8.2）
ARG PHP_VERSION=8.2

# ============================================================
# 阶段 1：构建阶段 —— 编译 PHP 扩展（GD / PDO MySQL）
# ============================================================
FROM php:${PHP_VERSION}-fpm-alpine AS builder

RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql \
    && apk del .build-deps

# ============================================================
# 阶段 2：运行阶段 —— 最小化镜像（Nginx + PHP-FPM）
# ============================================================
FROM php:${PHP_VERSION}-fpm-alpine

# 从构建阶段拷贝编译好的扩展及配置
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/docker-php-ext-gd.ini /usr/local/etc/php/conf.d/docker-php-ext-gd.ini
COPY --from=builder /usr/local/etc/php/conf.d/docker-php-ext-pdo_mysql.ini /usr/local/etc/php/conf.d/docker-php-ext-pdo_mysql.ini

# 安装 Nginx 与 GD 运行时库（体积小、无编译工具链）
RUN apk add --no-cache nginx \
        libpng \
        libjpeg-turbo \
        libwebp \
        freetype \
    && mkdir -p /var/log/nginx /var/cache/nginx /run/nginx /var/www/html \
    && chown -R nginx:nginx /var/log/nginx /var/cache/nginx /run/nginx

# 覆盖 Nginx 主配置（Alpine nginx 包用 /etc/nginx/http.d/ 而非 conf.d）
COPY docker-nginx.conf /etc/nginx/http.d/default.conf
RUN rm -f /etc/nginx/conf.d/default.conf

# 启动脚本
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# 拷贝应用代码
COPY . /var/www/html/

# 目录与权限（www-data 写 uploads/cache/logs，nginx 只读）
RUN mkdir -p /var/www/html/cache /var/www/html/logs /var/www/html/assets/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/cache /var/www/html/logs /var/www/html/assets/uploads

# PHP-FPM 通过 unix socket 与 Nginx 通信
# （Alpine 官方 www.conf 默认 listen 行是注释的 ;listen = 127.0.0.1:9000）
RUN sed -i 's|^;listen = 127.0.0.1:9000|listen = /run/php-fpm.sock|; s|;listen.owner = www-data|listen.owner = www-data|; s|;listen.group = www-data|listen.group = www-data|; s|;listen.mode = 0660|listen.mode = 0660|' /usr/local/etc/php-fpm.d/www.conf

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD ["wget", "-q", "-O", "-", "http://127.0.0.1/api/health"]

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]