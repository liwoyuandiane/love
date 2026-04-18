document.addEventListener('DOMContentLoaded', function() {
    const API_BASE = '/api';
    const TYPE_ICONS = {
        anniversary: 'fa-calendar-heart',
        birthday: 'fa-birthday-cake',
        wedding: 'fa-ring',
        other: 'fa-star'
    };
    const COLORS = ['#ff6b9d', '#ffa8c5', '#f8b500', '#c44569', '#ff4777'];
    const EMOJIS = ['❤','⭐','💕','🌟','💖'];

    const CACHE_KEY = 'siteData';
    const CACHE_TTL = 300000;
    const SYNC_INTERVAL = 60000;

    let siteData = null;
    let syncTimer = null;
    let timerInterval = null;
    let currentPhotoIndex = 0;
    let photos = [];
    let lastDataHash = null;

    const LOVE_QUOTES = [
        "愿得一人心，白首不相离","情不知所起，一往而深","入目无他人，四下皆是你",
        "山河远阔，人间烟火，无一是你，无一不是你","既见君子，云胡不喜",
        "愿我如星君如月，夜夜流光相皎洁",
        "两情若是久长时，又岂在朝朝暮暮","身无彩凤双飞翼，心有灵犀一点通",
        "此情可待成追忆，只是当时已惘然","天长地久有时尽，此恨绵绵无绝期",
        "心似双丝网，中有千千结","愿君多采撷，此物最相思","只愿君心似我心，定不负相思意",
        "浮世万千，吾爱有三，日月与卿","日为朝，月为暮，卿为朝朝暮暮",
        "一生一世一双人，半醉半醒半浮生","你是我的半截诗，不许别人改一个字","我跨过山，涉过水，见过万物复苏",
        "往后余生，风雪是你，平淡是你","心跳多久，爱你多久","遇见你是最美的意外","爱你是我这辈子唯一的事业",
        "有你在身边，就是最好的未来","海底月是天上月，眼前人是心上人","风里雨里，我在等你",
        "时光不染，回忆不淡","你是我生命中最美的遇见","愿与你共赴天涯海角","相思成灾，念你如初",
        "三生有幸遇见你，纵使悲凉也是情","以你之名，冠我之姓","入骨相思知不知","愿我如烟愿你如月",
        "慕尔如星鹊桥相会","温一壶月光下酒","与你共饮长江水","今夕何夕，见此良人",
        "桃之夭夭，灼灼其华","青青子衿，悠悠我心","纵我不往，子宁不嗣音","投我以木桃，报之以琼瑶",
        "关关雎鸠，在河之洲","蒹葭苍苍，白露为霜","所谓伊人，在水一方"
    ];

    const SURPRISE_MESSAGES = ['我爱你！','你是我的唯一！','永远在一起！','么么哒！',' Love You! ',' sweetie! ',' forever! ','想你了！','嫁给我吧！'];
    const SURPRISE_ICONS = ['fa-heart', 'fa-star', 'fa-kiss', 'fa-rose', 'fa-gem'];

    const $ = id => document.getElementById(id);
    const $$ = sel => document.querySelectorAll(sel);
    const escape = utils.escapeHtml;

    const csrfFetch = async (url, options = {}) => {
        const headers = { ...options.headers };
        if (window.CSRF_TOKEN) {
            headers['X-CSRF-Token'] = window.CSRF_TOKEN;
        }
        return fetch(url, { ...options, headers });
    };

    function initTheme() {
        const saved = localStorage.getItem('theme') || 'auto';
        applyTheme(saved);
        $('themeToggle')?.addEventListener('click', cycleTheme);
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            if (localStorage.getItem('theme') === 'auto') applyTheme('auto');
        });
    }

    function applyTheme(theme) {
        const actual = theme === 'auto'
            ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
            : theme;
        document.documentElement.setAttribute('data-theme', actual);
        localStorage.setItem('theme', theme);
        const icon = $('themeToggle')?.querySelector('i');
        const btn = $('themeToggle');
        const labels = { light: '浅色模式', dark: '深色模式', auto: '自动模式' };
        if (icon) icon.className = { light: 'fas fa-moon', dark: 'fas fa-sun', auto: 'fas fa-adjust' }[theme];
        if (btn) btn.title = labels[theme] || '切换主题';
    }

    function cycleTheme() {
        const themes = ['light', 'dark', 'auto'];
        const current = localStorage.getItem('theme') || 'auto';
        applyTheme(themes[(themes.indexOf(current) + 1) % themes.length]);
    }

    function loadCachedData() {
        try {
            const cached = localStorage.getItem(CACHE_KEY);
            if (cached) {
                const { data, timestamp } = JSON.parse(cached);
                if (Date.now() - timestamp < CACHE_TTL) {
                    return data;
                }
            }
        } catch (e) { }
        return null;
    }

    function cacheData(data) {
        try {
            localStorage.setItem(CACHE_KEY, JSON.stringify({
                data,
                timestamp: Date.now()
            }));
        } catch (e) { }
    }

    async function syncData() {
        try {
            const res = await fetch(`${API_BASE}/data`);
            if (res.status === 401) return;
            const result = await res.json();
            if (result.success) {
                const newData = result.data;
                const newHash = JSON.stringify(newData);
                if (newHash !== lastDataHash) {
                    siteData = newData;
                    photos = newData.photos || [];
                    lastDataHash = newHash;
                    cacheData(newData);
                    renderLists();
                    renderPhotos();
                    renderMusic();
                }
            }
        } catch (e) { }
    }

    function startBackgroundSync() {
        if (syncTimer) clearInterval(syncTimer);
        syncTimer = setInterval(syncData, SYNC_INTERVAL);
    }

    function renderWithData() {
        initTheme();
        renderCoupleInfo();
        renderFooterIcp();
        renderLists();
        renderPhotos();
        renderMusic();
        startTimer();
        initBackgroundEffects();
        initTypewriter();
        initScrollAnimations();
        initLightbox();
        initSurprise();
        setupMusicPlayer();
        startBackgroundSync();
        setTimeout(() => syncData(), 500);
    }

    async function loadSiteData() {
        try {
            const res = await fetch(`${API_BASE}/data`);
            if (res.status === 401) {
                siteData = {};
                renderWithData();
                return;
            }
            const result = await res.json();
            if (result.success) {
                const newData = result.data;
                const newHash = JSON.stringify(newData);
                if (newHash !== lastDataHash) {
                    siteData = newData;
                    photos = newData.photos || [];
                    lastDataHash = newHash;
                    cacheData(newData);
                    renderWithData();
                }
            } else {
                siteData = {};
                renderWithData();
            }
        } catch (e) {
            const cached = loadCachedData();
            if (cached) {
                siteData = cached;
                photos = cached.photos || [];
                renderWithData();
            } else {
                siteData = {};
                renderWithData();
            }
        }
    }

    function renderCoupleInfo() {
        if (!siteData?.coupleInfo) return;
        const { name1, name2, anniversary } = siteData.coupleInfo;
        const fill = (id, val) => { const el = $(id); if (el) el.textContent = val || ''; };
        fill('name1', name1); fill('name2', name2);
        fill('footer-name1', name1); fill('footer-name2', name2);
        const date = anniversary?.split('T')[0] || '';
        fill('anniversary-date', date);
        if (anniversary) {
            const annDate = new Date(anniversary);
            if (isNaN(annDate.getTime())) return;
            const days = Math.ceil(Math.abs(Date.now() - annDate) / 86400000);
            fill('days-counter', days);
            renderMilestoneBadge(days);
        }
    }

    function renderFooterIcp() {
        if (!siteData?.settings) return;
        const { icp_code, police_record_code, site_name } = siteData.settings;
        const footerIcp = $('footer-icp');

        if (site_name) {
            document.title = site_name + ' - 我们的专属空间';
        }

        if (!footerIcp) return;

        let icpHtml = '';
        if (icp_code) {
            icpHtml += `<a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener" style="color: inherit;">${icp_code}</a>`;
        }
        if (police_record_code) {
            if (icpHtml) icpHtml += ' | ';
            icpHtml += `<a href="https://www.beian.gov.cn/" target="_blank" rel="noopener" style="color: inherit;">${police_record_code}</a>`;
        }
        footerIcp.innerHTML = icpHtml;
    }

    function renderMilestoneBadge(days) {
        const milestones = [
            { days: 100, icon: '🎉', label: '100天纪念' },
            { days: 365, icon: '🎊', label: '1周年' },
            { days: 500, icon: '🌟', label: '500天纪念' },
            { days: 1000, icon: '💝', label: '1000天纪念' },
            { days: 1825, icon: '💖', label: '5周年' },
            { days: 3650, icon: '💕', label: '10周年' }
        ];
        const m = milestones.find(o => o.days === days);
        $('milestone-container').innerHTML = m ? `<div class="milestone-badge"><span>${m.icon}</span><span>${m.label}</span></div>` : '';
    }

    function renderLists() {
        if (!siteData) return;
        renderAnniversaryList();
        renderWishlistList();
        renderExploreList();
    }

    function emptyState(icon, msg) {
        return `<li class="empty-state" style="padding:20px;text-align:center;color:var(--text-muted)"><i class="fas ${icon}" style="font-size:1.5rem;opacity:0.5"></i><p style="margin-top:10px;font-size:0.9rem">${msg}</p></li>`;
    }

    function renderAnniversaryList() {
        const list = $('anniversary-list');
        const items = siteData.anniversaries || [];
        if (!items.length) { list.innerHTML = emptyState('fa-calendar-heart','暂无纪念日'); return; }
        list.innerHTML = items.map(item => `
            <li><span class="item-title"><i class="fas ${TYPE_ICONS[item.type]}" style="margin-right:8px;color:var(--primary)"></i>${escape(item.title)}</span>
            ${item.date ? `<span class="item-date">${item.date.split('T')[0]}</span>` : ''}</li>`).join('');
        renderAnniversaryTimeline(items);
    }

    function renderAnniversaryTimeline(items) {
        const container = $('anniversary-timeline');
        if (!container) return;
        if (!items.length) { container.innerHTML = '<div class="timeline-empty"><i class="fas fa-clock"></i><p>暂无时间线</p></div>'; return; }
        const sorted = [...items].sort((a, b) => a.date && b.date ? new Date(b.date) - new Date(a.date) : 1);
        container.innerHTML = sorted.map((item, i) => `
            <div class="timeline-item" style="animation-delay:${i*0.1}s">
                <div class="timeline-marker"></div>
                <div class="timeline-content">
                    <span class="timeline-date"><i class="fas ${TYPE_ICONS[item.type]}" style="margin-right:6px"></i>${item.date ? item.date.split('T')[0] : '日期待定'}</span>
                    <span class="timeline-title">${escape(item.title)}</span>
                    ${item.description ? `<p class="timeline-desc">${escape(item.description)}</p>` : ''}
                </div>
            </div>`).join('');
    }

    function renderWishlistList() {
        const list = $('wishlist-list');
        const items = siteData.wishlists || [];
        if (!items.length) { list.innerHTML = emptyState('fa-list-check','暂无愿望'); return; }
        list.innerHTML = items.map(item => `
            <li class="${item.completed ? 'completed' : ''}" onclick="toggleWishlistCompletion(${item.id})">
                <span class="item-title">${escape(item.title)}</span>
                <i class="${item.completed ? 'fas fa-check-circle' : 'far fa-circle'}" style="color:${item.completed ? 'var(--success)' : 'var(--primary-light)'};font-size:0.9rem"></i>
            </li>`).join('');
    }

    window.toggleWishlistCompletion = async function(id) {
        try {
            const res = await csrfFetch(`${API_BASE}/wishlists/${id}/toggle`, { method: 'POST', credentials: 'include' });
            if (res.ok) {
                const result = await res.json();
                if (result.success && result.data) {
                    const item = siteData.wishlists.find(w => w.id === id);
                    if (item) {
                        item.completed = result.data.completed;
                        item.completed_at = result.data.completed_at;
                    }
                    renderWishlistList();
                }
            }
        } catch (e) { console.error('Toggle wishlist error:', e); }
    };

    function renderExploreList() {
        const list = $('explore-list');
        const items = siteData.explores || [];
        if (!items.length) { list.innerHTML = emptyState('fa-map-marked-alt','暂无探索地点'); return; }
        list.innerHTML = items.map(item => `
            <li><span class="item-title">${escape(item.title)}</span><i class="fas fa-map-pin" style="color:var(--primary-light);font-size:0.85rem"></i></li>`).join('');
    }

    function renderPhotos() {
        const grid = $('photos-grid');
        if (!photos.length) { grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1"><i class="fas fa-images"></i><p>暂无照片</p></div>'; return; }
        const isValidUrl = url => {
            if (!url) return false;
            try {
                const parsed = new URL(url);
                return ['http:', 'https:'].includes(parsed.protocol);
            } catch { return false; }
        };
        const safeUrl = url => isValidUrl(url) ? url : '';
        grid.innerHTML = photos.map((photo, i) => `
            <div class="photo-card" data-index="${i}" onclick="openLightbox(${i})" style="animation-delay:${i*0.05}s">
                <img src="${safeUrl(photo.url)}" alt="${escape(photo.caption || '')}" loading="lazy" onerror="handleImageError(this,'${escape(safeUrl(photo.url))}')" data-url="${escape(safeUrl(photo.url))}">
                <div class="photo-overlay"><p class="photo-caption">${escape(photo.caption || '')}</p></div>
                <button class="photo-retry-btn" onclick="retryImage(this)" style="display:none" title="重试加载"><i class="fas fa-redo"></i></button>
            </div>`).join('');
    }

    window.handleImageError = function(img, url) {
        img.style.display = 'none';
        const retryBtn = img.closest('.photo-card')?.querySelector('.photo-retry-btn');
        if (retryBtn) retryBtn.style.display = 'flex';
    };

    window.retryImage = function(btn) {
        const img = btn.closest('.photo-card').querySelector('img');
        img.src = img.dataset.url + '?t=' + Date.now();
        img.style.display = 'block';
        btn.style.display = 'none';
    };

    function renderMusic() {
        if (!siteData?.music) return;
        const { source_url, backup_url, title, artist } = siteData.music;
        const audio = $('love-song');
        const isValidUrl = url => {
            if (!url) return false;
            try {
                const parsed = new URL(url);
                return ['http:', 'https:'].includes(parsed.protocol);
            } catch { return false; }
        };
        const safeUrl = url => isValidUrl(url) ? url : '';
        audio.dataset.primaryUrl = safeUrl(source_url);
        audio.dataset.backupUrl = safeUrl(backup_url) || '';
        audio.src = safeUrl(source_url);
        audio.load();
        $('music-title').textContent = title || '音乐';
        $('music-artist').textContent = artist || '';
    }

    function startTimer() {
        if (!siteData?.coupleInfo) return;
        const [y, mo, d] = siteData.coupleInfo.anniversary.split('T')[0].split('-').map(Number);
        const startDate = new Date(y, mo - 1, d, 0, 0, 0);
        let totalSeconds = Math.floor((Date.now() - startDate.getTime()) / 1000);
        if (totalSeconds < 0) totalSeconds = 0;

        const MS_PER_MINUTE = 60;
        const MS_PER_HOUR = MS_PER_MINUTE * 60;
        const MS_PER_DAY = MS_PER_HOUR * 24;
        const MS_PER_MONTH = MS_PER_DAY * 30.44;
        const MS_PER_YEAR = MS_PER_DAY * 365.25;

        function update() {
            totalSeconds++;
            let remaining = totalSeconds;

            const years = Math.floor(remaining / MS_PER_YEAR);
            remaining -= years * MS_PER_YEAR;
            const months = Math.floor(remaining / MS_PER_MONTH);
            remaining -= months * MS_PER_MONTH;
            const days = Math.floor(remaining / MS_PER_DAY);
            remaining -= days * MS_PER_DAY;
            const hours = Math.floor(remaining / MS_PER_HOUR);
            remaining -= hours * MS_PER_HOUR;
            const minutes = Math.floor(remaining / MS_PER_MINUTE);
            const seconds = remaining - minutes * MS_PER_MINUTE;

            const set = (id, v) => { const el = $(id); if (el) el.textContent = v };
            set('years', years); set('months', months); set('days', days);
            set('hours', String(hours).padStart(2,'0')); set('minutes', String(minutes).padStart(2,'0')); set('seconds', String(seconds).padStart(2,'0'));
        }

        update();
        timerInterval = setInterval(update, 1000);
    }

    function initBackgroundEffects() {
        const stars = $('stars-container');
        for (let i = 0; i < 50; i++) {
            const star = document.createElement('div');
            star.className = 'star';
            star.style.cssText = `left:${Math.random()*100}%;top:${Math.random()*100}%;--duration:${2+Math.random()*3}s;--delay:${Math.random()*3}s;--opacity:${0.3+Math.random()*0.5}`;
            stars.appendChild(star);
        }

        const hearts = $('hearts-container');
        if (!hearts.children.length) {
            const icons = ['❤','💕','💖','💗','💓','💝','💘','💞'];
            for (let i = 0; i < 15; i++) {
                const h = document.createElement('div');
                h.className = 'floating-heart';
                h.textContent = icons[Math.floor(Math.random() * icons.length)];
                h.style.cssText = `left:${Math.random()*100}%;--duration:${12+Math.random()*10}s;--delay:${Math.random()*10}s;--size:${12+Math.random()*14}px`;
                hearts.appendChild(h);
            }
        }
    }

    function initTypewriter() {
        const el = $('typewriter-quote');
        const cursor = $('cursor');
        let quote = LOVE_QUOTES[Math.floor(Math.random() * LOVE_QUOTES.length)];
        let idx = 0, deleting = false, running = true, timeoutId = null;

        function type() {
            if (!running || document.hidden) return;
            if (!deleting && idx <= quote.length) {
                el.textContent = quote.substring(0, idx++);
                timeoutId = setTimeout(type, 100);
            } else if (deleting && idx >= 0) {
                el.textContent = quote.substring(0, idx--);
                timeoutId = setTimeout(type, 50);
            } else if (idx === quote.length + 1) {
                deleting = true;
                cursor.style.display = 'none';
                timeoutId = setTimeout(type, 5200);
            } else if (idx === -1) {
                deleting = false;
                cursor.style.display = 'inline-block';
                quote = LOVE_QUOTES[Math.floor(Math.random() * LOVE_QUOTES.length)];
                idx = 0;
                timeoutId = setTimeout(type, 500);
            }
        }

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) { clearTimeout(timeoutId); running = false; }
            else if (!running) { running = true; type(); }
        });
        type();
    }

    function initScrollAnimations() {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
        }, { threshold: 0.1 });
        $$('.fade-in').forEach(el => observer.observe(el));
    }

    function initLightbox() {
        $('lightbox-close')?.addEventListener('click', closeLightbox);
        $('lightbox-prev')?.addEventListener('click', () => navigateLightbox(-1));
        $('lightbox-next')?.addEventListener('click', () => navigateLightbox(1));
        $('lightbox')?.addEventListener('click', e => { if (e.target.id === 'lightbox') closeLightbox(); });
        document.addEventListener('keydown', e => {
            if (!$('lightbox')?.classList.contains('active')) return;
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') navigateLightbox(-1);
            if (e.key === 'ArrowRight') navigateLightbox(1);
        });
    }

    window.openLightbox = function(idx) {
        if (!photos.length) return;
        currentPhotoIndex = idx;
        const photo = photos[idx];
        const isValidUrl = url => {
            if (!url) return false;
            try {
                const parsed = new URL(url);
                return ['http:', 'https:'].includes(parsed.protocol);
            } catch { return false; }
        };
        $('lightbox-img').src = isValidUrl(photo.url) ? photo.url : '';
        $('lightbox-caption').textContent = photo.caption || '';
        $('lightbox').classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    function closeLightbox() {
        $('lightbox')?.classList.remove('active');
        document.body.style.overflow = '';
    }

    function navigateLightbox(dir) {
        currentPhotoIndex = (currentPhotoIndex + dir + photos.length) % photos.length;
        const photo = photos[currentPhotoIndex];
        const isValidUrl = url => {
            if (!url) return false;
            try {
                const parsed = new URL(url);
                return ['http:', 'https:'].includes(parsed.protocol);
            } catch { return false; }
        };
        $('lightbox-img').src = isValidUrl(photo.url) ? photo.url : '';
        $('lightbox-caption').textContent = photo.caption || '';
    }

    function initSurprise() {
        const heart = $('loveHeart');
        if (!heart) return;

        let lastTriggerTime = 0;
        const THROTTLE_MS = 300;
        const MAX_PARTICLES = 300;
        const PARTICLES_PER_TRIGGER = 20;

        function getCurrentParticleCount() {
            return document.querySelectorAll('.particle').length;
        }

        function trigger(e) {
            e.preventDefault();
            e.stopPropagation();

            const now = Date.now();
            if (now - lastTriggerTime < THROTTLE_MS) return;
            lastTriggerTime = now;

            const currentCount = getCurrentParticleCount();
            if (currentCount >= MAX_PARTICLES) return;

            const toCreate = Math.min(PARTICLES_PER_TRIGGER, MAX_PARTICLES - currentCount);
            const msg = SURPRISE_MESSAGES[Math.floor(Math.random() * SURPRISE_MESSAGES.length)];
            const icon = SURPRISE_ICONS[Math.floor(Math.random() * SURPRISE_ICONS.length)];
            const rect = heart.getBoundingClientRect();
            const cx = rect.left + rect.width / 2;
            const cy = rect.top + rect.height / 2;
            for (let i = 0; i < toCreate; i++) createParticle(cx, cy, icon);
            createFloatingText(cx, cy, msg);
        }

        heart.addEventListener('click', trigger);
        heart.addEventListener('touchend', e => { e.preventDefault(); trigger(e); });
        heart.addEventListener('mouseenter', () => heart.style.transform = 'scale(1.3)');
        heart.addEventListener('mouseleave', () => heart.style.transform = 'scale(1)');
    }

    function createFloatingText(x, y, text) {
        const f = document.createElement('div');
        f.className = 'surprise-floater';
        f.textContent = text;
        f.style.cssText = `left:${x}px;top:${y}px`;
        document.body.appendChild(f);
        f.animate([
            { transform: 'translate(-50%,-50%) scale(0.5)', opacity: 1 },
            { transform: 'translate(-50%,-200%) scale(1.5)', opacity: 0 }
        ], { duration: 2000, easing: 'ease-out' });
        setTimeout(() => f.remove(), 2000);
    }

    function createParticle(x, y, iconClass) {
        const p = document.createElement('div');
        p.className = 'particle';
        p.innerHTML = Math.random() > 0.3
            ? `<i class="fas ${iconClass}" style="font-size:${2+Math.random()*2}rem;color:${COLORS[Math.floor(Math.random()*COLORS.length)]}"></i>`
            : `<span style="font-size:${1.5+Math.random()*1.5}rem;color:${COLORS[Math.floor(Math.random()*COLORS.length)]}">${EMOJIS[Math.floor(Math.random()*EMOJIS.length)]}</span>`;
        const angle = Math.random() * Math.PI * 2;
        const vel = 150 + Math.random() * 200;
        const endX = Math.cos(angle) * vel;
        const endY = Math.sin(angle) * vel - 100;
        const startTransform = 'translate(-50%,-50%) scale(1)';
        const endTransform = `translate(${endX}px,${endY}px) scale(0)`;
        p.style.cssText = `left:${x}px;top:${y}px`;
        document.body.appendChild(p);
        p.animate([
            { transform: startTransform, opacity: 1 },
            { transform: endTransform, opacity: 0 }
        ], { duration: 1500, easing: 'ease-out' });
        setTimeout(() => p.remove(), 1500);
    }

    function setupMusicPlayer() {
        const audio = $('love-song');
        const playBtn = $('play-btn');
        const playIcon = playBtn.querySelector('i');
        const audioErr = $('audio-error');
        let isPlaying = false, useBackup = false;

        audio.addEventListener('error', function() {
            const backup = audio.dataset.backupUrl;
            if (backup && !useBackup) { useBackup = true; audio.src = backup; audio.load(); audio.play().catch(()=>{}); return; }
            audioErr.textContent = '音乐加载失败';
            audioErr.style.display = 'block';
            setTimeout(() => audioErr.style.display = 'none', 3000);
        });

        playBtn.addEventListener('click', function() {
            if (isPlaying) { audio.pause(); playIcon.classList.remove('fa-pause'); playIcon.classList.add('fa-play'); }
            else {
                audio.play().catch(() => { audioErr.textContent = '播放失败'; audioErr.style.display = 'block'; setTimeout(() => audioErr.style.display = 'none', 3000); });
                playIcon.classList.remove('fa-play'); playIcon.classList.add('fa-pause');
            }
            isPlaying = !isPlaying;
        });
    }

    loadSiteData();
});
