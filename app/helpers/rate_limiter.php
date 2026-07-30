<?php
/**
 * app/helpers/rate_limiter.php
 *
 * 以檔案為基礎的每 IP 速率限制：預設每 60 秒 30 次請求，超過即回傳 HTTP 429。
 *
 * - 使用 flock() 共享／獨佔鎖避免競態條件。
 * - 檔案操作加上 @ 抑制，與全域錯誤處理器（會把警告轉為例外）相容。
 * - 反向代理（NAS 部署）下優先採用 X-Forwarded-For 最左側 IP。
 */

if (!function_exists('rateLimitClientIp')) {
    /**
     * 取得用於速率限制的客戶端 IP。
     * @return string
     */
    function rateLimitClientIp() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($parts[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

if (!function_exists('enforceRateLimit')) {
    /**
     * 強制速率限制；超過上限時送出 429 並結束程式。
     *
     * @param int $maxRequests 視窗內允許的最大請求數（預設 30）
     * @param int $window      視窗秒數（預設 60）
     * @return bool 永遠回傳 true（超過時會先 exit）
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

        // 讀取既有時間戳（共享鎖）
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

        // 濾除視窗外的舊紀錄
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
            exit;
        }

        // 記錄本次請求（獨佔鎖）
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

        return true;
    }
}
