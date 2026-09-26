# 代码审查问题清单（两轮合并 + 修复状态）

> 项目：情侣纪念网站（PHP 8 + MySQL/MariaDB + Nginx/Docker）
> 审查日期：2026-09-23；修复与回归：2026-09-24
> 范围：安全性、正确性、性能、可维护性、UI
> 回归：`/tmp/opencode/round2_test.php` **73 PASS / 0 FAIL**（第五轮复跑）

## 摘要

| 严重度 | 数量 | 已修 | 未修/留档 |
|--------|------|------|-----------|
| 高 | 6 | 6 | 0（含 H6 逐跳 curl 复检） |
| 中 | 8 | 8 | 1（字体体积 M2 留档；M5 CSRF 2h 已补） |
| 低 | 7 | 7 | 0（L4 已核对为已修；style-src 已收紧） |
| UI | 若干 | 静态内联清零 + toast class 化 | 仅 CSSOM 动态 style（不受 style-src 限制） |
| 已撤销 | 1 | — | — |

---

## 高（High）— 全部已修（2026-09-24）

### H1. 后台登出请求缺少 CSRF 头 → 必然 403 — **已修**
- **位置**：`assets/js/admin.js` 与 `api/admin-logout.php`
- **原问题**：登出裸 `fetch` 未带 `X-CSRF-Token` → 403。
- **修复**：`data-action="logout"` 委托 + `csrfFetch`。

### H2. 后台照片编辑按钮单引号逃逸 XSS — **已修**
- **位置**：`assets/js/admin.js` 模板 onclick
- **修复**：全部改 `data-action` + `document` 级委托；属性上下文用补强 `escapeHtml`。

### H3. 照片 `src` / 回调参数属性未充分转义 — **已修**
- **位置**：`assets/js/main.js`、`admin.js`
- **修复**：`escapeHtml` 补 `"` `'`；图片 `onerror` 改 `addEventListener('error', ..., {once:true})`。

### H4. `escapeHtml` 不覆盖引号 — **已修**
- **位置**：`assets/js/utils.js`
- **修复**：字符映射转义 `& < > " '`（含等价单测）。

### H5. 照片上传文件名可预测 — **已修**
- **位置**：`api/photos.php`
- **修复**：`bin2hex(random_bytes(16)) . '.' . $ext`。

### H6. 远程图片 URL 校验 TOCTOU / 重定向后未复检 — **已修（第五轮）**
- **位置**：`api/photos.php`
- **修复**：`get_headers` 改为 curl `probeImageUrl`：`CURLOPT_FOLLOWLOCATION=false` 关闭自动重定向，逐跳校验 `Location` 的 `isUrlSafe` 与最终 `Content-Type`（image/jpeg|png|gif|webp）；5s 超时 + 最多 3 跳；协议限定 `CURLPROTO_HTTP|HTTPS`。
- **终态**：多跳场景每跳独立复检，Content-Type 白名单在最终 200 响应上强制。

---

## 中（Medium）

### M1. 无任何 gzip/br 压缩配置 — **已修**
- **位置**：`nginx.conf`、`docker-nginx.conf`
- **修复**：Nginx 开启 `gzip`；`php -S` 本地环境无 gzip 为预期。

### M2. Noto Sans SC 三字重约 3.4MB，无 unicode-range 分片 — **留档（不删）**
- **位置**：`assets/fonts/noto-sans-sc-{400,500,700}.woff2`；`assets/css/fonts.css`
- **问题**：`fonts.css` 仅声明 400/700，磁盘 `noto-sans-sc-500.woff2`（~1.1MB）未引用仍占体积；无 `unicode-range`。
- **状态**：按「无删除操作」约束，文件保留；如需减重由用户手动删 500 或做 unicode-range/子集化。

### M3. 审计日志全量读入内存 — **已修（流式）**
- **位置**：`api/audit-logs.php`
- **修复**：`fgets` 两遍扫描分页；`Logger` 2MB 轮转已在第一轮落地。

### M4. 限速计次早于参数校验 — **已修**
- **位置**：`api/login.php`、`api/admin-verify.php`
- **修复**：先校验非空再 `RateLimiter::check`。

### M5. CSRF token 永不过期、不轮换 — **已修（含 2h 过期，第五轮）**
- **位置**：`includes/csrf.php`、login/logout
- **修复**：登录成功后重新生成；登出轮换；session 记录 `csrf_token_time`，`TTL=7200`（2h）过期后 `validate` 失败并清除，`generate` 在过期时自动换新。

### M6. CSP 含 `script-src 'unsafe-inline'` — **已修**
- **位置**：`config.php`、`nginx.conf`、`docker-nginx.conf`
- **修复**：`script-src 'self'`；内联 bootstrap → `assets/js/boot.js`；安装页 → `assets/js/install.js`；admin/main 全量 `data-action` 委托。
- **残留清理（第五轮）**：`style-src` 去 `'unsafe-inline'` 与 `https://fonts.googleapis.com`；`font-src` 去 `fonts.gstatic.com`/`https:`（字体全自托管）。

