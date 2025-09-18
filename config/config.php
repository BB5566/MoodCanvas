<?php

/**
 * MoodCanvas 配置文件 - 修正版本
 * 移除對 mysqli 的依賴，使用 PDO
 */

// 開始 session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 載入 .env 檔案
function loadEnvFile($path)
{
    if (file_exists($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue; // 跳過註解行
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);

                // 處理行內註釋：移除 # 後的所有內容
                if (strpos($value, '#') !== false) {
                    $value = substr($value, 0, strpos($value, '#'));
                }

                $value = trim($value, '"\''); // 移除引號和空格

                // 設定環境變數
                if (!getenv($key)) { // 只有當環境變數不存在時才設定
                    putenv("{$key}={$value}");
                    $_ENV[$key] = $value;
                }
            }
        }
    }
}

// 載入 .env 檔案
loadEnvFile(dirname(__DIR__) . '/.env');
// 將資料庫設定定義成常數，確保 Diary.php 能正確取得
if (isset($_ENV['DB_HOST'])) define('DB_HOST', $_ENV['DB_HOST']);
if (isset($_ENV['DB_NAME'])) define('DB_NAME', $_ENV['DB_NAME']);
if (isset($_ENV['DB_USER'])) define('DB_USER', $_ENV['DB_USER']);
if (isset($_ENV['DB_PASS'])) define('DB_PASS', $_ENV['DB_PASS']);

// 基本路徑設定 (增加 !defined 檢查，使其可以安全地被多次引入)
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
if (!defined('APP_PATH')) {
    define('APP_PATH', BASE_PATH . '/app');
}
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', BASE_PATH . '/public');
}
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', BASE_PATH . '/config');
}
if (!defined('LOGS_PATH')) {
    define('LOGS_PATH', BASE_PATH . '/logs');
}
if (!defined('IMAGE_STORAGE_PATH')) {
    define('IMAGE_STORAGE_PATH', BASE_PATH . '/public/storage/generated_images');
}

// 應用程式設定
if (!defined('APP_NAME')) {
    define('APP_NAME', 'MoodCanvas');
}
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}

// 動態生成應用程式 URL，解決轉址與靜態資源路徑問題
if (!defined('APP_URL')) {
    // 優先從 .env 檔案讀取，如果沒有才動態生成
    $env_app_url = getenv('APP_URL');
    if ($env_app_url) {
        define('APP_URL', rtrim($env_app_url, '/'));
    } else {
        // 1. 協議 (http or https)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";

        // 2. 主機 (e.g., localhost or bb-made.com)
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // 3. 基礎路徑 (e.g., /MoodCanvas or /project/MoodCanvas)
        // 計算從網站根目錄到專案根目錄的路徑
        $basePath = dirname(dirname($_SERVER['SCRIPT_NAME']));
        $basePath = rtrim(str_replace('\\', '/', $basePath), '/'); // 清理路徑並移除結尾斜線

        define('APP_URL', $protocol . $host . $basePath);
    }
}

if (!defined('DEBUG')) {
    define('DEBUG', getenv('DEBUG') === 'true' ? true : true);
}
if (!defined('SESSION_NAME')) {
    define('SESSION_NAME', getenv('SESSION_NAME') ?: 'moodcanvas_session');
}

// 管理員密碼
if (!defined('ADMIN_PASSWORD')) {
    define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'admin@moodcanvas2024');
}

// 資料庫設定 (使用環境變數，若無則使用預設值)
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'moodcanvas_db');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') ?: '');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');
}

// 建立 PDO 連線函數
function getDbConnection()
{
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5, // 5秒連線超時
            PDO::ATTR_PERSISTENT => true, // 啟用持久連接
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        if (DEBUG) {
            // 記錄詳細錯誤訊息以便除錯
            $error_msg = "資料庫連線失敗: " . $e->getMessage();
            @error_log($error_msg);

            // 顯示更詳細的錯誤訊息供除錯
            echo "<div style='background:#ffebee;border:1px solid #f44336;padding:10px;margin:10px;'>";
            echo "<h3>資料庫連線錯誤</h3>";
            echo "<p><strong>主機:</strong> " . DB_HOST . ":" . DB_PORT . "</p>";
            echo "<p><strong>資料庫:</strong> " . DB_NAME . "</p>";
            echo "<p><strong>使用者:</strong> " . DB_USER . "</p>";
            echo "<p><strong>錯誤訊息:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>建議:</strong> 請檢查資料庫是否已建立、使用者權限是否正確</p>";
            echo "</div>";
        }
        return null;
    }
}

