# MoodCanvas 資料庫設定指南

## 📋 目錄
- [資料庫概述](#資料庫概述)
- [快速開始](#快速開始)
- [詳細設定步驟](#詳細設定步驟)
- [資料表結構](#資料表結構)
- [常見問題](#常見問題)

---

## 資料庫概述

MoodCanvas 使用 **MySQL** 資料庫來儲存使用者資料和日記內容。

### 技術規格
- **資料庫類型:** MySQL / MariaDB
- **字元集:** UTF-8 (utf8mb4)
- **連線方式:** PDO
- **預設資料庫名稱:** `mood_canvas_db`

### 資料表清單
1. **users** - 使用者帳號資料
2. **diaries** - 日記內容與 AI 生成資料
3. **schema_version** - 資料庫版本管理

---

## 快速開始

### 方法一：使用 MySQL CLI

```bash
# 1. 登入 MySQL
mysql -u root -p

# 2. 執行 schema 檔案
source /path/to/MoodCanvas/database/schema.sql

# 3. 驗證資料庫
USE mood_canvas_db;
SHOW TABLES;
```

### 方法二：使用 phpMyAdmin

1. 開啟 phpMyAdmin
2. 點選「匯入」(Import)
3. 選擇 `database/schema.sql` 檔案
4. 點擊「執行」(Go)

---

## 詳細設定步驟

### 步驟 1: 設定環境變數

複製 `.env.example` 為 `.env` 並填入資料庫資訊:

```bash
cp .env.example .env
```

編輯 `.env` 檔案:

```env
# 資料庫配置
DB_HOST=localhost
DB_PORT=3306
DB_NAME=mood_canvas_db
DB_USER=your_database_username
DB_PASS=your_database_password
DB_CHARSET=utf8mb4
```

### 步驟 2: 建立資料庫

使用以下其中一種方法:

#### 選項 A: 自動建立 (推薦)
```bash
mysql -u root -p < database/schema.sql
```

#### 選項 B: 手動建立
```sql
CREATE DATABASE mood_canvas_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

### 步驟 3: 執行資料庫 Schema

```bash
mysql -u your_username -p mood_canvas_db < database/schema.sql
```

### 步驟 4: 驗證安裝

訪問應用程式首頁，如果沒有出現資料庫連線錯誤，表示設定成功！

或使用 MySQL CLI 驗證:

```sql
USE mood_canvas_db;

-- 檢查資料表
SHOW TABLES;

-- 檢查版本
SELECT * FROM schema_version;
```

---

## 資料表結構

### 1. users 資料表

儲存使用者帳號資訊。

| 欄位 | 類型 | 說明 | 約束 |
|------|------|------|------|
| id | INT | 使用者ID | 主鍵, 自動遞增 |
| username | VARCHAR(50) | 使用者帳號 | 唯一, 非空 |
| password_hash | VARCHAR(255) | 密碼雜湊 | 非空 |
| email | VARCHAR(100) | 電子信箱 | 可空 |
| created_at | TIMESTAMP | 建立時間 | 預設當前時間 |
| updated_at | TIMESTAMP | 更新時間 | 自動更新 |

**索引:**
- `idx_username` - 加速帳號查詢
- `idx_email` - 加速信箱查詢

---

### 2. diaries 資料表

儲存日記內容和 AI 生成資料。

| 欄位 | 類型 | 說明 | 約束 |
|------|------|------|------|
| id | INT | 日記ID | 主鍵, 自動遞增 |
| user_id | INT | 使用者ID | 外鍵 → users.id |
| title | VARCHAR(255) | 日記標題 | 非空 |
| content | TEXT | 日記內容 | 非空 |
| mood | VARCHAR(50) | 心情狀態 | 可空 |
| diary_date | DATE | 日記日期 | 非空 |
| ai_generated_text | TEXT | AI 心情短語 | 可空 |
| image_path | VARCHAR(255) | AI 圖片路徑 | 可空 |
| created_at | TIMESTAMP | 建立時間 | 預設當前時間 |

**外鍵約束:**
- `user_id` 關聯到 `users.id`
- 刪除使用者時會級聯刪除其所有日記 (CASCADE DELETE)

**索引:**
- `idx_user_date` - 優化按使用者和日期查詢
- `idx_user_created` - 優化按建立時間排序
- `idx_diary_date` - 加速日期範圍查詢

---

### 3. schema_version 資料表

資料庫版本管理。

| 欄位 | 類型 | 說明 |
|------|------|------|
| version | VARCHAR(20) | 版本號 (主鍵) |
| applied_at | TIMESTAMP | 套用時間 |
| description | VARCHAR(255) | 版本說明 |

---

## 資料庫連線測試

執行以下 PHP 檔案測試連線:

```php
<?php
require_once __DIR__ . '/config/config.php';

try {
    $db = getDbConnection();
    if ($db) {
        echo "✅ 資料庫連線成功!\n";

        // 檢查資料表
        $stmt = $db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "📋 資料表清單: " . implode(', ', $tables) . "\n";

        // 檢查版本
        $stmt = $db->query("SELECT version FROM schema_version ORDER BY applied_at DESC LIMIT 1");
        $version = $stmt->fetchColumn();
        echo "🔢 資料庫版本: " . $version . "\n";
    }
} catch (Exception $e) {
    echo "❌ 連線失敗: " . $e->getMessage() . "\n";
}
?>
```

---

## 常見問題

### Q1: 資料庫連線失敗怎麼辦?

**檢查清單:**
1. 確認 MySQL 服務已啟動
2. 檢查 `.env` 檔案的資料庫帳密是否正確
3. 確認資料庫 `mood_canvas_db` 已建立
4. 檢查資料庫使用者權限

**除錯方式:**
```bash
# 測試 MySQL 連線
mysql -u your_username -p -h localhost

# 檢查使用者權限
SHOW GRANTS FOR 'your_username'@'localhost';
```

---

### Q2: 如何重置資料庫?

```sql
-- ⚠️ 警告: 這會刪除所有資料!
DROP DATABASE IF EXISTS mood_canvas_db;
```

然後重新執行 `database/schema.sql`。

---

### Q3: 如何備份資料庫?

```bash
# 完整備份
mysqldump -u root -p mood_canvas_db > backup_$(date +%Y%m%d).sql

# 僅備份結構
mysqldump -u root -p --no-data mood_canvas_db > schema_backup.sql

# 僅備份資料
mysqldump -u root -p --no-create-info mood_canvas_db > data_backup.sql
```

---

### Q4: 如何修改資料庫名稱?

1. 修改 `.env` 檔案的 `DB_NAME`
2. 編輯 `database/schema.sql` 的第一行
3. 重新執行 schema 建立流程

---

### Q5: 圖片路徑儲存規則?

圖片儲存在 `public/storage/generated_images/` 目錄，資料庫儲存格式:

```
storage/generated_images/filename.png
```

程式會自動處理完整路徑轉換。

---

## 維護建議

### 定期備份
建議每週備份一次資料庫:

```bash
# 建立 crontab 定期任務
0 2 * * 0 mysqldump -u root -p your_password mood_canvas_db > /path/to/backup/mood_canvas_$(date +\%Y\%m\%d).sql
```

### 索引優化
如果日記資料量超過 10,000 筆，建議執行:

```sql
ANALYZE TABLE diaries;
OPTIMIZE TABLE diaries;
```

### 清理舊圖片
刪除資料庫中已不存在的圖片檔案:

```php
// 在 maintenance/ 目錄建立清理腳本
// 參考 Diary.php 的 getActualImagePath() 方法
```

---

## 技術支援

如有問題，請檢查:
1. `logs/app.log` - 應用程式日誌
2. `logs/error.log` - PHP 錯誤日誌
3. MySQL error log - 資料庫錯誤日誌

---

**文件版本:** 1.0.0
**最後更新:** 2025-11-03
**相容版本:** MoodCanvas v1.0.0+