### M6b. 静态内联样式与 `style.display` 双轨显隐 — **已修（第四轮）**
- **位置**：`admin.php`、`install/index.php`、`assets/js/{admin,install,main}.js`
- **修复**：静态 `style="` → class（`is-hidden`/`is-visible`/`span-full`/`backup-card`/`info-banner` 等）；JS 显隐 → `classList`；install 样式外置 `assets/css/install.css`；`onsubmit` 属性移除改 JS 防默认。
- **终态**：PHP/HTML/JS 模板 `style="` = 0；`<style>` 块 = 0；`style.display` = 0；仅存 CSSOM（`cssText`/`setProperty`/`style.x=`，不受 `style-src` 限制）。

### M6c. `utils.js` toast 内联 cssText — **已修（第五轮）**
- **位置**：`assets/js/utils.js`、`assets/css/style.css`
- **修复**：`createToast` 改 class（`.toast-notification` + `.toast-info|success|error|warning` + `.is-visible`）；`window.createToast` 全局导出（修 boot.js 回落落空）；`style.css` 补 toast 规则与 `--success`/`--danger`。

### M7. CRUD 控制器重复度高 — **已修**
- **位置**：`includes/CrudController.php` + `api/{anniversaries,wishlists,explores}.php`
- **修复**：抽象基类 + 薄子类；photos 因上传逻辑保留独立实现但共用 `resolveId`。

### M8. `escapeHtml` DOM 实现性能 — **已修**
- **位置**：`assets/js/utils.js`
- **修复**：改为纯字符串 replace（与 H4 一并）。

---

## 低（Low）

### L1. Font Awesome `font-display:block` + 缺失 .ttf 回退 — **已修**
- **修复**：去掉 ttf 源；`font-display: swap`。

### L2. 路由正则未过滤 `..` — **已修**
- **修复**：`router.php` 拒绝 `..` + API basename 白名单 + realpath 白名单。

### L3. `Cache::clear` 前缀 glob 误删隐患 — **已修**
- **位置**：`includes/cache.php`
- **修复**：非空前缀精确 `unlink(getFilePath($prefix))`；空/`*` 清全部。回归 decoy 存活。

### L4. 日志路径依赖 `__DIR__/../logs` — **已修（此前误标未修）**
- **位置**：`includes/logger.php`
- **修复**：mkdir 失败 / 写失败 → `error_log` 降级（约 :23-25、:64-66）；启动自检告警可选。

### L5. 安装向导重装会 `DROP DATABASE` — **已修**
- **位置**：`install/index.php`
- **修复**：已有 `.env` GET/POST 均 302；有表数据必须 `confirm_drop=1`。

### L6. HSTS 本地/HTTP 环境无意义 — **已修**
- **修复**：仅 HTTPS 时输出 HSTS。

### L7. 会话空闲 30 分钟强制登出提示不足 — **已修**
- **位置**：`includes/auth.php`、`BaseController`、`assets/js/boot.js`
- **修复**：`session_expired` → 401 `SESSION_EXPIRED`；前端提示重新登录；`consumeSessionExpiredFlag()`。

---

## 已撤销（误报）

### ~~X1. `site_setting` / `site_settings` 表名不一致~~
- **结论**：grep 全项目确认统一为 `site_settings`（`api/data.php:51`、`install/index.php:202` 等），撤销。

---

## 复核通过（无问题）

- `api/export.php` / `api/import.php`：admin + CSRF 已校验
- `api/sync.php`：仅刷新 session 活跃时间，无 DB 查询
- `api/wishlists-toggle.php`：经 `includes/functions.php` 隐式加载 csrf，链路有效
- `includes/auth.php`：bcrypt cost 12、72 字节上限、5 次失败锁 15 分钟、登录成功 `session_regenerate_id`
- Dockerfile `PHP_VERSION` 默认 8.2，与 README 一致

---

## 仍待处理

1. **M2 字体体积**：`noto-sans-sc-500.woff2` 按不删约束留档；评估 400/700 子集化 / unicode-range（由用户决定是否删）
2. ~~**L4 日志写失败降级 `error_log`**~~ — 已修（`includes/logger.php`）
3. ~~**`style-src` 去 `unsafe-inline`；H6 逐跳 curl；CSRF 2h 过期**~~ — 均已修（第五轮）

---

## 建议修复顺序（已完成情况）

1. ~~H1 登出 CSRF → H2/H3/H4 XSS 与转义~~ — 已完成
2. ~~H5 随机文件名、H6 URL 复检 + 超时 + 逐跳 curl~~ — 已完成
3. ~~M1 gzip、M4 限速顺序、M3 日志~~ — 已完成
4. ~~M5 token 轮换 + 2h TTL、M6 CSP、M7 CRUD~~ — 已完成；**M2 字体**留档
5. ~~L1–L7~~ — 全部已修（含 L4 核对）
6. ~~静态内联样式 class 化（M6b）~~ — 已完成；回归 73/0
7. ~~第五轮：H6 逐跳、CSRF 2h、toast class、style-src 收紧~~ — 已完成；回归 73/0

回归入口：`php /tmp/opencode/round2_test.php`（串行，勿并行双开）
