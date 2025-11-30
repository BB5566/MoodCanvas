-- MoodCanvas SQLite 資料庫結構
-- 版本: 1.0.0
-- 建立時間: 2025-11-20
-- 說明: AI 情緒日記應用資料庫結構（SQLite 版本）

-- ====================================================================
-- 使用者資料表
-- ====================================================================

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 建立索引
CREATE INDEX IF NOT EXISTS idx_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_email ON users(email);

-- 建立 Trigger 處理 updated_at 自動更新
CREATE TRIGGER IF NOT EXISTS update_users_timestamp
AFTER UPDATE ON users
BEGIN
    UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

-- ====================================================================
-- 日記資料表
-- ====================================================================

CREATE TABLE IF NOT EXISTS diaries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    mood VARCHAR(50) DEFAULT NULL,
    diary_date DATE NOT NULL,
    ai_generated_text TEXT DEFAULT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- 外鍵約束
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- 建立索引優化
CREATE INDEX IF NOT EXISTS idx_user_date ON diaries(user_id, diary_date);
CREATE INDEX IF NOT EXISTS idx_user_created ON diaries(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_diary_date ON diaries(diary_date);

-- ====================================================================
-- 資料庫版本資訊
-- ====================================================================

CREATE TABLE IF NOT EXISTS schema_version (
    version VARCHAR(20) PRIMARY KEY,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    description VARCHAR(255) DEFAULT NULL
);

INSERT INTO schema_version (version, description) VALUES
('1.0.0', '初始資料庫結構（SQLite 版本）');

-- ====================================================================
-- 完成
-- ====================================================================
