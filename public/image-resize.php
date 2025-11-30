<?php
/**
 * 圖片縮小工具 - 將現有的大圖片壓縮成較小尺寸
 */

session_start();

// 安全檢查 - 需要管理員密碼 (從環境變數讀取)
$adminPassword = getenv('ADMIN_PASSWORD') ?: 'please_change_this_password';

if (!isset($_POST['admin_password']) && !isset($_SESSION['admin_auth'])) {
    // 顯示密碼輸入表單
    echo '<html><head><title>圖片壓縮工具 - 管理員登入</title></head><body>';
    echo '<div style="max-width:400px;margin:100px auto;font-family:Arial;">';
    echo '<h2>圖片壓縮工具</h2>';
    echo '<p>此工具需要管理員權限，請輸入密碼：</p>';
    echo '<form method="post">';
    echo '<input type="password" name="admin_password" placeholder="管理員密碼" style="padding:10px;width:100%;margin:10px 0;">';
    echo '<button type="submit" style="padding:10px 20px;background:#007cba;color:white;border:none;cursor:pointer;">登入</button>';
    echo '</form>';
    echo '</div></body></html>';
    exit;
}

if (isset($_POST['admin_password'])) {
    if ($_POST['admin_password'] === $adminPassword) {
        $_SESSION['admin_auth'] = true;
    } else {
        die('<script>alert("密碼錯誤！"); history.back();</script>');
    }
}

if (!isset($_SESSION['admin_auth'])) {
    die('未授權訪問');
}

require_once dirname(__DIR__) . '/config/config.php';

// 處理登出
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// 開啟錯誤顯示以便調試
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>圖片縮小工具</h1>";
echo "<p style='text-align:right;'><a href='?logout=1'>登出</a></p>";
echo "<style>body{font-family:monospace;} .ok{color:green;} .error{color:red;} .info{color:blue;}</style>";

// 檢查 GD 擴展
if (!extension_loaded('gd')) {
    echo "<span class='error'>錯誤: GD 擴展未安裝！</span><br>";
    exit;
} else {
    echo "<span class='ok'>✓ GD 擴展已安裝</span><br>";
}

$imageDir = dirname(__DIR__) . '/public/storage/generated_images';

// 掃描圖片檔案
$images = glob($imageDir . '/*.{png,jpg,jpeg,PNG,JPG,JPEG}', GLOB_BRACE);
echo "<span class='info'>找到 " . count($images) . " 張圖片</span><br><br>";

