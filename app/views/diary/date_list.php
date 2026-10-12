<?php
// app/views/diary/date_list.php

require_once BASE_PATH . '/app/views/layout/header.php';

$dateFormatted = date('Y年m月d日', strtotime($date));

$pageTitle = $dateFormatted . ' 的日記列表';
$metaDescription = '瀏覽 ' . $dateFormatted . ' 所有心情日記，快速檢視多篇內容與 AI 洞察。';
?>

<div class="container date-list-container" style="max-width: 900px; margin: 0 auto;">
    <div class="date-list-header">
        <h1>📅 <?php echo htmlspecialchars($dateFormatted); ?> 的日記</h1>
        <a href="index.php?action=home" class="btn btn-secondary">
            ← 返回日曆
        </a>
    </div>

    <?php if (empty($diaries)): ?>
        <div class="alert alert-warning">此日期沒有日記。</div>
    <?php else: ?>
    <div class="diaries-list">
        <?php foreach ($diaries as $index => $diary): ?>
            <article class="diary-card">
                <header class="diary-card-header">
                    <div class="diary-meta">
                        <span class="mood-emoji" role="img" aria-label="心情"><?php echo htmlspecialchars($diary['mood'] ?? '📝'); ?></span>
                        <h3><?php echo htmlspecialchars($diary['title']); ?></h3>
                        <time class="diary-time"><?php echo date('H:i', strtotime($diary['created_at'])); ?></time>
                    </div>
                </header>
                
                <div class="diary-card-content">
                    <p><?php echo nl2br(htmlspecialchars(mb_substr($diary['content'], 0, 150))); ?><?php echo mb_strlen($diary['content']) > 150 ? '...' : ''; ?></p>
                    
                    <?php if (!empty($diary['ai_generated_text'])): ?>
                        <div class="ai-insight">
                            <strong>🤖 AI 心情洞察：</strong>
                            <p><?php echo nl2br(htmlspecialchars(mb_substr($diary['ai_generated_text'], 0, 100))); ?><?php echo mb_strlen($diary['ai_generated_text']) > 100 ? '...' : ''; ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($diary['image_path']) && file_exists(PUBLIC_PATH . '/' . $diary['image_path'])): ?>
                        <?php
                        // 構建圖片 URL（與 detail.php / calendar.php 一致）
                        $imagePath = $diary['image_path'];
                        if (strpos($imagePath, 'http') === 0) {
                            $imgUrl = $imagePath;
                        } elseif (strpos($imagePath, 'public/') === 0) {
                            $imgUrl = APP_URL . '/' . $imagePath;
                        } else {
                            $imgUrl = APP_URL . '/public/' . ltrim($imagePath, '/');
                        }
                        ?>
                        <div class="diary-image-preview">
                            <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php echo htmlspecialchars($diary['title']); ?> 配圖" loading="lazy" />
                        </div>
                    <?php endif; ?>
                </div>
                
                <footer class="diary-card-actions">
                    <a href="index.php?action=diary_detail&id=<?php echo $diary['id']; ?>" class="btn btn-primary" aria-label="完整查看：<?php echo htmlspecialchars($diary['title']); ?>">
                        完整查看
                    </a>
                </footer>
            </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once BASE_PATH . '/app/views/layout/footer.php'; ?>
