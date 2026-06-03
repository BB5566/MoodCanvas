// public/assets/js/main.js
// MoodCanvas v2 — Simplified JS (card flip, toast, calendar interactions)

document.addEventListener('DOMContentLoaded', function () {

    // ================================================================
    // 全域 Toast 通知
    // ================================================================
    window.showToast = function (message, type) {
        type = type || 'success';
        var toast = document.createElement('div');
        toast.className = 'toast ' + type;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(function () { toast.classList.add('show'); }, 50);
        setTimeout(function () {
            toast.classList.remove('show');
            setTimeout(function () { document.body.removeChild(toast); }, 300);
        }, 3000);
    };

    // ================================================================
    // 日曆互動：點擊日期格子的空白處 → 快速建立日記
    // ================================================================
    var noDiaryCells = document.querySelectorAll('.no-diary');
    noDiaryCells.forEach(function (cell) {
        cell.addEventListener('click', function (e) {
            if (cell.classList.contains('no-diary-guest')) return;
            var date = cell.getAttribute('data-date');
            if (date) {
                window.location.href = 'index.php?action=diary_create&date=' + date;
            }
        });
    });

    // ================================================================
    // 動畫：頁面載入時卡片淡入
    // ================================================================
    var bentoCards = document.querySelectorAll('.bento-card');
    if (bentoCards.length > 0) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.1 });

        bentoCards.forEach(function (card) {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            observer.observe(card);
        });
    }

    // ================================================================
    // 字數計數（create 頁面）
    // ================================================================
    var contentTextarea = document.getElementById('content');
    var charCount = document.getElementById('content-char-count');
    if (contentTextarea && charCount) {
        contentTextarea.addEventListener('input', function () {
            charCount.textContent = this.value.length + ' / 1000';
        });
    }

});