if (isset($_GET['action']) && $_GET['action'] === 'resize') {
    // 執行壓縮
    $processed = 0;
    $totalSaved = 0;

    foreach ($images as $imagePath) {
        $filename = basename($imagePath);
        $originalSize = filesize($imagePath);

        echo "處理: {$filename} (" . formatBytes($originalSize) . ")... ";

        try {
            // 檢查原始圖片資訊
            $imageInfo = getimagesize($imagePath);
            if ($imageInfo) {
                list($origWidth, $origHeight, $imageType) = $imageInfo;
                echo "原始: {$origWidth}x{$origHeight}, 類型: {$imageType} - ";
            }

            // 直接縮小並壓縮圖片
            $result = resizeImage($imagePath, $imagePath, 768, 768, 75);
            echo "處理結果: " . ($result ? 'true' : 'false') . " - ";

            if ($result) {
                $newSize = filesize($imagePath);
                $saved = $originalSize - $newSize;
                $totalSaved += $saved;
                $processed++;

                // 檢查新圖片資訊
                $newImageInfo = getimagesize($imagePath);
                if ($newImageInfo) {
                    list($newWidth, $newHeight) = $newImageInfo;
                    echo "<span class='ok'>成功!</span> 新尺寸: {$newWidth}x{$newHeight}, ";
                }

                echo "新大小: " . formatBytes($newSize) .
                     " (節省: " . formatBytes($saved) . ")<br>";
            } else {
                echo "<span class='error'>失敗</span><br>";
            }
        } catch (Exception $e) {
            echo "<span class='error'>錯誤: " . $e->getMessage() . "</span><br>";
        }
    }

    echo "<br><h2>完成!</h2>";
    echo "處理了 {$processed} 張圖片<br>";
    echo "總共節省: " . formatBytes($totalSaved) . "<br>";
    echo "<p><a href='?'>返回</a></p>";

} else {
    // 顯示預覽和操作選項
    echo "<h2>圖片列表 (前10張)</h2>";
    $totalSize = 0;
    $count = 0;

    foreach (array_slice($images, 0, 10) as $imagePath) {
        $filename = basename($imagePath);
        $size = filesize($imagePath);
        $totalSize += $size;

        list($width, $height) = getimagesize($imagePath);

        echo "<div style='margin:10px 0; padding:10px; border:1px solid #ccc;'>";
        echo "<strong>{$filename}</strong><br>";
        echo "尺寸: {$width} x {$height} px<br>";
        echo "檔案大小: " . formatBytes($size) . "<br>";
        echo "</div>";

        $count++;
    }

    if (count($images) > 10) {
        echo "<p>... 還有 " . (count($images) - 10) . " 張圖片</p>";
    }

    echo "<h2>預估結果</h2>";
    echo "目前總大小: " . formatBytes(array_sum(array_map('filesize', $images))) . "<br>";
    echo "預估壓縮後: " . formatBytes(array_sum(array_map('filesize', $images)) * 0.3) . " (約70%縮減)<br>";

    echo "<h2>操作選項</h2>";
    echo "<p><strong>注意:</strong> 此操作會:</p>";
    echo "<ul>";
    echo "<li>將所有圖片縮小到 768x768 像素</li>";
    echo "<li>轉換為 JPEG 格式 (品質75%)</li>";
    echo "<li><span style='color:red;'>直接覆蓋原始檔案 (不可逆)</span></li>";
    echo "</ul>";

    echo "<p><a href='?action=resize' style='background:#ff6b35;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;' onclick='return confirm(\"確定要壓縮所有圖片嗎？此操作不可逆！\")'>開始壓縮所有圖片</a></p>";
}

/**
 * 縮放圖片
 */
function resizeImage($sourcePath, $destPath, $maxWidth, $maxHeight, $quality = 75) {
    if (!extension_loaded('gd')) {
        error_log("GD extension not loaded");
        return false;
    }

    // 獲取原始圖片資訊
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        error_log("Cannot get image info for: $sourcePath");
        return false;
    }

    list($origWidth, $origHeight, $imageType) = $imageInfo;

    // 計算新尺寸 (保持比例)
    $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
    $newWidth = round($origWidth * $ratio);
    $newHeight = round($origHeight * $ratio);

    // 如果圖片已經足夠小，仍然要轉換格式
    // 創建新圖片
    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    // 設定白色背景 (對 PNG 透明度重要)
    $white = imagecolorallocate($newImage, 255, 255, 255);
    imagefill($newImage, 0, 0, $white);

    // 載入原始圖片
    switch ($imageType) {
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        default:
            error_log("Unsupported image type: $imageType");
            imagedestroy($newImage);
            return false;
    }

    if (!$sourceImage) {
        error_log("Cannot create image from source: $sourcePath");
        imagedestroy($newImage);
        return false;
    }

    // 縮放圖片
    $resampleResult = imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0,
                      $newWidth, $newHeight, $origWidth, $origHeight);

    if (!$resampleResult) {
        error_log("imagecopyresampled failed");
        imagedestroy($sourceImage);
        imagedestroy($newImage);
        return false;
    }

    // 確保目錄可寫
    $destDir = dirname($destPath);
    if (!is_writable($destDir)) {
        error_log("Destination directory not writable: $destDir");
        imagedestroy($sourceImage);
        imagedestroy($newImage);
        return false;
    }

    // 保存為 JPEG
    $result = imagejpeg($newImage, $destPath, $quality);

    if (!$result) {
        error_log("imagejpeg failed for: $destPath");
    }

    // 清理記憶體
    imagedestroy($sourceImage);
    imagedestroy($newImage);

    return $result;
}

/**
 * 格式化檔案大小
 */
function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}
?>