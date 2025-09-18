<?php
// app/views/layout/header.php

if (!defined('APP_URL')) {
    // Fallback if not defined by index.php
    require_once __DIR__ . '/../../../config/config.php';
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - AI 情緒日記' : 'MoodCanvas - AI 情緒日記'; ?></title>
    <meta name="description" content="<?php echo isset($metaDescription) ? htmlspecialchars($metaDescription) : 'MoodCanvas 是一個結合 AI 與情緒日記的現代化網頁應用，支援心情日曆、AI 圖文生成、RWD、個人化體驗。'; ?>">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/public/assets/css/style.css">
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
                <!--
                <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/public/index.php?action=admin">⚙️ 管理後台</a>
                    </li>
                <?php endif; ?>
                -->
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
