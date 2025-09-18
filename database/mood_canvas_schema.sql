-- database/mood_canvas_schema.sql
-- MoodCanvas 資料庫結構
-- 適用於 MySQL/MariaDB
-- 建立資料庫
CREATE DATABASE IF NOT EXISTS mood_canvas_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE mood_canvas_db;

-- 使用者表
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE = InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 日記表
CREATE TABLE IF NOT EXISTS diaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    mood VARCHAR(20) NULL,
    diary_date DATE NOT NULL,
    image_path VARCHAR(255) NULL COMMENT 'AI生成圖片的路徑',
    ai_generated_text TEXT NULL COMMENT 'AI生成的詩句或名言',
    image_prompt TEXT NULL COMMENT '生成圖片使用的提示詞',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, diary_date),
    INDEX idx_mood (mood),
    INDEX idx_date (diary_date)
) ENGINE = InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- AI生成的圖片記錄表 (可選)
CREATE TABLE IF NOT EXISTS generated_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    diary_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    prompt TEXT NOT NULL COMMENT '生成圖片的提示詞',
    api_response JSON NULL COMMENT 'API回應的完整資料',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (diary_id) REFERENCES diaries(id) ON DELETE CASCADE,
    INDEX idx_diary (diary_id)
) ENGINE = InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 插入範例用戶 (Demo模式用) - 使用正確的欄位名稱
INSERT
    IGNORE INTO users (id, username, password_hash, email)
VALUES
    (
        999,
        'demo',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'demo@moodcanvas.com'
    );

-- 預設密碼是 'password'
-- 插入範例日記 (Demo模式用)
-- 注意：原先使用的 `quote` 欄位在表結構中不存在，改為使用 `ai_generated_text` 欄位
INSERT
    IGNORE INTO diaries (
        user_id,
        title,
        content,
        mood,
        diary_date,
        ai_generated_text
    )
VALUES
    (
        999,
        '歡迎使用 MoodCanvas',
        '這是一個展示用的日記條目。MoodCanvas 結合了傳統日記寫作與現代AI技術，為您的回憶增添藝術色彩。',
        '😊',
        CURDATE(),
        '「生活不在於等待風暴過去，而在於學會在雨中起舞。」'
    ),
    (
        999,
        '技術展示',
        '這個專案展示了PHP MVC架構、資料庫設計、API整合等多項技術能力。',
        '💻',
        DATE_SUB(CURDATE(), INTERVAL 1 DAY),
        '「程式設計不僅是科學，更是一門藝術。」'
    ),
    (
        999,
        '創作靈感',
        '結合AI與日記的想法來自於想要為平凡的文字記錄增添更多的創意元素。',
        '💡',
        DATE_SUB(CURDATE(), INTERVAL 2 DAY),
        '「創新來自於跨領域的思考碰撞。」'
    );

-- 建立資料庫用戶 (請根據實際需求調整)
-- CREATE USER 'moodcanvas_user'@'localhost' IDENTIFIED BY 'your_secure_password';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON mood_canvas_db.* TO 'moodcanvas_user'@'localhost';
-- FLUSH PRIVILEGES;