// API 設定 - 優先從環境變數讀取
if (!defined('OPENAI_API_KEY')) {
    define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: 'your_openai_api_key_here');
}
define('OPENAI_API_URL', 'https://api.openai.com/v1');
if (!defined('PERPLEXITY_API_KEY')) {
    define('PERPLEXITY_API_KEY', getenv('PERPLEXITY_API_KEY') ?: 'your_perplexity_api_key_here');
}
define('PERPLEXITY_API_URL', 'https://api.perplexity.ai');
if (!defined('STABILITY_API_KEY')) {
    define('STABILITY_API_KEY', getenv('STABILITY_API_KEY') ?: 'your_stability_api_key_here');
}
define('STABILITY_API_URL', 'https://api.stability.ai');
if (!defined('STABILITY_MODEL')) {
    define('STABILITY_MODEL', getenv('STABILITY_MODEL') ?: 'stable-diffusion-xl-1024-v1-0'); // 修正模型名稱
}

// AI 模型設定 (可透過環境變數覆蓋)
if (!defined('PERPLEXITY_MODEL')) {
    define('PERPLEXITY_MODEL', getenv('PERPLEXITY_MODEL') ?: 'llama-3.1-sonar-large-128k-online'); // 經過驗證的高性能模型
}
if (!defined('PERPLEXITY_FALLBACK_MODEL')) {
    define('PERPLEXITY_FALLBACK_MODEL', getenv('PERPLEXITY_FALLBACK_MODEL') ?: 'llama-3.1-sonar-small-128k-online'); // 經濟型備用模型
}
if (!defined('STABILITY_IMAGE_MODEL')) {
    define('STABILITY_IMAGE_MODEL', getenv('STABILITY_MODEL') ?: 'stable-diffusion-xl-1024-v1-0');
}

// 未來 Perplexity 圖像功能準備
define('PERPLEXITY_IMAGE_ENABLED', getenv('PERPLEXITY_IMAGE_ENABLED') === 'true' ? true : false);
if (!defined('PERPLEXITY_IMAGE_MODEL')) {
    define('PERPLEXITY_IMAGE_MODEL', getenv('PERPLEXITY_IMAGE_MODEL') ?: 'dalle-3');
}

// 安全設定
if (!defined('SECURITY_SALT')) {
    define('SECURITY_SALT', 'MoodCanvas_' . md5(APP_NAME . APP_VERSION));
}
if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 3600); // 1小時
}
if (!defined('CSRF_TOKEN_NAME')) {
    define('CSRF_TOKEN_NAME', 'csrf_token');
}

// 檔案上傳設定
if (!defined('UPLOAD_MAX_SIZE')) {
    define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
}
if (!defined('ALLOWED_IMAGE_TYPES')) {
    define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
}
if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', PUBLIC_PATH . '/storage/generated_images');
}

// 確保上傳目錄存在
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

// 確保日誌目錄存在
if (!file_exists(LOGS_PATH)) {
    mkdir(LOGS_PATH, 0755, true);
}

// 設定錯誤報告
if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', LOGS_PATH . '/error.log');
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// 自動載入函數
spl_autoload_register(function ($class) {
    $file = APP_PATH . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// 簡單的路由函數
function route($uri, $controller, $action = 'index')
{
    $current_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $base_path = '/project/MoodCanvas';
    $current_uri = str_replace($base_path, '', $current_uri);
    $current_uri = trim($current_uri, '/');

    if ($current_uri === trim($uri, '/')) {
        $controller_file = APP_PATH . '/controllers/' . $controller . '.php';
        if (file_exists($controller_file)) {
            require_once $controller_file;
            $controller_class = $controller;
            if (class_exists($controller_class)) {
                $instance = new $controller_class();
                if (method_exists($instance, $action)) {
                    return $instance->$action();
                }
            }
        }
        return false;
    }
    return false;
}

// 輔助函數
function url($path = '')
{
    return APP_URL . '/' . ltrim($path, '/');
}

function asset($path)
{
    return url('public/assets/' . ltrim($path, '/'));
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function view($template, $data = [])
{
    extract($data);
    $view_file = APP_PATH . '/views/' . $template . '.php';
    if (file_exists($view_file)) {
        ob_start();
        include $view_file;
        return ob_get_clean();
    }
    return "View not found: $template";
}

function generateCsrfToken()
{
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verifyCsrfToken($token)
{
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// 記錄函數 - 安全版本
function logMessage($message, $level = 'INFO')
{
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;

    // 安全的日誌寫入，避免權限錯誤導致程式中斷
    $log_file = LOGS_PATH . '/app.log';
    if (is_writable(dirname($log_file)) || (file_exists($log_file) && is_writable($log_file))) {
        @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    } else {
        // 備選：使用系統錯誤日誌
        @error_log("MoodCanvas [$level] $message");
    }
}

// 初始化完成標記
define('CONFIG_LOADED', true);

// 記錄配置載入
if (DEBUG) {
    logMessage('配置文件載入成功 - 修正版本');
}
