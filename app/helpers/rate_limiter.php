<?php
/**
 * app/helpers/rate_limiter.php
 *
 * 以檔案為基礎的每 IP 速率限制：預設每 60 秒 30 次請求，超過即回傳 HTTP 429。
 *
 * - 使用 flock() 共享／獨佔鎖避免競態條件。
 * - 檔案操作加上 @ 抑制，與全域錯誤處理器（會把警告轉為例外）相容。
 * - 反向代理（NAS 部署）下優先採用 X-Forwarded-For，但限定可信任代理。
 */

if (!function_exists('rateLimitClientIp')) {
    /**
     * 取得用於速率限制的客戶端 IP。
     * @return string
     */
    function rateLimitClientIp() {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $trustedProxies = defined('TRUSTED_PROXIES') ? TRUSTED_PROXIES : ['127.0.0.1', '::1'];

        if (in_array($remoteAddr, $trustedProxies) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            for ($i = count($parts) - 1; $i >= 0; $i--) {
                $ip = trim($parts[$i]);
                if (!in_array($ip, $trustedProxies)) {
                    return $ip;
                }
            }
            return trim($parts[0]);
        }

        return $remoteAddr;
    }
}

if (!function_exists('enforceRateLimit')) {
    /**
     * 強制速率限制；超過上限時送出 429 並結束程式。
     *
     * @param int $maxRequests 視窗內允許的最大請求數（預設 30）
     * @param int $window      視窗秒數（預設 60）
     * @return bool 永遠回傳 true
     */
    function enforceRateLimit($maxRequests = 30, $window = 60) {
        $dir = defined('LOGS_PATH') ? LOGS_PATH : sys_get_temp_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $ip   = rateLimitClientIp();
        $file = $dir . '/rate_limit_' . md5($ip) . '.json';

        $now   = time();
        $cutoff = $now - $window;

        $entries = [];
        if (is_file($file)) {
            $fh = @fopen($file, 'r');
            if ($fh) {
                if (flock($fh, LOCK_SH)) {
                    $raw = stream_get_contents($fh);
                    flock($fh, LOCK_UN);
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $entries = array_values($decoded);
                    }
                }
                fclose($fh);
            }
        }

        $entries = array_values(array_filter($entries, function ($ts) use ($cutoff) {
            return is_int($ts) && $ts > $cutoff;
        }));

        if (count($entries) >= $maxRequests) {
            $oldest = $entries[0] ?? $now;
            $retry  = max(1, $window - ($now - $oldest));
            http_response_code(429);
            header('Retry-After: ' . $retry);
            header('Content-Type: text/plain; charset=utf-8');
            echo '429 Too Many Requests — 請稍後再試。';
            die;
        }

        $entries[] = $now;
        $fh = @fopen($file, 'c');
        if ($fh) {
            if (flock($fh, LOCK_EX)) {
                ftruncate($fh, 0);
                rewind($fh);
                fwrite($fh, json_encode($entries));
                fflush($fh);
                flock($fh, LOCK_UN);
            }
            fclose($fh);
        }

        if (mt_rand(1, 100) === 1) {
            $files = glob($dir . '/rate_limit_*.json');
            if ($files !== false) {
                foreach ($files as $f) {
                    if (is_file($f) && (time() - filemtime($f)) > $window * 2) {
                        @unlink($f);
                    }
                }
            }
        }

        return true;
    }
}
