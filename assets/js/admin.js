const API_BASE = '/api';
const ADMIN_API = {
    status: '/api/admin-status.php',
    verify: '/api/admin-verify.php',
    logout: '/api/admin-logout.php',
    sync: '/api/sync.php'
};
const $ = id => document.getElementById(id);
const $$ = sel => document.querySelectorAll(sel);

const csrfFetch = async (url, options = {}) => {
    const headers = { ...options.headers };
    if (window.CSRF_TOKEN) {
        headers['X-CSRF-Token'] = window.CSRF_TOKEN;
    }
    return fetch(url, { ...options, headers });
};
const TYPE_NAMES = { anniversary: '纪念日', wishlist: '愿望', explore: '探索地点' };
const TYPE_ICONS = { anniversary: 'fa-calendar-heart', wishlist: 'fa-list-check', explore: 'fa-map-marked-alt' };
const ENDPOINTS = { anniversary: 'anniversaries', wishlist: 'wishlists', explore: 'explores', photo: 'photos' };

let isLoggedIn = false, currentSection = 'couple', siteData = null;
let isAdmin = window.IS_ADMIN || false;
let syncTimer = null;
const SYNC_INTERVAL = 60000;

document.addEventListener('DOMContentLoaded', () => { checkAuthStatus(); setupEventListeners(); });

const checkAuthStatus = async () => {
    try {
        const res = await fetch(ADMIN_API.status, { credentials: 'include' });
        const result = await res.json();
        if (result.success) {
            isLoggedIn = true;
            isAdmin = result.isAdmin || (result.role === 'admin');
            window.IS_ADMIN = isAdmin;
            filterTabsByRole();
            $('loginOverlay').style.display = 'none';
            $('adminContainer').classList.add('active');
            loadAllData();
            startPeriodicSync();
        } else {
            $('loginOverlay').style.display = 'flex';
            $('adminContainer').classList.remove('active');
        }
    } catch (e) { console.error('Auth check error:', e); }
};

const startPeriodicSync = () => {
    if (syncTimer) clearInterval(syncTimer);
    syncTimer = setInterval(async () => {
        try {
            await csrfFetch(ADMIN_API.sync, { method: 'POST', credentials: 'include' });
        } catch (e) { console.error('Sync error:', e); }
    }, SYNC_INTERVAL);
};

const stopPeriodicSync = () => {
    if (syncTimer) {
        clearInterval(syncTimer);
        syncTimer = null;
    }
};

const filterTabsByRole = () => {
    $$('.tab[data-role]').forEach(tab => {
        if (tab.dataset.role !== 'admin') return;
        tab.style.display = isAdmin ? '' : 'none';
    });
    if (!isAdmin) {
        const firstAllowed = $$('.tab:not([data-role])')[0];
        if (firstAllowed) switchSection(firstAllowed.dataset.section);
    }
};

const logout = async () => {
    stopPeriodicSync();
    try { await fetch(ADMIN_API.logout, { method: 'POST', credentials: 'include' }); } catch (e) {}
    window.location.href = '/';
};

