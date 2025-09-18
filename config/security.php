<?php
// config/security.php
// 外網部署安全配置

// 基本安全標頭
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // 如果使用HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// 速率限制
function checkRateLimit($ip = null) {
    if (!defined('RATE_LIMIT_ENABLED') || !RATE_LIMIT_ENABLED) {
        return true;
    }
    
    // 確保 LOG_PATH 已定義
    if (!defined('LOG_PATH')) {
        define('LOG_PATH', dirname(__DIR__) . '/logs');
    }
    
    $ip = $ip ?: ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']);
    $cache_file = LOG_PATH . '/rate_limit_' . md5($ip) . '.txt';
    
    $current_time = time();
    $requests = [];
    
    // 讀取現有請求記錄
    if (file_exists($cache_file)) {
        $content = file_get_contents($cache_file);
        $requests = $content ? explode("\n", trim($content)) : [];
    }
    
    // 清除一小時前的記錄
    $requests = array_filter($requests, function($timestamp) use ($current_time) {
        return ($current_time - (int)$timestamp) < 3600; // 1小時
    });
    
    // 檢查是否超過限制 (每小時100次請求)
    if (count($requests) >= 100) {
        http_response_code(429);
        die('Too Many Requests. Please try again later.');
    }
    
    // 記錄當前請求
    $requests[] = $current_time;
    file_put_contents($cache_file, implode("\n", $requests));
    
    return true;
}

// IP白名單檢查
function checkIPWhitelist() {
    if (!defined('ALLOWED_IPS')) {
        return true; // 如果沒有定義白名單，允許所有訪問
    }
    
    $allowed_ips = ALLOWED_IPS;
    if (empty($allowed_ips)) {
        return true;
    }
    
    $client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    
    if (!in_array($client_ip, $allowed_ips)) {
        http_response_code(403);
        die('Access Denied');
    }
    
    return true;
}

// 清理輸入數據
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    return $data;
}

// 檢查是否為可疑的請求
function detectSuspiciousActivity() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    
    // 檢查常見的攻擊模式
    $suspicious_patterns = [
        '/union.*select/i',
        '/script.*alert/i',
        '/<script/i',
        '/eval\(/i',
        '/base64_decode/i',
        '/wp-admin/i',
        '/admin\.php/i',
        '/phpMyAdmin/i'
    ];
    
    foreach ($suspicious_patterns as $pattern) {
        if (preg_match($pattern, $request_uri) || preg_match($pattern, $user_agent)) {
            // 記錄可疑活動
            error_log("Suspicious activity detected from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . 
                     " - URI: $request_uri - UA: $user_agent");
            
            http_response_code(403);
            die('Forbidden');
        }
    }
    
    return true;
}

// 初始化安全檢查
function initSecurity() {
    // 設定安全標頭
    setSecurityHeaders();
    
    // 檢查可疑活動
    detectSuspiciousActivity();
    
    // 檢查速率限制
    checkRateLimit();
    
    // 清理所有輸入
    $_GET = sanitizeInput($_GET);
    $_POST = sanitizeInput($_POST);
    $_COOKIE = sanitizeInput($_COOKIE);
}

// 如果定義了啟用安全檢查，則自動執行
if (defined('ENABLE_SECURITY_CHECKS') && ENABLE_SECURITY_CHECKS) {
    initSecurity();
}

?>
