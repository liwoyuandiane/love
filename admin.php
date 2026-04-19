<?php
/**
 * 后台管理页面
 */

$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    header('Location: /install/');
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

ensureSession();

$error = '';
$success = false;
$currentUsername = '';
$currentUserId = 0;

if (isLoggedIn()) {
    $success = true;
    $currentUsername = $_SESSION['user_username'] ?? '';
    $currentUserRole = $_SESSION['user_role'] ?? 'admin';
    $currentUserId = $_SESSION['user_id'] ?? 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = '请填写用户名和密码';
        } else {
            $result = login($username, $password);
            if ($result['success']) {
                $success = true;
            } else {
                $error = $result['message'];
            }
        }
    } elseif ($_POST['action'] === 'logout') {
        logout();
        $success = false;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台管理 - 情侣网站</title>
    <link rel="stylesheet" href="/assets/css/fonts.css">
    <link rel="stylesheet" href="/assets/css/fontawesome.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <div class="login-overlay" id="loginOverlay" <?php if ($success) echo 'style="display:none"'; ?>>
        <div class="login-box">
            <div class="icon"><i class="fas fa-heart"></i></div>
            <h2>后台管理登录</h2>
            <p>请输入管理员账号信息</p>
            <form id="loginForm">
                <input type="text" class="login-input" id="usernameInput" placeholder="用户名" maxlength="50" autocomplete="username" required>
                <input type="password" class="login-input" id="passwordInput" placeholder="密码" maxlength="100" autocomplete="current-password" required>
                <button type="submit" class="login-btn" id="loginBtn">登 录</button>
            </form>
            <p class="login-error" id="loginError">用户名或密码错误</p>
        </div>
    </div>

    <div class="loading-overlay" id="loadingOverlay" style="display:none">
        <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i></div>
        <p>正在加载数据...</p>
    </div>

    <div class="admin-container <?php if ($success) echo 'active'; ?>" id="adminContainer" <?php if (!$success) echo 'style="display:none"'; ?>>
        <div class="admin-header">
            <h1><i class="fas fa-cog"></i> 管理面板</h1>
            <div class="header-actions">
                <a href="/" class="back-link"><i class="fas fa-home"></i> <span>返回首页</span></a>
                <button class="logout-btn" onclick="logout()"><i class="fas fa-sign-out-alt"></i> <span>退出</span></button>
            </div>
        </div>

        <div class="tabs">
            <button class="tab active" data-section="couple" data-role="admin"><i class="fas fa-heart"></i> <span>情侣信息</span></button>
            <button class="tab" data-section="anniversary"><i class="fas fa-calendar-heart"></i> <span>纪念日</span></button>
            <button class="tab" data-section="wishlist"><i class="fas fa-list-check"></i> <span>愿望清单</span></button>
            <button class="tab" data-section="explore"><i class="fas fa-map-marked-alt"></i> <span>探索地点</span></button>
            <button class="tab" data-section="photos"><i class="fas fa-images"></i> <span>记忆墙</span></button>
            <button class="tab" data-section="music"><i class="fas fa-music"></i> <span>音乐设置</span></button>
            <button class="tab" data-section="backup" data-role="admin"><i class="fas fa-download"></i> <span>备份导出导入</span></button>
            <button class="tab" data-section="settings" data-role="admin"><i class="fas fa-cog"></i> <span>网站设置</span></button>
            <button class="tab" data-section="admin" data-role="admin"><i class="fas fa-user-shield"></i> <span>账号管理</span></button>
            <button class="tab" data-section="logs" data-role="admin"><i class="fas fa-file-alt"></i> <span>审计日志</span></button>
        </div>

        <div class="section active" id="section-couple" data-role="admin">
            <div class="section-header">
                <h2><i class="fas fa-heart"></i> 情侣信息管理</h2>
            </div>
            <form id="coupleForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label>第一人姓名</label>
                        <input type="text" class="form-input" id="name1" required maxlength="50" placeholder="例如：小明">
                    </div>
                    <div class="form-group">
                        <label>第二人姓名</label>
                        <input type="text" class="form-input" id="name2" required maxlength="50" placeholder="例如：小红">
                    </div>
                    <div class="form-group">
                        <label>纪念日日期</label>
                        <input type="date" class="form-input" id="anniversary" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存修改</button>
            </form>
        </div>

        <div class="section" id="section-anniversary">
            <div class="section-header">
                <h2><i class="fas fa-calendar-heart"></i> 纪念日管理</h2>
            </div>
            <form id="anniversaryForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label>纪念日标题</label>
                        <input type="text" class="form-input" id="anniversaryTitle" required maxlength="200" placeholder="例如：相识纪念日">
                    </div>
                    <div class="form-group">
                        <label>日期（可选）</label>
                        <input type="date" class="form-input" id="anniversaryDate">
                    </div>
                    <div class="form-group">
                        <label>类型</label>
                        <select class="form-input" id="anniversaryType">
                            <option value="anniversary">纪念日</option>
                            <option value="birthday">生日</option>
                            <option value="wedding">婚礼</option>
                            <option value="other">其他</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>提前提醒天数</label>
                        <input type="number" class="form-input" id="anniversaryReminder" value="0" min="0" max="365" placeholder="0表示不提醒">
                        <small style="color: var(--text-muted); font-size: 0.8rem;">设置提前几天发送提醒</small>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>描述（可选）</label>
                        <input type="text" class="form-input" id="anniversaryDesc" maxlength="500" placeholder="描述...">
                    </div>
                </div>
                <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> 添加纪念日</button>
            </form>
            <table class="data-table">
                <thead><tr><th>标题</th><th>日期</th><th>类型</th><th>提醒</th><th>操作</th></tr></thead>
                <tbody id="anniversaryTable"></tbody>
            </table>
        </div>

        <div class="section" id="section-wishlist">
            <div class="section-header">
                <h2><i class="fas fa-list-check"></i> 愿望清单管理</h2>
            </div>
            <form id="wishlistForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label>愿望标题</label>
                        <input type="text" class="form-input" id="wishlistTitle" required maxlength="200" placeholder="例如：一起去旅行">
                    </div>
                    <div class="form-group">
                        <label>描述（可选）</label>
                        <input type="text" class="form-input" id="wishlistDesc" maxlength="500" placeholder="愿望描述...">
                    </div>
                    <div class="form-group">
                        <label>日期（可选）</label>
                        <input type="date" class="form-input" id="wishlistDate">
                    </div>
                </div>
                <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> 添加愿望</button>
            </form>
            <table class="data-table">
                <thead><tr><th>愿望</th><th>描述</th><th>日期</th><th>操作</th></tr></thead>
                <tbody id="wishlistTable"></tbody>
            </table>
        </div>

        <div class="section" id="section-explore">
            <div class="section-header">
                <h2><i class="fas fa-map-marked-alt"></i> 探索地点管理</h2>
            </div>
            <form id="exploreForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label>地点名称</label>
                        <input type="text" class="form-input" id="exploreTitle" required maxlength="200" placeholder="例如：马尔代夫">
                    </div>
                    <div class="form-group">
                        <label>描述（可选）</label>
                        <input type="text" class="form-input" id="exploreDesc" maxlength="500" placeholder="地点描述...">
                    </div>
                    <div class="form-group">
                        <label>日期（可选）</label>
                        <input type="date" class="form-input" id="exploreDate">
                    </div>
                </div>
                <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> 添加地点</button>
            </form>
            <table class="data-table">
                <thead><tr><th>地点</th><th>描述</th><th>日期</th><th>操作</th></tr></thead>
                <tbody id="exploreTable"></tbody>
            </table>
        </div>

        <div class="section" id="section-photos">
            <div class="section-header">
                <h2><i class="fas fa-images"></i> 记忆墙管理</h2>
            </div>
            
            <h3 style="margin: 20px 0 15px; color: var(--primary-light); font-size: 1rem;"><i class="fas fa-link"></i> 通过URL添加图片（推荐）</h3>
            <form id="photoUrlForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label>图片URL</label>
                        <input type="url" class="form-input" id="photoUrl" required placeholder="https://example.com/image.jpg">
                    </div>
                    <div class="form-group">
                        <label>照片说明（可选）</label>
                        <input type="text" class="form-input" id="photoUrlCaption" maxlength="200" placeholder="照片描述...">
                    </div>
                </div>
                <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> 添加链接</button>
            </form>

            <h3 style="margin: 30px 0 15px; color: var(--primary-light); font-size: 1rem;"><i class="fas fa-upload"></i> 上传本地图片</h3>
            <form id="photoUploadForm">
                <div class="form-group">
                    <div class="file-upload" id="fileUploadArea">
                        <input type="file" id="photoFile" accept="image/*">
                        <i class="fas fa-cloud-upload-alt" id="uploadIcon"></i>
                        <p id="uploadText">点击或拖拽图片到此处上传</p>
                        <p class="hint" id="uploadHint">支持 jpg, png, gif, webp 格式，最大 10MB</p>
                        <p id="fileNameDisplay" style="display:none; margin-top:8px; color: var(--success); word-break:break-all;"></p>
                    </div>
                </div>
                <div class="form-group" style="margin-top: 15px;">
                    <label>照片说明（可选）</label>
                    <input type="text" class="form-input" id="photoCaption" maxlength="200" placeholder="照片描述...">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> 上传图片</button>
            </form>

            <h3 style="margin: 30px 0 15px; color: var(--primary-light); font-size: 1rem;"><i class="fas fa-th"></i> 已上传的照片</h3>
            <div class="photo-grid" id="photoGrid"></div>
        </div>

        <div class="section" id="section-music">
            <div class="section-header">
                <h2><i class="fas fa-music"></i> 音乐设置</h2>
            </div>
            <form id="musicForm">
                <div class="form-group">
                    <label>音乐来源</label>
                    <div class="radio-group">
                        <label><input type="radio" name="sourceType" value="local" id="sourceLocal"> 本地文件</label>
                        <label><input type="radio" name="sourceType" value="url" id="sourceUrl" checked> URL链接</label>
                    </div>
                </div>
                <div class="form-group" id="localPathGroup" style="display: none;">
                    <label>本地文件路径</label>
                    <input type="text" class="form-input" id="localPath" placeholder="/uploads/love-song.mp3">
                    <small style="color: var(--text-muted); margin-top: 5px; display: block;">请将音乐文件放入 public/uploads/ 目录</small>
                </div>
                <div class="form-group" id="urlPathGroup">
                    <label>音乐URL</label>
                    <input type="url" class="form-input" id="musicUrl" placeholder="https://example.com/music.mp3">
                </div>
                <div class="form-group">
                    <label>备用音乐URL（可选）</label>
                    <input type="url" class="form-input" id="musicBackupUrl" placeholder="当主音乐加载失败时自动切换">
                    <small style="color: var(--text-muted); margin-top: 5px; display: block;">当主音乐链接失效时，自动切换到备用链接</small>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>歌曲名称</label>
                        <input type="text" class="form-input" id="musicTitle" maxlength="200" placeholder="歌曲名">
                    </div>
                    <div class="form-group">
                        <label>歌手（可选）</label>
                        <input type="text" class="form-input" id="musicArtist" maxlength="100" placeholder="歌手名">
                    </div>
                </div>
                <div class="music-preview" id="musicPreview" style="display: none;">
                    <p>音乐预览：</p>
                    <audio id="previewAudio" controls></audio>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存设置</button>
            </form>
        </div>

        <div class="section" id="section-backup" data-role="admin">
            <div class="section-header">
                <h2><i class="fas fa-download"></i> 数据备份导出导入</h2>
            </div>
            <div style="background: var(--card-bg); border-radius: 16px; padding: 25px; margin-bottom: 20px; border: 1px solid var(--card-border);">
                <p style="color: var(--text-secondary); margin-bottom: 15px;">导出所有网站数据为JSON文件，包含情侣信息、纪念日、愿望清单、探索地点和音乐设置。</p>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-primary" onclick="exportData()"><i class="fas fa-download"></i> 导出数据</button>
                    <button type="button" class="btn btn-success" onclick="document.getElementById('importFile').click()"><i class="fas fa-upload"></i> 导入数据</button>
                    <input type="file" id="importFile" accept=".json" style="display:none" onchange="importData(this.files[0])">
                </div>
            </div>
        </div>

        <div class="section" id="section-settings" data-role="admin">
            <div class="section-header">
                <h2><i class="fas fa-cog"></i> 网站设置</h2>
            </div>
            <form id="settingsForm">
                <div class="form-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>网站名称</label>
                        <input type="text" class="form-input" id="siteName" maxlength="200" placeholder="显示在浏览器标签页">
                        <small style="color: var(--text-muted); font-size: 0.8rem;">显示在浏览器标签页和首页标题</small>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="timezone">时区设置</label>
                        <select class="form-input" id="timezone">
                            <option value="Asia/Shanghai">北京时间 (UTC+8)</option>
                            <option value="Asia/Tokyo">东京时间 (UTC+9)</option>
                            <option value="Europe/London">伦敦时间 (UTC+0)</option>
                            <option value="Europe/Paris">巴黎时间 (UTC+1)</option>
                            <option value="America/New_York">纽约时间 (UTC-5)</option>
                            <option value="America/Los_Angeles">洛杉矶时间 (UTC-8)</option>
                            <option value="Australia/Sydney">悉尼时间 (UTC+10)</option>
                        </select>
                        <small style="color: var(--text-muted); font-size: 0.8rem;">选择你所在的时区，计时器将按照此时区显示时间</small>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>ICP备案号</label>
                        <input type="text" class="form-input" id="icpCode" maxlength="100" placeholder="如：粤ICP备XXXXXXXX号-X">
                        <small style="color: var(--text-muted); font-size: 0.8rem;">中国大陆网站必须填写。格式示例：粤ICP备12345678号</small>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>公安联网备案号（可选）</label>
                        <input type="text" class="form-input" id="policeRecordCode" maxlength="100" placeholder="如：粤公网安备XXXXXXXXXXXXXXXX号">
                        <small style="color: var(--text-muted); font-size: 0.8rem;">如果网站需要公安联网备案，请填写</small>
                    </div>
                </div>
                <div style="background: rgba(255,107,157,0.15); border-radius: 12px; padding: 15px; margin-bottom: 20px; border: 1px solid rgba(255,107,157,0.3);">
                    <p style="color: var(--text-secondary); font-size: 0.85rem;"><i class="fas fa-info-circle" style="color: var(--primary);"></i> 备案信息将显示在网站底部。根据中国大陆法规，经营性和非经营性网站都需进行ICP备案。</p>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存设置</button>
            </form>
        </div>

        <div class="section" id="section-admin" data-role="admin">
            <div class="section-header">
                <h2><i class="fas fa-user-shield"></i> 账号管理</h2>
            </div>
            <div class="current-user-info" style="margin-bottom: 20px; padding: 12px; background: rgba(255,107,157,0.2); border-radius: 8px;">
                <i class="fas fa-user-circle"></i> 当前登录账号: <strong><?php echo htmlspecialchars($currentUsername); ?></strong>
            </div>
            <form id="adminUserForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label>新用户名</label>
                        <input type="text" class="form-input" id="adminUsername" required maxlength="50" placeholder="请输入新用户名" autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label>新密码</label>
                        <input type="password" class="form-input" id="adminPassword" required minlength="8" maxlength="100" placeholder="请输入新密码（至少8位）" autocomplete="new-password">
                    </div>
                </div>
                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> 保存修改</button>
            </form>
        </div>

        <div class="section" id="section-logs" data-role="admin">
            <div class="section-header">
                <h2><i class="fas fa-file-alt"></i> 审计日志</h2>
            </div>
            <div class="log-filters">
                <input type="text" class="form-input" id="logSearch" placeholder="搜索日志内容..." style="max-width: 300px;">
                <select class="form-input" id="logLevel" style="max-width: 150px;">
                    <option value="">全部级别</option>
                    <option value="AUDIT">仅 AUDIT</option>
                </select>
                <button type="button" class="btn btn-secondary" onclick="loadAuditLogs()"><i class="fas fa-search"></i> 搜索</button>
                <button type="button" class="btn btn-secondary" onclick="refreshAuditLogs()"><i class="fas fa-redo"></i> 刷新</button>
            </div>
            <div class="log-container">
                <table class="data-table log-table">
                    <thead>
                        <tr>
                            <th>时间</th>
                            <th>用户</th>
                            <th>IP</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="auditLogTable"></tbody>
                </table>
            </div>
            <div class="log-pagination" id="logPagination"></div>
        </div>
    </div>

    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="editModalTitle"><i class="fas fa-edit"></i> 编辑</h3>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form id="editForm"></form>
        </div>
    </div>

    <div class="modal" id="photoEditModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="photoEditModalTitle"><i class="fas fa-image"></i> 编辑照片</h3>
                <button class="close-btn" onclick="closePhotoEditModal()">&times;</button>
            </div>
            <form id="photoEditForm">
                <div class="form-group">
                    <label>图片说明</label>
                    <input type="text" class="form-input" id="photoEditCaption" maxlength="200">
                </div>
                <div class="form-group" id="photoUrlGroup">
                    <label>图片链接</label>
                    <input type="url" class="form-input" id="photoEditUrl" placeholder="https://example.com/image.jpg">
                </div>
                <div class="form-group" id="photoLocalPathGroup" style="display:none">
                    <label>本地路径</label>
                    <input type="text" class="form-input" id="photoEditLocalPath" placeholder="/assets/uploads/xxx.jpg" readonly>
                </div>
                <div class="form-group">
                    <label>预览</label>
                    <div class="photo-edit-preview" id="photoEditPreview"></div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closePhotoEditModal()">取消</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存</button>
                </div>
            </form>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
        window.CSRF_TOKEN = '<?php echo CSRF::generate(); ?>';
        window.CURRENT_USER_ROLE = '<?php echo $currentUserRole; ?>';
        window.CURRENT_USER_ID = <?php echo intval($currentUserId); ?>;
        window.IS_ADMIN = <?php echo $currentUserRole === 'admin' ? 'true' : 'false'; ?>;
    </script>
    <script src="/assets/js/utils.js"></script>
    <script src="/assets/js/admin.js"></script>
</body>
</html>