const setupEventListeners = () => {
    $('loginForm')?.addEventListener('submit', e => { e.preventDefault(); handleLogin(); });
    ['usernameInput', 'passwordInput'].forEach(id => {
        $(id).addEventListener('keypress', e => { if (e.key === 'Enter') handleLogin(); });
    });
    $$('.tab').forEach(tab => tab.addEventListener('click', () => switchSection(tab.dataset.section)));
    const formHandlers = {
        coupleForm: handleCoupleSubmit,
        anniversaryForm: handleAnniversarySubmit,
        wishlistForm: handleWishlistSubmit,
        exploreForm: handleExploreSubmit,
        photoUploadForm: handlePhotoUpload,
        photoUrlForm: handlePhotoUrl,
        musicForm: handleMusicSubmit,
        adminUserForm: handleAdminUserSubmit
    };
    Object.entries(formHandlers).forEach(([id, handler]) => {
        $(id)?.addEventListener('submit', e => { e.preventDefault(); handler(e); });
    });
    $$('input[name="sourceType"]').forEach(r => r.addEventListener('change', () => {
        const isLocal = r.value === 'local';
        $('localPathGroup').style.display = isLocal ? 'block' : 'none';
        $('urlPathGroup').style.display = isLocal ? 'none' : 'block';
        updateMusicPreview();
    }));
    $('musicUrl')?.addEventListener('input', updateMusicPreview);
    $('localPath')?.addEventListener('input', updateMusicPreview);
    const fileArea = $('fileUploadArea'), photoFile = $('photoFile');
    fileArea?.addEventListener('click', () => photoFile.click());
    fileArea?.addEventListener('dragover', e => { e.preventDefault(); fileArea.style.borderColor = 'var(--primary)'; });
    fileArea?.addEventListener('dragleave', () => { fileArea.style.borderColor = 'rgba(255, 255, 255, 0.15)'; });
    fileArea?.addEventListener('drop', e => { e.preventDefault(); fileArea.style.borderColor = 'rgba(255, 255, 255, 0.15)'; if (e.dataTransfer.files.length) photoFile.files = e.dataTransfer.files; });
};

