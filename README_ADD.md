
- **net::ERR_NAME_NOT_RESOLVED (瀏覽器主控台錯誤)**
  如果在瀏覽器的開發者工具主控台中看到 `Failed to load resource: net::ERR_NAME_NOT_RESOLVED` 且指向 `google-analytics.com` 或 `googletagmanager.com` 等追蹤腳本，這是因為 Cloudflare 或第三方網站追蹤代碼 (例如 Cloudflare Zaraz) 嘗試加載外部資源時被瀏覽器阻擋（如 AdBlocker、隱私保護擴充套件，或無痕模式）。這是**完全無害的第三方訊息**，不會影響 MoodCanvas 網站的核心功能。
