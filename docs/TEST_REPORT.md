# 情侣纪念网站 · 本地运行与完整测试报告

- 日期：2026-09-24
- 环境：PHP 8.2.33 内置服务器（`router.php`）+ MariaDB 10.11.18
- 数据库：库 `love`，用户 `love` / `LoveApp2026!`（本地测试用）
- 启动：`php -S 0.0.0.0:8000 router.php`（常驻后台）
- 测试账号：`admin` / `admin@123`（本地测试用，2026-09-24 已改）
- 测试脚本：`/tmp/opencode/round2_test.php`（**73 用例，第五轮复跑**）

## 一、运行状态

| 项 | 结果 |
|----|------|
| 首页 `/` | 200 |
| 后台 `/admin.php` | 200 |
| 安装页 `/install/` | 302 → `/`（已有 `.env`） |
| `GET /api/data.php` | 200，数据结构完整 |
| `GET /api/health.php` | 200，db/cache/logs/uploads 全 ok |
| 建表 | 8 张表就绪 |
| CSP | `script-src 'self'` + `style-src 'self'`（均无 `unsafe-inline`），三处配置一致 |

## 二、测试结论

### 第五轮（CSRF 2h / H6 逐跳 / toast class / style-src 收紧）2026-09-24

**73 PASS / 0 FAIL。** 同套 73 用例串行复跑（exit 0）。

#### 本轮改动
- `includes/csrf.php`：session 记录 `csrf_token_time`，`TTL=7200`（2h）；`validate` 过期清除并拒绝；`generate` 过期自动换新
- `api/photos.php`：`get_headers` 合并 header 改为 curl `probeImageUrl`（`FOLLOWLOCATION=false` 逐跳 `isUrlSafe` + 最终 Content-Type 白名单 + 5s 超时 + 最多 3 跳 + 协议限定）
- `assets/js/utils.js`：`createToast` 改 class（`.toast-notification` + type + `.is-visible`）；`window.createToast` 全局导出
- `assets/css/style.css`：补 toast 规则与 `--success`/`--danger`；`main.js` 时间线 `--delay` 改 `setProperty`，粒子改 `createElement` + CSSOM
- `install/index.php` + 新建 `assets/css/install.css`：页内 `<style>` 外置
- 三处 CSP：`style-src` 去 `'unsafe-inline'`/`fonts.googleapis.com`；`font-src` 去 `fonts.gstatic.com`/`https:`（字体全自托管）

#### 静态终态
- PHP/HTML/JS 模板 `style="`：**0**
- `<style>` 块：**0**
- `style.display`：**0**
- 仅存 CSSOM（`cssText`/`setProperty`/`style.x=`），不受 `style-src` 限制

#### UI 断言（24 项，PASS）
style 属性清零、`<style>` 清零、`style.display` 清零、三处 CSP 无 `unsafe-inline`/`fonts.googleapis`、CSRF TTL、photos curl 逐跳、toast class 化、install.css 外置、`window.createToast` 导出、`--delay` setProperty、粒子 createElement、CSP 实测 `style-src 'self'`、无 onclick/onsubmit、lint 全量等

#### Lint
全量 `php -l` + 5 个 JS `node --check` 通过

### 第四轮（UI 静态内联样式 class 化收尾）2026-09-24

**73 PASS / 0 FAIL。** 与第三轮同套 73 用例串行复跑（exit 0）。

#### 本轮改动
- `install/index.php`：清零静态 `style="`（backBtn `margin-top`、successPage `display:none` 改页内 CSS + `is-hidden`）；`onsubmit` 属性移除，改为 `install.js` `submit` 防默认
- `install.js`：6 处 `style.display` → `classList`（adminSection 显隐、dbForm 隐藏、successPage 显示）
- `main.js`：cursor / audioErr `style.display` → `classList`（`is-hidden` / `is-visible`）；粒子 `cssText`、`--delay`、随机色等动态 style 保留
- `style.css`：补 `.audio-error.is-visible`、`.cursor.is-hidden`；`fadeInUp` 唯一定义（20px）
- `admin.php` / `admin.js`：上一轮已清零静态内联与 `style.display`（本轮复核仍为 0）

#### 静态 `style="` 终态
- PHP 模板（`index.php` / `admin.php` / `install/index.php`）：**0**
- 项目级仅存 `main.js` 动态模板 3 处（`--delay`、粒子随机 size/color），行为保留

