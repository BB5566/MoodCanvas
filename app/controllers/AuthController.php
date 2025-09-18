<?php
// app/controllers/AuthController.php

namespace App\Controllers;

use App\Models\User;
use Exception;

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
     * 顯示註冊頁面或處理註冊邏輯
     */
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];

            // 基本驗證
            if (empty($username) || empty($password)) {
                $this->showError('使用者名稱和密碼不能為空！');
                return;
            }
            if ($password !== $confirm_password) {
                $this->showError('兩次輸入的密碼不一致！');
                return;
            }
            if ($this->userModel->findByUsername($username)) {
                $this->showError('此使用者名稱已被註冊！');
                return;
            }

            // 建立使用者
            try {
                $this->userModel->create($username, $password);
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
            $username = trim($_POST['username']);
            $password = $_POST['password'];

            if (empty($username) || empty($password)) {
                $this->showError('請輸入使用者名稱和密碼。');
                return;
            }

            try {
                $user = $this->userModel->findByUsername($username);
                if ($user && password_verify($password, $user['password_hash'])) {
                    // 登入成功，設定 session
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
        // 簡單地導回登入頁面，可以根據情境做得更複雜
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php?action=login'));
        exit;
    }
}
?>
