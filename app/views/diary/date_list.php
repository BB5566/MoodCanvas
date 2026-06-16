<?php
// app/views/diary/date_list.php

require_once BASE_PATH . '/app/views/layout/header.php';

$dateFormatted = date('Y年m月d日', strtotime($date));

$pageTitle = $dateFormatted . ' 的日記列表';
$metaDescription = '瀏覽 ' . $dateFormatted . ' 所有心情日記，快速檢視多篇內容與 AI 洞察。';
?>

<div class="container" style="max-width: 900px; margin: 0 auto; padding: 2rem;">
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
            <div class="diary-card">
                <div class="diary-card-header">
                    <div class="diary-meta">
                        <span class="mood-emoji"><?php echo htmlspecialchars($diary['mood'] ?? '📝'); ?></span>
                        <h3><?php echo htmlspecialchars($diary['title']); ?></h3>
                        <span class="diary-time"><?php echo date('H:i', strtotime($diary['created_at'])); ?></span>
                    </div>
                </div>
                
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
                            <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="日記配圖" />
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="diary-card-actions">
                    <a href="index.php?action=diary_detail&id=<?php echo $diary['id']; ?>" class="btn btn-primary">
                        完整查看
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.date-list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid rgba(255, 255, 255, 0.1);
}

.date-list-header h1 {
    color: var(--color-text-primary);
    font-size: 2rem;
    margin: 0;
}

.diaries-list {
    display: grid;
    gap: 1.5rem;
}

.diary-card {
    background: var(--color-surface);
    border: var(--color-border-light);
    border-radius: var(--border-radius-lg);
    backdrop-filter: 10px;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.diary-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-card);
    background: rgba(255, 255, 255, 0.15);
}

.diary-card-header {
    margin-bottom: 1rem;
}

.diary-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.diary-meta .mood-emoji {
    font-size: 2rem;
}

.diary-meta h3 {
    flex-grow: 1;
    color: var(--color-text-primary);
    margin: 0;
    font-size: 1.3rem;
}

.diary-time {
    color: var(--color-text-secondary);
    font-size: 0.9rem;
    background: rgba(255, 255, 255, 0.1);
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
}

.diary-card-content {
    margin-bottom: 1.5rem;
}

.diary-card-content p {
    color: var(--color-text-primary);
    line-height: 1.6;
    margin-bottom: 1rem;
}

.ai-insight {
    background: rgba(77, 208, 225, 0.1);
    border-left: 3px solid var(--color-accent);
    padding: 1rem;
    border-radius: var(--border-radius-sm);
    margin-top: 1rem;
}

.ai-insight strong {
    color: var(--color-accent);
    display: block;
    margin-bottom: 0.5rem;
}

.ai-insight p {
    color: var(--color-text-secondary);
    margin: 0;
    font-size: 0.9rem;
}

.diary-image-preview {
    margin-top: 1rem;
}

.diary-image-preview img {
    width: 100%;
    max-width: 200px;
    height: auto;
    border-radius: var(--border-radius-md);
    box-shadow: var(--shadow-subtle);
}

.diary-card-actions {
    display: flex;
    justify-content: flex-end;
}

.btn {
    padding: 0.6rem 1.2rem;
    border: none;
    border-radius: var(--border-radius-sm);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    cursor: pointer;
    display: inline-block;
}

.btn-primary {
    background: linear-gradient(135deg, var(--color-primary-btn), var(--color-accent));
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-subtle);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: var(--color-text-primary);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.5);
}

@media (max-width: 768px) {
    .date-list-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .diary-meta {
        flex-direction: column;
        gap: 0.5rem;
        text-align: center;
    }
    
    .diary-meta h3 {
        font-size: 1.1rem;
    }
}
</style>

<?php require_once BASE_PATH . '/app/views/layout/footer.php'; ?>
