<?php
// app/helpers/guest_wall.php
// 訪客牆：訪客寫的日記互相看得到。兩層保護：
// 1. 有跡可循：每篇記下寫的人 IP 的雜湊（HMAC，金鑰放在資料庫旁邊，不存原始 IP）
// 2. 公開前先檢查：有網址、電話、Email、LINE 帳號的不公開；AI 讀日記時順便判斷適不適合公開

// IP 雜湊用的金鑰：第一次用時產生一把亂數存進資料庫目錄（跟資料庫在同一個 volume，重新部署不會變）
function guestIpHash(string $ip): string
{
    $dir = BASE_PATH . '/database';
    $file = $dir . '/.ip_hash_key';
    $key = is_readable($file) ? trim((string) file_get_contents($file)) : '';
    if ($key === '') {
        $key = bin2hex(random_bytes(32));
        @file_put_contents($file, $key, LOCK_EX);
        @chmod($file, 0600);
    }
    return hash_hmac('sha256', $ip, $key);
}

// 看起來像廣告或留個資的就不公開（這些人最常拿留言板亂用）
function looksLikeContactOrSpam(string $text): bool
{
    $patterns = [
        '~https?://|www\.|\.(com|net|org|tw|io|cc|me)\b~i', // 網址
        '~[\w.+-]+@[\w-]+\.[\w.]+~',                         // Email
        '~(?<!\d)09\d{2}[-\s]?\d{3}[-\s]?\d{3}(?!\d)~',       // 台灣手機
        '~(?<!\d)0\d{1,2}[-\s]?\d{6,8}(?!\d)~',               // 市話
        '~\bline\s*(id)?\s*[:：]~i',                          // LINE 帳號
        '~加\s*(賴|line|LINE|微信|wechat)~u',
    ];
    foreach ($patterns as $p) {
        if (preg_match($p, $text)) return true;
    }
    return false;
}

// 單獨問一次 AI：這篇適不適合給陌生人看（給「訪客改了日記」用；新日記在讀日記那次就一起判斷）
function aiSaysPublicOk(string $title, string $content): bool
{
    $prompt = "判斷下面這篇日記能不能公開給陌生人看，只回傳 JSON：{\"public_ok\": true 或 false}。\n"
            . "以下任何一種就是 false：辱罵或歧視他人、色情、暴力威脅、自傷內容、指名道姓攻擊真實人物、廣告或拉人、留下任何聯絡方式或個資。"
            . "一般心情、抱怨、難過都是 true。\n\n"
            . "標題：" . mb_substr($title, 0, 100) . "\n內容：" . mb_substr($content, 0, 600);
    try {
        $data = extractJsonObject(replicateText($prompt, 60)) ?? [];
        return ($data['public_ok'] ?? false) === true;
    } catch (Throwable $e) {
        logMessage('公開檢查失敗: ' . $e->getMessage(), 'ERROR');
        return false; // 檢查不了就先不公開
    }
}
