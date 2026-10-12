<?php
// app/views/diary/detail.php

require_once BASE_PATH . '/app/views/layout/header.php';

// 翻轉卡片 CSS 已在 style-v2.css 中定義（由 header.php 載入），此處不再重複
$flipCSS = '20260603a'; ?>

<?php

if (!$diary) {
    echo "<div class='bento-card text-center'><h2>日記不存在或已被刪除。</h2><a href='index.php?action=home' class='btn'>返回日曆</a></div>";
    require_once BASE_PATH . '/app/views/layout/footer.php';
    exit;
}

$title = htmlspecialchars($diary['title'] ?? '無標題');
$content = nl2br(htmlspecialchars($diary['content'] ?? ''));
$mood = htmlspecialchars($diary['mood'] ?? '📝');
$date = new DateTime($diary['diary_date'] ?? 'now');
$imagePath = $diary['image_path'] ?? '';
$aiText = $diary['ai_generated_text'] ?? '';
$diaryId = $diary['id'];

$pageTitle = $title . ' | ' . $date->format('Y-m-d') . ' ' . $mood;
$metaDescription = mb_substr(strip_tags($diary['content'] ?? ''), 0, 80, 'UTF-8');

// 圖片 URL
$imageUrl = '';
if (!empty($imagePath)) {
    if (strpos($imagePath, 'http') === 0) {
        $imageUrl = $imagePath;
    } elseif (strpos($imagePath, 'public/') === 0) {
        $imageUrl = APP_URL . '/' . $imagePath;
    } else {
        $imageUrl = APP_URL . '/public/' . ltrim($imagePath, '/');
    }
}

$hasImage = !empty($imageUrl);
$hasQuote = !empty($aiText);
$aiGenerating = ($ai_generating ?? false);
?>

<div class="diary-card-wrapper">
    
    <!-- 翻轉卡片本體 -->
    <div class="flip-card" id="flip-card">
        <div class="flip-card-inner" id="flip-card-inner">
            
            <!-- 正面：拍立得照片（圖片 / Emoji）-->
            <div class="flip-card-front" id="card-front">
                <div class="polaroid-photo">
                    <?php if ($hasImage): ?>
                        <img src="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES); ?>"
                             alt="AI 生成圖片"
                             class="card-image"
                             loading="lazy"
                             onerror="this.parentElement.innerHTML='<div class=\'card-emoji-fallback\'><?php echo $mood; ?></div>'">
                    <?php else: ?>
                        <div class="card-emoji-fallback" id="card-emoji">
                            <span class="card-emoji"><?php echo $mood; ?></span>
                            <?php if ($aiGenerating): ?>
                                <div class="ai-generating-badge">
                                    <div class="loading-spinner-small"></div>
                                    <span>AI 正在創作...</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 拍立得下方手寫感說明 -->
                <div class="polaroid-caption">
                    <span class="card-date"><?php echo $date->format('Y . m . d'); ?></span>
                    <span class="card-mood-tag" role="img" aria-label="心情"><?php echo $mood; ?></span>
                </div>

                <!-- 翻轉提示 -->
                <div class="flip-hint">
                    <span>點擊翻轉 ↻</span>
                </div>
            </div>
            
            <!-- 背面：內容 + 短語 -->
            <div class="flip-card-back" id="card-back">
                <div class="card-back-content">
                    <h2 class="card-back-title"><?php echo $title; ?></h2>
                    <time class="card-back-date"><?php echo $date->format('Y年m月d日'); ?></time>
                    
                    <div class="card-back-diary">
                        <?php echo $content; ?>
                    </div>
                    
                    <?php if ($hasQuote): ?>
                        <div class="card-back-quote" id="quote-section">
                            <div class="quote-divider"></div>
                            <blockquote id="quote-text"><?php echo htmlspecialchars($aiText); ?></blockquote>
                        </div>
                    <?php elseif ($aiGenerating): ?>
                        <div class="card-back-quote" id="quote-section">
                            <div class="quote-divider"></div>
                            <div class="quote-loading" id="quote-loading">
                                <div class="loading-spinner-small"></div>
                                <span>AI 正在為你寫下心情短語...</span>
                            </div>
                            <blockquote id="quote-text" style="display:none;"></blockquote>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- 翻轉提示 -->
                <div class="flip-hint">
                    <span>點擊翻轉 ↻</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 操作按鈕 -->
    <div class="diary-actions">
        <a href="index.php?action=home" class="btn btn-tertiary">📅 返回日曆</a>
        <a href="index.php?action=diary_edit&id=<?php echo $diaryId; ?>" class="btn btn-tertiary">✏️ 編輯</a>
        <a href="index.php?action=diary_create" class="btn btn-secondary">✏️ 再寫一篇</a>
    </div>
</div>

<?php if (!$aiGenerating && !$hasImage): ?>
<div class="diary-actions" style="margin-top:0.5rem">
    <button class="btn btn-secondary" style="font-size:0.85rem" onclick="regenerateAI(<?php echo $diaryId; ?>, event)">🔄 重新生成 AI</button>
</div>
<?php endif; ?>

<?php if ($aiGenerating): ?>
<div id="ai-generating-data" style="display:none;"
     data-ai-generating="true"
     data-diary-id="<?php echo (int) $diaryId; ?>"
     data-app-url="<?php echo htmlspecialchars(APP_URL, ENT_QUOTES); ?>"
     data-card-date="<?php echo $date->format('Y.m.d'); ?>"
     data-card-mood="<?php echo $mood; ?>">
</div>
<?php endif; ?>

<script src="<?php echo APP_URL; ?>/public/assets/js/diary.js" defer></script>

<?php
require_once BASE_PATH . '/app/views/layout/footer.php';
?>
