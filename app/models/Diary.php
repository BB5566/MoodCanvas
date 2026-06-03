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
                "INSERT INTO diaries (user_id, title, content, mood, diary_date, ai_generated_text, image_path, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now','localtime'))"
            );
            $stmt->execute([$userId, $title, $content, $mood, $date, $aiText, $imagePath]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Failed to create diary entry: " . $e->getMessage());
            throw new Exception("Failed to create diary entry: " . $e->getMessage());
        }
    }

    /**
     * 更新日記的 AI 生成內容
     * @param int $diaryId
     * @param array $data 包含 ai_generated_text 和/或 image_path
     * @return bool
     */
    public function update($diaryId, array $data)
    {
        try {
            $fields = [];
            $values = [];
            foreach (['ai_generated_text', 'image_path', 'title', 'content', 'mood', 'diary_date'] as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }
            if (empty($fields)) {
                return false;
            }
            $values[] = $diaryId;
            $sql = "UPDATE diaries SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("Failed to update diary: " . $e->getMessage());
            return false;
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
     * 根據 ID 尋找一篇日記（不驗證擁有者，用於公開檢視）
     */
    public function findByIdPublic($diaryId)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM diaries WHERE id = ?");
            $stmt->execute([$diaryId]);
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
            $endDate = date("Y-m-t", strtotime($startDate));

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
     */
    public function delete($diaryId, $userId)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM diaries WHERE id = ? AND user_id = ?");
            $stmt->execute([$diaryId, $userId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Failed to delete diary: " . $e->getMessage());
            return false;
        }
    }

    public static function getActualImagePath($imagePath)
    {
        if (empty($imagePath)) return null;
        if (strpos($imagePath, 'http') === 0) return $imagePath;
        return APP_URL . '/public/' . ltrim($imagePath, '/');
    }

    public static function deleteImageFile($imagePath)
    {
        if (empty($imagePath)) return;
        $localPath = BASE_PATH . '/public/' . ltrim($imagePath, '/');
        if (file_exists($localPath)) {
            @unlink($localPath);
        }
    }

    public function __destruct()
    {
        $this->db = null;
    }
}
