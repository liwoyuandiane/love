const escapeHtml = text => {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
};

const escapeJs = text => {
    if (!text) return '';
    return String(text)
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/"/g, '\\"')
        .replace(/\n/g, '\\n')
        .replace(/\r/g, '\\r')
        .replace(/</g, '\\x3C')
        .replace(/>/g, '\\x3E');
};

const formatDate = dateStr => {
    if (!dateStr) return '';
    return dateStr.includes('T') ? dateStr.split('T')[0] : dateStr;
};

const formatDateTime = dateStr => dateStr ? new Date(dateStr).toLocaleString('zh-CN') : '';

const fetchWithRetry = async (url, options = {}, retries = 3) => {
    for (let i = 0; i < retries; i++) {
        try { return await fetch(url, options); }
        catch (e) { if (i === retries - 1) throw e; await new Promise(r => setTimeout(r, 1000 * (i + 1))); }
    }
};

const debounce = (fn, wait) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), wait); }; };
const throttle = (fn, limit) => { let p = false; return (...a) => { if (!p) { fn(...a); p = true; setTimeout(() => p = false, limit); } }; };

const createToast = (() => {
    let toast;
    const colors = { info: 'rgba(100, 180, 255, 0.95)', error: 'rgba(255, 100, 100, 0.95)', success: 'rgba(100, 255, 150, 0.95)', warning: 'rgba(255, 200, 100, 0.95)' };
    return (msg, type = 'info', duration = 3000) => {
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toast-notification';
            toast.style.cssText = 'position:fixed;bottom:100px;left:50%;transform:translateX(-50%) translateY(20px);padding:14px 28px;border-radius:14px;z-index:2000;opacity:0;transition:all 0.4s ease;font-weight:500;max-width:90%;text-align:center;pointer-events:none';
            document.body.appendChild(toast);
        }
        toast.style.background = colors[type] || colors.info;
        toast.style.color = type === 'info' ? '#1a1a2e' : '#fff';
        toast.textContent = msg;
        requestAnimationFrame(() => { toast.style.opacity = '1'; toast.style.transform = 'translateX(-50%) translateY(0)'; });
        setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(-50%) translateY(20px)'; }, duration);
    };
})();

window.utils = { escapeHtml, escapeJs, formatDate, formatDateTime, fetchWithRetry, debounce, throttle, createToast };
