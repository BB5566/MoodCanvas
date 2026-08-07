<?php
// public/index.php — MoodCanvas v3
// 簡潔路由，CSRF 驗證，編輯功能

// 1) 全域錯誤處理器：最早載入，涵蓋後續所有階段的錯誤／例外
require_once __DIR__ . '/../app/helpers/error_handler.php';

// 2) 工作階段安全設定（Fix 1）：須在 session_start() 之前呼叫；
//    session_start() 實際於下方 config.php 內執行，會套用此處參數。
//    secure 依環境動態判斷：正式環境（HTTPS）→ true；本機 HTTP 開發 → false，
//    避免 cookie secure=true 在 HTTP 下導致 session 失效。
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') == 443)
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'httponly' => true,
    'secure'   => $isHttps,
    'samesite' => 'Strict',
    'lifetime' => 86400,
]);
session_name('__Secure-MoodCanvas-SID');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/autoloader.php';
require_once __DIR__ . '/../config/security.php';

// 3) 安全輔助層（CSRF / 輸入驗證 / 速率限制）
require_once __DIR__ . '/../app/helpers/csrf.php';
require_once __DIR__ . '/../app/helpers/validator.php';
require_once __DIR__ . '/../app/helpers/rate_limiter.php';

// 啟用基本安全標頭（nosniff / X-Frame-Options / Referrer-Policy / HSTS）
// 註：不啟用 initSecurity 的可疑活動偵測，避免 NAS 反向代理下誤擋
setSecurityHeaders();

// 速率限制（Fix 6）：每 60 秒最多 30 次請求，超過回傳 429。
// 此為獨立於 security.php（已停用之 100/hr）的較嚴格限制；採 X-Forwarded-For 感知 IP。
enforceRateLimit(30, 60);

if (!isset($_SESSION['user_id'])) {
    // 免登陸試用：為每位訪客建立獨立的 guest 帳號，資料彼此隔離，
    // 不會看到或刪除其他訪客的日記
    $guestId = (new \App\Models\User())->createGuest();
    if ($guestId) {
        $_SESSION['user_id'] = (int) $guestId;
        $_SESSION['username'] = 'guest_' . $guestId;
        $_SESSION['is_guest'] = true;
    } else {
        // 建立失敗的後援，仍可讓站台運作
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'guest';
    }
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
    case 'get_ai_insight':         (new DiaryController())->getAiInsight();        break;

    // 儀表板
    case 'dashboard':              (new DiaryController())->dashboard();  break;

    // 首頁
    case 'home':
    default:                       (new DiaryController())->index();      break;
}
