<?php
namespace App\Models;

use PDO;
use PDOException;
use Exception;

class User {

    private $db;

    public function __construct() {
        $this->connectDB();
    }

    /**
     * 建立資料庫連線
     */
    private function connectDB() {
        // 使用統一的資料庫連線函數（支援 SQLite 和 MySQL）
        $this->db = getDbConnection();
        if (!$this->db) {
            error_log("Database Connection Failed in User model");
            die("資料庫連線時發生嚴重錯誤，請檢查伺服器日誌。");
        }
    }

    /**
     * 根據使用者名稱尋找使用者
     * @param string $username
     * @return array|null
     */
    public function findByUsername($username) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            return $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            error_log("Failed to find user: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 建立新使用者
     * @param string $username
     * @param string $password
     * @return int|bool The new user's ID or false on failure.
     */
    public function create($username, $password) {
        try {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
            $stmt->execute([$username, $password_hash]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Failed to create user: " . $e->getMessage());
            throw new Exception("Failed to create user");
        }
    }
    
    /**
     * 獲取所有用戶 (管理員功能)
     */
    public function getAllUsers($limit = 100, $offset = 0) {
        try {
            if (!$this->db) return [];
            
            $sql = "SELECT id, username, email, created_at, updated_at 
                    FROM users 
                    ORDER BY created_at DESC 
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("獲取用戶列表失敗: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 獲取用戶統計 (管理員功能)
     */
    public function getUserStats() {
        try {
            if (!$this->db) {
                return ['total_users' => 0];
            }
            $stmt = $this->db->query("SELECT COUNT(*) as total_users FROM users");
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("獲取用戶統計失敗: " . $e->getMessage());
            return ['total_users' => 0];
        }
    }
}
?>
