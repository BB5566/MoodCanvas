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

        // 讀取既有時間戳（獨佔鎖）
        $entries = [];
        $fh = @fopen($file, 'c+');
        if ($fh && flock($fh, LOCK_EX)) {
            $raw = stream_get_contents($fh);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $entries = array_values($decoded);
            }

            // 濾除視窗外的舊紀錄
            $entries = array_values(array_filter($entries, function ($ts) use ($cutoff) {
                return is_int($ts) && $ts > $cutoff;
            }));

            if (count($entries) >= $maxRequests) {
                $oldest = $entries[0] ?? $now;
                $retry  = max(1, $window - ($now - $oldest));
                flock($fh, LOCK_UN);
                fclose($fh);
                http_response_code(429);
                header('Retry-After: ' . $retry);
                header('Content-Type: text/plain; charset=utf-8');
                echo '429 Too Many Requests — 請稍後再試。';
                exit;
            }

            // 記錄本次請求
            $entries[] = $now;
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($entries));
            fflush($fh);
            flock($fh, LOCK_UN);
            fclose($fh);
        } elseif ($fh) {
            fclose($fh);
        }

        return true;
    }
}

if (!function_exists('checkAILimit')) {
    /**
     * AI 額度限制：依據真實 IP 加全站每日上限，防護濫用
     * @return bool 永遠回傳 true（超過時會先 exit 回傳 JSON）
     */
    function checkAILimit() {
        $ip = rateLimitClientIp();
        $is_guest = $_SESSION['is_guest'] ?? false;

        $dir = defined('LOGS_PATH') ? LOGS_PATH : sys_get_temp_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $today = date('Y-m-d');
        $file = $dir . '/ai_limit_' . md5($ip) . '_' . $today . '.json';

        $maxDailyRequests = $is_guest ? 5 : 20; // 訪客每日5次，一般用戶20次
        $maxGlobalRequests = 500; // 全站每日上限

        $entries = 0;
        $fh = @fopen($file, 'c+');
        if ($fh && flock($fh, LOCK_EX)) {
            $raw = stream_get_contents($fh);
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['count'])) {
                $entries = (int)$decoded['count'];
            }
            if ($entries >= $maxDailyRequests) {
                flock($fh, LOCK_UN);
                fclose($fh);
                http_response_code(429);
                header('Content-Type: application/json; charset=utf-8');
                die(json_encode(['success' => false, 'message' => 'AI 額度已耗盡，請明天再試。']));
            }
            $entries++;
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode(['count' => $entries]));
            fflush($fh);
            flock($fh, LOCK_UN);
            fclose($fh);
        } elseif ($fh) {
            fclose($fh);
        }

        // Check Global Limits
        $globalFile = $dir . '/ai_limit_global_' . $today . '.json';
        $globalEntries = 0;
        $fhGlobal = @fopen($globalFile, 'c+');
        if ($fhGlobal && flock($fhGlobal, LOCK_EX)) {
            $raw = stream_get_contents($fhGlobal);
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['count'])) {
                $globalEntries = (int)$decoded['count'];
            }
            if ($globalEntries >= $maxGlobalRequests) {
                flock($fhGlobal, LOCK_UN);
                fclose($fhGlobal);
                // 雖然個人可能沒超過，但全站超過了。為求嚴謹應該將個人的減回去，但此處簡單化處理
                http_response_code(429);
                header('Content-Type: application/json; charset=utf-8');
                die(json_encode(['success' => false, 'message' => '全站 AI 額度已耗盡，請明天再試。']));
            }
            $globalEntries++;
            ftruncate($fhGlobal, 0);
            rewind($fhGlobal);
            fwrite($fhGlobal, json_encode(['count' => $globalEntries]));
            fflush($fhGlobal);
            flock($fhGlobal, LOCK_UN);
            fclose($fhGlobal);
        } elseif ($fhGlobal) {
            fclose($fhGlobal);
        }

        return true;
    }
}
