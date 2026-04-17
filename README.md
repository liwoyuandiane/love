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

- PHP 8.0+
- MySQL 5.7+ 或 8.0+
- Web 服务器（Apache/Nginx）
- 支持 PDO 和 pdo_mysql 扩展

## 目录结构

```
/                          # 前台入口
├── index.php              # 前台首页
├── config.php             # 配置引入
├── .env.example          # 环境变量模板
├── assets/
│   ├── css/style.css     # 前台样式
│   ├── js/app.js         # 前台脚本
│   └── images/           # 本地上传图片目录
├── admin/                 # 后台管理
│   ├── index.php         # 登录页
│   ├── dashboard.php     # 仪表盘
│   ├── couple.php        # 情侣信息
│   ├── anniversaries.php # 纪念日
│   ├── wishlists.php     # 愿望清单
│   ├── explores.php      # 探索地点
│   ├── memories.php      # 记忆墙
│   ├── settings.php      # 设置
│   ├── export.php        # 数据导出
│   ├── import.php        # 数据导入
│   └── includes/         # 公共模块
├── install/              # 安装向导
├── sql/schema.sql       # 数据库建表 SQL
└── README.md
```

## 安装

### 方式一：1Panel PHP 应用部署（推荐）

#### 步骤 1：创建 MySQL 数据库

如果你使用远程 MySQL 或 1Panel 上的 MySQL 容器：

1. 登录 MySQL：`mysql -u root -p`
2. 创建数据库：

```sql
CREATE DATABASE love_couple CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### 步骤 2：在 1Panel 中创建 PHP 应用

1. 登录 1Panel 面板
2. 进入「应用商店」→ 搜索「PHP」
3. 选择「PHP」应用，点击「安装」
4. 配置：
   - 应用名称：`love-couple`
   - 容器名称：`love-couple`
   - 端口：选择自定义端口（如 8080）
   - 网站根目录：`/opt/love-couple`（或其他路径）
   - PHP 版本：选择 8.x

5. 点击「确认」等待创建完成

#### 步骤 3：上传代码

1. 将项目代码打包上传到 `/opt/love-couple` 目录
2. 解压并确保目录结构正确

#### 步骤 4：配置网站

1. 在 1Panel 中进入「网站」→「PHP站点」
2. 点击「创建」：
   - 主域名：填写你的域名
   - 备注：情侣纪念网站
   - 应用：选择刚才创建的 PHP 应用
   - 伪静态规则：选择 `WordPress` 或自定义

#### 步骤 5：访问安装向导

1. 打开浏览器访问 `http://你的域名/install`
2. 填写数据库信息：
   - 数据库主机：远程 MySQL 的 IP:端口
   - 数据库名称：`love_couple`
   - 数据库用户名：你的数据库用户名
   - 数据库密码：你的数据库密码
3. 填写管理员账号信息
4. 点击「开始安装」

#### 步骤 6：访问后台

1. 安装完成后访问 `http://你的域名/admin`
2. 使用刚才创建的管理员账号登录
3. 开始管理你的情侣网站

---

### 方式二：传统部署

#### 1. 配置 Web 服务器

**Nginx 配置示例：**

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/love-couple;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
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

**Apache (.htaccess)：**

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?/$1 [L]
```

#### 2. 访问安装向导

访问 `http://your-domain.com/install` 完成安装。

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

### Q: 安装页面提示"数据库连接失败"

A: 请检查：
1. 数据库主机地址是否正确
2. 数据库用户名和密码是否正确
3. 数据库是否允许远程连接
4. 防火墙是否开放了数据库端口

### Q: 图片无法上传

A: 请检查：
1. `assets/images` 目录是否有写入权限
2. PHP 的 `upload_max_filesize` 配置
3. Nginx 的 `client_max_body_size` 配置

### Q: 音乐无法播放

A: 请检查：
1. 音乐 URL 是否可访问
2. 浏览器控制台是否有错误信息
3. 某些浏览器需要用户交互后才能播放音频

---

## 更新日志

### v1.0.0
- 全新重构版本
- PHP + MySQL 架构
- 完整的后台管理
- 白天/黑夜模式
- 数据导入导出

---

## 许可证

MIT License

---

## 致谢

- [Font Awesome](https://fontawesome.com/) - 图标库
- 设计灵感来自众多优秀的情侣纪念网站