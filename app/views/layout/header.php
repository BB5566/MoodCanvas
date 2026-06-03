<?php
// app/views/layout/header.php

if (!defined('APP_URL')) {
    require_once __DIR__ . '/../../../config/config.php';
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - AI 情緒日記' : 'MoodCanvas - AI 情緒日記'; ?></title>
    <meta name="description" content="<?php echo isset($metaDescription) ? htmlspecialchars($metaDescription) : 'MoodCanvas 結合 AI 與情緒日記，支援心情日曆、AI 圖文生成、RWD。'; ?>">
    <meta name="theme-color" content="#8B9A89">
    <meta property="og:title" content="<?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'MoodCanvas - AI 情緒日記'; ?>">
    <meta property="og:description" content="<?php echo isset($metaDescription) ? htmlspecialchars($metaDescription) : '用 AI 記錄每一天的心情，寫下日記，AI 自動生成專屬圖片與心情短語。'; ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo APP_URL; ?>/public/index.php">
    <meta property="og:locale" content="zh_TW">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/public/assets/css/style-v2.css?v=<?php echo filemtime(BASE_PATH . '/public/assets/css/style-v2.css'); ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🎨</text></svg>">
</head>

<body>

    <header class="glass-header">
        <nav class="navbar">
            <a class="navbar-brand" href="<?php echo APP_URL; ?>/public/index.php?action=home">🎨 MoodCanvas</a>
            <ul class="navbar-nav">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item nav-user-greeting">
                        <span>您好, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/public/index.php?action=diary_create">✏️ 寫日記</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/public/index.php?action=logout">👋 登出</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/public/index.php?action=login">登入</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/public/index.php?action=register">註冊</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main class="main-container">
        <script>
            window.__IS_LOGGED_IN__ = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
        </script>

        <?php if (!isset($_SESSION['user_id'])): ?>
            <div id="demo-banner" style="background:#fff3cd;border:1px solid #ffeeba;color:#856404;padding:10px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <div style="display:flex;gap:12px;align-items:center;">
                    <strong style="font-size:0.98rem;">示範模式（公開預覽）</strong>
                    <span style="font-size:0.92rem;color:#705b00;">您目前以訪客檢視範例日記；如需新增或刪除請先登入。為避免濫用 API，本範例僅提供閱讀功能。</span>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <button id="demo-banner-dismiss" style="background:transparent;border:1px solid #856404;color:#856404;padding:6px 10px;border-radius:6px;cursor:pointer;">我知道了</button>
                </div>
            </div>
            <script>
                (function() {
                    try {
                        var key = 'moodcanvas_demo_banner_hidden';
                        var banner = document.getElementById('demo-banner');
                        if (!banner) return;
                        if (localStorage && localStorage.getItem(key) === '1') {
                            banner.style.display = 'none';
                            return;
                        }
                        document.getElementById('demo-banner-dismiss').addEventListener('click', function() {
                            if (localStorage) localStorage.setItem(key, '1');
                            banner.style.display = 'none';
                        });
                    } catch (e) {}
                })();
            </script>
        <?php endif; ?>
