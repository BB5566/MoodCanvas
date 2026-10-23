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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@300;400;500;700&family=Zen+Kaku+Gothic+New:wght@400;500;700&family=Klee+One:wght@400;600&family=Inter:wght@300;400;500;600&display=swap">
    <link rel="stylesheet" id="async-google-fonts" href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@300;400;500;700&family=Zen+Kaku+Gothic+New:wght@400;500;700&family=Klee+One:wght@400;600&family=Inter:wght@300;400;500;600&display=swap" media="print">
    <noscript>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@300;400;500;700&family=Zen+Kaku+Gothic+New:wght@400;500;700&family=Klee+One:wght@400;600&family=Inter:wght@300;400;500;600&display=swap">
    </noscript>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/public/assets/css/style-v2.css?v=<?php echo filemtime(BASE_PATH . '/public/assets/css/style-v2.css'); ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🎨</text></svg>">
</head>

<body>

    <style>
        /* 跳至主要內容連結：平時視覺隱藏，鍵盤聚焦時顯示 */
        .skip-link {
            position: absolute;
            left: -9999px;
            top: 0;
            z-index: 1000;
            padding: 0.6rem 1rem;
            background: #8B9A89;
            color: #fff;
            border-radius: 0 0 6px 0;
            text-decoration: none;
        }

        .skip-link:focus {
            left: 0;
        }
    </style>
    <a href="#main" class="skip-link">跳至主要內容</a>

    <header class="glass-header">
        <nav class="navbar" aria-label="主要導覽">
            <a class="navbar-brand" href="<?php echo APP_URL; ?>/public/index.php?action=home">🎨 MoodCanvas</a>
            <button class="navbar-toggler" id="navbar-toggler" type="button" aria-label="開啟選單" aria-expanded="false" aria-controls="navbar-nav">
                <span class="toggler-bar"></span>
                <span class="toggler-bar"></span>
                <span class="toggler-bar"></span>
            </button>
            <ul class="navbar-nav" id="navbar-nav">
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

    <main class="main-container" id="main" data-logged-in="<?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>">

        <?php if (!empty($_SESSION['is_guest'])): ?>
            <div id="demo-banner" class="demo-banner" role="region" aria-label="訪客模式提示">
                <div class="demo-banner-content">
                    <span style="font-size:0.92rem;"><strong>示範模式（訪客檢視）</strong>未註冊資料不保留，請登入以永久保存。</span>
                </div>
                <div class="demo-banner-actions">
                    <a href="<?php echo APP_URL; ?>/public/index.php?action=register" class="btn btn-primary demo-banner-cta">立即註冊 / 登入</a>
                    <button id="demo-banner-dismiss" class="demo-banner-dismiss" type="button" aria-label="關閉訪客模式提示" title="關閉">✕</button>
                </div>
            </div>
        <?php endif; ?>
