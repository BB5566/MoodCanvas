<?php
/**
 * config/cache.php
 * MoodCanvas 多層快取系統
 * 
 * 支持：
 * - 文件系統快取（簡單、無依賴）
 * - Redis 快取（高性能，未來升級）
 * 
 * 快取層次：
 * 1. 日曆數據（TTL: 5 分鐘）
 * 2. AI Prompt（TTL: 1 天，内容-style 哈希）
 * 3. Dashboard Insight（TTL: 1 天，用戶-月份組合）
 * 4. Rate Limit 計數器（TTL: 30 秒）
 */

class Cache {
    // 驅動選擇
    const DRIVER = 'file'; // 'file' | 'redis'
    const BASE_PATH = BASE_PATH . '/cache';
    
    // TTL 設定（秒）
    const TTL_CALENDAR = 300;    // 5 分鐘
    const TTL_PROMPT = 86400;    // 1 天
    const TTL_INSIGHT = 86400;   // 1 天
    const TTL_RATE_LIMIT = 30;   // 30 秒
    
    // 快取鍵模板（支持 sprintf）
    const KEY_CALENDAR = 'cal:{user_id}:{year}:{month}';
    const KEY_PROMPT = 'prompt:{hash}';
    const KEY_INSIGHT = 'insight:{user_id}:{year}:{month}';
    const KEY_RATE_LIMIT = 'ratelimit:{hash}';
    
    // 統計指標（可選）
    private static $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    ];
    
    /**
     * 獲取快取值
     * 
     * @param string $key 快取鍵
     * @return mixed 快取的值，若不存在或過期返回 null
     */
    public static function get($key) {
        if (self::DRIVER === 'redis') {
            return self::getRedis($key);
        }
        
        return self::getFile($key);
    }
    
    /**
     * 設置快取值
     * 
     * @param string $key 快取鍵
     * @param mixed $value 要快取的值
     * @param int|null $ttl 時間-生活（秒），null 則使用默認 3600
     */
    public static function set($key, $value, $ttl = null) {
        if (!is_numeric($ttl) || $ttl <= 0) {
            $ttl = 3600; // 默認 1 小時
        }
        
        if (self::DRIVER === 'redis') {
            self::setRedis($key, $value, $ttl);
        } else {
            self::setFile($key, $value, $ttl);
        }
        
        self::$stats['sets']++;
    }
    
    /**
     * 刪除快取（支持 glob pattern）
     * 
     * @param string $pattern 快取鍵或 glob pattern（例：'cal:1:2026:*'）
     */
    public static function invalidate($pattern) {
        if (self::DRIVER === 'redis') {
            self::invalidateRedis($pattern);
        } else {
            self::invalidateFile($pattern);
        }
        
        self::$stats['deletes']++;
    }
    
    /**
     * 清空所有快取
     */
    public static function clear() {
        if (self::DRIVER === 'redis') {
            self::clearRedis();
        } else {
            self::clearFile();
        }
    }
    
    /**
     * 獲取統計信息
     */
    public static function getStats() {
        $total = self::$stats['hits'] + self::$stats['misses'];
        $hitRate = $total > 0 ? round(self::$stats['hits'] / $total * 100, 2) : 0;
        
        return [
            'hits' => self::$stats['hits'],
            'misses' => self::$stats['misses'],
            'total' => $total,
            'hit_rate' => $hitRate . '%',
            'sets' => self::$stats['sets'],
            'deletes' => self::$stats['deletes']
        ];
    }
    
    // ========== 文件系統實現 ==========
    
    private static function getFile($key) {
        $filename = self::hashKey($key);
        $filepath = self::BASE_PATH . '/' . $filename;
        
        if (!file_exists($filepath)) {
            self::$stats['misses']++;
            return null;
        }
        
        $data = @json_decode(file_get_contents($filepath), true);
        if (!$data) {
            self::$stats['misses']++;
            return null;
        }
        
        // 檢查過期時間
        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            @unlink($filepath);
            self::$stats['misses']++;
            return null;
        }
        
        self::$stats['hits']++;
        return $data['value'] ?? null;
    }
    
    private static function setFile($key, $value, $ttl) {
        if (!is_dir(self::BASE_PATH)) {
            @mkdir(self::BASE_PATH, 0755, true);
        }
        
        $filename = self::hashKey($key);
        $filepath = self::BASE_PATH . '/' . $filename;
        
        $data = [
            'key' => $key,
            'value' => $value,
            'expires_at' => time() + $ttl,
            'created_at' => time(),
            'ttl' => $ttl
        ];
        
        @file_put_contents($filepath, json_encode($data, JSON_UNESCAPED_UNICODE));
        @chmod($filepath, 0644);
    }
    
    private static function invalidateFile($pattern) {
        if (!is_dir(self::BASE_PATH)) {
            return;
        }
        
        // 支持 glob pattern（例：'cal:1:2026:*'）
        $globPattern = str_replace('*', '*', str_replace(':', '_', $pattern));
        $files = @glob(self::BASE_PATH . '/' . md5(substr($pattern, 0, strrpos($pattern, ':'))) . '*');
        
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }
    
    private static function clearFile() {
        if (!is_dir(self::BASE_PATH)) {
            return;
        }
        
        $files = @glob(self::BASE_PATH . '/*.cache');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }
    
    // ========== Redis 實現（未來升級） ==========
    
    private static function getRedis($key) {
        // 待實現：使用 Redis 客戶端
        return null;
    }
    
    private static function setRedis($key, $value, $ttl) {
        // 待實現：使用 Redis 客戶端
    }
    
    private static function invalidateRedis($pattern) {
        // 待實現：使用 Redis 客戶端
    }
    
    private static function clearRedis() {
        // 待實現：使用 Redis 客戶端
    }
    
    // ========== 工具方法 ==========
    
    /**
     * 計算 key 的 hash（防止文件名過長）
     */
    private static function hashKey($key) {
        return md5($key) . '.cache';
    }
    
    /**
     * 計算內容 hash（用於 prompt 和其他去重）
     */
    public static function hashContent($content, $style = '', $mood = '') {
        return md5($content . '|' . $style . '|' . $mood);
    }
}

// 確保快取目錄存在
if (!is_dir(Cache::BASE_PATH)) {
    @mkdir(Cache::BASE_PATH, 0755, true);
}