#### UI 断言（25 项，PASS）
fadeInUp 唯一、focus-visible、prefers-reduced-motion、is-hidden、upload has-file、version-dot inline-block、love-heart/section-header-offset/footer-icp/lightbox-close+nav、admin/install/index 无静态 `style="`、全 JS 无 `style.display`、span-full/backup-card/info-banner、audio-error/cursor 显隐 class、L4 error_log 等

#### Lint
全量 `php -l` + 5 个 JS `node --check` 通过；无 `router.js`（不存在）

### 第三轮（CSP 去 inline + 修复闭环回归）2026-09-24

**73 PASS / 0 FAIL。** 含第二轮全部 63 用例 + 10 项第三轮断言。

#### 第三轮新增断言
- CSP `script-src` 无 `unsafe-inline`（响应头实测）
- 后台无 `onclick=`、无内联 `<script>` 块、引 `boot.js`
- 首页无 `onclick=` / `onerror=`、无内联 `<script>` 块
- install：已有 `.env` 时 POST → 302 跳转不执行安装；源码含 `confirm_drop`/`hasTables` 门；库存活
- `Cache::clear('api_data')` 精确删 `api_data_{md5}.cache`，同前缀 decoy `api_data_DEADBEEF.cache` 不误删；空串清全部
- 会话过期：改 session `last_activity` 后 API 返回 `SESSION_EXPIRED`/`UNAUTHORIZED`，或 `consumeSessionExpiredFlag()` 单测通过

#### 本轮根因修复
- `api/photos.php:145` `get_headers()` 无超时 → 外链慢时卡死单线程 `php -S`，导致后续 `settings.php` curl 20s 超时。已加 `stream_context_create(['http'=>['timeout'=>5,'max_redirects'=>3]])`。
- 并行跑两套回归会互相打挂内置服务器（单进程）；**必须串行跑**。

### 第二轮（用户真实备份回归）

**63 PASS / 0 FAIL。** 备份文件：`love-backup-2026-09-24-1.json`（version 3.2.0）。

#### 认证与 CSRF
- 错误密码 → **401**；空请求 → **400** VALIDATION_ERROR（校验在限速之前）
- 登录成功返回 `csrf_token`；登出带轮换 token → 200
- 登出无 token → 403；未登录登出 → 401
- 无会话 CRUD → 401/403；无 CSRF 创建 → 403

#### 路径 id CRUD（前端 body 不带 id）
- 纪念日 / 心愿 / 探索 / 照片：路径 id `PUT` + 空 body `DELETE` 全部成功
- 心愿 `POST /api/wishlists/{id}/toggle` 成功
- 照片 multipart 上传成功；路径改/删成功
- 照片 URL 创建：不可达 URL → 400（合理拒绝，现带 5s 超时）

#### 设置 / 公开 API
- 站点设置 PUT/GET、情侣信息 PUT、音乐配置 PUT → 200
- 公开 data API 反映情侣信息

#### 导出 / 导入
- 导出含 `version` + `data`
- multipart 导入用户备份 → 成功
- raw JSON 导入（前端 admin.js 路径）→ 成功
- 导入后校验：情侣 韩/吕/2018-06-16、愿望「一起去爬泰山」、探索「河南宝泉」、照片外链、音乐「特别的人/1820314965」、ICP `京ICP备2026015291号`、纪念日为空（备份如此）
- 再导出含韩/吕数据（闭环）
- 无效导入结构拒绝；错误 CSRF 导入 → 403

#### 安全
- 路径穿越 4 条（含 `--path-as-is` / `CURLOPT_PATH_AS_IS`）→ 404，无泄漏
- escapeHtml 等价单测通过
- X-Frame-Options / X-Content-Type-Options / CSP 存在；HTTP 下不下发 HSTS
- sync 保活 200；admin-status 已登录；审计日志可读；migrate-role 非 500

#### 测试环境注意
- 内置服务器 keep-alive 并发易出现 curl `0 bytes` 超时；测试客户端 `Connection: close` + `FRESH_CONNECT`。
- **禁止并行跑两套 `round2_test.php`**（单线程互相阻塞）。
- `php -S` 无 gzip；无独立 `/login.php`（环境限制，非缺陷）。

### 第一轮（54/54，已并入）

路径穿越、CSRF 登出、日志轮转、限速顺序、escapeHtml 等主用例均通过；问题清单见 CODE_REVIEW。

## 三、第三轮修复