const handleLogin = async () => {
    const username = $('usernameInput').value, password = $('passwordInput').value;
    if (!username || !password) { showToast('请输入用户名和密码', 'error'); return; }
    try {
        const res = await fetch(ADMIN_API.verify, { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include', body: JSON.stringify({ username, password }) });
        const result = await res.json();
        if (result.success) {
            isLoggedIn = true;
            isAdmin = true;
            window.IS_ADMIN = true;
            filterTabsByRole();
            $('loginOverlay').style.display = 'none';
            $('adminContainer').style.display = 'block';
            $('adminContainer').classList.add('active');
            $('loadingOverlay').style.display = 'flex';
            loadAllData();
            startPeriodicSync();
        } else { showToast(result.error?.message || '登录失败', 'error'); $('passwordInput').value = ''; }
    } catch (e) { showToast('网络错误，请稍后重试', 'error'); }
};

const loadAllData = async (showError = true) => {
    try {
        const [dataRes, adminRes, statusRes] = await Promise.all([
            csrfFetch(`${API_BASE}/data`, { credentials: 'include' }),
            csrfFetch(`${API_BASE}/admin-users`, { credentials: 'include' }),
            csrfFetch(`${API_BASE}/admin-status`, { credentials: 'include' })
        ]);

        $('loadingOverlay').style.display = 'none';

        if (dataRes.ok) {
            const data = await dataRes.json();
            if (data.success) siteData = data.data;
        }
        if (adminRes.ok) {
            const admin = await adminRes.json();
            if (admin.success) window.adminUsers = admin.data;
        }
        if (statusRes.ok) {
            const status = await statusRes.json();
            if (status.success) window.currentUsername = status.username;
        }

        renderAllSections();
    } catch (e) {
        console.error('loadAllData error:', e);
        $('loadingOverlay').style.display = 'none';
        if (showError) showToast('数据加载失败，请刷新页面', 'error');
    }
};

const renderAllSections = () => {
    renderCoupleInfo(); renderAnniversaryTable(); renderWishlistTable(); renderExploreTable();
    renderPhotoGrid(); renderMusicForm();
};

const addToLocalList = (type, item) => {
    if (!siteData) return;
    const list = siteData[type];
    if (Array.isArray(list)) {
        list.unshift(item);
    }
};

const updateInLocalList = (type, id, updates) => {
    if (!siteData) return;
    const list = siteData[type];
    if (Array.isArray(list)) {
        const idx = list.findIndex(i => i.id === id);
        if (idx !== -1) Object.assign(list[idx], updates);
    }
};

const removeFromLocalList = (type, id) => {
    if (!siteData) return;
    const list = siteData[type];
    if (Array.isArray(list)) {
        const idx = list.findIndex(i => i.id === id);
        if (idx !== -1) list.splice(idx, 1);
    }
};

const renderCoupleInfo = () => {
    if (!siteData?.coupleInfo) return;
    const { name1, name2, anniversary } = siteData.coupleInfo;
    $('name1').value = name1 || ''; $('name2').value = name2 || ''; $('anniversary').value = utils.formatDate(anniversary);
};

const handleCoupleSubmit = async e => {
    e.preventDefault();
    const data = { name1: $('name1').value.trim(), name2: $('name2').value.trim(), anniversary: $('anniversary').value };
    try {
        const res = await csrfFetch(`${API_BASE}/couple-info`, { method: 'PUT', credentials: 'include', body: JSON.stringify(data) });
        const result = await res.json();
        if (result.success) {
            showToast('情侣信息更新成功', 'success');
            if (siteData?.coupleInfo) {
                siteData.coupleInfo.name1 = data.name1;
                siteData.coupleInfo.name2 = data.name2;
                siteData.coupleInfo.anniversary = data.anniversary;
            }
        }
        else showToast(result.error?.message || '更新失败', 'error');
    } catch (e) { showToast('网络错误，请稍后重试', 'error'); }
};

const emptyState = (icon, msg) => `<tr><td colspan="10" class="empty-state"><i class="fas ${icon}"></i><p>${msg}</p></td></tr>`;

const renderAnniversaryTable = () => {
    const items = siteData?.anniversaries || [];
    const tbody = $('anniversaryTable');
    const typeLabels = { anniversary: '纪念日', birthday: '生日', wedding: '婚礼', other: '其他' };
    if (!items.length) { tbody.innerHTML = emptyState('fa-calendar-heart', '暂无纪念日'); return; }
    tbody.innerHTML = items.map(item => `
        <tr><td>${utils.escapeHtml(item.title)}</td><td>${utils.formatDate(item.date) || '-'}</td><td>${typeLabels[item.type] || '纪念日'}</td>
        <td>${item.reminder_days > 0 ? `提前${item.reminder_days}天` : '-'}</td>
        <td class="actions"><button class="btn btn-secondary btn-sm" onclick="editItem('anniversary',${item.id},'${utils.escapeJs(item.title)}','${utils.escapeJs(utils.formatDate(item.date))}','${utils.escapeJs(item.description || '')}')"><i class="fas fa-edit"></i></button>
        <button class="btn btn-danger btn-sm" onclick="deleteItem('anniversary',${item.id})"><i class="fas fa-trash"></i></button></td></tr>`).join('');
};

const handleAnniversarySubmit = async e => {
    e.preventDefault();
    const data = { title: $('anniversaryTitle').value.trim(), date: $('anniversaryDate').value || null, description: $('anniversaryDesc').value.trim() || null, type: $('anniversaryType').value, reminder_days: parseInt($('anniversaryReminder').value) || 0 };
    try {
        const res = await csrfFetch(`${API_BASE}/anniversaries`, { method: 'POST', credentials: 'include', body: JSON.stringify(data) });
        const result = await res.json();
        if (result.success) {
            showToast('添加成功', 'success');
            ['anniversaryTitle', 'anniversaryDate', 'anniversaryDesc'].forEach(id => $(id).value = '');
            if (result.data) addToLocalList('anniversaries', result.data);
            renderAnniversaryTable();
        }
        else showToast(result.error?.message || '添加失败', 'error');
    } catch (e) { showToast('网络错误，请稍后重试', 'error'); }
};

const renderWishlistTable = () => {
    const items = siteData?.wishlists || [];
    const tbody = $('wishlistTable');
    if (!items.length) { tbody.innerHTML = emptyState('fa-list-check', '暂无愿望'); return; }
    tbody.innerHTML = items.map(item => `
        <tr class="${item.completed ? 'completed' : ''}">
        <td>${utils.escapeHtml(item.title)}</td><td>${utils.escapeHtml(item.description || '-')}</td><td>${utils.formatDate(item.date) || '-'}</td>
        <td><i class="fas ${item.completed ? 'fa-check-circle' : 'fa-circle'}" style="color:${item.completed ? 'var(--success)' : 'var(--text-muted)'}"></i></td>
        <td class="actions"><button class="btn btn-secondary btn-sm" onclick="toggleWishlistAdmin(${item.id})" title="${item.completed ? '标记未完成' : '标记完成'}"><i class="fas ${item.completed ? 'fa-undo' : 'fa-check'}"></i></button>
        <button class="btn btn-secondary btn-sm" onclick="editItem('wishlist',${item.id},'${utils.escapeJs(item.title)}','${utils.escapeJs(utils.formatDate(item.date))}','${utils.escapeJs(item.description || '')}')"><i class="fas fa-edit"></i></button>
        <button class="btn btn-danger btn-sm" onclick="deleteItem('wishlist',${item.id})"><i class="fas fa-trash"></i></button></td></tr>`).join('');
};

window.toggleWishlistAdmin = async function(id) {
    try {
        const res = await csrfFetch(`${API_BASE}/wishlists/${id}/toggle`, { method: 'POST', credentials: 'include' });
        const result = await res.json();
        if (result.success) {
            if (siteData?.wishlists) {
                const item = siteData.wishlists.find(w => w.id === id);
                if (item) {
                    item.completed = result.data.completed;
                    item.completed_at = result.data.completed_at;
                }
            }
            renderWishlistTable();
            showToast(result.message || '操作成功', 'success');
        } else {
            showToast(result.error?.message || '操作失败', 'error');
        }
    }
    catch (e) { showToast('操作失败', 'error'); }
};

const handleWishlistSubmit = async e => {
    e.preventDefault();
    const data = { title: $('wishlistTitle').value.trim(), description: $('wishlistDesc').value.trim() || null, date: $('wishlistDate').value || null };
    try {
        const res = await csrfFetch(`${API_BASE}/wishlists`, { method: 'POST', credentials: 'include', body: JSON.stringify(data) });
        const result = await res.json();
        if (result.success) {
            showToast('添加成功', 'success');
            ['wishlistTitle', 'wishlistDesc', 'wishlistDate'].forEach(id => $(id).value = '');
            if (result.data) addToLocalList('wishlists', result.data);
            renderWishlistTable();
        }
        else showToast(result.error?.message || '添加失败', 'error');
    } catch (e) { showToast('网络错误，请稍后重试', 'error'); }
};

const renderExploreTable = () => {
    const items = siteData?.explores || [];
    const tbody = $('exploreTable');
    if (!items.length) { tbody.innerHTML = emptyState('fa-map-marked-alt', '暂无探索地点'); return; }
    tbody.innerHTML = items.map(item => `
        <tr><td>${utils.escapeHtml(item.title)}</td><td>${utils.escapeHtml(item.description || '-')}</td><td>${utils.formatDate(item.date) || '-'}</td>
        <td class="actions"><button class="btn btn-secondary btn-sm" onclick="editItem('explore',${item.id},'${utils.escapeJs(item.title)}','${utils.escapeJs(utils.formatDate(item.date))}','${utils.escapeJs(item.description || '')}')"><i class="fas fa-edit"></i></button>
        <button class="btn btn-danger btn-sm" onclick="deleteItem('explore',${item.id})"><i class="fas fa-trash"></i></button></td></tr>`).join('');
};

const handleExploreSubmit = async e => {
    e.preventDefault();
    const data = { title: $('exploreTitle').value.trim(), description: $('exploreDesc').value.trim() || null, date: $('exploreDate').value || null };
    try {
        const res = await csrfFetch(`${API_BASE}/explores`, { method: 'POST', credentials: 'include', body: JSON.stringify(data) });
        const result = await res.json();
        if (result.success) {
            showToast('添加成功', 'success');
            ['exploreTitle', 'exploreDesc', 'exploreDate'].forEach(id => $(id).value = '');
            if (result.data) addToLocalList('explores', result.data);
            renderExploreTable();
        }
        else showToast(result.error?.message || '添加失败', 'error');
    } catch (e) { showToast('网络错误，请稍后重试', 'error'); }
};

const renderPhotoGrid = () => {
    const photos = siteData?.photos || [];
    const grid = $('photoGrid');
    if (!photos.length) { grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1"><i class="fas fa-images"></i><p>暂无照片</p></div>'; return; }
    const isValidUrl = url => {
        if (!url) return false;
        try {
            const parsed = new URL(url);
            return ['http:', 'https:'].includes(parsed.protocol);
        } catch { return false; }
    };
    const safeUrl = url => isValidUrl(url) ? url : '';
    grid.innerHTML = photos.map(p => `
        <div class="photo-item"><img src="${safeUrl(p.url)}" alt="${utils.escapeHtml(p.caption || '')}" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 200%22><rect fill=%22%23333%22 width=%22200%22 height=%22200%22/><text x=%2250%%22 y=%2250%%22 fill=%22%23999%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2214%22>加载失败</text></svg>'">
        <p class="caption">${utils.escapeHtml(p.caption || '无说明')}</p>
        <div class="actions"><button class="btn btn-danger btn-sm" onclick="deleteItem('photo',${p.id})"><i class="fas fa-trash"></i></button></div></div>`).join('');
};

const handlePhotoUpload = async e => {
    e.preventDefault();
    const fileInput = $('photoFile'), caption = $('photoCaption').value.trim();
    if (!fileInput.files.length) { showToast('请选择要上传的图片', 'error'); return; }
    const formData = new FormData();
    formData.append('image', fileInput.files[0]);
    formData.append('caption', caption);
    try {
        const res = await csrfFetch(`${API_BASE}/photos`, { method: 'POST', credentials: 'include', body: formData });
        const result = await res.json();
        if (result.success) {
            showToast('上传成功', 'success');
            fileInput.value = '';
            $('photoCaption').value = '';
            if (result.data) addToLocalList('photos', result.data);
            renderPhotoGrid();
        }
        else showToast(result.error?.message || '上传失败', 'error');
    } catch (e) { showToast('网络错误，请稍后重试', 'error'); }
};

const handlePhotoUrl = async e => {
    e.preventDefault();
    const data = { url: $('photoUrl').value.trim(), caption: $('photoUrlCaption').value.trim(), source_type: 'url' };
    try {
        const res = await csrfFetch(`${API_BASE}/photos`, { method: 'POST', credentials: 'include', body: JSON.stringify(data) });
        const result = await res.json();
        if (result.success) {
            showToast('添加成功', 'success');
            $('photoUrl').value = '';
            $('photoUrlCaption').value = '';
            if (result.data) addToLocalList('photos', result.data);
            renderPhotoGrid();
        }
        else showToast(result.error?.message || '添加失败', 'error');
    } catch (e) { showToast('网络错误，请稍后重试', 'error'); }
};

const renderMusicForm = () => {
    if (!siteData?.music) return;
    const { source_type, source_url, backup_url, title, artist } = siteData.music;
    $$('input[name="sourceType"]').forEach(r => r.checked = r.value === source_type);
    const isLocal = source_type === 'local';
    const localPathGroup = $('localPathGroup');
    const urlPathGroup = $('urlPathGroup');
    if (localPathGroup) localPathGroup.style.display = isLocal ? 'block' : 'none';
    if (urlPathGroup) urlPathGroup.style.display = isLocal ? 'none' : 'block';
    if (isLocal) { const lp = $('localPath'); if (lp) lp.value = source_url; }
    else { const up = $('musicUrl'); if (up) up.value = source_url; }
    const bu = $('musicBackupUrl'); if (bu) bu.value = backup_url || '';
    const mt = $('musicTitle'); if (mt) mt.value = title || '';
    const ma = $('musicArtist'); if (ma) ma.value = artist || '';
    updateMusicPreview();
};

const updateMusicPreview = () => {
    const sourceTypeRadio = document.querySelector('input[name="sourceType"]:checked');
    if (!sourceTypeRadio) return;
    const sourceType = sourceTypeRadio.value;
    const urlInput = sourceType === 'local' ? $('localPath') : $('musicUrl');
    const url = urlInput ? urlInput.value.trim() : '';
    const preview = $('musicPreview'), audio = $('previewAudio');
    if (url) { preview.style.display = 'block'; audio.src = url; }
    else preview.style.display = 'none';
};

const handleMusicSubmit = async e => {
    e.preventDefault();
    const source_type = document.querySelector('input[name="sourceType"]:checked')?.value;
    if (!source_type) { showToast('请选择音乐来源类型', 'error'); return; }
    const source_url = source_type === 'local' ? $('localPath')?.value.trim() : $('musicUrl')?.value.trim();
    const data = {
        source_type,
        source_url: source_url || '',
        backup_url: $('musicBackupUrl')?.value.trim() || null,
        title: $('musicTitle')?.value.trim() || '',
        artist: $('musicArtist')?.value.trim() || ''
    };
    try {
        const res = await csrfFetch(`${API_BASE}/music`, { method: 'PUT', credentials: 'include', body: JSON.stringify(data) });
        const result = await res.json();
        if (result.success) {
            showToast('音乐设置已保存', 'success');
            if (siteData?.music) {
                Object.assign(siteData.music, data);
            }
            updateMusicPreview();
        }
        else showToast(result.error?.message || '保存失败', 'error');
    } catch (e) { showToast('网络错误，请稍后重试', 'error'); }
};

const exportData = async () => {
    try {
        const res = await csrfFetch(`${API_BASE}/export`, { method: 'POST', credentials: 'include' });
        if (!res.ok) throw new Error('导出失败');
        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a'); a.href = url; a.download = `love-backup-${new Date().toISOString().split('T')[0]}.json`;
        document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
        showToast('数据导出成功', 'success');
    } catch (e) { showToast('导出失败，请稍后重试', 'error'); }
};

const importData = async file => {
    if (!file) return;
    try {
        const text = await file.text();
        const json = JSON.parse(text);
        if (!json.data || !json.version) {
            showToast('无效的备份文件', 'error'); return;
        }
        const res = await csrfFetch(`${API_BASE}/import`, {
            method: 'POST',
            credentials: 'include',
            body: JSON.stringify(json)
        });
        const result = await res.json();
        if (result.success) {
            showToast('数据导入成功', 'success');
            loadAllData();
        } else {
            showToast(result.error?.message || '导入失败', 'error');
        }
    } catch (e) { showToast('导入失败，请稍后重试', 'error'); }
};

window.editItem = function(type, id, title, date, description) {
    const modal = $('editModal'), modalTitle = $('editModalTitle'), form = $('editForm');
    const safeType = ['anniversary', 'wishlist', 'explore', 'photo'].includes(type) ? type : 'anniversary';
    modalTitle.innerHTML = `<i class="fas ${TYPE_ICONS[safeType] || 'fa-star'}"></i> 编辑${TYPE_NAMES[safeType] || '内容'}`;
    form.innerHTML = `
        <div class="form-group"><label>标题</label><input type="text" class="form-input" id="editTitle" value="${utils.escapeHtml(title)}" required maxlength="200"></div>
        <div class="form-group"><label>日期（可选）</label><input type="date" class="form-input" id="editDate" value="${utils.escapeHtml(date)}"></div>
        <div class="form-group"><label>描述（可选）</label><input type="text" class="form-input" id="editDescription" value="${utils.escapeHtml(description)}" maxlength="500"></div>
        <div class="modal-actions"><button type="button" class="btn btn-secondary" onclick="closeEditModal()">取消</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存</button></div>`;
    form.onsubmit = async e => {
        e.preventDefault();
        const updateData = { title: $('editTitle').value.trim(), date: $('editDate').value || null, description: $('editDescription').value.trim() || null };
        try {
            const res = await csrfFetch(`${API_BASE}/${ENDPOINTS[safeType] || safeType + 's'}/${id}`, { method: 'PUT', credentials: 'include', body: JSON.stringify(updateData) });
            const result = await res.json();
            if (result.success) {
                showToast('更新成功', 'success');
                closeEditModal();
                updateInLocalList(safeType + 's', id, updateData);
                if (safeType === 'anniversary') renderAnniversaryTable();
                else if (safeType === 'wishlist') renderWishlistTable();
                else if (safeType === 'explore') renderExploreTable();
                else if (safeType === 'photo') renderPhotoGrid();
            }
            else showToast(result.error?.message || '更新失败', 'error');
        } catch (e) { showToast('网络错误，请稍后重试', 'error'); }
    };
    modal.classList.add('active');
};

window.closeEditModal = () => { $('editModal').classList.remove('active'); $('editForm').innerHTML = ''; };

const deleteItem = async (type, id) => {
    if (!confirm('确定要删除这条记录吗？')) return;
    try {
        const res = await csrfFetch(`${API_BASE}/${ENDPOINTS[type]}/${id}`, { method: 'DELETE', credentials: 'include' });
        const result = await res.json();
        if (result.success) {
            showToast('删除成功', 'success');
            removeFromLocalList(type === 'photo' ? 'photos' : type + 's', id);
            if (type === 'anniversary') renderAnniversaryTable();
            else if (type === 'wishlist') renderWishlistTable();
            else if (type === 'explore') renderExploreTable();
            else if (type === 'photo') renderPhotoGrid();
        }
        else showToast(result.error?.message || '删除失败', 'error');
    } catch (e) { showToast('网络错误，请稍后重试', 'error'); }
};

const switchSection = section => {
    const sectionEl = $(`section-${section}`);
    if (!sectionEl) return;
    if (sectionEl.dataset.role === 'admin' && !isAdmin) return;
    currentSection = section;
    $$('.tab').forEach(tab => tab.classList.toggle('active', tab.dataset.section === section));
    $$('.section').forEach(sec => sec.classList.toggle('active', sec.id === `section-${section}`));
};

window.showToast = (message, type = 'success') => {
    const toast = $('toast');
    toast.textContent = message;
    toast.className = `toast ${type}`;
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => toast.classList.remove('show'), 3000);
};

const renderAdminUserTable = () => {
    const tbody = $('adminUserTable');
    if (!tbody) return;
    const users = window.adminUsers || [];
    const currentUser = window.currentUsername || '';

    if (!users.length) { tbody.innerHTML = emptyState('fa-users', '暂无管理员'); return; }
    tbody.innerHTML = users.map(u => {
        const isCurrent = u.username === currentUser;
        return `<tr>
            <td>${u.id}</td>
            <td>${utils.escapeHtml(u.username)}${isCurrent ? ' <span class="badge-current">(当前账号)</span>' : ''}</td>
            <td>${new Date(u.created_at).toLocaleString('zh-CN')}</td>
            <td class="actions">
            ${isCurrent ? '' : '<button class="btn btn-danger btn-sm" onclick="deleteAdminUser(' + u.id + ')"><i class="fas fa-trash"></i></button>'}
            </td></tr>`;
    }).join('');
};

const handleAdminUserSubmit = async e => {
    e.preventDefault();
    const username = $('adminUsername').value.trim(), password = $('adminPassword').value;
    if (!username && !password) { showToast('请填写用户名或密码', 'warning'); return; }
    try {
        const res = await csrfFetch(`${API_BASE}/admin-users`, { method: 'PUT', credentials: 'include', body: JSON.stringify({ id: window.CURRENT_USER_ID, username, password }) });
        const result = await res.json();
        if (result.success) {
            showToast('修改成功', 'success');
            $('adminUsername').value = '';
            $('adminPassword').value = '';
        }
        else showToast(result.error?.message || '修改失败', 'error');
    } catch (e) { showToast('网络错误，请稍后重试', 'error'); }
};

window.showChangePasswordModal = userId => {
    const modal = $('editModal'), modalTitle = $('editModalTitle'), form = $('editForm');
    modalTitle.innerHTML = '<i class="fas fa-key"></i> 修改密码';
    form.innerHTML = `
        <div class="form-group"><label>新密码（至少8位）</label><input type="password" class="form-input" id="newPassword" required minlength="8" maxlength="100" placeholder="输入新密码"></div>
        <div class="modal-actions"><button type="button" class="btn btn-secondary" onclick="closeEditModal()">取消</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存</button></div>`;
    form.onsubmit = async e => {
        e.preventDefault();
        const newPassword = $('newPassword').value;
        try {
            const res = await csrfFetch(`${API_BASE}/admin-users/${userId}`, { method: 'PUT', credentials: 'include', body: JSON.stringify({ password: newPassword }) });
            const result = await res.json();
            if (result.success) { showToast('密码修改成功', 'success'); closeEditModal(); }
            else showToast(result.error?.message || '修改失败', 'error');
        } catch (e) { showToast('网络错误，请稍后重试', 'error'); }
    };
    modal.classList.add('active');
};

window.showChangeUsernameModal = (userId, currentUsername) => {
    const modal = $('editModal'), modalTitle = $('editModalTitle'), form = $('editForm');
    modalTitle.innerHTML = '<i class="fas fa-user-edit"></i> 修改用户名';
    form.innerHTML = `
        <div class="form-group"><label>新用户名</label><input type="text" class="form-input" id="newUsername" required maxlength="50" value="${utils.escapeHtml(currentUsername)}" placeholder="输入新用户名"></div>
        <div class="modal-actions"><button type="button" class="btn btn-secondary" onclick="closeEditModal()">取消</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存</button></div>`;
    form.onsubmit = async e => {
        e.preventDefault();
        const newUsername = $('newUsername').value.trim();
        if (!newUsername) { showToast('用户名不能为空', 'error'); return; }
        try {
            const res = await csrfFetch(`${API_BASE}/admin-users/${userId}`, { method: 'PUT', credentials: 'include', body: JSON.stringify({ username: newUsername }) });
            const result = await res.json();
            if (result.success) {
                showToast('用户名修改成功', 'success');
                closeEditModal();
                if (window.adminUsers) {
                    const user = window.adminUsers.find(u => u.id === userId);
                    if (user) user.username = newUsername;
                }
                renderAdminUserTable();
            }
            else showToast(result.error?.message || '修改失败', 'error');
        } catch (e) { showToast('网络错误，请稍后重试', 'error'); }
    };
    modal.classList.add('active');
};

const deleteAdminUser = async userId => {
    if (!confirm('确定要删除这个管理员账号吗？')) return;
    try {
        const res = await csrfFetch(`${API_BASE}/admin-users/${userId}`, { method: 'DELETE', credentials: 'include' });
        const result = await res.json();
        if (result.success) {
            showToast('删除成功', 'success');
            if (window.adminUsers) {
                const idx = window.adminUsers.findIndex(u => u.id === userId);
                if (idx !== -1) window.adminUsers.splice(idx, 1);
            }
            renderAdminUserTable();
        }
        else showToast(result.error?.message || '删除失败', 'error');
    } catch (e) { showToast('网络错误，请稍后重试', 'error'); }
};
