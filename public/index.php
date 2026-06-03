<?php
// public/index.php — MoodCanvas v3
// 簡潔路由，CSRF 驗證，編輯功能

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/autoloader.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'guest';
}

use App\Controllers\AuthController;
use App\Controllers\DiaryController;

$action = $_GET['action'] ?? 'home';

switch ($action) {
    // 認證
    case 'login':       (new AuthController())->login();    break;
    case 'register':    (new AuthController())->register(); break;
    case 'logout':      (new AuthController())->logout();   break;

    // 日記
    case 'diary_create':           (new DiaryController())->create();     break;
    case 'diary_store':            (new DiaryController())->store();      break;
    case 'diary_detail':           (new DiaryController())->show();       break;
    case 'diary_edit':             (new DiaryController())->edit();       break;
    case 'diary_delete':           (new DiaryController())->delete();     break;
    case 'diary_by_date':          (new DiaryController())->showByDate(); break;
    case 'diary_quick_create':     (new DiaryController())->quickCreate();break;

    // AI
    case 'generate_card_content':  (new DiaryController())->generateCardContent(); break;

    // 儀表板
    case 'dashboard':              (new DiaryController())->dashboard();  break;

    // 首頁
    case 'home':
    default:                       (new DiaryController())->index();      break;
}
