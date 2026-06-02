# 🎨 MoodCanvas — AI 情緒日記

> **寫下心情，AI 幫你畫出來**

[![PHP](https://img.shields.io/badge/PHP-8.2-blue)](https://php.net)
[![SQLite](https://img.shields.io/badge/SQLite-3-green)](https://sqlite.org)
[![Replicate](https://img.shields.io/badge/AI-Replicate_Imagen4-purple)](https://replicate.com)
[![Pioneer](https://img.shields.io/badge/AI-Pioneer_Haiku4.5-orange)](https://pioneer.ai)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

一鍵寫日記，AI 自動生成專屬插畫與心情短語。卡片正面是 AI 畫作，點擊 3D 翻轉看日記內容。

## ✨ 功能

- **一鍵送出** — 不用先預覽、不用先生成，寫完直接送出
- **3D 翻轉卡片** — 正面 AI 圖片，點擊旋轉到背面看日記 + 心情短語
- **不需登入** — 訪客直接使用，自動 guest session
- **AI 非同步生成** — 送出後背景產生圖片和短語，不用等
- **編輯功能** — 事後可修改標題、內容、心情
- **CSRF 防護** — 所有 POST 請求驗證 token
- **RWD 響應式** — 手機、平板、桌面全適配

## 🖼️ 展示

| 寫日記 | AI 創作中 | 卡片正面 | 翻轉背面 |
|--------|----------|---------|---------|
| ![](https://bb-made.com/demo-01-typing.png) | ![](https://bb-made.com/demo-02-generating.png) | ![](https://bb-made.com/demo-03-cardfront.png) | ![](https://bb-made.com/demo-04-cardback.png) |

🔗 線上展示：https://bb-made.com/mood/

## 🛠 技術棧

| 層 | 技術 |
|----|------|
| **後端** | PHP 8.2, 自訂 MVC 路由 |
| **資料庫** | SQLite（單檔案，免安裝） |
| **圖片生成** | Replicate API（google/imagen-4） |
| **短語生成** | Pioneer API（Claude Haiku 4.5） |
| **前端** | 原生 HTML/CSS/JS, CSS 3D Transform |
| **部署** | Docker, Nginx reverse proxy, Cloudflare Tunnel |

## 🏗 架構

```
moodcanvas/
├── app/
│   ├── controllers/    # DiaryController, AuthController
│   ├── models/         # Diary, User
│   └── views/          # create, detail, edit, calendar, date_list...
├── config/             # config.php, autoloader.php
├── public/
│   ├── index.php       # 路由入口
│   └── assets/         # CSS, JS
├── database/           # moodcanvas.sqlite
└── logs/
```

## 🚀 快速開始

```bash
git clone https://github.com/BB5566/MoodCanvas.git
cd MoodCanvas

# 複製環境設定
cp .env.example .env

# 設定 API keys
# REPLICATE_API_KEY=r8_...
# PIONEER_API_KEY=pio_sk_...

# 啟動（PHP built-in server）
php -S localhost:8000 -t public

# 或 Docker
docker compose up -d
```

## 📋 .env 設定

```bash
# AI 服務
REPLICATE_API_KEY=r8_YOUR_KEY
PIONEER_API_KEY=pio_sk_YOUR_KEY

# 資料庫（SQLite 預設可用）
DB_TYPE=sqlite
DB_NAME=moodcanvas

# 應用
APP_URL=https://your-domain.com/mood
DEBUG=false
```

## 🎨 支援的藝術風格

🎲 AI 自選 | 📷 真實攝影 | 🌸 宮崎駿 | 👾 像素 | 🧸 皮克斯 | 🎨 扁平設計 | ✏️ 手繪 | 🖌️ 水墨

## 📄 License

MIT

---

<div align="center">
<a href="https://bb-made.com/mood/">🌐 Live Demo</a> •
<a href="https://github.com/BB5566/MoodCanvas">📂 GitHub</a>
</div>
