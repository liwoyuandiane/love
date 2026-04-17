# AGENTS.md

## 项目概述

这是一个 PHP + MySQL 情侣纪念网站（韩森宁与吕自长），包含前台展示和后台管理功能。

## 技术栈

- **后端**: PHP 8.x (原生，无框架)
- **数据库**: MySQL (PDO)
- **前端**: 原生 HTML5/CSS3/JavaScript (ES6+)
- **会话管理**: PHP Session
- **安全**: CSRF Token + BCrypt 密码哈希

## 项目结构

```
/workspace/
├── index.php              # 前台首页
├── admin.php             # 后台管理页面
├── router.php            # PHP内置服务器路由
├── config.php            # 配置入口
├── api/                   # RESTful API 接口
│   ├── anniversaries.php  # 纪念日 CRUD
│   ├── wishlists.php      # 愿望清单 CRUD
│   ├── explores.php        # 探索地点 CRUD
│   ├── photos.php          # 照片管理
│   ├── music.php           # 音乐设置
│   ├── couple-info.php     # 情侣信息
│   ├── admin-*.php         # 管理员认证
│   ├── data.php            # 获取所有数据
│   └── export/import.php   # 数据备份
├── includes/              # 核心模块
│   ├── BaseController.php  # 基础控制器 (OOP架构)
│   ├── Validator.php        # 输入验证器
│   ├── auth.php            # 认证、会话、密码
│   ├── csrf.php           # CSRF Token
│   ├── functions.php       # 公共函数
│   ├── logger.php         # 日志记录
│   └── crypter.php        # AES加密 (.env密码)
├── install/               # 安装向导
└── assets/                # 前端资源
```

## 架构规范

### API 设计模式
- 使用 `BaseController` 类，所有 API 控制器继承它
- 使用 `Validator` 类进行输入验证
- HTTP 方法语义: GET(读取) POST(创建) PUT(更新) DELETE(删除)
- 统一响应格式: `{ success: bool, data: any, message: string }`

### 新增 API 文件应遵循
```php
require_once __DIR__ . '/../includes/BaseController.php';
require_once __DIR__ . '/../includes/Validator.php';

class XxxController extends BaseController {
    private PDO $db;
    public function __construct() { $this->db = getDB(); }
    public function handle(): void {
        try {
            match($this->getMethod()) {
                'GET' => $this->index(),
                'POST' => $this->create(),
                // ...
                default => $this->error('不支持的方法', 'METHOD_NOT_ALLOWED', 405)
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

## 启动开发服务器

```bash
php -S localhost:3000 router.php
```

## 数据库

- **主机**: `mysql.zichang.eu.org:3306`
- **数据库**: `love1`
- **安装**: 删除 `.env` 后访问 `/install/`

## 安全特性

- SQL 预处理语句 (PDO)
- XSS 防护 (`escapeHtml()`, `escapeJs()`)
- CSRF 验证 (所有写操作)
- 密码 BCrypt 哈希
- 登录锁定 (5次失败 = 15分钟)
- Session HttpOnly + Secure (HTTPS)
- 文件上传 MIME 验证 (`finfo_file()`)

## 密码要求

- 最小长度: 8位
- 安装向导: `install/index.php`
- 默认管理员: admin / Admin@12345678

## 前端缓存

- localStorage key: `siteData`
- TTL: 5分钟 (300000ms)
- 后台同步: 60秒间隔
