<?php
/**
 * 清除超過 24 小時的未註冊訪客帳號與其資料
 * 將此腳本加入 Cronjob 定期執行 (例如每天一次)：
 * 0 2 * * * php /path/to/project/scripts/cleanup_guests.php
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';

$db = getDbConnection();
if (!$db) {
    die("資料庫連線失敗\n");
}

try {
    // 找出建立超過 24 小時，且為 guest 帳號的使用者
    $sql = "SELECT id FROM users
            WHERE username LIKE 'guest_%'
            AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)";

    // 對於 SQLite 的支援 (如果使用 SQLite，NOW() 會報錯，需要換寫法)
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $sql = "SELECT id FROM users
                WHERE username LIKE 'guest_%'
                AND created_at < datetime('now', '-24 hours')";
    }

    $stmt = $db->query($sql);
    $guests = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($guests)) {
        echo "沒有需要清除的訪客帳號。\n";
        exit(0);
    }

    // 刪除訪客帳號 (資料庫層有 ON DELETE CASCADE 設定的話，diaries 會一併刪除)
    // 為了安全起見，這裡使用明確的 DELETE
    $count = 0;
    foreach ($guests as $guestId) {
        // 找出並刪除日記相關的圖片檔案 (假設需要實體檔案刪除)
        $diaryStmt = $db->prepare("SELECT image_path FROM diaries WHERE user_id = ? AND image_path IS NOT NULL");
        $diaryStmt->execute([$guestId]);
        $imagePaths = $diaryStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($imagePaths as $path) {
            if (empty($path) || strpos($path, 'http') === 0) continue;
            $localPath = BASE_PATH . '/public/' . ltrim($path, '/');
            if (file_exists($localPath)) {
                @unlink($localPath);
            }
        }

        $delUserStmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $delUserStmt->execute([$guestId]);
        $count++;
    }

    echo "成功清除了 {$count} 個過期的訪客帳號。\n";

} catch (PDOException $e) {
    echo "執行清除任務時發生錯誤: " . $e->getMessage() . "\n";
    exit(1);
}
