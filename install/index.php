<?php
/**
 * 安装向导
 */

// .env 路径统一解析（Docker 数据目录优先，见 env.php）：安装成功后配置会持久化到宿主机 love/ 目录
require_once __DIR__ . '/../env.php';
require_once __DIR__ . '/../includes/assets.php';
$envFile = resolveEnvFile();
if (file_exists($envFile)) {
    header('Location: /');
    exit;
}

/**
 * 校验数据库标识符（数据库名/用户名），只允许安全字符
 * 防止通过 DROP/CREATE DATABASE 拼接注入 SQL
 */
function sanitizeIdentifier(string $value): ?string {
    $value = trim($value);
    if ($value === '' || strlen($value) > 64) {
        return null;
    }
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $value)) {
        return null;
    }
    return $value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'test_connection') {
        header('Content-Type: application/json');
        $dbHost = trim($_POST['db_host'] ?? '');
        $dbPort = trim($_POST['db_port'] ?? '3306');
        $dbName = trim($_POST['db_name'] ?? '');
        $dbUser = trim($_POST['db_user'] ?? '');
        $dbPass = trim($_POST['db_pass'] ?? '');

        if (empty($dbHost) || empty($dbName) || empty($dbUser)) {
            echo json_encode(['success' => false, 'message' => '请填写完整的数据库信息']);
            exit;
        }
        if (sanitizeIdentifier($dbName) === null || sanitizeIdentifier($dbUser) === null) {
            echo json_encode(['success' => false, 'message' => '数据库名称/用户名只能包含字母、数字、下划线或中划线']);
            exit;
        }
        if (!ctype_digit($dbPort) || intval($dbPort) < 1 || intval($dbPort) > 65535) {
            echo json_encode(['success' => false, 'message' => '端口号无效']);
            exit;
        }

        try {
            $dsn = "mysql:host=$dbHost;port=$dbPort;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);

            $stmt = $pdo->prepare("SHOW DATABASES LIKE ?");
            $stmt->execute([$dbName]);
            $exists = $stmt->fetch() !== false;

            if ($exists) {
                echo json_encode([
                    'success' => true,
                    'message' => '连接成功！注意：继续安装将清空该数据库所有数据！',
                    'warning' => true,
                    'db_exists' => true
                ]);
            } else {
                echo json_encode(['success' => true, 'message' => '连接成功，数据库不存在，将自动创建', 'db_exists' => false]);
            }
        } catch (PDOException $e) {
            // 不向客户端暴露具体连接错误细节，仅提示通用信息
            echo json_encode(['success' => false, 'message' => '数据库连接失败，请检查主机、端口、账号密码是否正确']);
        }
        exit;
    }

    if ($_POST['action'] === 'install') {
        header('Content-Type: application/json');
        $dbHost = trim($_POST['db_host'] ?? '');
        $dbPort = trim($_POST['db_port'] ?? '3306');
        $dbName = trim($_POST['db_name'] ?? '');
        $dbUser = trim($_POST['db_user'] ?? '');
        $dbPass = trim($_POST['db_pass'] ?? '');
        $adminUser = trim($_POST['admin_user'] ?? '');
        $adminPass = trim($_POST['admin_pass'] ?? '');

        if (empty($dbHost) || empty($dbName) || empty($dbUser) || empty($adminUser) || empty($adminPass)) {
            echo json_encode(['success' => false, 'message' => '请填写所有必填项']);
            exit;
        }
        // 标识符白名单校验（数据库名/用户名/管理员用户名），防止 SQL 注入
        if (sanitizeIdentifier($dbName) === null || sanitizeIdentifier($dbUser) === null) {
            echo json_encode(['success' => false, 'message' => '数据库名称/用户名只能包含字母、数字、下划线或中划线']);
            exit;
        }
        $adminUser = sanitizeIdentifier($adminUser);
        if ($adminUser === null) {
            echo json_encode(['success' => false, 'message' => '管理员用户名只能包含字母、数字、下划线或中划线']);
            exit;
        }
        if (!ctype_digit($dbPort) || intval($dbPort) < 1 || intval($dbPort) > 65535) {
            echo json_encode(['success' => false, 'message' => '端口号无效']);
            exit;
        }
        // 密码强度：8-72 位（bcrypt 上限 72 字节），需包含字母和数字
        if (strlen($adminPass) < 8 || strlen($adminPass) > 72) {
            echo json_encode(['success' => false, 'message' => '密码长度需为 8-72 位']);
            exit;
        }
        if (!preg_match('/[A-Za-z]/', $adminPass) || !preg_match('/[0-9]/', $adminPass)) {
            echo json_encode(['success' => false, 'message' => '密码需包含字母和数字']);
            exit;
        }
        if (strlen($dbPass) > 256 || strlen($dbHost) > 200) {
            echo json_encode(['success' => false, 'message' => '输入内容过长']);
            exit;
        }

        try {
            $dsn = "mysql:host=$dbHost;port=$dbPort;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // 已有表数据时必须显式 confirm_drop=1，防止绕过前端确认直接 DROP
            $hasTables = false;
            try {
                $stmt = $pdo->query("SHOW TABLES");
                $hasTables = $stmt !== false && $stmt->fetch() !== false;
            } catch (PDOException $e) {
                $hasTables = false;
            }
            if ($hasTables && (($_POST['confirm_drop'] ?? '') !== '1')) {
                echo json_encode(['success' => false, 'message' => '目标数据库已有数据，需勾选确认清空（confirm_drop=1）']);
                exit;
            }

            $pdo->exec("DROP DATABASE IF EXISTS `$dbName`");
            $pdo->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");

            $pdo->exec("CREATE TABLE `couple_info` (
                `id` INT PRIMARY KEY AUTO_INCREMENT,
                `name1` VARCHAR(50) NOT NULL DEFAULT '',
                `name2` VARCHAR(50) NOT NULL DEFAULT '',
                `anniversary` DATE NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE `anniversaries` (
                `id` INT PRIMARY KEY AUTO_INCREMENT,
                `title` VARCHAR(200) NOT NULL,
                `date` DATE,
                `description` TEXT,
                `type` ENUM('anniversary', 'birthday', 'wedding', 'other') DEFAULT 'anniversary',
                `reminder_days` INT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_date` (`date`),
                INDEX `idx_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE `wishlists` (
                `id` INT PRIMARY KEY AUTO_INCREMENT,
                `title` VARCHAR(200) NOT NULL,
                `description` TEXT,
                `date` DATE,
                `completed` TINYINT(1) DEFAULT 0,
                `completed_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE `explores` (
                `id` INT PRIMARY KEY AUTO_INCREMENT,
                `title` VARCHAR(200) NOT NULL,
                `description` TEXT,
                `date` DATE,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE `photos` (
                `id` INT PRIMARY KEY AUTO_INCREMENT,
                `url` VARCHAR(500) NOT NULL,
                `caption` VARCHAR(200),
                `source_type` ENUM('local', 'url') NOT NULL DEFAULT 'url',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE `music` (
                `id` INT PRIMARY KEY DEFAULT 1,
                `source_type` ENUM('local', 'url') NOT NULL DEFAULT 'url',
                `source_url` VARCHAR(500) NOT NULL,
                `backup_url` VARCHAR(500),
                `title` VARCHAR(200),
                `artist` VARCHAR(100),
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE `admin_users` (
                `id` INT PRIMARY KEY AUTO_INCREMENT,
                `username` VARCHAR(50) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `role` VARCHAR(20) NOT NULL DEFAULT 'user',
                `login_attempts` INT DEFAULT 0,
                `locked_until` TIMESTAMP NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE `site_settings` (
                `id` INT PRIMARY KEY DEFAULT 1,
                `icp_code` VARCHAR(100) NOT NULL DEFAULT '',
                `police_record_code` VARCHAR(100) NOT NULL DEFAULT '',
                `site_name` VARCHAR(200) NOT NULL DEFAULT '',
                `timezone` VARCHAR(50) NOT NULL DEFAULT 'Asia/Shanghai',
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("INSERT INTO `site_settings` (`id`) VALUES (1)");

            $pdo->exec("INSERT INTO `couple_info` (`id`, `name1`, `name2`, `anniversary`) VALUES (1, '小红', '小明', '2018-06-16')");
            $pdo->exec("INSERT INTO `music` (`id`, `source_type`, `source_url`, `backup_url`, `title`, `artist`) VALUES (1, 'url', 'https://music.163.com/song/media/outer/url?id=2147248772.mp3', 'http://music.163.com/song/media/outer/url?id=2147248772.mp3', '特别的人', '方大同')");

            $hashedPassword = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("INSERT INTO `admin_users` (`username`, `password`, `role`) VALUES (?, ?, 'admin')");
            $stmt->execute([$adminUser, $hashedPassword]);

            // 写入 .env 时移除换行符（防止注入环境变量）
            $dbPass = preg_replace('/[\r\n]/', '', $dbPass);
            $envContent = "DB_HOST=$dbHost\nDB_PORT=$dbPort\nDB_NAME=$dbName\nDB_USER=$dbUser\nDB_PASS=$dbPass\n";

            if (file_put_contents($envFile, $envContent)) {
                // 敏感配置仅所有者可读写
                @chmod($envFile, 0600);
                echo json_encode(['success' => true, 'message' => '安装成功，配置已写入 ' . basename(dirname($envFile)) . '/.env']);
            } else {
                echo json_encode(['success' => false, 'message' => '配置文件写入失败']);
            }
        } catch (PDOException $e) {
            // 不向客户端暴露底层错误细节
            echo json_encode(['success' => false, 'message' => '数据库错误，请检查数据库配置后重试']);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装向导 - 情侣纪念网站</title>
    <link rel="stylesheet" href="<?php echo asset('assets/css/fonts.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('assets/css/fontawesome.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('assets/css/install.css'); ?>">
</head>
<body>
    <form class="install-box" id="dbForm">
        <div class="icon"><i class="fas fa-heart"></i></div>
        <h1>安装向导</h1>
        <p class="subtitle">情侣纪念网站</p>

        <div class="form-group">
            <label>数据库主机 *</label>
            <input type="text" id="dbHost" placeholder="localhost 或 IP" required maxlength="200">
        </div>
        <div class="form-group">
            <label>数据库端口</label>
            <input type="number" id="dbPort" value="3306" placeholder="3306" min="1" max="65535">
        </div>
        <div class="form-group">
            <label>数据库名称 *</label>
            <input type="text" id="dbName" placeholder="仅字母数字下划线中划线" required maxlength="64" pattern="[A-Za-z0-9_\-]+">
        </div>
        <div class="form-group">
            <label>数据库用户名 *</label>
            <input type="text" id="dbUser" placeholder="仅字母数字下划线中划线" required maxlength="64" pattern="[A-Za-z0-9_\-]+" autocomplete="username">
        </div>
        <div class="form-group">
            <label>数据库密码</label>
            <input type="password" id="dbPass" placeholder="密码" autocomplete="new-password">
        </div>

        <button type="button" class="btn btn-outline" id="testBtn">
            <i class="fas fa-plug"></i> 测试连接
        </button>
        <div class="status" id="testStatus"></div>

        <div class="warning-box" id="warningBox">
            <i class="fas fa-exclamation-triangle"></i>
            <span id="warningText"></span>
        </div>

        <div class="admin-section is-hidden" id="adminSection">
            <h3><i class="fas fa-user-shield"></i> 管理员账号</h3>
            <div class="form-group">
                <label>用户名 *</label>
                <input type="text" id="adminUser" placeholder="登录用户名" required maxlength="50" pattern="[A-Za-z0-9_\-]+">
            </div>
            <div class="form-group">
                <label>密码 *</label>
                <input type="password" id="adminPass" placeholder="8-72位，含字母和数字" minlength="8" maxlength="72" required autocomplete="new-password">
            </div>

            <button type="button" class="btn" id="installBtn">
                <i class="fas fa-rocket"></i> 开始安装
            </button>
            <button type="button" class="btn btn-outline" id="backBtn">
                <i class="fas fa-arrow-left"></i> 返回
            </button>
        </div>
    </form>

    <div class="install-box success-page is-hidden" id="successPage">
        <div class="icon"><i class="fas fa-check"></i></div>
        <h2>安装成功</h2>
        <p>恭喜！您的情侣纪念网站已安装完成。</p>
        <div class="success-links">
            <a href="/" class="btn-home">
                <i class="fas fa-home"></i> 进入前台
            </a>
            <a href="/admin.php" class="btn-admin">
                <i class="fas fa-cog"></i> 进入后台
            </a>
        </div>
    </div>

    <script src="<?php echo asset('assets/js/install.js'); ?>"></script>
</body>
</html>