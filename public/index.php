<?php
// public/index.php

// 載入設定檔案與自訂的自動載入器
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/autoloader.php';

// Basic session validation: if a session user_id exists but not found in DB, clear session and redirect to login
if (isset($_SESSION['user_id'])) {
    try {
        $db = getDbConnection();
        if ($db) {
            $stmt = $db->prepare('SELECT id FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            if (!$user) {
                // clear invalid session and redirect to login
                session_unset();
                session_destroy();
                header('Location: index.php?action=login');
                exit;
            }
        }
    } catch (Exception $e) {
        // If DB check fails, we won't block the request here; just log and continue
        @error_log('Session validation error: ' . $e->getMessage());
    }
}

// 引入所有會用到的控制器
use App\Controllers\AuthController;
use App\Controllers\DiaryController;
use App\Controllers\AIController;

// 簡易路由
// 格式: http://your-domain/MoodCanvas/public/index.php?action=some_action
$action = $_GET['action'] ?? 'home';

// 根據 action 決定要載入哪個控制器和執行哪個方法
switch ($action) {
    // 認證相關
    case 'login':
        $controller = new AuthController();
        $controller->login();
        break;
    case 'register':
        $controller = new AuthController();
        $controller->register();
        break;
    case 'logout':
        $controller = new AuthController();
        $controller->logout();
        break;

    // 日記相關
    case 'diary_create':
        $controller = new DiaryController();
        $controller->create();
        break;
    case 'diary_detail':
        $controller = new DiaryController();
        $controller->show(); // 顯示單篇日記
        break;
    case 'diary_by_date':
        $controller = new DiaryController();
        $controller->showByDate(); // 顯示指定日期的所有日記
        break;
    case 'diary_store':
        $controller = new DiaryController();
        $controller->store();
        break;
    case 'diary_delete':
        $controller = new DiaryController();
        $controller->delete();
        break;
    case 'diary_quick_create': // 新增的 action
        $controller = new DiaryController();
        $controller->quickCreate();
        break;
    case 'dashboard': // 心情觀測儀表板
        $controller = new DiaryController();
        $controller->dashboard();
        break;

    // AI 相關
    case 'generate_image':
        $controller = new AIController();
        $controller->generateImage();
        break;
    case 'generate_text':
        $controller = new AIController();
        $controller->generateText();
        break;
    case 'get_ai_insight': // For dashboard AI analysis
        $controller = new AIController();
        $controller->getDashboardInsight();
        break;
    case 'generate_preview':
        $controller = new DiaryController();
        $controller->generatePreview();
        break;

    // 預設首頁 (日曆檢視)
    case 'home':
    default:
        // 只顯示日曆首頁
        $controller = new DiaryController();
        $controller->index(); // 顯示日曆
        break;
}
