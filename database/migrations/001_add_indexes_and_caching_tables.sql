-- MoodCanvas Phase 1 Optimization: Database Indexes + Logging Tables
-- 版本: 1.0
-- 日期: 2026-06-02

-- ====================================================================
-- 1. 添加複合索引（優化日曆查詢）
-- ====================================================================

-- 檢查索引是否已存在，避免重複創建
SELECT CONCAT('Checking index: idx_user_month') AS status;

ALTER TABLE diaries 
ADD INDEX IF NOT EXISTS idx_user_month (user_id, diary_date);

ALTER TABLE diaries 
ADD INDEX IF NOT EXISTS idx_user_date (user_id, diary_date, id);

-- ====================================================================
-- 2. 創建快取追蹤表（支持智能失效）
-- ====================================================================

CREATE TABLE IF NOT EXISTS cache_invalidations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cache_key VARCHAR(255) UNIQUE NOT NULL COMMENT '快取 key（支持 glob pattern）',
    invalidated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reason VARCHAR(255),
    INDEX idx_cache_key (cache_key),
    INDEX idx_timestamp (invalidated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 3. 創建請求日誌表（監控性能）
-- ====================================================================

CREATE TABLE IF NOT EXISTS request_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    method VARCHAR(10),
    path VARCHAR(500),
    user_id INT,
    status_code INT,
    duration_ms INT,
    user_agent VARCHAR(500),
    ip VARCHAR(45),
    memory_peak_mb DECIMAL(10, 2),
    INDEX idx_user_timestamp (user_id, timestamp),
    INDEX idx_path_timestamp (path, timestamp),
    INDEX idx_slow_queries (duration_ms),
    INDEX idx_errors (status_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='API 請求審計日誌 - 用於性能分析和監控';

-- ====================================================================
-- 4. 創建錯誤日誌表（集中式錯誤追蹤）
-- ====================================================================

CREATE TABLE IF NOT EXISTS error_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    error_id VARCHAR(50) UNIQUE NOT NULL,
    endpoint VARCHAR(255),
    error_message TEXT,
    error_code INT,
    context JSON,
    user_id INT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved TINYINT DEFAULT 0,
    resolved_at DATETIME,
    notes TEXT,
    INDEX idx_endpoint_timestamp (endpoint, timestamp),
    INDEX idx_error_id (error_id),
    INDEX idx_unresolved (resolved),
    INDEX idx_user_id (user_id),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='集中式錯誤追蹤，支持手工標記為已解決';

-- ====================================================================
-- 5. 創建 API 配額追蹤表
-- ====================================================================

CREATE TABLE IF NOT EXISTS quota_daily (
    provider VARCHAR(50),
    date DATE,
    used_count INT DEFAULT 0,
    PRIMARY KEY (provider, date),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COMMENT='每日 API 配額使用追蹤';

CREATE TABLE IF NOT EXISTS quota_monthly (
    provider VARCHAR(50),
    month VARCHAR(7),
    used_count INT DEFAULT 0,
    PRIMARY KEY (provider, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COMMENT='每月 API 配額使用追蹤';

-- ====================================================================
-- 6. 驗證索引創建
-- ====================================================================

SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_NAME = 'diaries' AND TABLE_SCHEMA = DATABASE()
ORDER BY INDEX_NAME, SEQ_IN_INDEX;
