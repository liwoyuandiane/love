(() => {
    const meta = (name) => {
        const el = document.querySelector('meta[name="' + name + '"]');
        return el ? (el.getAttribute('content') || '') : '';
    };

    window.CSRF_TOKEN = meta('csrf-token');
    window.APP_VERSION = meta('app-version');
    window.GITHUB_REPO = meta('github-repo') || 'liwoyuandiane/love';
    window.CURRENT_USER_ROLE = meta('user-role') || 'user';
    window.CURRENT_USER_ID = parseInt(meta('user-id') || '0', 10) || 0;
    window.IS_ADMIN = meta('is-admin') === '1';

    try {
        const t = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', t === 'dark' ? 'dark' : 'light');
    } catch (e) { /* privacy mode */ }

    window.handleSessionExpiredResponse = async function (res) {
        let code = '';
        try {
            const body = await res.clone().json();
            code = (body && body.error && body.error.code) || '';
        } catch (e) { /* ignore */ }
        if (code !== 'SESSION_EXPIRED') return false;
        const msg = '登录已过期，请重新登录';
        if (typeof window.showToast === 'function') {
            window.showToast(msg, 'warning');
        } else if (typeof window.createToast === 'function') {
            window.createToast(msg, 'warning');
        } else {
            alert(msg);
        }
        const onAdmin = location.pathname.indexOf('/admin') !== -1;
        setTimeout(function () { location.href = onAdmin ? '/admin.php' : '/'; }, 600);
        return true;
    };

    window.csrfFetch = async function (url, options) {
        options = options || {};
        const headers = { ...(options.headers || {}) };
        if (window.CSRF_TOKEN) {
            headers['X-CSRF-Token'] = window.CSRF_TOKEN;
        }
        const res = await fetch(url, { ...options, headers });
        if (res.status === 401) {
            await window.handleSessionExpiredResponse(res);
        } else if (res.status === 403) {
            // CSRF token 2 小时 TTL 过期后长开页面的所有写操作都会 403，
            // 此前完全静默；给出明确提示引导用户刷新（刷新会重新生成 token）
            let code = '';
            try {
                const body = await res.clone().json();
                code = (body && body.error && body.error.code) || '';
            } catch (e) { /* ignore */ }
            if (code === 'CSRF_ERROR') {
                const msg = '安全校验已过期，请刷新页面后重试';
                if (typeof window.showToast === 'function') {
                    window.showToast(msg, 'warning');
                } else if (typeof window.createToast === 'function') {
                    window.createToast(msg, 'warning');
                }
            }
        }
        return res;
    };
})();
