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

            adminSection.classList.remove('is-hidden');
        } else {
            testStatus.className = 'status error';
            testStatus.innerHTML = '<i class="fas fa-times-circle"></i> ' + result.message;
            adminSection.classList.add('is-hidden');
            warningBox.classList.remove('show');
        }
    } catch (e) {
        testStatus.className = 'status error';
        testStatus.textContent = '连接失败，请检查网络';
        adminSection.classList.add('is-hidden');
    }

    testBtn.disabled = false;
    testBtn.innerHTML = '<i class="fas fa-plug"></i> 测试连接';
});

backBtn.addEventListener('click', () => {
    adminSection.classList.add('is-hidden');
    warningBox.classList.remove('show');
    testStatus.className = 'status';
    testStatus.textContent = '';
});

dbForm.addEventListener('submit', (e) => e.preventDefault());

installBtn.addEventListener('click', async () => {
    const adminUser = document.getElementById('adminUser').value;
    const adminPass = document.getElementById('adminPass').value;

    if (!adminUser || !adminPass) { alert('请填写管理员信息'); return; }
    if (adminPass.length < 8 || adminPass.length > 72) { alert('密码长度需为 8-72 位'); return; }

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
        if (dbExists) fd.append('confirm_drop', '1');
        const res = await fetch('/install/', { method: 'POST', body: fd });
        const result = await res.json();

        if (result.success) {
            dbForm.classList.add('is-hidden');
            successPage.classList.remove('is-hidden');
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
