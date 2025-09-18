# MoodCanvas

## 專案簡介
MoodCanvas 是一個簡潔的心情日記網站，支援日曆檢視、AI 生成圖片與文字、用戶註冊/登入等功能，並針對 SEO、RWD、可存取性與表單體驗進行優化。

## 主要功能
- 日記新增、瀏覽、刪除、依日期列表
- 日曆檢視所有日記
- AI 生成日記內容與圖片
- 用戶註冊、登入、登出
- 響應式設計，支援手機/桌機
- 主要頁面皆有動態 SEO 標籤
- 表單皆有可存取性與即時互動提示

## 專案結構與檔案說明
```
config/
  config.php         # 主要設定檔，資料庫、API 金鑰等
  autoloader.php     # PSR-4 自動載入器
  security.php       # 安全性相關設定

app/
  controllers/
    DiaryController.php   # 日記功能主控制器
    AuthController.php    # 用戶註冊、登入、登出控制器
    AIController.php      # AI 生成圖片/文字控制器
  models/
    Diary.php             # 日記資料模型
    User.php              # 用戶資料模型
    PerplexityAdapter.php # AI 文字產生 API 介接
    StabilityAI.php       # AI 圖片產生 API 介接
  views/
    layout/
      header.php, footer.php   # 全站共用頁首、頁尾
    diary/
      calendar.php, create.php, detail.php, date_list.php # 日記相關頁面
    auth/
      login.php, register.php  # 登入、註冊頁面

database/
  mood_canvas_schema.sql  # 資料庫結構 SQL

public/
  index.php              # 網站進入點，路由主程式
  assets/
    css/style.css        # 全站樣式
    js/main.js           # 前端互動腳本
    images/              # 預設圖片等
  storage/
    generated_images/    # AI 產生圖片儲存目錄
logs/
  app.log, error.log     # 系統與錯誤日誌
```

## 安裝與啟動
1. `composer install` (如有需要)
2. 複製 `config/config.php` 並設定資料庫、API 金鑰
3. 匯入 `database/mood_canvas_schema.sql`
4. 設定 `public/storage/generated_images` 與 `logs` 目錄可寫入
5. 啟動本地伺服器：
   ```
   php -S localhost:8000 -t public
   ```
6. 以瀏覽器開啟 `http://localhost:8000`

## 其他說明
- 已移除 portfolio、admin、AIService 等未用元件，僅保留日記、AI、帳號功能。
- 所有主要功能皆可用，細節優化持續進行中。

---
如需協助或有建議，請於 Issues 留言。
