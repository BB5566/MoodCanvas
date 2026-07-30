<?php
/**
 * app/helpers/csrf.php
 *
 * CSRF 權杖產生與驗證。
 *
 * 注意：config/config.php 已定義 generateCsrfToken() 與 verifyCsrfToken()，
 * 因此此處全部以 function_exists 守護，避免重複宣告造成 fatal error。
 * validateCsrfToken() 為規範要求的標準命名入口（與 verifyCsrfToken 同義），
 * 供所有「表單處理控制器」統一使用；csrfField() 則供表單輸出隱藏欄位。
 */

if (!function_exists('generateCsrfToken')) {
    /**
     * 產生（或取回既有）CSRF 權杖並存入 session。
     * @return string 64 字元的十六進位權杖
     */
    function generateCsrfToken() {
        $key = defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'csrf_token';
        if (empty($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }
        return $_SESSION[$key];
    }
}

if (!function_exists('validateCsrfToken')) {
    /**
     * 以常數時間比較驗證 CSRF 權杖，避免時序攻擊。
     *
     * @param mixed $token 來自表單或請求的權杖
     * @return bool
     */
    function validateCsrfToken($token) {
        $key = defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'csrf_token';
        $stored = $_SESSION[$key] ?? null;
        if ($stored === null || $token === null || $token === '') {
            return false;
        }
        return hash_equals($stored, (string) $token);
    }
}

if (!function_exists('csrfField')) {
    /**
     * 回傳可直接放入 <form> 的隱藏 CSRF 欄位 HTML（已逸出）。
     * @return string
     */
    function csrfField() {
        return '<input type="hidden" name="csrf_token" value="'
             . htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8')
             . '">';
    }
}
