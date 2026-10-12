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

    // ================================================================
    // Navbar 手機版漢堡選單切換
    // ================================================================
    var toggler = document.getElementById('navbar-toggler');
    var nav = document.getElementById('navbar-nav');
    if (toggler && nav) {
        toggler.addEventListener('click', function () {
            var isOpen = nav.classList.toggle('open');
            toggler.classList.toggle('active', isOpen);
            toggler.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            toggler.setAttribute('aria-label', isOpen ? '關閉選單' : '開啟選單');
        });
        // 點選單連結後自動收起（手機 UX）
        nav.addEventListener('click', function (e) {
            if (e.target.closest('a') && window.innerWidth < 768) {
                nav.classList.remove('open');
                toggler.classList.remove('active');
                toggler.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // ================================================================
    // 示範模式 Banner
    // ================================================================
    try {
        var key = 'moodcanvas_demo_banner_hidden';
        var banner = document.getElementById('demo-banner');
        var dismissBtn = document.getElementById('demo-banner-dismiss');
        if (banner && dismissBtn) {
            if (localStorage && localStorage.getItem(key) === '1') {
                banner.style.display = 'none';
            } else {
                dismissBtn.addEventListener('click', function() {
                    if (localStorage) localStorage.setItem(key, '1');
                    banner.style.display = 'none';
                });
            }
        }
    } catch (e) {}

    // ================================================================
    // __IS_LOGGED_IN__ 全域變數初始化（從 data-logged-in 讀取）
    // ================================================================
    var mainContainer = document.getElementById('main');
    if (mainContainer && mainContainer.hasAttribute('data-logged-in')) {
        window.__IS_LOGGED_IN__ = mainContainer.getAttribute('data-logged-in') === 'true';
    }

});
