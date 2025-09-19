<?php
// app/views/diary/create.php

require_once BASE_PATH . '/app/views/layout/header.php';

$pageTitle = '撰寫新的心情日記';
$metaDescription = '在這裡新增你的心情日記，支援 Emoji、AI 圖文生成與多種藝術風格，讓每一天都值得紀錄。';
?>

<div class="bento-card form-container">
    <h2 class="form-title">撰寫新的心情日記</h2>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form action="index.php?action=diary_store" method="POST" id="diary-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
        
        <div class="form-grid">
            <div class="form-group">
                <label for="diary_date">日期</label>
                <input type="date" id="diary_date" name="diary_date" value="<?php echo $_GET['date'] ?? date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group">
                <label for="mood">心情 Emoji</label>
                <select id="mood" name="mood" required>
                    <option value="😊" selected>😊 開心</option>
                    <option value="😢">😢 傷心</option>
                    <option value="😡">😡 憤怒</option>
                    <option value="😍">😍 戀愛</option>
                    <option value="😴">😴 疲憊</option>
                    <option value="🤔">🤔 思考</option>
                    <option value="😂">😂 大笑</option>
                    <option value="😰">😰 焦慮</option>
                    <option value="🥰">🥰 溫暖</option>
                    <option value="🙄">🙄 無奈</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="title">標題 (選填)</label>
            <input type="text" id="title" name="title" placeholder="今天過得如何？" maxlength="50" aria-label="日記標題 (選填) 最多50字">
        </div>

        <div class="form-group">
            <label for="content">日記內容 <span style="color:#f55;">*</span></label>
            <textarea id="content" name="content" rows="8" placeholder="在這裡寫下你的故事、想法或感受..." required maxlength="1000" aria-label="日記內容，必填，最多1000字"></textarea>
            <div id="content-char-count" style="text-align:right;color:#aaa;font-size:0.95em;">0 / 1000</div>
        </div>

        <fieldset class="ai-section">
            <legend>🎨 AI 幫你畫</legend>
            
            <div class="form-group">
                <label for="image-style">藝術風格</label>
                <select id="image-style" name="image_style">
                    <option value="random">🎲 隨機風格 (AI 自選)</option>
                    <option value="photographic">📷 寫實攝影</option>
                    <option value="van-gogh">🌻 梵谷風格</option>
                    <option value="ghibli">🌸 吉卜力風格</option>
                    <option value="kandinsky">🎨 康丁斯基</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="image-prompt">圖像提示詞 (AI 自動生成)</label>
                <textarea id="image-prompt" name="image_prompt" rows="3" placeholder="點擊「生成預覽圖」時，AI 將根據您的日記內容自動產生對應的圖像提示詞。" readonly></textarea>
            </div>

            <button type="button" id="generate-image-btn" class="btn btn-secondary">生成預覽圖</button>
            
            <div class="spinner-container">
                 <div class="loading-spinner" id="image-loading-spinner" style="display: none;"></div>
            </div>

            <div id="image-preview-container" class="image-preview-container" style="display:none;">
                <img id="image-preview" src="" alt="AI 生成圖片預覽">
            </div>
            <input type="hidden" name="generated_image_id" id="generated-image-id">
        </fieldset>
        
        <fieldset class="ai-section">
            <legend>✨ AI 幫你寫</legend>
            <button type="button" id="generate-text-btn" class="btn btn-secondary">生成心情短語</button>
            
            <div class="spinner-container">
                <div class="loading-spinner" id="text-loading-spinner" style="display: none;"></div>
            </div>

            <div id="text-result-container" class="text-result-container" style="display:none;">
                <p id="generated-text"></p>
            </div>
            <input type="hidden" name="generated_quote" id="generated-quote">
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 儲存日記</button>
            <a href="index.php?action=home" class="btn btn-tertiary">📅 返回日曆</a>
        </div>
    </form>
</div>



<?php
require_once BASE_PATH . '/app/views/layout/footer.php';
?>
