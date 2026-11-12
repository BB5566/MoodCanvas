<?php
// app/views/diary/detail.php

if (!$diary) {
    require_once BASE_PATH . '/app/views/layout/header.php';
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
$isDemo = !empty($diary['is_demo']); // 訪客看的範例日記：唯讀，不顯示編輯與 AI 按鈕

$pageTitle = ($diary['title'] ?? '無標題') . ' | ' . $date->format('Y-m-d');
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
$aiGenerating = !$isDemo && ($ai_generating ?? false);
$ogImage = $imageUrl; // 分享時的預覽圖用這篇的圖
require_once BASE_PATH . '/app/views/layout/header.php';
?>

<div class="diary-card-wrapper">

    <!-- 翻轉卡片本體：正面拍立得、背面 AI 寫的話和日記 -->
    <div class="flip-card" id="flip-card" role="button" tabindex="0" aria-label="翻轉拍立得卡片">
        <div class="flip-card-inner" id="flip-card-inner">

            <div class="flip-card-front" id="card-front">
                <div class="polaroid-photo">
                    <?php if ($hasImage): ?>
                        <img src="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES); ?>"
                             alt="<?php echo htmlspecialchars($title, ENT_QUOTES); ?> - AI 生成插畫"
                             class="card-image"
                             loading="lazy"
                             id="card-image-el"
                             data-fallback-mood="<?php echo htmlspecialchars(moodLabel($diary['mood'] ?? ''), ENT_QUOTES); ?>">
                    <?php elseif ($aiGenerating): ?>
                        <!-- 生成中：像拍立得剛吐出來，底片慢慢顯影 -->
                        <div class="card-emoji-fallback developing" id="card-emoji">
                            <div class="develop-status">
                                <span class="develop-stage" id="develop-stage" role="status">正在讀你的日記</span>
                                <span class="develop-time" id="develop-time" aria-hidden="true">0 秒</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card-emoji-fallback" id="card-emoji">
                            <span class="card-emoji"><?php echo htmlspecialchars(moodLabel($diary['mood'] ?? '')); ?></span>
                        </div>
                    <?php endif; ?>
                    <span class="develop-stamp" id="develop-stamp" aria-hidden="true">顯影完成</span>
                </div>

                <div class="polaroid-caption">
                    <span class="card-date"><?php echo $date->format('Y . m . d'); ?></span>
                    <span class="card-mood-tag"><?php echo htmlspecialchars(moodLabel($diary['mood'] ?? '')); ?></span>
                </div>
            </div>

            <div class="flip-card-back" id="card-back">
                <div class="card-back-content">
                    <h2 class="card-back-title"><?php echo $title; ?></h2>
                    <time class="card-back-date"><?php echo $date->format('Y年m月d日'); ?></time>

                    <?php if ($hasQuote): ?>
                        <div class="card-back-quote" id="quote-section">
                            <blockquote id="quote-text"><?php echo htmlspecialchars($aiText); ?></blockquote>
                        </div>
                    <?php elseif ($aiGenerating): ?>
                        <div class="card-back-quote" id="quote-section">
                            <div class="quote-loading" id="quote-loading">
                                <span>AI 正在想一句話給你…</span>
                            </div>
                            <blockquote id="quote-text" style="display:none;"></blockquote>
                        </div>
                    <?php endif; ?>

                    <div class="card-back-diary">
                        <?php echo $content; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <p class="sr-only" id="develop-announce" role="status"></p>

    <!-- 翻面按鈕：手機沒有滑過提示，一定要看得到（原本的提示會蓋到日期） -->
    <button type="button" class="flip-toggle" id="flip-toggle" aria-pressed="false">
        翻到背面，看 AI 寫給你的話
    </button>

    <div class="diary-actions">
        <a href="index.php?action=home" class="btn btn-tertiary">返回日曆</a>
        <?php if ($isDemo): ?>
            <a href="index.php?action=diary_create" class="btn btn-primary">我也試寫一篇</a>
        <?php else: ?>
            <a href="index.php?action=diary_edit&id=<?php echo $diaryId; ?>" class="btn btn-tertiary">編輯</a>
            <a href="index.php?action=diary_create" class="btn btn-primary">再寫一篇</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$isDemo && !$aiGenerating && !$hasImage): ?>
<div class="diary-actions" style="margin-top:0.5rem">
    <button id="regenerate-ai-btn" class="btn btn-secondary" style="font-size:0.85rem" data-diary-id="<?php echo (int) $diaryId; ?>">重新生成 AI</button>
</div>
<?php endif; ?>

<?php if ($aiGenerating): ?>
<div id="ai-generating-data" style="display:none;"
     data-ai-generating="true"
     data-diary-id="<?php echo (int) $diaryId; ?>"
     data-app-url="<?php echo htmlspecialchars(APP_URL, ENT_QUOTES); ?>"
     data-card-date="<?php echo htmlspecialchars($date->format('Y.m.d'), ENT_QUOTES); ?>"
     data-card-mood="<?php echo $mood; ?>">
</div>
<?php endif; ?>

<script src="<?php echo APP_URL; ?>/public/assets/js/diary.js?v=<?php echo filemtime(BASE_PATH . '/public/assets/js/diary.js'); ?>" defer></script>

<?php
require_once BASE_PATH . '/app/views/layout/footer.php';
?>
