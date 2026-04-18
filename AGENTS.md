# AGENTS.md

PHP + MySQL 情侣纪念网站。

## 启动

```bash
php -S localhost:3000 router.php
```

## 架构

- **入口**: `index.php` (前台), `admin.php` (后台), `router.php` (路由)
- **API**: `api/*.php` — RESTful 接口
  - 标准: `/api/{resource}` → `api/{resource}.php`
  - 带动作: `/api/{resource}/{id}/{action}` → `api/{resource}-{action}.php`
- **路由规则** (`router.php`):
  - `/install/` 或 `/install` → 安装向导
  - `/admin.php` 或 `/admin` → 后台入口
  - `/` → 前台首页
- **核心**: `includes/BaseController.php` (所有 API 控制器继承), `includes/Validator.php`, `includes/functions.php`

## API 设计模式

```php
require_once __DIR__ . '/../includes/BaseController.php';
require_once __DIR__ . '/../includes/Validator.php';

class XxxController extends BaseController {
    private PDO $db;
    public function __construct() { $this->db = getDB(); }
    public function handle(): void {
        try {
            match($this->getMethod()) {
                'GET'    => $this->index(),
                'POST'   => $this->create(),
                'PUT'    => $this->update(),
                'DELETE' => $this->delete(),
                default  => $this->error('不支持的方法', 'METHOD_NOT_ALLOWED', 405)
            };
        } catch (ValidationException $e) {
            $this->error($e->getMessage(), 'VALIDATION_ERROR');
        } catch (Exception $e) {
            Logger::error('Xxx API error: ' . $e->getMessage());
            $this->serverError();
        }
    }
}
$controller = new XxxController();
$controller->handle();
```

## 数据库

- 主机: `mysql.zichang.eu.org:3306`
- 库名: `love1`
- 安装: 删除 `.env` 后访问 `/install/`

## 安全

- SQL 预处理 (PDO)
- XSS: `escapeHtml()`, `escapeJs()`
- CSRF: 所有写操作需 `X-CSRF-TOKEN` header
- 密码: BCrypt 哈希，登录锁定 (5次失败=15分钟)
- Session: HttpOnly + Secure (HTTPS)

## 密码要求

最小 8 位。默认管理员: `admin / Admin@12345678`

## 前端缓存

- localStorage key: `siteData`
- TTL: 5分钟，后台 60 秒同步

## 后台性能优化

**重要**：CRUD 操作后不要调用 `loadAllData()` 重新加载全部数据。使用 `admin.js` 中的本地状态更新函数：

- `addToLocalList(type, item)` — 添加
- `updateInLocalList(type, id, updates)` — 更新
- `removeFromLocalList(type, id)` — 删除
- `updateWishlistItem(id, updates)` — 愿望清单状态切换

`loadAllData()` 只在初始化、切换 Tab、同步时调用。

## 前台数据公开

`/api/data` 是公开 API，**不需要**登录认证。前台页面应该可以无认证访问。

## 登录后处理

`handleLogin` 成功后必须设置：
- `isAdmin = true`
- `filterTabsByRole()`
- `adminContainer.style.display = 'block'`

## 导入/导出

- 导出: POST `/api/export`
- 导入: POST `/api/import`，JSON 结构需包含 `data` 和 `version` 字段
- 使用 `csrfFetch()` 发送请求

## 账号管理

只有修改当前登录用户的功能（用户名和密码），无添加/删除管理员。

## 部署配置

### 1Panel/Nginx 部署

在 1Panel 网站配置中添加伪静态规则（或 include nginx.conf）：

```nginx
location / {
    try_files $uri $uri/ /router.php?$query_string;
}

location ~ \.php$ {
    try_files $uri =404;
    fastcgi_pass unix:/tmp/php-cgi-85.sock;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

注意：`fastcgi_pass` 路径根据 PHP 版本调整：
- PHP 7.4: `/tmp/php-cgi-74.sock`
- PHP 8.0: `/tmp/php-cgi-80.sock`
- PHP 8.2: `/tmp/php-cgi-82.sock`
- PHP 8.5: `/tmp/php-cgi-85.sock`

### Apache 部署

项目自带 `.htaccess`，Apache 会自动加载，无需额外配置。

### PHP 要求

必须安装 `pdo_mysql` 扩展，否则安装时会报 "could not find driver" 错误。
