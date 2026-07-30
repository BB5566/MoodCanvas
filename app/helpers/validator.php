<?php
/**
 * app/helpers/validator.php
 *
 * 輸入驗證與淨化層。供控制器包覆直接的 $_POST / $_GET 存取，
 * 統一基本檢查規則。
 */

if (!function_exists('validateEmail')) {
    /**
     * 驗證電子郵件：filter_var + 基本格式正規式 + 長度限制。
     *
     * @param mixed $email
     * @return bool
     */
    function validateEmail($email) {
        if (!is_string($email) || $email === '') {
            return false;
        }
        if (strlen($email) > 254) {
            return false;
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }
        // 額外收緊：filter_var 對某些邊界案例過於寬鬆，這裡要求 local@domain.tld 結構
        return (bool) preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email);
    }
}

if (!function_exists('validateRequired')) {
    /**
     * 檢查 $data 中哪些欄位缺失或為空。
     *
     * @param array $fields 必填欄位名稱清單
     * @param array $data   待檢查資料（通常是 $_POST）
     * @return array 缺失的欄位名稱（空陣列代表全部通過）
     */
    function validateRequired(array $fields, array $data) {
        $missing = [];
        foreach ($fields as $field) {
            $value = $data[$field] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $missing[] = $field;
            }
        }
        return $missing;
    }
}

if (!function_exists('sanitizeOutput')) {
    /**
     * 輸出淨化：將 HTML 特殊字元轉義。
     * ⚠ 僅用於「即將輸出到畫面」的資料；切勿對即將寫入資料庫的內容呼叫，以免雙重轉義。
     *
     * @param mixed $str
     * @return string
     */
    function sanitizeOutput($str) {
        return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitizeInt')) {
    /**
     * 將值驗證為整數；無效時回傳預設值。
     *
     * @param mixed $val
     * @param int   $default
     * @return int
     */
    function sanitizeInt($val, $default = 0) {
        if ($val === null || $val === '') {
            return $default;
        }
        $int = filter_var($val, FILTER_VALIDATE_INT);
        return ($int === false) ? $default : (int) $int;
    }
}
