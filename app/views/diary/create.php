<?php
// app/views/diary/create.php — v2 簡化版
require_once BASE_PATH . '/app/views/layout/header.php';
$pageTitle = '寫下心情 - MoodCanvas';
$metaDescription = '一鍵寫下你的心情日記，AI 會自動為你生成專屬圖片與心情短語。';
?>
<div class="bento-card form-container">
    <h2 class="form-title">✍️ 寫下今天的心情</h2>
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form action="index.php?action=diary_store" method="POST" id="diary-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
        <div class="form-grid">
            <div class="form-group">
                <label for="diary_date">📅 日期</label>
                <input type="date" id="diary_date" name="diary_date" value="<?php echo $_GET['date'] ?? date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <label for="mood">😊 心情</label>
                <select id="mood" name="mood" required>
                    <option value="😊" selected>😊 開心</option>
                    <option value="😢">😢 傷心</option><option value="😡">😡 憤怒</option><option value="😍">😍 戀愛</option>
                    <option value="😴">😴 疲憊</option><option value="🤔">🤔 思考</option><option value="😂">😂 大笑</option>
                    <option value="😰">😰 焦慮</option><option value="🥰">🥰 溫暖</option><option value="🙄">🙄 無奈</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="title">📝 標題 <span class="optional-tag">選填</span></label>
            <input type="text" id="title" name="title" placeholder="今天過得如何？" maxlength="50">
        </div>
        <div class="form-group">
            <label for="content">💬 日記內容 <span class="required-star">*</span></label>
            <textarea id="content" name="content" rows="6" placeholder="在這裡寫下你的故事、想法或感受..." required maxlength="1000"></textarea>
            <div class="char-count" id="content-char-count">0 / 1000</div>
        </div>
        <details class="art-style-details" open>
            <summary>🎨 圖片風格 <span class="summary-hint">— AI 會根據內容自動選擇，也可手動指定</span></summary>
            <div class="form-group" style="margin-top: 0.8rem;">
                <select id="image-style" name="image_style">
                    <option value="random">🎲 讓 AI 自己決定</option>
                    <option value="photographic">📷 真實攝影</option><option value="ghibli">🌸 宮崎駿風格</option>
                    <option value="pixel-art">👾 像素風格</option><option value="3d-render">🧸 皮克斯風格</option>
                    <option value="flat-illustration">🎨 扁平設計</option><option value="sketch">✏️ 手繪素描</option>
                    <option value="ink-wash">🖌️ 中國水墨</option>
                </select>
            </div>
        </details>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-lg" id="submit-diary-btn">
                <span class="btn-icon">✨</span><span class="btn-text">寫下心情</span>
            </button>
        </div>
        <div class="submit-overlay" id="submit-overlay" style="display:none;">
            <div class="submit-overlay-content">
                <div class="loading-spinner"></div>
                <p class="submit-status" id="submit-status">正在儲存日記...</p>
                <p class="submit-hint">AI 正在為你生成專屬圖片與心情短語 ✨</p>
            </div>
        </div>
    </form>
</div>
<script>
document.getElementById('content').addEventListener('input', function() { document.getElementById('content-char-count').textContent = this.value.length + ' / 1000'; });
document.getElementById('diary-form').addEventListener('submit', function() {
    var btn = document.getElementById('submit-diary-btn'), overlay = document.getElementById('submit-overlay'), status = document.getElementById('submit-status');
    btn.disabled = true; btn.querySelector('.btn-text').textContent = '處理中...'; overlay.style.display = 'flex';
    var msgs = ['正在儲存日記...','AI 正在生成圖片...','AI 正在撰寫短語...','即將完成...'], i = 0;
    var interval = setInterval(function() { i = (i + 1) % msgs.length; status.textContent = msgs[i]; }, 2000);
    window.addEventListener('beforeunload', function() { clearInterval(interval); });
});
</script>
<?php require_once BASE_PATH . '/app/views/layout/footer.php'; ?>
