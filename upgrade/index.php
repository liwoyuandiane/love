<?php
/**
 * 数据库升级脚本
 * 访问此页面执行升级操作
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: text/html; charset=utf-8');

$messages = [];
$success = true;

try {
    $db = getDB();

    // 检查 timezone 字段是否存在
    $stmt = $db->query("SHOW COLUMNS FROM site_settings LIKE 'timezone'");
    $exists = $stmt->fetch();

    if (!$exists) {
        $db->exec("ALTER TABLE site_settings ADD COLUMN timezone VARCHAR(50) NOT NULL DEFAULT 'Asia/Shanghai' AFTER site_name");
        $messages[] = ['type' => 'success', 'text' => '已添加 timezone 字段到 site_settings 表'];
    } else {
        $messages[] = ['type' => 'info', 'text' => 'timezone 字段已存在，无需更新'];
    }

    // 确保默认时区值正确
    $db->exec("UPDATE site_settings SET timezone = 'Asia/Shanghai' WHERE timezone = '' OR timezone IS NULL");
    $messages[] = ['type' => 'success', 'text' => '已设置默认时区为 Asia/Shanghai'];

} catch (Exception $e) {
    $success = false;
    $messages[] = ['type' => 'error', 'text' => '升级失败: ' . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>数据库升级</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        h1 { color: #333; }
        .message { padding: 10px 15px; border-radius: 5px; margin: 10px 0; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .btn { display: inline-block; padding: 10px 20px; background: #ff6b9d; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>数据库升级</h1>

    <?php foreach ($messages as $msg): ?>
        <div class="message <?= $msg['type'] ?>"><?= htmlspecialchars($msg['text']) ?></div>
    <?php endforeach; ?>

    <?php if ($success): ?>
        <p style="color: green;">升级完成！</p>
        <a href="/admin.php" class="btn">前往后台</a>
        <a href="/" class="btn" style="background: #6c757d;">返回首页</a>
    <?php else: ?>
        <p style="color: red;">升级失败，请检查错误信息。</p>
    <?php endif; ?>
</body>
</html>
