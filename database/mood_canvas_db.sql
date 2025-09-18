-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主機： localhost
-- 產生時間： 2025 年 09 月 18 日 15:56
-- 伺服器版本： 11.4.5-MariaDB-log
-- PHP 版本： 8.3.23
CREATE DATABASE IF NOT EXISTS mood_canvas_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE mood_canvas_db;

SET
  SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

START TRANSACTION;

SET
  time_zone = "+08:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */
;

/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */
;

/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */
;

/*!40101 SET NAMES utf8mb4 */
;

--
-- 資料庫： `mood_canvas_db`
--
-- --------------------------------------------------------
--
-- 資料表結構 `diaries`
--
CREATE TABLE `diaries` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `mood` varchar(20) DEFAULT NULL,
  `mood_score` int(11) DEFAULT NULL COMMENT 'AI生成的心情分數 (1-10)',
  `mood_keywords` varchar(255) DEFAULT NULL COMMENT 'AI生成的心情關鍵詞',
  `diary_date` date NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `ai_generated_text` text DEFAULT NULL,
  `image_prompt` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `diaries`
--
INSERT INTO
  `diaries` (
    `id`,
    `user_id`,
    `title`,
    `content`,
    `mood`,
    `mood_score`,
    `mood_keywords`,
    `diary_date`,
    `image_path`,
    `ai_generated_text`,
    `image_prompt`,
    `created_at`,
    `updated_at`
  )
VALUES
  (
    22,
    1,
    '無標題日記 - 2025-09-18',
    '測試日記',
    '😊',
    NULL,
    NULL,
    '2025-09-18',
    'storage/generated_images/ai_1758210328_68cc2918e665c.png',
    '「今天是你餘生的第一天。」— 阿比·霍夫曼',
    NULL,
    '2025-09-18 15:45:39',
    '2025-09-18 15:45:39'
  );

-- --------------------------------------------------------
--
-- 資料表結構 `generated_images`
--
CREATE TABLE `generated_images` (
  `id` int(11) NOT NULL,
  `diary_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `prompt` text NOT NULL,
  `api_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`api_response`)),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------
--
-- 資料表結構 `memories`
--
CREATE TABLE `memories` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `mood` varchar(50) DEFAULT NULL,
  `memory_date` date NOT NULL,
  `ai_image_url` varchar(500) DEFAULT NULL,
  `ai_image_path` varchar(255) DEFAULT NULL,
  `ai_prompt` text DEFAULT NULL,
  `ai_quote` text DEFAULT NULL,
  `ai_quote_source` varchar(200) DEFAULT NULL,
  `ai_mood_analysis` text DEFAULT NULL,
  `polaroid_style` varchar(50) DEFAULT 'classic',
  `background_color` varchar(20) DEFAULT '#ffffff',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------
--
-- 資料表結構 `quotes`
--
CREATE TABLE `quotes` (
  `id` int(11) NOT NULL,
  `quote_text` text NOT NULL,
  `author` varchar(100) DEFAULT NULL,
  `source` varchar(200) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `mood_tags` varchar(200) DEFAULT NULL,
  `language` varchar(10) DEFAULT 'zh',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `quotes`
--
INSERT INTO
  `quotes` (
    `id`,
    `quote_text`,
    `author`,
    `source`,
    `category`,
    `mood_tags`,
    `language`,
    `is_active`,
    `created_at`
  )
VALUES
  (
    1,
    '生命不是等待暴風雨過去，而是學會在雨中起舞。',
    '未知',
    '網路',
    '勵志',
    '快樂,勇敢,積極',
    'zh',
    1,
    '2025-07-01 05:40:27'
  ),
  (
    2,
    '今天是你餘生的第一天。',
    '未知',
    '網路',
    '勵志',
    '希望,新開始,積極',
    'zh',
    1,
    '2025-07-01 05:40:27'
  ),
  (
    3,
    '不要因為結束而哭泣，微笑吧，為你的曾經擁有。',
    '蘇斯博士',
    '書籍',
    '勵志',
    '感恩,回憶,溫暖',
    'zh',
    1,
    '2025-07-01 05:40:27'
  ),
  (
    4,
    '昨天已經過去，明天還未到來，今天是一個禮物，所以才叫做現在。',
    '未知',
    '網路',
    '哲理',
    '當下,珍惜,智慧',
    'zh',
    1,
    '2025-07-01 05:40:27'
  ),
  (
    5,
    '夢想不會因為時間而褪色，只會因為不行動而失去光彩。',
    '未知',
    '網路',
    '勵志',
    '夢想,行動,堅持',
    'zh',
    1,
    '2025-07-01 05:40:27'
  ),
  (
    6,
    '生命不是等待暴風雨過去，而是學會在雨中起舞。',
    '未知',
    '網路',
    '勵志',
    '快樂,勇敢,積極',
    'zh',
    1,
    '2025-07-01 05:41:50'
  ),
  (
    7,
    '今天是你餘生的第一天。',
    '未知',
    '網路',
    '勵志',
    '希望,新開始,積極',
    'zh',
    1,
    '2025-07-01 05:41:50'
  ),
  (
    8,
    '不要因為結束而哭泣，微笑吧，為你的曾經擁有。',
    '蘇斯博士',
    '書籍',
    '勵志',
    '感恩,回憶,溫暖',
    'zh',
    1,
    '2025-07-01 05:41:50'
  );

-- --------------------------------------------------------
--
-- 資料表結構 `system_settings`
--
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) DEFAULT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------
--
-- 資料表結構 `users`
--
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `display_name` varchar(100) DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `users`
--
INSERT INTO
  `users` (
    `id`,
    `username`,
    `password_hash`,
    `created_at`,
    `updated_at`,
    `display_name`,
    `avatar_path`
  )
