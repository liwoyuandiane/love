const escapeHtml = text => {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
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
    let hideTimer;
    return (msg, type = 'info', duration = 3000) => {
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toast-notification';
            toast.className = 'toast-notification';
            document.body.appendChild(toast);
        }
        toast.className = 'toast-notification toast-' + (['info', 'error', 'success', 'warning'].includes(type) ? type : 'info');
        toast.textContent = msg;
        requestAnimationFrame(() => { toast.classList.add('is-visible'); });
        clearTimeout(hideTimer);
        hideTimer = setTimeout(() => { toast.classList.remove('is-visible'); }, duration);
    };
})();

window.createToast = createToast;
window.utils = { escapeHtml, escapeJs, formatDate, formatDateTime, fetchWithRetry, debounce, throttle, createToast };