| 严重度 | 问题 | 修复 |
|--------|------|------|
| 高 | 全项目 inline script / onclick 依赖导致 CSP 无法收紧 | 抽出 `boot.js`、`install.js`；admin/main 改 `data-action` 委托；三处 CSP `script-src 'self'` |
| 中 | `Cache::clear('api_data')` glob 前缀误删 | `includes/cache.php` 空/`*` 清全部，非空精确删 `getFilePath($prefix)` |
| 中 | `audit-logs` 全量 `file()` 读内存 | `fgets` 流式两遍扫描分页 |
| 中 | 会话超时突兀登出 | `session_expired` 标志 → API `SESSION_EXPIRED` → boot.js 提示；`consumeSessionExpiredFlag()` |
| 中 | CRUD 三端点重复 | `includes/CrudController.php` 抽象基类，anniversaries/wishlists/explores 薄子类 |
| 中 | 安装重装 DROP 无二次确认 | 有表数据须 `confirm_drop=1`；已有 `.env` GET/POST 均 302 |
| 中 | 外链图片校验无超时拖死内置服务器 | `get_headers` 5s 超时 + 限重定向（本轮实测根因） |

## 三之三、第五轮修复（安全与 CSP 收紧）

| 严重度 | 问题 | 修复 |
|--------|------|------|
| 高 | H6 多跳 Content-Type 基于合并 header 可宽松 | curl `FOLLOWLOCATION=false` 逐跳复检 Location + 最终 CT 白名单 |
| 中 | CSRF token 永不过期 | session `csrf_token_time`，TTL 2h |
| 低 | toast 内联 cssText + boot 回落未导出 | class 化 + `window.createToast` 导出 |
| 低 | `style-src 'unsafe-inline'` 与外部字体域 | 去 `unsafe-inline`/`fonts.googleapis`；install `<style>` 外置；粒子/`--delay` 改 CSSOM |

## 三之二、第四轮修复（UI class 化）

| 严重度 | 问题 | 修复 |
|--------|------|------|
| 低 | PHP 模板静态 `style="` 依赖 `style-src unsafe-inline` | install/index/admin 全部改 class；仅存 main.js 动态 style |
| 低 | install/admin/main `style.display` 与 CSS 双轨显隐 | 统一 `is-hidden` / `is-visible` / 页内 `.show` |
| 低 | `fadeInUp` 定义重复（30px/20px 两份） | 合并为唯一 20px 定义 |
| 低 | 空态/焦点态/响应式/上传状态类不完整 | admin.css + style.css 补齐（前序 UI 优化，本轮复核） |

## 四、第二轮新修（摘要）

| 严重度 | 问题 | 修复 |
|--------|------|------|
| 高 | `api/login.php` 密码错误 200；空请求无 400 | 补 401/400 |
| 高 | 前端路径 id PUT/DELETE 失败 | `resolveId()` + 空 body `[]` |

## 五、第一轮已修（摘要）

| 严重度 | 问题 | 修复 |
|--------|------|------|
| 高 | `admin-logout` 未 require functions | 补 require |
| 高 | `router.php` 静态路径穿越 | 拒绝 `..` + realpath 白名单 |
| 高 | H1–H6 | 见 CODE_REVIEW |

## 六、清单内仍未修 / 留档

| 级别 | 项 | 状态 |
|------|-----|------|
| 中 | 字体文件仍 ~2.3MB（400+700）；`noto-sans-sc-500.woff2` 未被 CSS 引用 | M2：按不删约束留档，不删文件 |
| 低 | 日志目录写失败 | L4：`includes/logger.php` 已 `error_log` 降级 |
| 低 | ~~`style-src` 暂留 `'unsafe-inline'`~~ | 第五轮已收紧为 `style-src 'self'`；三处一致 |
| — | ~~CSRF 无 TTL / H6 多跳 CT / toast cssText~~ | 第五轮均已修 |

已闭环：CSP script-src + style-src、CRUD 收敛、Cache::clear 精确删、audit-logs 流式、SESSION_EXPIRED、install confirm_drop、H6 逐跳 curl、静态内联样式 class 化、CSRF 2h、toast class 化。

## 七、测试数据

- 第一轮测试数据已清理，`couple_info` 曾恢复演示值。
- 第二轮/第三轮/第四轮/第五轮已导入用户真实备份（韩/吕等），当前库内容以该备份导入后状态为准。
- settings 回归写入 `site_name=回归站` 后，导入会再次覆盖为备份 ICP 等；以最终导入断言为准。

详细问题清单见：`/workspace/docs/CODE_REVIEW.md`
