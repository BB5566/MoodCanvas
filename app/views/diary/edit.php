<?php
// app/views/diary/edit.php

require_once BASE_PATH . '/app/views/layout/header.php';

$pageTitle = '編輯日記';
$metaDescription = '修改你的心情日記';
?>

<div class="bento-card form-container">
    <h2 class="form-title">✏️ 編輯日記</h2>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form action="index.php?action=diary_edit&id=<?php echo $diary['id']; ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
        
        <div class="form-grid">
            <div class="form-group">
                <label>📅 日期</label>
                <input type="date" value="<?php echo htmlspecialchars($diary['diary_date'] ?? ''); ?>" disabled>
            </div>
            <div class="form-group">
                <label for="mood">😊 心情</label>
                <select id="mood" name="mood">
                    <?php
                    $moods = ['😊 開心','😢 傷心','😡 憤怒','😍 戀愛','😴 疲憊','🤔 思考','😂 大笑','😰 焦慮','🥰 溫暖','🙄 無奈'];
                    foreach ($moods as $m) {
                        $emoji = mb_substr($m, 0, 1);
                        $selected = ($diary['mood'] ?? '😊') === $emoji ? ' selected' : '';
                        echo "<option value=\"$emoji\"$selected>$m</option>";
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="title">📝 標題</label>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($diary['title'] ?? ''); ?>" maxlength="50">
        </div>

        <div class="form-group">
            <label for="content">💬 日記內容 <span class="required-star">*</span></label>
            <textarea id="content" name="content" rows="8" required maxlength="1000"><?php echo htmlspecialchars($diary['content'] ?? ''); ?></textarea>
            <div class="char-count" id="content-char-count"><?php echo mb_strlen($diary['content'] ?? ''); ?> / 1000</div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-lg">💾 儲存修改</button>
            <a href="index.php?action=diary_detail&id=<?php echo $diary['id']; ?>" class="btn btn-tertiary">取消</a>
        </div>
    </form>
</div>

<script>
document.getElementById('content').addEventListener('input', function() {
    document.getElementById('content-char-count').textContent = this.value.length + ' / 1000';
});
</script>

<?php
require_once BASE_PATH . '/app/views/layout/footer.php';
?>
