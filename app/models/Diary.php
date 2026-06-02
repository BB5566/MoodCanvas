<?php
// app/models/Diary.php — v2 含 update()

namespace App\Models;

use PDO;
use PDOException;
use Exception;

class Diary
{
    private $db;

    public function __construct() { $this->connectDB(); }

    private function connectDB() {
        $this->db = getDbConnection();
        if (!$this->db) { error_log("DB Connection Failed"); die("資料庫錯誤"); }
    }

    public function create($userId, $title, $content, $mood, $date, $aiText = null, $imagePath = null) {
        try {
            $stmt = $this->db->prepare("INSERT INTO diaries (user_id, title, content, mood, diary_date, ai_generated_text, image_path, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now','localtime'))");
            $stmt->execute([$userId, $title, $content, $mood, $date, $aiText, $imagePath]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) { throw new Exception("Create failed: " . $e->getMessage()); }
    }

    public function update($diaryId, array $data) {
        $fields = []; $values = [];
        foreach (['ai_generated_text','image_path','title','content','mood','diary_date'] as $f) {
            if (array_key_exists($f, $data)) { $fields[] = "$f = ?"; $values[] = $data[$f]; }
        }
        if (empty($fields)) return false;
        $values[] = $diaryId;
        $stmt = $this->db->prepare("UPDATE diaries SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($values);
    }

    public function findById($diaryId, $userId) {
        $stmt = $this->db->prepare("SELECT * FROM diaries WHERE id = ? AND user_id = ?");
        $stmt->execute([$diaryId, $userId]);
        return $stmt->fetch() ?: null;
    }

    public function getDiariesByMonth($userId, $year, $month) {
        $start = "$year-$month-01"; $end = date("Y-m-t", strtotime($start));
        $stmt = $this->db->prepare("SELECT id, title, mood, diary_date, image_path FROM diaries WHERE user_id = ? AND diary_date BETWEEN ? AND ? ORDER BY diary_date ASC");
        $stmt->execute([$userId, $start, $end]);
        return $stmt->fetchAll();
    }

    public function getDiariesByDate($userId, $date) {
        $stmt = $this->db->prepare("SELECT * FROM diaries WHERE user_id = ? AND diary_date = ? ORDER BY created_at ASC");
        $stmt->execute([$userId, $date]);
        return $stmt->fetchAll();
    }

    public function findAllByUserId($userId) {
        $stmt = $this->db->prepare("SELECT * FROM diaries WHERE user_id = ? ORDER BY diary_date DESC, created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function delete($diaryId, $userId) {
        $stmt = $this->db->prepare("DELETE FROM diaries WHERE id = ? AND user_id = ?");
        $stmt->execute([$diaryId, $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function getActualImagePath($imagePath) {
        if (empty($imagePath)) return null;
        if (strpos($imagePath, 'http') === 0) return $imagePath;
        return APP_URL . '/public/' . ltrim($imagePath, '/');
    }

    public static function deleteImageFile($imagePath) {
        if (empty($imagePath)) return;
        $local = BASE_PATH . '/public/' . ltrim($imagePath, '/');
        if (file_exists($local)) @unlink($local);
    }

    public function __destruct() { $this->db = null; }
}
