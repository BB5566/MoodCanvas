// public/assets/js/diary.js

document.addEventListener('DOMContentLoaded', function () {
    // 取代 detail.php 原本的 inline onerror／onclick（CSP 不允許 inline 事件）
    document.querySelectorAll('img[data-fallback-emoji]').forEach(function (img) {
        function showFallback() {
            var box = document.createElement('div');
            box.className = 'card-emoji-fallback';
            box.textContent = img.dataset.fallbackEmoji;
            img.parentElement.replaceChildren(box);
        }
        if (img.complete && img.naturalWidth === 0) {
            showFallback(); // 腳本載入前圖片就已經載入失敗
        } else {
            img.addEventListener('error', showFallback);
        }
    });
    document.querySelectorAll('[data-regenerate-ai]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            window.regenerateAI(button.dataset.regenerateAi, event);
        });
    });


    // ================================================================
    // 字數計數（edit / create 頁面）
    // ================================================================
    var contentTextarea = document.getElementById('content');
    var charCount = document.getElementById('content-char-count');
    if (contentTextarea && charCount) {
        contentTextarea.addEventListener('input', function () {
            charCount.textContent = this.value.length + ' / 1000';
        });
    }

    // ================================================================
    // Submit loading state (create 頁面)
    // ================================================================
    var diaryForm = document.getElementById('diary-form');
    if (diaryForm) {
        diaryForm.addEventListener('submit', function() {
            var btn = document.getElementById('submit-diary-btn');
            var overlay = document.getElementById('submit-overlay');
            var status = document.getElementById('submit-status');

            if(btn) {
                btn.disabled = true;
                var btnText = btn.querySelector('.btn-text');
                if(btnText) btnText.textContent = '處理中...';
            }
            if(overlay) overlay.style.display = 'flex';

            // Animate status messages
            var messages = [
                '正在儲存日記...',
                'AI 正在生成圖片...',
                'AI 正在撰寫短語...',
                '即將完成...'
            ];
            var i = 0;
            if(status) {
                var interval = setInterval(function() {
                    i = (i + 1) % messages.length;
                    status.textContent = messages[i];
                }, 2000);

                // Clean up on page unload
                window.addEventListener('beforeunload', function() {
                    clearInterval(interval);
                });
            }
        });
    }

    // ================================================================
    // 翻轉卡片互動 (detail 頁面)
    // ================================================================
    var card = document.getElementById('flip-card');
    var inner = document.getElementById('flip-card-inner');
    if (card && inner) {
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
    }

    // ================================================================
    // AI 非同步生成卡片內容 (detail 頁面)
    // ================================================================
    var aiData = document.getElementById('ai-generating-data');
    if (aiData && aiData.hasAttribute('data-ai-generating') && aiData.getAttribute('data-ai-generating') === 'true') {
        var diaryId = aiData.getAttribute('data-diary-id');
        var appUrl = aiData.getAttribute('data-app-url');
        var cardDate = aiData.getAttribute('data-card-date');
        var cardMood = aiData.getAttribute('data-card-mood');

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
                            imgUrl = appUrl + '/public/' + imgUrl.replace(/^\//, '');
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
                            meta.innerHTML = '<span class="card-date">' + cardDate + '</span><span class="card-mood-tag">' + cardMood + '</span>';
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
    }

});

// Global function for retrying AI generation
window.regenerateAI = function(id, event) {
    var btn = event ? event.target : window.event.target;
    btn.disabled = true;
    btn.textContent = '生成中...';
    fetch('index.php?action=generate_card_content&id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) location.reload();
            else { btn.textContent = '失敗，點此重試'; btn.disabled = false; }
        })
        .catch(function() { btn.textContent = '失敗，點此重試'; btn.disabled = false; });
};
