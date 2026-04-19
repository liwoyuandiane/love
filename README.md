# 情侣纪念网站

一个优雅的 PHP + MySQL 情侣纪念网站，支持纪念日、愿望清单、探索地点、记忆墙等功能。

## 功能特性

- 浪漫温馨的界面设计（白天/黑夜模式）
- 花瓣飘落动画 + 柔光效果
- 纪念日计时器（精确到秒）
- 愿望清单管理
- 探索地点记录
- 记忆墙（照片墙）
- 背景音乐播放
- 数据导入/导出（JSON 格式）
- 完整的后台管理
- 安全防护（CSRF、SQL 注入防护、登录锁定）

## 环境要求

- PHP 8.0+ (推荐 8.2 或 8.5)
- MySQL 5.7+ 或 8.0+
- Web 服务器（Apache/Nginx）
- **必须安装 `pdo_mysql` 扩展**

## 目录结构

```
/                          # 项目根目录
├── index.php              # 前台首页入口
├── admin.php              # 后台管理入口
├── router.php             # API 路由（核心）
├── config.php             # 配置文件引入
├── .env                   # 环境变量（安装后生成）
├── .htaccess              # Apache 配置（自动加载）
├── nginx.conf             # Nginx 配置（手动加载）
├── assets/
│   ├── css/              # 样式文件
│   ├── js/               # JavaScript 文件
│   ├── fonts/            # 字体文件
│   ├── images/           # 图片资源
│   ├── uploads/          # 上传文件目录
│   └── manifest.json     # PWA 清单
├── api/                   # API 接口
│   ├── data.php          # 数据 API
│   ├── anniversaries.php # 纪念日 API
│   ├── wishlists*.php    # 愿望清单 API
│   ├── explores.php      # 探索地点 API
│   ├── photos.php        # 照片 API
│   ├── music.php         # 音乐 API
│   ├── login.php         # 登录认证 API
│   └── ...
├── includes/              # 核心类库
│   ├── auth.php          # 认证模块
│   ├── BaseController.php
│   ├── Validator.php
│   ├── functions.php
│   └── ...
├── install/              # 安装向导
│   └── index.php
├── cache/                # 缓存目录
├── logs/                 # 日志目录
└── README.md
```

## 安装

### 方式一：1Panel 部署（推荐）

#### 步骤 1：上传代码

1. 将项目代码上传到 1Panel 网站目录
2. 确保目录结构包含 `router.php`、`index.php`、`admin.php` 等文件

#### 步骤 2：配置伪静态

**方法 A：直接配置（推荐）**

1. 在 1Panel 中找到你的网站 → **设置** → **伪静态**
2. 选择「自定义」，添加以下配置：

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

**方法 B：包含项目中的 nginx.conf**

在伪静态中添加一行：
```nginx
include /你的网站目录/nginx.conf;
```

#### 步骤 3：确认 PHP 版本

确保 1Panel 中网站的 PHP 版本为 8.x（推荐 8.2 或 8.5）。

> **注意**：如果报 502 错误，需要修改 `fastcgi_pass` 的 socket 路径：
> - PHP 7.4: `/tmp/php-cgi-74.sock`
> - PHP 8.0: `/tmp/php-cgi-80.sock`
> - PHP 8.2: `/tmp/php-cgi-82.sock`
> - PHP 8.5: `/tmp/php-cgi-85.sock`
>
> 如果都不行，在 1Panel PHP 设置中查看实际的 socket 路径。

#### 步骤 4：访问安装向导

1. 打开浏览器访问 `http://你的域名/install/`
2. 填写数据库信息和管理员账号
3. 点击「开始安装」

#### 步骤 5：访问后台

1. 安装完成后访问 `http://你的域名/admin.php`
2. 使用创建的管理员账号登录

---

### 方式二：传统部署

#### 1. 配置 Web 服务器

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

    location ~ /\.ht {
        deny all;
    }
}
```

**Apache**：`.htaccess` 文件已在项目中，使用 `RewriteBase /` 并支持所有路由。

#### 2. 访问安装向导

访问 `http://your-domain.com/install/` 完成安装。

---

## 使用说明

### 后台管理

- 地址：`/admin`
- 默认账号：在安装时设置

### 功能模块

| 模块 | 说明 |
|------|------|
| 情侣信息 | 设置两个人的名字、头像、在一起纪念日 |
| 纪念日 | 添加和管理重要日期 |
| 愿望清单 | 记录想要一起实现的愿望 |
| 探索地点 | 标记想去的地方 |
| 记忆墙 | 上传和管理照片 |
| 设置 | 配置音乐 URL、网站标题等 |
| 导入导出 | 备份和恢复数据 |

### 数据导入导出

1. 登录后台
2. 点击左侧菜单「导出数据」下载 JSON 备份
3. 需要恢复时，点击「导入数据」上传备份文件

**注意**：导入操作会覆盖现有数据，请谨慎操作。

### 主题切换

- **自动模式**：白天（6:00-18:00）和黑夜自动切换
- **手动模式**：点击右上角太阳/月亮图标切换

### 上传图片

两种方式：

1. **本地上传**：在「记忆墙」页面直接上传图片文件
2. **外部 URL**：在图片输入框填入外部图片链接

---

## 安全设置

### 修改默认密码

安装后请立即修改管理员密码。

### 文件权限

```bash
# 设置目录权限
chmod 755 /path/to/love-couple

# 设置文件权限
chmod 644 /path/to/love-couple/*.php
chmod 644 /path/to/love-couple/.env

# 确保上传目录可写
chmod 755 /path/to/love-couple/assets/images
```

### 目录保护

`.env` 文件包含敏感信息，确保 Web 服务器禁止直接访问：

```nginx
location ~ /\.env {
    deny all;
}
```

---

## 常见问题

### Q: 安装页面提示"could not find driver"

A: PHP 未安装 `pdo_mysql` 扩展。在 1Panel 中：
1. 找到 PHP 应用 → **设置** → **扩展**
2. 安装 `pdo` 和 `pdo_mysql` 扩展
3. 重启 PHP 服务

### Q: 安装页面提示"数据库连接失败"

A: 请检查：
1. 数据库主机地址是否正确
2. 数据库用户名和密码是否正确
3. 数据库是否允许远程连接
4. 防火墙是否开放了数据库端口

### Q: 安装完成后前台/后台显示 404

A: Web 服务器未正确配置路由。需要配置伪静态规则让所有请求指向 `router.php`：
- Nginx：使用上面提供的 `try_files $uri $uri/ /router.php?$query_string;`
- Apache：项目已自带 `.htaccess`，确保 AllowOverride 开启

### Q: 图片无法上传

A: 请检查：
1. `assets/uploads` 目录是否有写入权限
2. PHP 的 `upload_max_filesize` 配置
3. Nginx 的 `client_max_body_size` 配置

### Q: 音乐无法播放

A: 请检查：
1. 音乐 URL 是否可访问
2. 浏览器控制台是否有错误信息
3. 某些浏览器需要用户交互后才能播放音频

---

## 更新日志

### v2.0.3
- 修复计时器时区问题，确保时分秒显示正确
- 在 health API 中添加 PHP 配置检测

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
- 全新重构版本
- PHP + MySQL 架构
- 完整的后台管理
- 白天/黑夜模式
- 数据导入导出
- 增强的安全防护
- 登录锁定机制
- CSRF 令牌保护

---

## 许可证

MIT License

---

## 致谢

- [Font Awesome](https://fontawesome.com/) - 图标库
- 设计灵感来自众多优秀的情侣纪念网站