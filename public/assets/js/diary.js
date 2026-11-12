// public/assets/js/diary.js

document.addEventListener('DOMContentLoaded', function () {

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
    // 新增頁：送出時按鈕變「存檔中」（這時只是存檔轉頁，AI 在下一頁才開始畫）
    // ================================================================
    var diaryForm = document.getElementById('diary-form');
    if (diaryForm) {
        diaryForm.addEventListener('submit', function () {
            var btn = document.getElementById('submit-diary-btn');
            if (!btn) return;
            btn.disabled = true;
            var btnText = btn.querySelector('.btn-text');
            if (btnText) btnText.textContent = '存檔中…';
        });
    }

    // 「幫我填一篇」：給不想自己寫的訪客（例如來看作品的人）一篇現成的
    var fillBtn = document.getElementById('fill-sample');
    if (fillBtn && contentTextarea) {
        var samples = [
            ['雨天的捷運', '😂', '下班遇到大雨，在捷運站等雨停。旁邊的阿伯拿著黃色雨傘一直轉，把水甩到我身上，他一直道歉，結果我們都笑了。'],
            ['第一次自己煮味噌湯', '🥰', '照著影片煮了味噌湯，豆腐切得歪七扭八，味道卻意外地好。端上桌的時候，室友說比外面的好喝。'],
            ['爬到山頂的那一刻', '😊', '週末去爬象山，最後一段樓梯腿一直抖。爬到頂的時候剛好夕陽，整個台北都變成橘色的。'],
            ['面試前一天', '😰', '明天是第一次正式面試，把作品集又看了三遍。睡前泡了一杯熱可可，告訴自己照平常講就好。'],
        ];
        var next = Math.floor(Math.random() * samples.length);
        fillBtn.addEventListener('click', function () {
            var pick = samples[next];
            next = (next + 1) % samples.length;
            document.getElementById('title').value = pick[0];
            contentTextarea.value = pick[2];
            contentTextarea.dispatchEvent(new Event('input')); // 更新字數
            var moodInput = document.querySelector('input[name="mood"][value="' + pick[1] + '"]');
            if (moodInput) moodInput.checked = true;
        });
    }

    // ================================================================
    // 翻轉卡片 (detail 頁面)：點卡片或下方按鈕都能翻
    // ================================================================
    var card = document.getElementById('flip-card');
    var inner = document.getElementById('flip-card-inner');
    var flipToggle = document.getElementById('flip-toggle');
    var flipped = false;
    var userFlipped = false; // 使用者自己翻過，就不再自動翻

    function setFlipped(value) {
        if (!inner) return;
        flipped = value;
        inner.classList.toggle('flipped', flipped);
        if (flipToggle) {
            flipToggle.setAttribute('aria-pressed', flipped ? 'true' : 'false');
            flipToggle.textContent = flipped ? '翻回照片' : '翻到背面，看 AI 寫給你的話';
        }
    }

    if (card && inner) {
        card.addEventListener('click', function (e) {
            if (e.target.closest('button')) return; // 卡片裡的「再試一次」不要順便翻面
            userFlipped = true;
            setFlipped(!flipped);
        });
        card.addEventListener('keydown', function (e) {
            if (e.target !== card) return;
            if (e.key === 'Enter' || e.key === ' ') {
                userFlipped = true;
                e.preventDefault();
                setFlipped(!flipped);
            }
        });
    }
    if (flipToggle) {
        flipToggle.addEventListener('click', function () { userFlipped = true; setFlipped(!flipped); });
    }

    // ================================================================
    // Regenerate AI button binding
    // ================================================================
    var regenBtn = document.getElementById('regenerate-ai-btn');
    if (regenBtn) {
        regenBtn.addEventListener('click', function(e) {
            var btn = e.currentTarget;
            var id = btn.getAttribute('data-diary-id');
            if (!id) return;

            btn.disabled = true;
            btn.textContent = '生成中...';
            fetch('index.php?action=generate_card_content&id=' + id)
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.success) location.reload();
                    else { btn.textContent = '失敗，點此重試'; btn.disabled = false; }
                })
                .catch(function() { btn.textContent = '失敗，點此重試'; btn.disabled = false; });
        });
    }

    // ================================================================
    // Detail Page Image Fallback
    // ================================================================
    var cardImage = document.getElementById('card-image-el');
    if (cardImage) {
        var fallbackMood = cardImage.getAttribute('data-fallback-mood') || '📝';

        function handleImageError() {
            var parent = cardImage.parentElement;
            if (parent) {
                var fallbackDiv = document.createElement('div');
                fallbackDiv.className = 'card-emoji-fallback';
                fallbackDiv.id = 'card-emoji';
                var span = document.createElement('span');
                span.className = 'card-emoji';
                span.textContent = fallbackMood;
                fallbackDiv.appendChild(span);
                parent.replaceChild(fallbackDiv, cardImage);
            }
        }

        if (cardImage.complete && cardImage.naturalWidth === 0) {
            handleImageError();
        } else {
            cardImage.addEventListener('error', handleImageError);
        }
    }

    // ================================================================
    // AI 生成 (detail 頁面)：底片顯影
    // 階段字跟著真實流程的順序走（先讀日記寫畫面描述，再生圖），秒數是真的經過時間
    // ================================================================
    var aiData = document.getElementById('ai-generating-data');
    if (aiData && aiData.getAttribute('data-ai-generating') === 'true') {
        var diaryId = aiData.getAttribute('data-diary-id');
        var appUrl = aiData.getAttribute('data-app-url');
        var film = document.getElementById('card-emoji');
        var stageEl = document.getElementById('develop-stage');
        var timeEl = document.getElementById('develop-time');
        var stamp = document.getElementById('develop-stamp');
        var quoteLoading = document.getElementById('quote-loading');
        var quoteText = document.getElementById('quote-text');
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var startTime = Date.now();
        var timer = null;

        function tick() {
            var sec = Math.floor((Date.now() - startTime) / 1000);
            if (timeEl) timeEl.textContent = sec + ' 秒';
            if (stageEl) stageEl.textContent = sec < 3 ? '正在讀你的日記' : sec < 7 ? '想像畫面' : '顯影中';
        }
        function startTimer() { startTime = Date.now(); tick(); timer = setInterval(tick, 500); }
        function stopTimer() { clearInterval(timer); }

        // 停下來說明原因；canRetry 時給一顆重試鈕
        function stopWith(message, canRetry) {
            stopTimer();
            if (film) film.classList.add('stopped');
            if (stageEl) stageEl.textContent = message;
            if (timeEl) timeEl.textContent = '';
            if (quoteLoading) quoteLoading.textContent = message;
            if (canRetry && timeEl) {
                var retry = document.createElement('button');
                retry.type = 'button';
                retry.className = 'btn btn-tertiary develop-retry';
                retry.textContent = '再試一次';
                retry.onclick = function () {
                    retry.remove();
                    if (film) film.classList.remove('stopped');
                    doGenerate();
                };
                timeEl.parentNode.appendChild(retry);
            }
        }

        function showResult(data) {
            if (data.quote && quoteText) {
                quoteText.textContent = data.quote;
                quoteText.style.display = 'block';
                if (quoteLoading) quoteLoading.style.display = 'none';
            }
            if (!film) return;
            if (!data.image_url) { stopWith('這次圖沒畫出來', true); return; }

            var imgUrl = data.image_url;
            if (imgUrl.indexOf('http') !== 0 && imgUrl.indexOf('public/') !== 0) {
                imgUrl = appUrl + '/public/' + imgUrl.replace(/^\//, '');
            }
            var img = new Image();
            img.alt = 'AI 生成插畫';
            img.className = 'card-image develop-in';
            img.id = 'card-image-el';
            img.onload = function () {
                film.replaceWith(img);
                // 下一格再加 class，CSS 才會從「剛吐出來」播到「顯影完成」
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () { img.classList.add('developed'); });
                });
                var announce = document.getElementById('develop-announce');
                if (announce) announce.textContent = data.quote ? '顯影完成，背面有 AI 寫給你的話' : '顯影完成';
                setTimeout(function () {
                    if (stamp) stamp.classList.add('show');
                    if (stamp) setTimeout(function () { stamp.classList.add('fade'); }, 2600);
                    // 小驚喜：讓人先看圖，再自動翻面看 AI 寫的話；減少動態或自己翻過就不翻
                    if (data.quote && !reduceMotion) {
                        setTimeout(function () { if (!userFlipped) setFlipped(true); }, 2800);
                    }
                }, reduceMotion ? 0 : 2000);
            };
            img.onerror = function () { stopWith('圖片載入失敗', true); };
            img.src = imgUrl;
        }

        function doGenerate() {
            startTimer();
            var controller = new AbortController();
            var timeout = setTimeout(function () { controller.abort(); }, 45000);

            fetch('index.php?action=generate_card_content&id=' + diaryId, { signal: controller.signal })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    clearTimeout(timeout);
                    if (data.success) {
                        stopTimer();
                        showResult(data);
                        return;
                    }
                    var msg = data.message || 'AI 這次沒畫出來';
                    // 60 秒內只能生成一次：等一下自動再試
                    if (msg.indexOf('請等待') !== -1) {
                        stopTimer();
                        if (stageEl) stageEl.textContent = msg;
                        var waitSec = parseInt((msg.match(/\d+/) || ['5'])[0], 10);
                        setTimeout(doGenerate, (waitSec + 1) * 1000);
                        return;
                    }
                    // 額度用完不給重試，其他錯誤可以重試
                    stopWith(msg, msg.indexOf('用完') === -1 && msg.indexOf('耗盡') === -1);
                })
                .catch(function () {
                    clearTimeout(timeout);
                    stopWith('等太久了，網路可能不穩', true);
                });
        }

        doGenerate();
    }

});
