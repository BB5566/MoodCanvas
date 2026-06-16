/**
 * MoodCanvas 動畫層（GSAP）
 * 原則：優雅降級 —— 若 GSAP 未載入或使用者偏好減少動態，內容維持最終可見狀態。
 */
(function () {
  if (typeof window.gsap === 'undefined') return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  var gsap = window.gsap;

  function init() {
    // 1) 進場：主要卡片/容器 淡入上浮（stagger）
    var blocks = gsap.utils.toArray(
      '.bento-card, .form-container, .diary-card-wrapper, .date-list-container > *'
    );
    if (blocks.length) {
      gsap.from(blocks, {
        opacity: 0,
        y: 26,
        duration: 0.75,
        ease: 'power2.out',
        stagger: 0.07,
        clearProps: 'opacity,transform'
      });
    }

    // 2) 統計數字 count-up（0 → 目標值，保留 % 等後綴）
    gsap.utils.toArray('.stat-number').forEach(function (el) {
      var raw = (el.textContent || '').trim();
      var target = parseFloat(raw);
      if (isNaN(target)) return;
      var suffix = raw.replace(/[\d.,\s-]/g, '');
      var obj = { v: 0 };
      gsap.to(obj, {
        v: target,
        duration: 1.3,
        ease: 'power2.out',
        delay: 0.25,
        onUpdate: function () {
          el.textContent = Math.round(obj.v) + suffix;
        }
      });
    });

    // 3) 心情長條：由 0 拉長到設定寬度
    gsap.utils.toArray('.mood-fill').forEach(function (el, i) {
      gsap.from(el, {
        width: 0,
        duration: 1,
        ease: 'power3.out',
        delay: 0.35 + i * 0.08
      });
    });

    // 4) 拍立得「顯影」：照片從暗、去飽和慢慢沖洗出色彩
    var photo = document.querySelector('.flip-card-front .card-image');
    if (photo) {
      var develop = function () {
        gsap.fromTo(
          photo,
          {
            filter: 'sepia(0.5) saturate(0.45) brightness(0.78) contrast(1.05)',
            opacity: 0.4
          },
          {
            filter: 'sepia(0) saturate(1) brightness(1) contrast(1)',
            opacity: 1,
            duration: 1.9,
            ease: 'power2.out',
            delay: 0.3,
            clearProps: 'filter,opacity'
          }
        );
      };
      if (photo.complete && photo.naturalWidth) develop();
      else photo.addEventListener('load', develop);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
