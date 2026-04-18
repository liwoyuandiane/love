<?php
/**
 * 安装向导
 */

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    header('Location: /');
    exit;
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
            echo json_encode(['success' => false, 'message' => '连接失败：' . $e->getMessage()]);
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

        $dbHost = preg_replace('/[\r\n]/', '', $dbHost);
        $dbName = preg_replace('/[\r\n]/', '', $dbName);
        $dbUser = preg_replace('/[\r\n]/', '', $dbUser);
        $adminUser = preg_replace('/[\r\n]/', '', $adminUser);

        if (empty($dbHost) || empty($dbName) || empty($dbUser) || empty($adminUser) || empty($adminPass)) {
            echo json_encode(['success' => false, 'message' => '请填写所有必填项']);
            exit;
        }
        if (strlen($adminPass) < 8 || !preg_match('/[A-Za-z]/', $adminPass) || !preg_match('/[0-9]/', $adminPass)) {
            echo json_encode(['success' => false, 'message' => '密码至少8位，需包含字母和数字']);
            exit;
        }

        try {
            $dsn = "mysql:host=$dbHost;port=$dbPort;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

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
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("INSERT INTO `site_settings` (`id`) VALUES (1)");

            $pdo->exec("INSERT INTO `couple_info` (`id`, `name1`, `name2`, `anniversary`) VALUES (1, '小红', '小明', '2018-06-16')");
            $pdo->exec("INSERT INTO `music` (`id`, `source_type`, `source_url`, `backup_url`, `title`, `artist`) VALUES (1, 'url', 'https://music.163.com/song/media/outer/url?id=2147248772.mp3', 'http://music.163.com/song/media/outer/url?id=2147248772.mp3', '特别的人', '方大同')");

            $hashedPassword = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 13]);
            $stmt = $pdo->prepare("INSERT INTO `admin_users` (`username`, `password`, `role`) VALUES (?, ?, 'admin')");
            $stmt->execute([$adminUser, $hashedPassword]);

            $envContent = "DB_HOST=$dbHost\nDB_PORT=$dbPort\nDB_NAME=$dbName\nDB_USER=$dbUser\nDB_PASS=$dbPass\n";

            if (file_put_contents($envFile, $envContent)) {
                echo json_encode(['success' => true, 'message' => '安装成功']);
            } else {
                echo json_encode(['success' => false, 'message' => '配置文件写入失败']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => '数据库错误: ' . $e->getMessage()]);
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
    <link rel="stylesheet" href="/assets/css/fonts.css">
    <link rel="stylesheet" href="/assets/css/fontawesome.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Quicksand', 'Noto Sans SC', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a2e 0%, #2d1f3d 50%, #2a1a3a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .install-box {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 40px;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        }
        .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ff6b9d, #c44569);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 2rem;
            color: white;
        }
        h1 { color: white; text-align: center; margin-bottom: 8px; font-size: 1.8rem; }
        p.subtitle { color: rgba(255,255,255,0.5); text-align: center; margin-bottom: 30px; font-size: 0.95rem; }
        .form-group { margin-bottom: 20px; }
        label { display: block; color: rgba(255,255,255,0.8); margin-bottom: 8px; font-size: 0.9rem; }
        input {
            width: 100%;
            padding: 14px 16px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s;
        }
        input:focus { outline: none; border-color: #ff6b9d; background: rgba(255,255,255,0.12); }
        input::placeholder { color: rgba(255,255,255,0.35); }
        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #ff6b9d, #c44569);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1.05rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 12px 35px rgba(255, 107, 157, 0.35); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .btn-outline {
            background: transparent;
            border: 2px solid rgba(255,255,255,0.2);
            margin-bottom: 15px;
        }
        .btn-outline:hover { background: rgba(255,255,255,0.08); }
        .status { margin-top: 15px; padding: 12px; border-radius: 10px; text-align: center; font-size: 0.9rem; display: none; }
        .status.success { display: block; background: rgba(46, 204, 113, 0.15); border: 1px solid rgba(46, 204, 113, 0.3); color: #2ecc71; }
        .status.error { display: block; background: rgba(255, 71, 87, 0.15); border: 1px solid rgba(255, 71, 87, 0.3); color: #ff6b6b; }
        .status.warning { display: block; background: rgba(241, 196, 15, 0.15); border: 1px solid rgba(241, 196, 15, 0.3); color: #f1c40f; }
        .admin-section { margin-top: 25px; padding-top: 25px; border-top: 1px solid rgba(255,255,255,0.1); display: none; }
        .admin-section h3 { color: rgba(255,255,255,0.9); margin-bottom: 15px; font-size: 1rem; }
        .success-page { text-align: center; display: none; }
        .success-page .icon { background: linear-gradient(135deg, #2ecc71, #27ae60); }
        .success-page h2 { color: white; margin-bottom: 15px; }
        .success-page p { color: rgba(255,255,255,0.7); margin-bottom: 25px; }
        .success-links { display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; }
        .success-links a { display: inline-flex; align-items: center; gap: 8px; padding: 14px 28px; background: linear-gradient(135deg, #ff6b9d, #c44569); color: white; text-decoration: none; border-radius: 14px; font-weight: 600; transition: all 0.3s; }
        .success-links a:hover { transform: translateY(-3px); box-shadow: 0 12px 35px rgba(255, 107, 157, 0.4); }
        .success-links .btn-home { background: linear-gradient(135deg, #667eea, #764ba2); }
        .success-links .btn-home:hover { box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4); }
        .warning-box {
            background: rgba(241, 196, 15, 0.1);
            border: 1px solid rgba(241, 196, 15, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            color: #f1c40f;
            font-size: 0.85rem;
            display: none;
        }
        .warning-box.show { display: block; }
        .warning-box i { margin-right: 8px; }
        @media (max-width: 480px) { .install-box { padding: 30px 20px; } }
    </style>
</head>
<body>
    <form class="install-box" id="dbForm" onsubmit="return false;">
        <div class="icon"><i class="fas fa-heart"></i></div>
        <h1>安装向导</h1>
        <p class="subtitle">情侣纪念网站</p>

        <div class="form-group">
            <label>数据库主机 *</label>
            <input type="text" id="dbHost" placeholder="localhost 或 IP" required>
        </div>
        <div class="form-group">
            <label>数据库端口</label>
            <input type="number" id="dbPort" value="3306" placeholder="3306">
        </div>
        <div class="form-group">
            <label>数据库名称 *</label>
            <input type="text" id="dbName" placeholder="数据库名称" required>
        </div>
        <div class="form-group">
            <label>数据库用户名 *</label>
            <input type="text" id="dbUser" placeholder="用户名" required autocomplete="username">
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

        <div class="admin-section" id="adminSection">
            <h3><i class="fas fa-user-shield"></i> 管理员账号</h3>
            <div class="form-group">
                <label>用户名 *</label>
                <input type="text" id="adminUser" placeholder="登录用户名" required>
            </div>
            <div class="form-group">
                <label>密码 *</label>
                <input type="password" id="adminPass" placeholder="至少8位" minlength="8" required autocomplete="new-password">
            </div>

            <button type="button" class="btn" id="installBtn">
                <i class="fas fa-rocket"></i> 开始安装
            </button>
            <button type="button" class="btn btn-outline" id="backBtn" style="margin-top:10px;">
                <i class="fas fa-arrow-left"></i> 返回
            </button>
        </div>
    </form>

    <div class="install-box success-page" id="successPage" style="display:none;">
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

    <script>
    const testBtn = document.getElementById('testBtn');
    const installBtn = document.getElementById('installBtn');
    const backBtn = document.getElementById('backBtn');
    const testStatus = document.getElementById('testStatus');
    const adminSection = document.getElementById('adminSection');
    const warningBox = document.getElementById('warningBox');
    const warningText = document.getElementById('warningText');
    const successPage = document.getElementById('successPage');
    const dbForm = document.getElementById('dbForm');
    let dbExists = false;
    let connectionData = {};

    testBtn.addEventListener('click', async () => {
        const dbHost = document.getElementById('dbHost').value;
        const dbPort = document.getElementById('dbPort').value;
        const dbName = document.getElementById('dbName').value;
        const dbUser = document.getElementById('dbUser').value;
        const dbPass = document.getElementById('dbPass').value;

        if (!dbHost || !dbName || !dbUser) {
            testStatus.className = 'status error';
            testStatus.textContent = '请填写完整的数据库信息';
            return;
        }

        testBtn.disabled = true;
        testBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 测试中...';

        try {
            const fd = new FormData();
            fd.append('action', 'test_connection');
            fd.append('db_host', dbHost);
            fd.append('db_port', dbPort);
            fd.append('db_name', dbName);
            fd.append('db_user', dbUser);
            fd.append('db_pass', dbPass);
            const res = await fetch('/install/', { method: 'POST', body: fd });
            const result = await res.json();

            if (result.success) {
                testStatus.className = 'status success';
                testStatus.innerHTML = '<i class="fas fa-check-circle"></i> ' + result.message;
                connectionData = { dbHost, dbPort, dbName, dbUser, dbPass };
                dbExists = result.db_exists || false;

                if (dbExists) {
                    warningBox.classList.add('show');
                    warningText.textContent = '警告：该数据库已存在！继续安装将删除所有现有数据！请提前做好备份！';
                } else {
                    warningBox.classList.remove('show');
                }

                adminSection.style.display = 'block';
            } else {
                testStatus.className = 'status error';
                testStatus.innerHTML = '<i class="fas fa-times-circle"></i> ' + result.message;
                adminSection.style.display = 'none';
                warningBox.classList.remove('show');
            }
        } catch (e) {
            testStatus.className = 'status error';
            testStatus.textContent = '连接失败，请检查网络';
            adminSection.style.display = 'none';
        }

        testBtn.disabled = false;
        testBtn.innerHTML = '<i class="fas fa-plug"></i> 测试连接';
    });

    backBtn.addEventListener('click', () => {
        adminSection.style.display = 'none';
        warningBox.classList.remove('show');
        testStatus.className = 'status';
        testStatus.textContent = '';
    });

    installBtn.addEventListener('click', async () => {
        const adminUser = document.getElementById('adminUser').value;
        const adminPass = document.getElementById('adminPass').value;

        if (!adminUser || !adminPass) { alert('请填写管理员信息'); return; }
        if (adminPass.length < 8) { alert('密码至少8位'); return; }

        if (dbExists) {
            if (!confirm('警告：即将清空数据库所有数据！此操作不可恢复！\n\n确定要继续吗？')) {
                return;
            }
        }

        installBtn.disabled = true;
        installBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 安装中...';

        try {
            const fd = new FormData();
            fd.append('action', 'install');
            fd.append('db_host', connectionData.dbHost);
            fd.append('db_port', connectionData.dbPort);
            fd.append('db_name', connectionData.dbName);
            fd.append('db_user', connectionData.dbUser);
            fd.append('db_pass', connectionData.dbPass);
            fd.append('admin_user', adminUser);
            fd.append('admin_pass', adminPass);
            const res = await fetch('/install/', { method: 'POST', body: fd });
            const result = await res.json();

            if (result.success) {
                dbForm.style.display = 'none';
                successPage.style.display = 'block';
            } else {
                alert(result.message);
                installBtn.disabled = false;
                installBtn.innerHTML = '<i class="fas fa-rocket"></i> 开始安装';
            }
        } catch (e) {
            alert('安装失败，请重试');
            installBtn.disabled = false;
            installBtn.innerHTML = '<i class="fas fa-rocket"></i> 开始安装';
        }
    });
    </script>
</body>
</html>
