<?php
/**
 * 前台首页
 */

// 检测安装
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    header('Location: /install/');
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
ensureSession();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我们的专属空间</title>
    <meta name="description" content="记录我们的爱情故事">
    <meta name="theme-color" content="#ff6b9d">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="专属空间">
    <link rel="manifest" href="/assets/manifest.json">
    <link rel="stylesheet" href="/assets/css/fonts.css">
    <link rel="stylesheet" href="/assets/css/fontawesome.css">
    <link rel="stylesheet" href="/assets/css/style.css?v=20260419">
</head>
<body>
    <script>
        window.CSRF_TOKEN = <?php echo json_encode(CSRF::generate()); ?>;
    </script>
    <a href="/admin.php" class="admin-link"><i class="fas fa-cog"></i> 管理</a>
    <button class="theme-toggle" id="themeToggle" title="切换主题">
        <i class="fas fa-moon"></i>
    </button>

    <div class="stars-container" id="stars-container"></div>
    <div class="hearts-container" id="hearts-container"></div>

    <div class="page-wrapper">
        <div class="container">
            <header>
                <h1 class="couple-names">
                    <span id="name1"></span> <span id="loveHeart" style="color: var(--primary); cursor: pointer; transition: transform 0.3s;" title="点击有惊喜!">❤</span> <span id="name2"></span>
                </h1>

                <div class="heart-divider">
                    <span class="line"></span>
                    <i class="fas fa-heart heart-icon"></i>
                    <span class="line"></span>
                </div>

                <div class="typewriter-container">
                    <p class="typewriter-quote" id="typewriter-quote"></p>
                    <span class="cursor" id="cursor"></span>
                </div>

                <div class="timer-section">
                    <p class="timer-title">在一起的美好时光</p>
                    <div class="timer" id="love-timer">
                        <div class="timer-unit">
                            <div class="timer-value" id="years">0</div>
                            <div class="timer-label">年</div>
                        </div>
                        <div class="timer-unit">
                            <div class="timer-value" id="months">0</div>
                            <div class="timer-label">月</div>
                        </div>
                        <div class="timer-unit">
                            <div class="timer-value" id="days">0</div>
                            <div class="timer-label">天</div>
                        </div>
                        <div class="timer-unit">
                            <div class="timer-value" id="hours">00</div>
                            <div class="timer-label">时</div>
                        </div>
                        <div class="timer-unit">
                            <div class="timer-value" id="minutes">00</div>
                            <div class="timer-label">分</div>
                        </div>
                        <div class="timer-unit">
                            <div class="timer-value" id="seconds">00</div>
                            <div class="timer-label">秒</div>
                        </div>
                    </div>
                </div>
                <div id="milestone-container"></div>
            </header>

            <section class="section fade-in">
                <div class="section-header" style="margin-top: 50px;">
                    <h2 class="section-title">纪念时间线</h2>
                    <p class="section-subtitle">我们的故事</p>
                </div>
                <div class="timeline-container" id="anniversary-timeline"></div>
            </section>

            <section class="section fade-in">
                <div class="section-header">
                    <h2 class="section-title">我们的故事</h2>
                    <p class="section-subtitle">记录每一个珍贵的瞬间</p>
                </div>
                <div class="feature-grid">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-calendar-heart"></i></div>
                        <h3>纪念日</h3>
                        <p>记录我们所有重要的日子</p>
                        <ul class="feature-list" id="anniversary-list"></ul>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-list-check"></i></div>
                        <h3>愿望清单</h3>
                        <p>一起规划未来的梦想</p>
                        <ul class="feature-list" id="wishlist-list"></ul>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-map-marked-alt"></i></div>
                        <h3>探索地点</h3>
                        <p>标记我们想去的地方</p>
                        <ul class="feature-list" id="explore-list"></ul>
                    </div>
                </div>
            </section>

            <section class="section photos-section fade-in">
                <div class="section-header">
                    <h2 class="section-title">记忆墙</h2>
                    <p class="section-subtitle">珍藏每一刻的美好</p>
                </div>
                <div class="photos-grid" id="photos-grid"></div>
            </section>
        </div>

        <footer>
            <p class="footer-names">
                <span id="footer-name1"></span> & <span id="footer-name2"></span>
            </p>
            <div class="footer-quote">
                真正的爱情故事永远不会结束，因为它们在心中永存。
            </div>
            <div class="footer-icp" id="footer-icp" style="margin-top: 10px; font-size: 0.8rem; color: rgba(255,255,255,0.5);"></div>
        </footer>
    </div>

    <div class="music-player">
        <button class="music-btn" id="play-btn">
            <i class="fas fa-play"></i>
        </button>
        <div class="music-info">
            <div class="music-title" id="music-title"></div>
            <div class="music-artist" id="music-artist"></div>
        </div>
        <div class="audio-error" id="audio-error"></div>
    </div>

    <audio id="love-song" loop>
        <source src="" type="audio/mpeg">
    </audio>

    <div class="lightbox" id="lightbox">
        <span class="lightbox-close" id="lightbox-close">&times;</span>
        <span class="lightbox-nav lightbox-prev" id="lightbox-prev"><i class="fas fa-chevron-left"></i></span>
        <span class="lightbox-nav lightbox-next" id="lightbox-next"><i class="fas fa-chevron-right"></i></span>
        <div class="lightbox-content">
            <img src="" alt="" id="lightbox-img">
        </div>
        <div class="lightbox-caption" id="lightbox-caption"></div>
    </div>

    <script src="/assets/js/utils.js"></script>
    <script src="/assets/js/main.js"></script>
</body>
</html>
