<?php
// app/views/diary/detail.php

require_once BASE_PATH . '/app/views/layout/header.php';

// 內嵌翻轉卡片關鍵 CSS（避免 Cloudflare 快取舊版）
$flipCSS = '20260603a'; ?>

<style>
/* Critical Flip Card CSS — inline to bypass Cloudflare cache */
.flip-card{perspective:1200px;-webkit-perspective:1200px;cursor:pointer;outline:none;-webkit-tap-highlight-color:transparent;margin-bottom:2rem}
.flip-card:focus-visible{outline:3px solid var(--color-accent);outline-offset:4px;border-radius:var(--border-radius-lg)}
.flip-card-inner{position:relative;width:100%;aspect-ratio:3/4;min-height:360px;transition:transform 0.7s cubic-bezier(0.4,0,0.2,1);transform-style:preserve-3d;-webkit-transform-style:preserve-3d;will-change:transform}
.flip-card-inner.flipped{transform:rotateY(180deg);-webkit-transform:rotateY(180deg)}
.flip-card-front,.flip-card-back{position:absolute;top:0;left:0;width:100%;height:100%;backface-visibility:hidden;-webkit-backface-visibility:hidden;border-radius:var(--border-radius-lg);overflow:hidden;box-shadow:var(--shadow-card)}
.flip-card-front{background:linear-gradient(135deg,#e8ecef 0%,#d5dbe0 100%);display:flex;align-items:center;justify-content:center}
.flip-card-back{transform:rotateY(180deg);-webkit-transform:rotateY(180deg);background:var(--color-surface);display:flex;flex-direction:column;padding:2rem;overflow-y:auto}
.card-image{width:100%;height:100%;object-fit:cover;display:block}
.card-emoji-fallback{display:flex;flex-direction:column;align-items:center;justify-content:center;width:100%;height:100%;background:linear-gradient(135deg,#f0ebe3 0%,#e8e0d5 100%)}
.card-emoji{font-size:6rem;line-height:1;filter:drop-shadow(0 4px 8px rgba(0,0,0,0.1));animation:float 3s ease-in-out infinite}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.flip-hint{position:absolute;bottom:1rem;left:50%;transform:translateX(-50%);background:rgba(0,0,0,0.45);color:#fff;padding:0.4rem 1rem;border-radius:20px;font-size:0.8rem;opacity:0;transition:opacity 0.3s;pointer-events:none;backdrop-filter:blur(4px)}
.flip-card:hover .flip-hint,.flip-card:focus-visible .flip-hint{opacity:1}
.card-front-meta{position:absolute;top:1rem;left:1rem;right:1rem;display:flex;justify-content:space-between;align-items:center}
.card-date{font-size:0.85rem;color:#fff;background:rgba(0,0,0,0.4);padding:0.2rem 0.7rem;border-radius:12px;backdrop-filter:blur(4px)}
.card-mood-tag{font-size:1.5rem;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.3))}
.card-back-content{flex:1;display:flex;flex-direction:column;gap:1rem}
.card-back-title{font-size:1.4rem;font-weight:600;color:var(--color-text-primary);line-height:1.4}
.card-back-date{font-size:0.85rem;color:var(--color-text-muted)}
.card-back-diary{flex:1;font-size:0.95rem;line-height:1.8;color:var(--color-text-primary);white-space:pre-wrap;overflow-y:auto}
.card-back-quote{margin-top:auto;padding-top:1rem}
.quote-divider{width:40px;height:2px;background:var(--color-accent);margin-bottom:1rem}
.card-back-quote blockquote{border-left:3px solid var(--color-accent);padding-left:1rem;margin:0;font-style:italic;color:var(--color-text-secondary);font-size:0.95rem;line-height:1.6}
.ai-generating-badge{display:flex;align-items:center;gap:0.5rem;margin-top:1.5rem;padding:0.6rem 1.2rem;background:rgba(255,255,255,0.85);border-radius:20px;font-size:0.85rem;color:var(--color-text-secondary);box-shadow:var(--shadow-subtle);transition:opacity 0.8s}
.diary-card-wrapper{max-width:560px;margin:0 auto;padding:1rem}
.diary-actions{display:flex;justify-content:center;gap:1rem;margin-top:1rem;flex-wrap:wrap}
@media(max-width:768px){.flip-card-inner{aspect-ratio:3/4.5;min-height:300px}.flip-card-back{padding:1.2rem}.card-back-title{font-size:1.2rem}.card-back-diary{font-size:0.9rem}.card-emoji{font-size:4rem}.diary-actions{flex-direction:column;align-items:center;gap:0.6rem}.btn{width:100%;max-width:280px}}
@media(max-width:480px){.flip-card-inner{aspect-ratio:2/3;min-height:260px}.flip-card-back{padding:0.8rem}.card-back-title{font-size:1.05rem}.card-back-diary{font-size:0.82rem;line-height:1.5}.card-back-quote blockquote{font-size:0.82rem}.card-emoji{font-size:2.8rem}}
</style>

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
            
            <!-- 正面：圖片 / Emoji -->
            <div class="flip-card-front" id="card-front">
                <?php if ($hasImage): ?>
                    <img src="<?php echo $imageUrl; ?>" 
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
                
                <!-- 翻轉提示 -->
                <div class="flip-hint">
                    <span>點擊翻轉 ↻</span>
                </div>
                
                <!-- 日期與心情標籤 -->
                <div class="card-front-meta">
                    <span class="card-date"><?php echo $date->format('Y.m.d'); ?></span>
                    <span class="card-mood-tag" role="img" aria-label="心情"><?php echo $mood; ?></span>
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

<script>
// --- 翻轉卡片互動 ---
(function() {
    var card = document.getElementById('flip-card');
    var inner = document.getElementById('flip-card-inner');
    var flipped = false;
    
    card.addEventListener('click', function() {
        flipped = !flipped;
        inner.classList.toggle('flipped', flipped);
    });
    
    // 鍵盤支援
    card.setAttribute('tabindex', '0');
    card.setAttribute('role', 'button');
    card.setAttribute('aria-label', '點擊翻轉卡片');
    card.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            card.click();
        }
    });
})();

<?php if ($aiGenerating): ?>
// --- AI 非同步生成卡片內容（含 timeout + 重試）---
(function() {
    var diaryId = <?php echo $diaryId; ?>;
    var statusEl = document.querySelector('.ai-generating-badge span');
    var emojiContainer = document.getElementById('card-emoji');
    var quoteLoading = document.getElementById('quote-loading');
    var quoteText = document.getElementById('quote-text');
    var badge = document.querySelector('.ai-generating-badge');
    var startTime = Date.now();
    var timerInterval = null;
    var aborted = false;
    
    function updateStatus(msg) {
        if (statusEl) statusEl.textContent = msg;
    }
    
    function updateTimer() {
        var elapsed = Math.floor((Date.now() - startTime) / 1000);
        updateStatus('AI 創作中... ' + elapsed + 's');
    }
    
    timerInterval = setInterval(updateTimer, 1000);
    updateStatus('AI 創作中...');
    
    function showRetry() {
        clearInterval(timerInterval);
        if (aborted) return;
        updateStatus('AI 生成逾時');
        if (badge) {
            badge.style.background = 'rgba(255,235,238,0.95)';
            badge.style.color = '#c62828';
            var retryBtn = document.createElement('button');
            retryBtn.textContent = '🔄 重試';
            retryBtn.style.cssText = 'margin-left:0.5rem;padding:0.2rem 0.6rem;border:1px solid #c62828;border-radius:12px;background:#fff;color:#c62828;cursor:pointer;font-size:0.8rem';
            retryBtn.onclick = function() {
                badge.style.background = 'rgba(255,255,255,0.85)';
                badge.style.color = 'var(--color-text-secondary)';
                retryBtn.remove();
                startTime = Date.now();
                timerInterval = setInterval(updateTimer, 1000);
                doGenerate();
            };
            badge.appendChild(retryBtn);
        }
    }
    
    function doGenerate() {
        var controller = new AbortController();
        var timeout = setTimeout(function() {
            controller.abort();
            showRetry();
        }, 45000);
        
        fetch('index.php?action=generate_card_content&id=' + diaryId, { signal: controller.signal })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                clearTimeout(timeout);
                clearInterval(timerInterval);
                if (aborted) return;
                
                if (!data.success) {
                    if (data.message && data.message.indexOf('請等待') !== -1) {
                        updateStatus('⏳ ' + data.message);
                        if (badge) badge.style.background = 'rgba(255,243,205,0.95)';
                        setTimeout(function() {
                            startTime = Date.now();
                            timerInterval = setInterval(updateTimer, 1000);
                            doGenerate();
                        }, 2000);
                        return;
                    }
                    updateStatus('AI 生成完成 ✨');
                    if (quoteLoading) quoteLoading.style.display = 'none';
                    if (badge) setTimeout(function() { badge.style.opacity = '0'; }, 2000);
                    return;
                }
                
                // 更新圖片
                if (data.image_url) {
                    var imgUrl = data.image_url;
                    if (imgUrl.indexOf('http') !== 0 && imgUrl.indexOf('public/') !== 0) {
                        imgUrl = '<?php echo APP_URL; ?>/public/' + imgUrl.replace(/^\//, '');
                    }
                    
                    if (emojiContainer) {
                        var img = document.createElement('img');
                        img.src = imgUrl;
                        img.alt = 'AI 生成圖片';
                        img.className = 'card-image';
                        img.loading = 'lazy';
                        img.onerror = function() { this.style.display = 'none'; };
                        img.onload = function() { this.style.animation = 'fadeIn 0.6s ease'; };
                        
                        emojiContainer.innerHTML = '';
                        emojiContainer.appendChild(img);
                        
                        var hint = document.createElement('div');
                        hint.className = 'flip-hint';
                        hint.innerHTML = '<span>點擊翻轉 ↻</span>';
                        emojiContainer.appendChild(hint);
                        
                        var meta = document.createElement('div');
                        meta.className = 'card-front-meta';
                        meta.innerHTML = '<span class="card-date"><?php echo $date->format('Y.m.d'); ?></span><span class="card-mood-tag"><?php echo $mood; ?></span>';
                        emojiContainer.appendChild(meta);
                        
                        emojiContainer.classList.remove('card-emoji-fallback');
                    }
                    updateStatus('🎨 圖片完成');
                }
                
                // 更新短語
                if (data.quote) {
                    if (quoteText) {
                        quoteText.textContent = data.quote;
                        quoteText.style.display = 'block';
                        quoteText.style.animation = 'fadeIn 0.5s ease';
                    }
                    if (quoteLoading) quoteLoading.style.display = 'none';
                }
                
                updateStatus('✨ 完成');
                if (badge) setTimeout(function() { badge.style.opacity = '0'; }, 2000);
            })
            .catch(function(err) {
                clearTimeout(timeout);
                if (err.name === 'AbortError') return;
                showRetry();
            });
    }
    
    doGenerate();
})();
<?php endif; ?>

<?php if (!$aiGenerating && !$hasImage): ?>
<div class="diary-actions" style="margin-top:0.5rem">
    <button class="btn btn-secondary" style="font-size:0.85rem" onclick="regenerateAI(<?php echo $diaryId; ?>)">🔄 重新生成 AI</button>
</div>
<script>
function regenerateAI(id) {
    var btn = event.target;
    btn.disabled = true;
    btn.textContent = '生成中...';
    fetch('index.php?action=generate_card_content&id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) location.reload();
            else { btn.textContent = '失敗，點此重試'; btn.disabled = false; }
        })
        .catch(function() { btn.textContent = '失敗，點此重試'; btn.disabled = false; });
}
</script>
<?php endif; ?>
</script>

<?php
require_once BASE_PATH . '/app/views/layout/footer.php';
?>
