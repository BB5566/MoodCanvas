-- MoodCanvas 資料庫結構
-- 版本: 1.0.0
-- 建立時間: 2025-11-03
-- 說明: AI 情緒日記應用資料庫結構

-- ====================================================================
-- 建立資料庫
-- ====================================================================

CREATE DATABASE IF NOT EXISTS mood_canvas_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE mood_canvas_db;

-- ====================================================================
-- 使用者資料表
-- ====================================================================

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT '使用者ID',
    username VARCHAR(50) UNIQUE NOT NULL COMMENT '使用者帳號',
    password_hash VARCHAR(255) NOT NULL COMMENT '密碼雜湊',
    email VARCHAR(100) DEFAULT NULL COMMENT '電子信箱',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='使用者資料表';

-- ====================================================================
-- 日記資料表
-- ====================================================================

CREATE TABLE IF NOT EXISTS diaries (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT '日記ID',
    user_id INT NOT NULL COMMENT '使用者ID',
    title VARCHAR(255) NOT NULL COMMENT '日記標題',
    content TEXT NOT NULL COMMENT '日記內容',
    mood VARCHAR(50) DEFAULT NULL COMMENT '心情狀態',
    diary_date DATE NOT NULL COMMENT '日記日期',
    ai_generated_text TEXT DEFAULT NULL COMMENT 'AI 生成的心情短語',
    image_path VARCHAR(255) DEFAULT NULL COMMENT 'AI 生成的圖片路徑',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',

    -- 外鍵約束
    CONSTRAINT fk_diaries_user_id
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    -- 索引優化
    INDEX idx_user_date (user_id, diary_date),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_diary_date (diary_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='日記資料表';

-- ====================================================================
-- 初始化資料 (選用)
-- ====================================================================

-- 建立測試用戶 (密碼: test123456)
-- INSERT INTO users (username, password_hash, email) VALUES
-- ('demo_user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'demo@moodcanvas.local');

-- ====================================================================
-- 資料庫版本資訊
-- ====================================================================

CREATE TABLE IF NOT EXISTS schema_version (
    version VARCHAR(20) PRIMARY KEY COMMENT '版本號',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '套用時間',
    description VARCHAR(255) DEFAULT NULL COMMENT '版本說明'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='資料庫版本資訊';

INSERT INTO schema_version (version, description) VALUES
('1.0.0', '初始資料庫結構');

-- ====================================================================
-- 完成訊息
-- ====================================================================

SELECT 'MoodCanvas 資料庫結構建立完成！' AS message;
SELECT CONCAT('資料庫: ', DATABASE()) AS current_database;
SELECT COUNT(*) AS total_tables FROM information_schema.tables
WHERE table_schema = 'mood_canvas_db';
