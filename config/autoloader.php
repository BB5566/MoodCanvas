<?php
/**
 * 簡易 PSR-4 自動載入器
 *
 * 這個檔案會根據類別的命名空間自動載入對應的檔案，
 * 讓我們可以移除所有手動的 require_once。
 */
spl_autoload_register(function ($class) {
    // 專案的根命名空間
    $prefix = 'App\\';

    // 專案的基礎目錄
    $base_dir = __DIR__ . '/../app/';

    // 檢查類別是否使用我們的根命名空間
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // 不是我們的類別，交給下一個自動載入器處理
        return;
    }

    // 取得相對於根命名空間的類別名稱
    $relative_class = substr($class, $len);

    // 將命名空間轉換為檔案路徑，並將目錄部分轉為小寫以解決大小寫敏感問題
    $last_slash_pos = strrpos($relative_class, '\\');
    if ($last_slash_pos !== false) {
        $namespace = substr($relative_class, 0, $last_slash_pos);
        $class_name = substr($relative_class, $last_slash_pos + 1);
        $file = $base_dir . strtolower(str_replace('\\', '/', $namespace)) . '/' . $class_name . '.php';
    } else {
        // 如果沒有命名空間，直接使用類別名稱
        $file = $base_dir . $relative_class . '.php';
    }

    // 如果檔案存在，就載入它
    if (file_exists($file)) {
        require $file;
    }
});
