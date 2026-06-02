<?php
/**
 * middleware/MonitoringMiddleware.php
 * P2 優化：性能監控中間件
 * 
 * 功能：
 * - 記錄每個請求的性能指標
 * - 追蹤錯誤和異常
 * - 提供即時性能報表
 */

class MonitoringMiddleware {
    
    private static $startTime;
    private static $startMemory;
    private static $peakMemory = 0;
    
    /**
     * 啟動監控
     */
    public static function start() {
        self::$startTime = microtime(true);
        self::$startMemory = memory_get_usage();
    }
    
    /**
     * 記錄請求日誌
     */
    public static function logRequest($method, $path, $userId = null, $statusCode = 200) {
        $duration = round((microtime(true) - self::$startTime) * 1000, 2);
        $peakMemory = round(memory_get_peak_usage() / 1024 / 1024, 2);
        
        // 存入數據庫
        $query = "INSERT INTO request_logs 
                  (method, path, user_id, status_code, duration_ms, memory_peak_mb, ip, user_agent) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        try {
            global $db;
            $stmt = $db->prepare($query);
            $stmt->execute([
                $method,
                $path,
                $userId,
                $statusCode,
                $duration,
                $peakMemory,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log("Failed to log request: " . $e->getMessage());
        }
    }
    
    /**
     * 記錄錯誤
     */
    public static function logError($endpoint, $errorMessage, $errorCode, $context = [], $userId = null) {
        $errorId = 'ERR-' . uniqid();
        
        $query = "INSERT INTO error_logs 
                  (error_id, endpoint, error_message, error_code, context, user_id) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        
        try {
            global $db;
            $stmt = $db->prepare($query);
            $stmt->execute([
                $errorId,
                $endpoint,
                $errorMessage,
                $errorCode,
                json_encode($context),
                $userId
            ]);
            
            // 同時寫入 PHP error log
            error_log("[{$errorId}] {$endpoint}: {$errorMessage} (Code: {$errorCode})");
        } catch (Exception $e) {
            error_log("Failed to log error: " . $e->getMessage());
        }
        
        return $errorId;
    }
    
    /**
     * 獲取性能報表
     */
    public static function getPerformanceReport() {
        global $db;
        
        try {
            // 過去 24 小時的統計
            $stmt = $db->query(
                "SELECT 
                    COUNT(*) as total_requests,
                    AVG(duration_ms) as avg_duration,
                    MAX(duration_ms) as max_duration,
                    AVG(memory_peak_mb) as avg_memory,
                    SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as error_count
                 FROM request_logs 
                 WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
            $stats = $stmt->fetch();
            
            // 最慢的 5 個端點
            $stmt = $db->query(
                "SELECT 
                    path,
                    COUNT(*) as call_count,
                    AVG(duration_ms) as avg_duration,
                    MAX(duration_ms) as max_duration
                 FROM request_logs 
                 WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                 GROUP BY path
                 ORDER BY avg_duration DESC
                 LIMIT 5"
            );
            $slowestEndpoints = $stmt->fetchAll();
            
            // 未解決的錯誤
            $stmt = $db->query(
                "SELECT id, error_id, endpoint, error_message, COUNT(*) as occurrences
                 FROM error_logs 
                 WHERE resolved = 0
                 GROUP BY error_message
                 ORDER BY occurrences DESC
                 LIMIT 10"
            );
            $unresolvedErrors = $stmt->fetchAll();
            
            return [
                'stats' => $stats,
                'slowest_endpoints' => $slowestEndpoints,
                'unresolved_errors' => $unresolvedErrors
            ];
        } catch (Exception $e) {
            error_log("Failed to get performance report: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 記錄 API 配額
     */
    public static function logQuotaUsage($provider, $count = 1) {
        global $db;
        
        $today = date('Y-m-d');
        $month = date('Y-m');
        
        try {
            // 日配額
            $stmt = $db->prepare(
                "INSERT INTO quota_daily (provider, date, used_count) 
                 VALUES (?, ?, ?) 
                 ON DUPLICATE KEY UPDATE used_count = used_count + ?"
            );
            $stmt->execute([$provider, $today, $count, $count]);
            
            // 月配額
            $stmt = $db->prepare(
                "INSERT INTO quota_monthly (provider, month, used_count) 
                 VALUES (?, ?, ?) 
                 ON DUPLICATE KEY UPDATE used_count = used_count + ?"
            );
            $stmt->execute([$provider, $month, $count, $count]);
        } catch (Exception $e) {
            error_log("Failed to log quota usage: " . $e->getMessage());
        }
    }
    
    /**
     * 獲取配額統計
     */
    public static function getQuotaStats($provider = null) {
        global $db;
        
        try {
            $today = date('Y-m-d');
            $month = date('Y-m');
            
            $where = $provider ? "WHERE provider = '$provider'" : '';
            
            $stmt = $db->query(
                "SELECT 
                    provider,
                    (SELECT used_count FROM quota_daily WHERE provider = qd.provider AND date = '$today') as today_usage,
                    (SELECT used_count FROM quota_monthly WHERE provider = qd.provider AND month = '$month') as month_usage
                 FROM quota_daily qd
                 {$where}
                 GROUP BY provider"
            );
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get quota stats: " . $e->getMessage());
            return [];
        }
    }
}

// 啟動監控
MonitoringMiddleware::start();
