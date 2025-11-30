<?php

namespace App\Models;

use PDO;
use PDOException;
use Exception;

class Diary
{

    private $db;

    public function __construct()
    {
        $this->connectDB();
    }

    private function connectDB()
    {
        // 使用統一的資料庫連線函數（支援 SQLite 和 MySQL）
        $this->db = getDbConnection();
        if (!$this->db) {
            error_log("Database Connection Failed in Diary model");
            die("資料庫連線時發生嚴重錯誤，請檢查伺服器日誌。");
        }
    }

    /**
     * 建立一篇新日記
     * @return int|bool The new diary's ID or false on failure.
     */
    public function create($userId, $title, $content, $mood, $date, $aiText = null, $imagePath = null)
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO diaries (user_id, title, content, mood, diary_date, ai_generated_text, image_path, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$userId, $title, $content, $mood, $date, $aiText, $imagePath]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Failed to create diary entry: " . $e->getMessage());
            throw new Exception("Failed to create diary entry: " . $e->getMessage());
        }
    }

    /**
     * 根據 ID 尋找一篇日記，並驗證擁有者
     * @return array|null
     */
    public function findById($diaryId, $userId)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM diaries WHERE id = ? AND user_id = ?");
            $stmt->execute([$diaryId, $userId]);
            return $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            error_log("Failed to find diary: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 獲取指定使用者和月份的所有日記
     * @return array
     */
    public function getDiariesByMonth($userId, $year, $month)
    {
        try {
            $startDate = "$year-$month-01";
            $endDate = date("Y-m-t", strtotime($startDate)); // 獲取該月最後一天

            $stmt = $this->db->prepare(
                "SELECT id, title, mood, diary_date, image_path FROM diaries WHERE user_id = ? AND diary_date BETWEEN ? AND ? ORDER BY diary_date ASC"
            );
            $stmt->execute([$userId, $startDate, $endDate]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Failed to get diaries by month: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 獲取指定使用者和日期的所有日記
     * @param int $userId
     * @param string $date 格式: Y-m-d
     * @return array
     */
    public function getDiariesByDate($userId, $date)
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, title, content, mood, diary_date, ai_generated_text, image_path, created_at FROM diaries WHERE user_id = ? AND diary_date = ? ORDER BY created_at ASC"
            );
            $stmt->execute([$userId, $date]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Failed to get diaries by date: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 獲取指定使用者的所有日記
     * @param int $userId
     * @return array
     */
    public function findAllByUserId($userId)
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, title, content, mood, diary_date, ai_generated_text, image_path, created_at FROM diaries WHERE user_id = ? ORDER BY diary_date DESC, created_at DESC"
            );
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Failed to get all diaries by user ID: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 刪除一篇日記（僅限擁有者）
     * @param int $diaryId
     * @param int $userId
     * @return bool
     */
    public function delete($diaryId, $userId)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM diaries WHERE id = ? AND user_id = ?");
            $stmt->execute([$diaryId, $userId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Failed to delete diary entry: " . $e->getMessage());
            throw new Exception("Failed to delete diary entry: " . $e->getMessage());
        }
    }

    /**
     * 獲取圖片檔案的實際路徑
     * @param string $imagePath 從資料庫取得的 image_path
     * @return string|null 實際檔案路徑，如果檔案不存在則返回 null
     */
    public static function getActualImagePath($imagePath)
    {
        if (empty($imagePath)) {
            return null;
        }

        // 首先嘗試標準路徑
        $fullPath = BASE_PATH . '/public/' . $imagePath;
        if (file_exists($fullPath)) {
            return $fullPath;
        }

        // 如果標準路徑不存在，嘗試修正路徑
        if (strpos($imagePath, 'storage/generated_images/') !== 0) {
            // 可能是舊格式或其他格式，嘗試修正
            $filename = basename($imagePath);
            $correctedPath = BASE_PATH . '/public/storage/generated_images/' . $filename;
            if (file_exists($correctedPath)) {
                return $correctedPath;
            }
        }

        return null;
    }

    /**
     * 刪除圖片檔案
     * @param string $imagePath 從資料庫取得的 image_path
     * @return bool 是否成功刪除
     */
    public static function deleteImageFile($imagePath)
    {
        $actualPath = self::getActualImagePath($imagePath);
        if ($actualPath && file_exists($actualPath)) {
            return unlink($actualPath);
        }
        return false;
    }

    public function __destruct()
    {
        $this->db = null; // PDO 會自動關閉連線
    }
}
