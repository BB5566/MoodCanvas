<?php
// app/controllers/AuthController.php

namespace App\Controllers;

use App\Models\User;
use Exception;

// 安全輔助層（CSRF 驗證 + 輸入驗證）
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/validator.php';

class AuthController {

    private $userModel;

    public function __construct() {
        // 確保 config.php 已被載入
        if (!defined('DB_HOST')) {
            // 不再需要，由 index.php 統一處理
            // require_once __DIR__ . '/../../config/config.php';
        }
        $this->userModel = new User();
    }

    /**
     * 轉移訪客資料到新帳號
     * 遇到同一天兩邊都有日記，保留正式帳號的，刪除訪客的。
     * 處理完畢後刪除該訪客帳號。
     */
    private function migrateGuestData($guestId, $newUserId) {
        $db = getDbConnection();
        if (!$db) return;

        try {
            // 找出該訪客的所有日記
            $stmt = $db->prepare("SELECT id, diary_date FROM diaries WHERE user_id = ?");
            $stmt->execute([$guestId]);
            $guestDiaries = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($guestDiaries as $diary) {
                // 檢查新帳號在這一天是否已經有日記
                $checkStmt = $db->prepare("SELECT id FROM diaries WHERE user_id = ? AND diary_date = ?");
                $checkStmt->execute([$newUserId, $diary['diary_date']]);
                $existing = $checkStmt->fetch();

                if ($existing) {
                    // 衝突：保留正式帳號，刪除訪客的
                    $delStmt = $db->prepare("DELETE FROM diaries WHERE id = ?");
                    $delStmt->execute([$diary['id']]);
                } else {
                    // 無衝突：轉移給新帳號
                    $updStmt = $db->prepare("UPDATE diaries SET user_id = ? WHERE id = ?");
                    $updStmt->execute([$newUserId, $diary['id']]);
                }
            }

            // 刪除訪客帳號
            $delUserStmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $delUserStmt->execute([$guestId]);

        } catch (\PDOException $e) {
            error_log("Migrate guest data failed: " . $e->getMessage());
        }
    }

    /**
     * 顯示註冊頁面或處理註冊邏輯
     */
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // CSRF 驗證（Fix 2）
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                $this->showError('安全驗證失敗，請重新整理頁面後再試。');
                return;
            }

            // 輸入驗證（Fix 5）：安全讀值並套用基本檢查
            $username         = trim($_POST['username'] ?? '');
            $email            = trim($_POST['email'] ?? '');
            $password         = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            // 必填欄位驗證
            if (!empty(validateRequired(['username', 'email', 'password'], $_POST))) {
                $this->showError('請填寫所有必填欄位。');
                return;
            }
            // 電子郵件格式驗證
            if (!validateEmail($email)) {
                $this->showError('電子郵件格式不正確。');
                return;
            }
            // 使用者名稱長度限制（與表單 maxlength=30 一致）
            if (mb_strlen($username) > 30) {
                $this->showError('使用者名稱不能超過 30 字。');
                return;
            }
            // 密碼長度（伺服器端強制，與表單 minlength=8 一致）
            if (strlen($password) < 8) {
                $this->showError('密碼長度至少需要 8 個字元。');
                return;
            }
            if ($password !== $confirm_password) {
                $this->showError('兩次輸入的密碼不一致！');
                return;
            }

            // 阻擋註冊以 guest_ 開頭的帳號，避免被清理腳本誤刪
            if (stripos($username, 'guest_') === 0) {
                $this->showError('此名稱為系統保留。');
                return;
            }

            if ($this->userModel->findByUsername($username)) {
                $this->showError('此使用者名稱已被註冊！');
                return;
            }

            // 建立使用者
            try {
                $newUserId = $this->userModel->create($username, $password);

                // 如果是訪客註冊，轉移資料
                if (!empty($_SESSION['is_guest']) && isset($_SESSION['user_id'])) {
                    $this->migrateGuestData($_SESSION['user_id'], $newUserId);
                    // 轉移後銷毀 session，讓使用者得到乾淨的新狀態並重新登入
                    session_unset();
                    session_destroy();
                }

                // 註冊成功後直接導向登入頁面
                header('Location: index.php?action=login&status=registered');
                exit;
            } catch (Exception $e) {
                error_log('User registration failed: ' . $e->getMessage());
                $this->showError('註冊失敗，請稍後再試。');
            }

        } else {
            // 顯示註冊表單
            require_once BASE_PATH . '/app/views/auth/register.php';
        }
    }

    /**
     * 顯示登入頁面或處理登入邏輯
     */
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // CSRF 驗證（Fix 2）
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                $this->showError('安全驗證失敗，請重新整理頁面後再試。');
                return;
            }

            // 輸入驗證（Fix 5）：安全讀值
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            // 必填欄位驗證
            if (!empty(validateRequired(['username', 'password'], $_POST))) {
                $this->showError('請輸入使用者名稱和密碼。');
                return;
            }

            try {
                $user = $this->userModel->findByUsername($username);
                if ($user && password_verify($password, $user['password_hash'])) {

                    // 如果目前是訪客，先記下訪客 ID 進行轉移
                    if (!empty($_SESSION['is_guest']) && isset($_SESSION['user_id'])) {
                        $guestId = $_SESSION['user_id'];
                        $this->migrateGuestData($guestId, $user['id']);
                        unset($_SESSION['is_guest']);
                    }

                    // 登入成功，設定 session
                    session_regenerate_id(true); // 防止 Session Fixation
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    // 導向到日記主頁
                    header('Location: index.php?action=home');
                    exit;
                } else {
                    $this->showError('使用者名稱或密碼錯誤。');
                }
            } catch (Exception $e) {
                error_log('User login failed: ' . $e->getMessage());
                $this->showError('登入時發生錯誤，請稍後再試。');
            }

        } else {
            // 顯示登入表單
            require_once BASE_PATH . '/app/views/auth/login.php';
        }
    }

    /**
     * 處理登出邏輯
     */
    public function logout() {
        // 若為訪客直接登出（或 session 過期模擬登出時呼叫），刪除訪客帳號避免殘留
        if (!empty($_SESSION['is_guest']) && isset($_SESSION['user_id'])) {
            $db = getDbConnection();
            if ($db) {
                try {
                    $delStmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $delStmt->execute([$_SESSION['user_id']]);
                } catch (\PDOException $e) {
                    error_log("Failed to delete guest on logout: " . $e->getMessage());
                }
            }
        }

        session_unset();
        session_destroy();
        header('Location: index.php?action=login&status=logged_out');
        exit;
    }

    /**
     * 顯示錯誤訊息並導回上一頁或指定頁面
     * @param string $message
     */
    private function showError($message) {
        $_SESSION['error_message'] = $message;
        // 安全導回：驗證 Referer 是否為同站 URL，避免 Open Redirect
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $allowed_host = $_SERVER['HTTP_HOST'] ?? '';
        $fallback = 'index.php?action=login';
        if (!empty($referer) && !empty($allowed_host)) {
            $parsed = parse_url($referer);
            if (isset($parsed['host']) && $parsed['host'] === $allowed_host) {
                $fallback = $referer;
            }
        }
        header('Location: ' . $fallback);
        exit;
    }
}
?>