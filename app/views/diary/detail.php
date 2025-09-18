<?php
require_once __DIR__ . '/../../../config/config.php';
require_once BASE_PATH . '/app/views/layout/header.php';

// 假設 $diary 變數從 DiaryController 傳遞過來
// $diary 是一個包含日記所有資訊的關聯陣列

if (!$diary) {
    // 如果沒有找到日記，顯示錯誤訊息或重導
    echo "<div class='bento-card text-center'><h2>日記不存在或已被刪除。</h2><a href='index.php?action=home' class='btn'>返回日曆</a></div>";
    require_once BASE_PATH . '/app/views/layout/footer.php';
    exit;
}

// 安全地處理輸出
$title = htmlspecialchars($diary['title'] ?? '無標題');
$content = nl2br(htmlspecialchars($diary['content'] ?? '')); // nl2br 用於保留換行
$mood = htmlspecialchars($diary['mood'] ?? '📝');
$date = new DateTime($diary['diary_date'] ?? 'now');
$imagePath = htmlspecialchars($diary['image_path'] ?? '');
$aiText = nl2br(htmlspecialchars($diary['ai_generated_text'] ?? ''));

// SEO: 動態標題與描述
$pageTitle = $title . ' | ' . $date->format('Y-m-d') . ' ' . $mood;
$metaDescription = mb_substr(strip_tags($diary['content'] ?? ''), 0, 80, 'UTF-8');

$showImage = !empty($imagePath);
$imageUrl = '';
if ($showImage) {
    // 檢查是否已經是完整 URL
    if (strpos($imagePath, 'http') === 0) {
        $imageUrl = $imagePath;
    } else {
        // 構建完整的圖片 URL - 修正路徑
        if (strpos($imagePath, 'public/') === 0) {
            // 如果路徑已包含 public/，直接使用
            $imageUrl = APP_URL . '/' . $imagePath;
        } else {
            // 如果路徑不包含 public/，添加
            $imageUrl = APP_URL . '/public/' . $imagePath;
        }
    }
    
    // 檢查檔案是否存在
    $localPath = str_replace(APP_URL . '/', '', $imageUrl);
    if (!file_exists(BASE_PATH . '/' . $localPath)) {
        $showImage = false;
    }
}

?>

<div class="bento-card diary-detail">
    <div class="diary-detail-header">
        <span class="diary-mood" title="心情"><?php echo $mood; ?></span>
        <h1 class="diary-title"><?php echo $title; ?></h1>
        <time class="diary-date" datetime="<?php echo $date->format('c'); ?>">
            <?php echo $date->format('Y年m月d日'); ?>
        </time>
    </div>

    <?php if ($showImage): ?>
        <div class="diary-image-container">
            <img src="<?php echo $imageUrl; ?>" alt="<?php echo $title; ?> 的 AI 生成圖片" class="diary-image" onerror="this.style.display='none';">
            <p class="ai-credit">AI 生成圖片</p>
        </div>
    <?php endif; ?>

    <div class="diary-content-wrapper">
        <div class="diary-content">
            <?php echo $content; ?>
        </div>

        <?php if (!empty($aiText)): ?>
            <div class="ai-generated-text">
                <hr>
                <h4>AI 的悄悄話</h4>
                <blockquote>
                    <p><?php echo $aiText; ?></p>
                </blockquote>
            </div>
        <?php endif; ?>
    </div>

    <div class="diary-actions">
        <a href="index.php?action=home" class="btn btn-tertiary">返回日曆</a>
            <?php if (!empty($is_owner) && $is_owner === true): ?>
                <a href="#" onclick="confirmDelete(<?php echo $diary['id']; ?>)" class="btn btn-danger">🗑️ 刪除日記</a>
            <?php else: ?>
                <div style="display:inline-block; margin-left:0.5rem; color:#f55;">需登入並為作者才能刪除或編輯此篇日記</div>
            <?php endif; ?>
            <!-- <a href="#" class="btn btn-secondary">編輯日記</a> -->
        </div>
</div>

<script>
function confirmDelete(diaryId) {
    if (confirm('確定要刪除這篇日記嗎？此操作無法復原。')) {
        // 創建表單並提交
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'index.php?action=diary_delete';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'diary_id';
        idInput.value = diaryId;
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = '<?php echo htmlspecialchars(generateCsrfToken()); ?>';
        
        form.appendChild(idInput);
        form.appendChild(csrfInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php
require_once BASE_PATH . '/app/views/layout/footer.php';
?>
