# 💕 情侣纪念网站 (Love Anniversary)

> 一个优雅、安全、轻量级的 PHP + MySQL 情侣纪念网站 —— 记录你们在一起的每一天。

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-多架构%20amd64%2Farm64-2496ED?style=flat-square&logo=docker&logoColor=white)
![Image Size](https://img.shields.io/badge/镜像大小-43MB-2496ED?style=flat-square)
![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)

---

## 📖 目录

- [功能特性](#-功能特性)
- [技术栈](#-技术栈)
- [快速开始](#-快速开始)
  - [Docker 部署（推荐）](#1-docker-部署推荐)
  - [1Panel 部署](#2-1panel-部署)
  - [传统部署（Nginx/Apache）](#3-传统部署)
- [使用说明](#-使用说明)
- [项目结构](#-项目结构)
- [API 接口](#-api-接口)
- [安全设计](#-安全设计)
- [常见问题](#-常见问题)
- [更新日志](#-更新日志)
- [贡献指南](#-贡献指南)
- [许可证](#-许可证)

---

## ✨ 功能特性

### 前台展示
- 🌹 浪漫温馨的响应式界面，**白天 / 黑夜自动切换**（支持手动模式）
- 🌸 花瓣飘落动画 + 柔光特效
- ⏱️ 纪念日计时器（精确到秒，页面不可见时自动暂停节省资源）
- 🏆 **里程碑徽章**（100 天、200 天、1-10 周年自动展示）
- 📅 纪念时间线（可自定义纪念日、生日、婚礼等类型）
- 💝 愿望清单（点击即可标记完成）
- 🗺️ 探索地点记录
- 🖼️ 记忆墙照片墙（灯箱预览、失败重试）
- 🎵 背景音乐播放（主 URL 失败自动切换备用 URL）
- ✍️ 打字机情话 + 爱心点击惊喜特效

### 后台管理
- 🔐 登录安全：bcrypt 密码哈希、登录失败锁定（5 次/15 分钟）、IP 限速
- 👤 情侣信息、纪念日、愿望、地点、照片、音乐、网站设置全量管理
- 📦 数据一键导入 / 导出（JSON 备份）
- 📋 审计日志（所有关键操作留痕）
- 🧑💻 管理员账号自助修改

### 工程特性
- 🐳 **官方 Docker 镜像**：多架构（`linux/amd64` + `linux/arm64`），镜像仅 **43MB**
- ⚡ **GitHub Actions 自动构建**：推 main 打 latest，打 `v*` 标签打版本
- 🚀 前台缓存秒开 + 后台增量刷新
- 🛡️ 全链路安全防护（见 [安全设计](#-安全设计)）

---

## 🧰 技术栈

| 层 | 技术 |
|----|------|
| 后端 | PHP 8.0+（推荐 8.2），原生无框架 |
| 数据库 | MySQL 5.7+ / 8.0+，PDO 预处理语句 |
| Web | Nginx（Docker）/ Apache / 1Panel |
| 前端 | 原生 HTML/CSS/JS，Font Awesome 图标 |
| 部署 | Docker / Docker Compose / GitHub Actions |

---

## 🚀 快速开始

### 1. Docker 部署（推荐）

#### ① 准备 `.env` 环境变量

在项目根目录创建 `.env` 文件（`cp .env.example .env`），填入数据库连接信息：

```ini
DB_HOST=你的数据库主机
DB_PORT=3306
DB_NAME=数据库名
DB_USER=数据库用户
DB_PASS=数据库密码
```

> `.env` 会被容器只读挂载，且已被 `.dockerignore` 排除，**不会打入镜像**。

#### ② 一键启动

```bash
docker compose up -d --build
```

访问 `http://127.0.0.1:8000`。数据（图片、缓存、日志）通过命名卷持久化，`docker compose down` 不会丢失。

常用命令：

```bash
docker compose ps          # 查看状态
docker compose logs -f     # 查看日志
docker compose down        # 停止
docker compose up -d       # 更新代码后重建
```

#### ③ 使用官方镜像（免构建）

```bash
docker pull ghcr.io/<你的GitHub用户名>/love:latest

docker run -d --name love \
  -p 8000:80 \
  -e DB_HOST=your-db-host \
  -e DB_PORT=3306 \
  -e DB_NAME=love \
  -e DB_USER=love_user \
  -e DB_PASS=your_password \
  -v love_uploads:/var/www/html/assets/uploads \
  -v love_cache:/var/www/html/cache \
  -v love_logs:/var/www/html/logs \
  ghcr.io/<你的GitHub用户名>/love:latest
```

#### ④ 初始化数据库

1. 访问 `http://127.0.0.1:8000/install/`
2. 填写数据库信息 + 管理员账号（**密码 8-72 位，需包含字母和数字**）
3. 点击「测试连接」→「开始安装」
4. 安装完成后访问 `http://127.0.0.1:8000/admin` 登录后台

> ⚠️ 若数据库已存在数据，安装会**清空该数据库**，请谨慎操作。

#### ⑤ GitHub Actions 自动构建

仓库内置 `.github/workflows/docker-build.yml`，自动构建 **amd64 + arm64** 双架构镜像并推送至 `ghcr.io`：

| 触发方式 | 生成的标签 |
|---------|-----------|
| 推送 `main` 分支 | `latest` |
| 推送 `v*` 标签（如 `v3.1.0`） | `3.1.0`、`3.1` |
| 手动触发（Actions → Run workflow） | 可选 PHP 版本参数 |

---

### 2. 1Panel 部署

#### ① 上传代码
将项目代码上传至 1Panel 网站目录。

#### ② 配置伪静态
在网站 **设置 → 伪静态 → 自定义** 中添加：

```nginx
location / {
    try_files $uri $uri/ /router.php?$query_string;
}

location ~ \.php$ {
    try_files $uri =404;
    fastcgi_pass unix:/tmp/php-cgi.sock;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

或直接引入项目自带的 `nginx.conf`：

```nginx
include /你的网站目录/nginx.conf;
```

#### ③ 确认 PHP 版本
网站 PHP 版本需为 8.x（推荐 8.2 或 8.5）。若报 502，请按实际环境修改 `fastcgi_pass` 的 socket 路径（如 `/tmp/php-cgi-82.sock`）。

#### ④ 访问安装向导
浏览器访问 `http://你的域名/install/`，按向导完成安装。

---

### 3. 传统部署

**Nginx 配置示例：**

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/love-couple;
    index index.php router.php;

    location / {
        try_files $uri $uri/ /router.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht { deny all; }
    location ~ /\.env { deny all; }
}
```

**Apache**：项目已自带 `.htaccess`（含路由重写、敏感文件保护、目录禁用索引），确保开启 `AllowOverride All` 即可。

---

## 📖 使用说明

### 后台管理
- 地址：`/admin`
- 登录：安装时创建的管理员账号

### 功能模块

| 模块 | 说明 |
|------|------|
| 情侣信息 | 设置两人姓名、在一起纪念日 |
| 纪念日 | 添加/编辑/删除重要日期，支持类型与提前提醒 |
| 愿望清单 | 记录共同愿望，一键切换完成状态 |
| 探索地点 | 标记想去的地方 |
| 记忆墙 | 本地上传或 URL 添加照片 |
| 音乐设置 | 配置背景音乐（主/备 URL）、歌名、歌手 |
| 网站设置 | 网站名称、时区、ICP 备案号 |
| 备份导出导入 | JSON 全量备份与恢复 |
| 审计日志 | 查看关键操作记录 |

### 主题切换
- **自动模式**：根据系统偏好自动切换深浅色
- **手动模式**：点击右上角太阳/月亮/调节图标循环切换

---

## 📁 项目结构

```
.
├── index.php              # 前台首页
├── admin.php              # 后台管理
├── router.php             # 路由控制器（PHP 内置服务器用）
├── config.php             # 全局配置（安全头、会话、时区、版本）
├── .env                   # 环境变量（安装后生成，禁止提交）
├── .env.example           # 环境变量示例
├── .htaccess              # Apache 配置（路由 + 安全）
├── nginx.conf             # Nginx 伪静态（1Panel/传统部署）
├── docker-nginx.conf      # Docker 容器内 Nginx 配置
├── Dockerfile             # 多阶段构建（约 43MB）
├── docker-compose.yml     # Compose 编排
├── docker-entrypoint.sh   # 容器启动脚本
├── .dockerignore          # Docker 构建排除
├── .github/workflows/     # GitHub Actions 自动构建
├── api/                   # API 接口（REST 风格）
│   ├── data.php           # 前台聚合数据
│   ├── login.php          # 登录 / 登出 / 会话检查
│   ├── photos.php         # 照片 CRUD + 上传
│   ├── wishlists.php      # 愿望清单 CRUD
│   ├── anniversaries.php  # 纪念日 CRUD
│   ├── explores.php       # 探索地点 CRUD
│   ├── music.php          # 音乐设置
│   ├── settings.php       # 网站设置
│   ├── couple-info.php    # 情侣信息
│   ├── export.php         # 数据导出
│   ├── import.php         # 数据导入
│   ├── sync.php           # 会话保活
│   └── ...                # 后台管理相关
├── includes/              # 核心类库
│   ├── auth.php           # 认证与登录锁定
│   ├── db.php             # PDO 数据库连接
│   ├── csrf.php           # CSRF 防护
│   ├── cache.php          # 文件缓存
│   ├── RateLimiter.php    # IP 限速（APCu/文件双后端）
│   ├── Validator.php      # 输入验证器
│   ├── BaseController.php # API 基类
│   ├── functions.php      # 公共函数 + SSRF 防护
│   └── logger.php         # 日志系统（app/error/audit）
├── install/               # 安装向导
├── assets/                # 前端资源（css/js/fonts/uploads）
├── cache/                 # 缓存目录
└── logs/                  # 日志目录
```

---

## 🔌 API 接口

所有接口均返回 JSON，格式：`{"success": bool, "data": ..., "message": ..., "error": {...}}`

| 方法 | 路径 | 说明 | 鉴权 |
|------|------|------|------|
| GET | `/api/data` | 前台聚合数据（含 5 分钟缓存） | 公开 |
| POST | `/api/login.php?action=login` | 登录 | 公开（IP 限速） |
| POST | `/api/login.php?action=logout` | 登出 | 登录 + CSRF |
| GET/POST | `/api/login.php?action=check` | 会话检查 | - |
| GET/POST/PUT/DELETE | `/api/wishlists` | 愿望清单 CRUD | 登录 + CSRF |
| POST | `/api/wishlists/{id}/toggle` | 切换完成状态 | 登录 + CSRF |
| GET/POST/PUT/DELETE | `/api/anniversaries` | 纪念日 CRUD | 登录 + CSRF |
| GET/POST/PUT/DELETE | `/api/explores` | 探索地点 CRUD | 登录 + CSRF |
| GET/POST/PUT/DELETE | `/api/photos` | 照片 CRUD + 上传 | 登录 + CSRF |
| GET/PUT | `/api/music` | 音乐设置 | 登录 + CSRF |
| GET/PUT | `/api/settings` | 网站设置 | 管理员 + CSRF |
| GET/PUT | `/api/couple-info` | 情侣信息 | 管理员 + CSRF |
| POST | `/api/export.php` | 导出备份 | 管理员 + CSRF |
| POST | `/api/import.php` | 导入备份 | 管理员 + CSRF |
| GET | `/api/health` | 健康检查（数据库/缓存/日志/上传） | 公开 |
| POST | `/api/sync.php` | 会话保活 | 登录 |

**约定：**
- 写操作需携带 CSRF Token（请求头 `X-CSRF-Token` 或表单字段 `csrf_token`）
- 状态码：`200` 成功、`400` 参数错误、`401` 未登录、`403` 无权限、`404` 不存在、`405` 方法不允许、`429` 限速、`500` 服务器错误

---

## 🛡️ 安全设计

| 防护项 | 实现 |
|--------|------|
| SQL 注入 | 全站 PDO 预处理语句，禁用模拟预处理 |
| XSS | 输出统一 `escapeHtml()`（ENT_QUOTES），前端 `escapeJs()` 双重防护 |
| CSRF | 会话绑定 Token，`hash_equals` 常量时间比较 |
| 密码安全 | bcrypt（cost=12），登录自动重哈希升级，72 字节上限 |
| 暴力破解 | 5 次失败锁定 15 分钟 + IP 限速（5 次/60 秒，APCu/文件双后端） |
| 登录锁定 | 数据库记录失败次数与锁定时间（本地时区，修复时区偏差） |
| SSRF | 域名全量 DNS 解析校验 + 拒绝内网/保留地址 + IPv6 防护（照片/音乐 URL） |
| 敏感文件 | `.env`/`.git`/隐藏文件/Dockerfile 等禁止访问（Apache + Nginx 双重） |
| 上传安全 | MIME + 魔数双重校验，uploads 目录禁止执行任何脚本 |
| 会话安全 | HttpOnly、Secure（HTTPS 下）、SameSite=Strict、定期过期 |
| 安全头 | HSTS、CSP、X-Content-Type-Options、X-Frame-Options、Referrer-Policy |
| 审计日志 | 登录/登出/增删改/导入导出等关键操作全部留痕 |

---

## ❓ 常见问题

### Q: 安装页面提示"could not find driver"
A: PHP 缺少 `pdo_mysql` 扩展。1Panel 中到 PHP 应用 → 设置 → 扩展 → 安装 `pdo`、`pdo_mysql`。Docker 部署已内置，无需处理。

### Q: Docker 部署后连不上数据库
A: 检查：① `.env` 数据库信息是否正确；② 数据库是否允许容器所在 IP 远程连接；③ `docker compose logs -f` 查看错误日志。

### Q: 前台/后台显示 404
A: 路由未配置。Nginx 需 `try_files $uri $uri/ /router.php?$query_string;`，Apache 需开启 AllowOverride。Docker 已内置。

### Q: 图片无法上传
A: 检查 uploads 目录写权限、PHP `upload_max_filesize`、Nginx `client_max_body_size`。Docker 已内置 20M 限制与持久卷。

### Q: 音乐无法播放
A: 检查音乐 URL 可访问性、浏览器是否拦截自动播放、控制台错误。主 URL 失效会自动切换备用 URL。

---

## 📝 更新日志

### v3.1.0（安全加固 + Docker 优化）
- 🐛 修复登录锁定因时区偏差完全失效的严重缺陷
- 🐛 修复前台计时器 visibilitychange 监听器累积泄漏
- 🛡️ 新增共享 SSRF 防护（全量 DNS 校验、IPv6、拒绝凭据 URL）
- 🛡️ 音乐 URL 接入 SSRF 校验
- 🛡️ 密码强制 72 字节上限（对齐 bcrypt）
- 🛡️ Apache/Nginx 双重保护 `.env`、`.git`、隐藏文件、uploads 脚本执行
- 🛡️ 安装向导：数据库标识符白名单校验（防注入）、错误信息脱敏、`.env` 写入权限 0600
- 🛡️ 后台表单登录路径补 IP 限速（防绕过 API 限速暴力破解）
- 📋 补齐 wishlists/anniversaries/explores/photos/music 更新审计日志
- ⚡ sync.php 移除无意义 COUNT 轮询（零数据库开销）
- ⚡ 静态资源版本号随文件 mtime 自动变化
- 🐳 镜像瘦身至 43MB，支持 amd64/arm64 双架构自动构建
- 📄 README 重写为规范化文档

### v3.0.0
- 添加里程碑功能（100天、200天、1-10周年等）
- 添加时区设置功能（支持 7 个代表性时区）
- 修复计时器显示"在一起多久了"而非当前时间
- 修复 index.php CSRF token XSS 漏洞
- 修复 admin.php username XSS 漏洞
- 修复 install SQL 注入风险
- 修复后台 JavaScript XSS 漏洞
- 修复 IP 限速可被伪造绕过的安全问题
- 修复前台 ICP 备案信息的 XSS 安全漏洞
- 修复安装脚本密码写入 .env 时的注入漏洞
- 优化计时器性能（避免每秒创建 DateTimeFormat 实例）
- 优化定时器在页面不可见时暂停，节省资源
- 优化前台数据同步失败时的错误提示
- 优化缓存和限流器初始化（延迟初始化）
- 增强 SSRF 防护
- 统一版本号管理
- 为所有关键操作添加审计日志记录
- cache.php 使用 json_decode 替代 unserialize 防止反序列化漏洞
- 添加 HSTS 安全头
- 删除未使用的 DataCache.php 文件
- 代码深度审查和安全加固

### v2.0.3
- 修复计时器时区问题，确保时分秒显示正确
- 在 health API 中添加 PHP 配置检测
- 修复导入导出功能支持 timezone 字段
- 修复 data.php 查询 site_settings 时缺少 timezone 字段
- 为所有关键操作添加审计日志记录
- login.php logout action 添加 CSRF 验证
- admin-logout.php 添加 CSRF 验证和登录状态检查
- admin-status.php 添加 username 转义
- settings.php 添加 timezone 验证和长度限制
- music.php 添加 URL 验证和长度限制
- 极限审查修复：install SQL 注入、admin.php JSON 编码 XSS 防护、SSRF 防护优化

### v2.0.2
- 照片卡片统一为 4:3 比例显示
- 照片编辑功能，支持修改图片链接和说明
- 本地上传照片和 URL 照片区分编辑模式

### v2.0.1
- 修复后台删除图片后服务器文件未删除的问题
- 修复照片删除 API 返回 404 的路由问题
- 优化前台缓存机制，后台修改数据后自动清除前台缓存
- 修复图片 URL 相对路径显示问题

### v2.0.0
- 全新重构版本：PHP + MySQL 架构
- 完整的后台管理、白天/黑夜模式、数据导入导出
- 增强的安全防护、登录锁定机制、CSRF 令牌保护

---

## 🤝 贡献指南

1. Fork 本仓库
2. 创建功能分支：`git checkout -b feature/your-feature`
3. 提交改动：`git commit -m "feat: 描述你的改动"`
4. 推送分支：`git push origin feature/your-feature`
5. 提交 Pull Request

**代码规范：**
- PHP 8.0+，遵循 PSR-12 编码风格
- 所有数据库操作必须使用 PDO 预处理语句
- 所有输出必须经过 HTML 转义
- 所有写操作必须校验 CSRF Token
- 关键操作必须记录审计日志

---

## 📄 许可证

本项目基于 [MIT License](LICENSE) 开源。

---

## 💌 致谢

- [Font Awesome](https://fontawesome.com/) - 图标库
- 设计灵感来自众多优秀的情侣纪念网站
