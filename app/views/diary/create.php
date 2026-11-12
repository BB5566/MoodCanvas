<?php
// app/views/diary/create.php

$pageTitle = '寫下心情';
$metaDescription = '寫下今天的心情，AI 會讀你的日記，畫成一張拍立得、再寫一句話給你。';
require_once BASE_PATH . '/app/views/layout/header.php';
?>

<div class="bento-card form-container">
    <h2 class="form-title">寫下今天的心情</h2>
    <p class="form-lead">寫完按下去，AI 會讀你的日記，畫成一張拍立得、再寫一句話給你。</p>
    <?php if (isset($aiRemaining)): ?>
        <div class="trial-bar">
            <span>不用註冊，今天還能試 <strong><?php echo (int) $aiRemaining; ?></strong> 次</span>
            <button type="button" class="btn btn-tertiary trial-fill" id="fill-sample">不知道寫什麼？幫我填一篇</button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form action="index.php?action=diary_store" method="POST" id="diary-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
        
        <!-- 日期 -->
        <div class="form-group">
            <label for="diary_date">日期</label>
            <input type="date" id="diary_date" name="diary_date"
                   value="<?php echo htmlspecialchars($_GET['date'] ?? date('Y-m-d'), ENT_QUOTES); ?>"
                   max="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <!-- 心情：一點就選的大按鈕（手機不用開下拉選單） -->
        <fieldset class="form-group chip-group">
            <legend>今天的心情</legend>
            <div class="mood-chips">
                <?php
                $moods = ['😊' => '開心', '😢' => '傷心', '😡' => '生氣', '😍' => '心動', '😴' => '疲憊',
                          '🤔' => '思考', '😂' => '大笑', '😰' => '焦慮', '🥰' => '溫暖', '🙄' => '無奈'];
                $first = true;
                foreach ($moods as $emoji => $name): ?>
                    <label class="mood-chip mood-<?php echo moodSlug($emoji); ?>">
                        <input type="radio" name="mood" value="<?php echo $emoji; ?>"<?php echo $first ? ' checked required' : ''; ?>>
                        <span class="chip-body"><?php echo $name; ?></span>
                    </label>
                <?php $first = false; endforeach; ?>
            </div>
        </fieldset>

        <!-- Title -->
        <div class="form-group">
            <label for="title">標題 <span class="optional-tag">選填</span></label>
            <input type="text" id="title" name="title" 
                   placeholder="今天過得如何？" maxlength="50">
        </div>

        <!-- Content -->
        <div class="form-group">
            <label for="content">日記內容 <span class="required-star">*</span></label>
            <textarea id="content" name="content" rows="6" 
                      placeholder="在這裡寫下你的故事、想法或感受..."
                      required maxlength="1000"></textarea>
            <div class="char-count" id="content-char-count">0 / 1000</div>
        </div>

        <!-- 畫風：同樣一點就選 -->
        <fieldset class="form-group chip-group">
            <legend>畫風</legend>
            <div class="style-chips">
                <?php
                // 名稱只寫畫法，不寫工作室或作品名
                $styles = ['handdrawn' => '日系手繪動畫', 'anime' => '日系動漫', '3d-render' => '3D 動畫電影',
                           'watercolor' => '水彩繪本', 'film' => '懷舊底片', 'clay' => '黏土動畫',
                           'pixel-art' => '像素遊戲', 'ink-wash' => '水墨', 'random' => '讓 AI 自己決定'];
                foreach ($styles as $value => $name): ?>
                    <label class="style-chip">
                        <input type="radio" name="image_style" value="<?php echo $value; ?>"<?php echo $value === 'handdrawn' ? ' checked' : ''; ?>>
                        <span class="chip-body"><?php echo $name; ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <!-- Single Submit Button -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-lg" id="submit-diary-btn">
                <span class="btn-text">寫好了，交給 AI 畫</span>
            </button>
        </div>

    </form>
</div>

<script src="<?php echo APP_URL; ?>/public/assets/js/diary.js?v=<?php echo filemtime(BASE_PATH . '/public/assets/js/diary.js'); ?>" defer></script>

<?php
require_once BASE_PATH . '/app/views/layout/footer.php';
?>
