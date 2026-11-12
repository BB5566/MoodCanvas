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
        $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Trusted proxies: localhost, private networks, docker networks
        $trusted_proxies = ['127.0.0.1', '::1'];
        $is_trusted = in_array($remote_addr, $trusted_proxies) ||
                      preg_match('/^(10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|192\.168\.)/', $remote_addr);

        if ($is_trusted) {
            if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
                return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
            }
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
                // 從最右側（最近的代理）往前找第一個不受信任的 IP
                $parts = array_reverse($parts);
                foreach ($parts as $ip) {
                    $is_ip_trusted = in_array($ip, $trusted_proxies) || preg_match('/^(10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|192\.168\.)/', $ip);
                    if (!$is_ip_trusted) {
                        return $ip;
                    }
                }
                // 如果全部都是受信任的，只好回傳最左邊的
                return end($parts);
            }
        }
        return $remote_addr;
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

        // 每次生成約 US$0.003（Flux 生圖＋GPT-5 nano 文字），上限壓在最壞一天約 NT$5
        $maxDailyRequests = $is_guest ? 3 : 20; // 訪客每天 3 次，註冊用戶 20 次
        $maxGlobalRequests = 50; // 全站每天上限

        $globalFile = $dir . '/ai_limit_global_' . $today . '.json';

        // Read current values without updating
        $entries = 0;
        if (is_file($file)) {
            $fh = @fopen($file, 'r');
            if ($fh && flock($fh, LOCK_SH)) {
                $raw = stream_get_contents($fh);
                flock($fh, LOCK_UN);
                fclose($fh);
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['count'])) {
                    $entries = (int)$decoded['count'];
                }
            } elseif ($fh) {
                fclose($fh);
            }
        }

        $globalEntries = 0;
        if (is_file($globalFile)) {
            $fhGlobal = @fopen($globalFile, 'r');
            if ($fhGlobal && flock($fhGlobal, LOCK_SH)) {
                $raw = stream_get_contents($fhGlobal);
                flock($fhGlobal, LOCK_UN);
                fclose($fhGlobal);
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['count'])) {
                    $globalEntries = (int)$decoded['count'];
                }
            } elseif ($fhGlobal) {
                fclose($fhGlobal);
            }
        }

        if ($entries >= $maxDailyRequests) {
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode(['success' => false, 'message' => '今天的 AI 試用次數用完了，明天再來，或註冊後繼續寫。']));
        }

        if ($globalEntries >= $maxGlobalRequests) {
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode(['success' => false, 'message' => '全站 AI 額度已耗盡，請明天再試。']));
        }

        // Update both if neither exceeded
        $fhGlobal = @fopen($globalFile, 'c+');
        if ($fhGlobal && flock($fhGlobal, LOCK_EX)) {
            $raw = stream_get_contents($fhGlobal);
            $decoded = json_decode($raw, true);
            $currentGlobal = (is_array($decoded) && isset($decoded['count'])) ? (int)$decoded['count'] : 0;

            if ($currentGlobal >= $maxGlobalRequests) {
                flock($fhGlobal, LOCK_UN);
                fclose($fhGlobal);
                http_response_code(429);
                header('Content-Type: application/json; charset=utf-8');
                die(json_encode(['success' => false, 'message' => '全站 AI 額度已耗盡，請明天再試。']));
            }

            $currentGlobal++;
            ftruncate($fhGlobal, 0);
            rewind($fhGlobal);
            fwrite($fhGlobal, json_encode(['count' => $currentGlobal]));
            fflush($fhGlobal);
            flock($fhGlobal, LOCK_UN);
            fclose($fhGlobal);
        } elseif ($fhGlobal) {
            fclose($fhGlobal);
        }

        $fh = @fopen($file, 'c+');
        if ($fh && flock($fh, LOCK_EX)) {
            $raw = stream_get_contents($fh);
            $decoded = json_decode($raw, true);
            $current = (is_array($decoded) && isset($decoded['count'])) ? (int)$decoded['count'] : 0;

            $current++;
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode(['count' => $current]));
            fflush($fh);
            flock($fh, LOCK_UN);
            fclose($fh);
        } elseif ($fh) {
            fclose($fh);
        }

        return true;
    }
}

if (!function_exists('aiLimitFiles')) {
    // 今天這個 IP 的額度檔和全站額度檔（跟 checkAILimit 用同一組檔名）
    function aiLimitFiles() {
        $dir = defined('LOGS_PATH') ? LOGS_PATH : sys_get_temp_dir();
        $today = date('Y-m-d');
        return [
            'ip' => $dir . '/ai_limit_' . md5(rateLimitClientIp()) . '_' . $today . '.json',
            'global' => $dir . '/ai_limit_global_' . $today . '.json',
        ];
    }

    function aiLimitCount($file) {
        $data = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
        return (int) ($data['count'] ?? 0);
    }

    // 這次沒畫出圖：把剛扣的一次還回去（失敗不算次數）
    function refundAILimit() {
        foreach (aiLimitFiles() as $file) {
            $fh = @fopen($file, 'c+');
            if (!$fh) continue;
            if (flock($fh, LOCK_EX)) {
                $data = json_decode(stream_get_contents($fh), true);
                $count = max(0, (int) ($data['count'] ?? 0) - 1);
                ftruncate($fh, 0);
                rewind($fh);
                fwrite($fh, json_encode(['count' => $count]));
                fflush($fh);
                flock($fh, LOCK_UN);
            }
            fclose($fh);
        }
    }

    // 訪客今天還能用幾次 AI（寫日記頁顯示用）
    function aiLimitRemaining() {
        $files = aiLimitFiles();
        $perIp = max(0, 3 - aiLimitCount($files['ip']));
        $global = max(0, 50 - aiLimitCount($files['global']));
        return min($perIp, $global);
    }
}