VALUES
  (
    1,
    'demo',
    '$2y$10$OLIT8HuKuWaoHnFs84ZPcOv4ZDF/IxnOYHoNqybE5.T/k3cjz8MiG',
    '2025-06-26 15:39:01',
    '2025-07-01 05:40:27',
    'demo',
    NULL
  );

--
-- 已傾印資料表的索引
--
--
-- 資料表索引 `diaries`
--
ALTER TABLE
  `diaries`
ADD
  PRIMARY KEY (`id`),
ADD
  KEY `idx_user_date` (`user_id`, `diary_date`),
ADD
  KEY `diaries_ibfk_1` (`user_id`);

--
-- 資料表索引 `generated_images`
--
ALTER TABLE
  `generated_images`
ADD
  PRIMARY KEY (`id`),
ADD
  KEY `idx_diary` (`diary_id`);

--
-- 資料表索引 `memories`
--
ALTER TABLE
  `memories`
ADD
  PRIMARY KEY (`id`),
ADD
  KEY `user_id` (`user_id`);

--
-- 資料表索引 `quotes`
--
ALTER TABLE
  `quotes`
ADD
  PRIMARY KEY (`id`);

--
-- 資料表索引 `system_settings`
--
ALTER TABLE
  `system_settings`
ADD
  PRIMARY KEY (`id`),
ADD
  UNIQUE KEY `setting_key` (`setting_key`);

--
-- 資料表索引 `users`
--
ALTER TABLE
  `users`
ADD
  PRIMARY KEY (`id`),
ADD
  UNIQUE KEY `username` (`username`);

--
-- 在傾印的資料表使用自動遞增(AUTO_INCREMENT)
--
--
-- 使用資料表自動遞增(AUTO_INCREMENT) `diaries`
--
ALTER TABLE
  `diaries`
MODIFY
  `id` int(11) NOT NULL AUTO_INCREMENT,
  AUTO_INCREMENT = 23;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `generated_images`
--
ALTER TABLE
  `generated_images`
MODIFY
  `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `memories`
--
ALTER TABLE
  `memories`
MODIFY
  `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `quotes`
--
ALTER TABLE
  `quotes`
MODIFY
  `id` int(11) NOT NULL AUTO_INCREMENT,
  AUTO_INCREMENT = 9;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `system_settings`
--
ALTER TABLE
  `system_settings`
MODIFY
  `id` int(11) NOT NULL AUTO_INCREMENT,
  AUTO_INCREMENT = 4;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `users`
--
ALTER TABLE
  `users`
MODIFY
  `id` int(11) NOT NULL AUTO_INCREMENT,
  AUTO_INCREMENT = 2;

--
-- 已傾印資料表的限制式
--
--
-- 資料表的限制式 `diaries`
--
ALTER TABLE
  `diaries`
ADD
  CONSTRAINT `diaries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 資料表的限制式 `generated_images`
--
ALTER TABLE
  `generated_images`
ADD
  CONSTRAINT `generated_images_ibfk_1` FOREIGN KEY (`diary_id`) REFERENCES `diaries` (`id`) ON DELETE CASCADE;

--
-- 資料表的限制式 `memories`
--
ALTER TABLE
  `memories`
ADD
  CONSTRAINT `memories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */
;

/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */
;

/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */
;
