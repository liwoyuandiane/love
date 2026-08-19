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

- [使用说明](#-使用说明)
- [常见问题](#-常见问题)
- [贡献指南](#-贡献指南)
- [许可证](#-许可证)

---

## ✨ 功能特性

### 前台展示
- 🌹 浪漫温馨的响应式界面，**白天 / 黑夜一键切换**（默认白天，右上角开关）
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
- 🔐 **登录会话持久化**：容器重建后登录态不丢失（CSRF 不失效）
- 🛡️ 全链路安全防护（SQL 注入/XSS/CSRF/SSRF/上传安全等）

---

## 🧰 技术栈

| 层 | 技术 |
|----|------|
| 后端 | PHP 8.0+（推荐 8.2），原生无框架 |
| 数据库 | MySQL 5.7+ / 8.0+，PDO 预处理语句 |
| Web | Nginx（Docker 容器内） |
| 前端 | 原生 HTML/CSS/JS，Font Awesome 图标 |
| 部署 | Docker / Docker Compose / GitHub Actions |

---

## 🚀 快速开始

### 1. Docker 部署（推荐）

> 💡 **所有数据统一存放在 `love/` 目录下**：照片、缓存、日志、登录会话都在这里，方便查找、备份、迁移，不会散落在 Docker 卷里。

#### ① 创建目录并启动 ⭐（只需两步）

```bash
mkdir -p love && cd love
docker run -d --name love -p 8000:80 -v "$PWD":/data ghcr.io/liwoyuandiane/love:latest
```

首次访问（`love/` 下还没有 `.env` 时）：浏览器打开 `http://127.0.0.1:8000/` 会自动进入安装向导，按提示填写数据库信息和管理员账号（密码 8-72 位，需含字母和数字）即可完成安装。

> 容器会自动检测并创建一切：`love/` 下有 `.env` 就用它，没有就进安装向导；`uploads/ cache/ logs/ sessions/` 数据目录自动生成；`.env` 由安装向导写入 `love/`，手动修改后执行 `docker restart love` 生效。

#### ② 本地构建（可选）

> 不想直接使用官方镜像、需要修改/调试源码时，将本仓库代码与 `docker-compose.yml` 一起放到你的工作目录（如 `love/`），然后：

```bash
cd love
docker compose up -d --build
```

> compose 同样采用一键数据目录（`./:/data`），`.env` 检测与目录自动创建行为与上方 `docker run` 完全一致。

#### ③ 数据目录（love/）

**所有数据都存放在 `love/` 目录下**，直接打包整个目录即可完成备份：

| 目录 | 作用 |
|------|------|
| `uploads/` | 📷 **上传的照片**：后台"记忆墙"本地上传的图片文件都存这里 |
| `cache/` | ⚡ 前台数据缓存 |
| `logs/` | 📋 应用/错误/审计日志 |
| `sessions/` | 🔐 登录会话（容器重建后登录态不丢） |

> 📷 **本地图片**：记忆墙上传的图片以随机文件名（如 `65c2d3e4f5a6b7.jpg`）保存到 `uploads/`，数据库 `photos` 表只记录路径（`/assets/uploads/xxx.jpg`）。因此**完整备份 = 后台导出 JSON + 打包 `love/` 目录**（含照片文件）。

#### ④ 常用命令与升级

```bash
docker ps                 # 查看容器状态
docker logs -f love       # 查看容器日志（会显示 .env 检测结果）
docker restart love       # 重启
docker stop love && docker rm love   # 删除容器（数据保留在 love/ 目录）
```

> 💡 升级镜像：`docker rm -f love && docker run ...`（重新执行 ① 的 run 命令即可），数据不丢。

#### ⑤ GitHub Actions 自动构建

仓库内置 `.github/workflows/docker-build.yml`，自动构建 **amd64 + arm64** 双架构镜像并推送至 `ghcr.io/liwoyuandiane/love`：

| 触发方式 | 生成的标签 |
|---------|-----------|
| 推送 `main` 分支 | `latest` |
| 推送 `v*` 标签（如 `v3.1.0`） | `3.1.0`、`3.1` |
| 手动触发（Actions → Run workflow） | 可选 PHP 版本参数 |

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
- **默认白天模式**，右上角横向开关：白天 = 左球太阳 ☀，点击滑到右侧 = 黑夜 = 月亮 🌙
- 选择保存在浏览器本地（`localStorage`），下次访问自动应用

---


## ❓ 常见问题

### Q: 后台操作提示"CSRF 验证失败，请刷新页面后重试"
A: 通常是 **容器重建导致 PHP 会话丢失**（session 未持久化）造成的：
1. 确认 `docker run` 时挂载了整个数据目录 `-v "$PWD":/data`（session 目录会自动映射）
2. 刷新页面（会重新生成与当前会话匹配的 CSRF Token）
3. 若仍失败，重启容器：`docker restart love` 后重新登录

> 新版镜像已内置 session 持久化（`session.save_path=/var/lib/php/sessions`），挂载 `-v "$PWD":/data` 后会话自动落到 `love/sessions/`，容器重建后登录态与 CSRF 均不受影响。

### Q: 安装页面提示"could not find driver"
A: PHP 缺少 `pdo_mysql` 扩展。Docker 部署已内置，无需处理；非 Docker 部署需在 PHP 中启用 `pdo`、`pdo_mysql` 扩展。

### Q: Docker 部署后连不上数据库
A: 检查：① `.env` 数据库信息是否正确；② 数据库是否允许容器所在 IP 远程连接；③ `docker logs -f love` 查看错误日志。

### Q: 前台/后台显示 404
A: 路由未配置。Nginx 需 `try_files $uri $uri/ /router.php?$query_string;`，Apache 需开启 AllowOverride。Docker 已内置。

### Q: 图片无法上传
A: 检查 `love/uploads` 目录写权限、PHP `upload_max_filesize`、Nginx `client_max_body_size`。Docker 已内置 20M 限制。

### Q: 音乐无法播放
A: 检查音乐 URL 可访问性、浏览器是否拦截自动播放、控制台错误。主 URL 失效会自动切换备用 URL。

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