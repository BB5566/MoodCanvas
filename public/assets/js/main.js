// public/assets/js/main.js

document.addEventListener('DOMContentLoaded', function () {
  // 只有在日記建立頁面才執行以下邏輯
  if (document.getElementById('diary-form')) {
    const imageBtn = document.getElementById('generate-image-btn');
    const textBtn = document.getElementById('generate-text-btn');

    const imageSpinner = document.getElementById('image-loading-spinner');
    const textSpinner = document.getElementById('text-loading-spinner');

    const imagePreviewContainer = document.getElementById(
      'image-preview-container'
    );
    const imagePreview = document.getElementById('image-preview');
    const generatedImageIdInput = document.getElementById('generated-image-id');

    const textResultContainer = document.getElementById(
      'text-result-container'
    );
    const generatedText = document.getElementById('generated-text');
    const generatedQuoteInput = document.getElementById('generated-quote');

    // 全域工具函數
    function showToast(message, type = 'success') {
      const toast = document.createElement('div');
      toast.className = `toast ${type}`;
      toast.textContent = message;
      document.body.appendChild(toast);

      setTimeout(() => toast.classList.add('show'), 100);
      setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => document.body.removeChild(toast), 300);
      }, 3000);
    }

    function setButtonLoading(button, loading = true) {
      if (loading) {
        button.classList.add('loading');
        button.disabled = true;
      } else {
        button.classList.remove('loading');
        button.disabled = false;
      }
    }

    // --- 圖片生成 --- //
    let imageGenerating = false; // 添加生成狀態標記
    let lastImageRequest = null; // 記錄上次請求的內容

    imageBtn.addEventListener('click', async (event) => {
      // 防止事件冒泡和預設行為
      event.preventDefault();
      event.stopPropagation();

      if (imageGenerating) {
        showToast('圖片正在生成中，請稍候...', 'warning');
        return;
      }

      // 獲取日記內容和相關資訊
      const content = document.getElementById('content').value.trim();
      const style = document.getElementById('image-style').value;
      const mood = document.getElementById('mood').value;

      if (!content) {
        showToast('請先填寫日記內容！', 'error');
        return;
      }

      // 檢查是否與上次請求重複
      const currentRequest = `${content}|${style}|${mood}`;
      if (lastImageRequest === currentRequest) {
        showToast('請稍等，不要重複點擊！', 'warning');
        return;
      }

      // 設置生成狀態
      imageGenerating = true;
      lastImageRequest = currentRequest;

      // 顯示載入動畫，隱藏預覽
      imageSpinner.style.display = 'block';
      imagePreviewContainer.style.display = 'none';
      setButtonLoading(imageBtn, true);

      // 更新提示詞區域顯示正在生成
      document.getElementById('image-prompt').value =
        'AI 正在為您生成提示詞...';

      try {
        const response = await fetch('index.php?action=generate_image', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            content: content,
            style: style,
            mood: mood,
          }),
        });

        if (!response.ok) {
          const errorData = await response.json();
          throw new Error(
            errorData.message || `HTTP error! status: ${response.status}`
          );
        }

        const data = await response.json();

        if (data.success) {
          imagePreview.src = data.imageUrl;
          // 從 URL 中提取檔案名稱並設定到隱藏欄位
          generatedImageIdInput.value = data.imageUrl.split('/').pop();

          // 更新提示詞文字區域
          document.getElementById('image-prompt').value =
            data.prompt || '已生成圖片提示詞';

          imagePreviewContainer.style.display = 'block';
          showToast('🎨 圖片生成成功！');
        } else {
          throw new Error(data.message || '圖片生成失敗，但未回傳錯誤訊息。');
        }
      } catch (error) {
        console.error('Error generating image:', error);
        showToast(`圖片生成失敗：${error.message}`, 'error');
        document.getElementById('image-prompt').value = '生成失敗，請重試。';
      } finally {
        // 隱藏載入動畫，恢復按鈕
        imageSpinner.style.display = 'none';
        setButtonLoading(imageBtn, false);
        imageGenerating = false; // 重置生成狀態

        // 延遲重置請求記錄，避免過快的重複請求
        setTimeout(() => {
          lastImageRequest = null;
        }, 2000);
      }
    });

    // --- 文字生成 --- //
    textBtn.addEventListener('click', async () => {
      const content = document.getElementById('content').value.trim();
      const mood = document.getElementById('mood').value;

      if (!content) {
        showToast('請先填寫日記內容！', 'error');
        return;
      }

      textSpinner.style.display = 'block';
      textResultContainer.style.display = 'none';
      setButtonLoading(textBtn, true);

      try {
        const response = await fetch('index.php?action=generate_text', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ content, mood }),
        });

        if (!response.ok) {
          const errorData = await response.json();
          throw new Error(
            errorData.message || `HTTP error! status: ${response.status}`
          );
        }

        const data = await response.json();

        if (data.success) {
          generatedText.textContent = data.quote;
          generatedQuoteInput.value = data.quote;
          textResultContainer.style.display = 'block';
          showToast('✨ 文字生成成功！');
        } else {
          throw new Error(data.message || '文字生成失敗，但未回傳錯誤訊息。');
        }
      } catch (error) {
        console.error('Error generating text:', error);
        showToast(`文字生成失敗：${error.message}`, 'error');
      } finally {
        textSpinner.style.display = 'none';
        setButtonLoading(textBtn, false);
      }
    });

    // --- 字數提示 --- //
    const contentInput = document.getElementById('content');
    const charCount = document.getElementById('content-char-count');
    if (contentInput && charCount) {
      contentInput.addEventListener('input', function () {
        charCount.textContent = `${this.value.length} / 1000`;
      });
      // 初始化
      charCount.textContent = `${contentInput.value.length} / 1000`;
    }
  }

  // --- Logic for Calendar Page (calendar.php) ---
  // 將函數掛載到 window 物件，以便 calendar.php 中的 inline onclick 可以呼叫
  window.addQuickDiary = function (day, month, year) {
    const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(
      day
    ).padStart(2, '0')}`;
    const mood = prompt(
      `為 ${dateStr} 快速記錄一個心情符號 (e.g., 😊, 😢, 🔥):`
    );

    if (mood && mood.trim() !== '') {
      // 注意：這裡需要一個後端端點來處理這個請求。
      // 我們假設有一個 action=diary_quick_create
      if (!window.__IS_LOGGED_IN__) {
        alert('需登入才能快速建立日記，請先登入或註冊。');
        return;
      }

      fetch('index.php?action=diary_quick_create', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          diary_date: dateStr,
          mood: mood.trim(),
          title: '快速記錄',
          content: '', // 快速記錄沒有內容
        }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            window.location.reload(); // 成功後重新載入頁面以顯示新日記
          } else {
            alert('快速記錄失敗：' + (data.message || '未知錯誤'));
          }
        })
        .catch((error) => {
          console.error('Error during quick diary creation:', error);
          alert('快速記錄時發生客戶端錯誤。');
        });
    }
  };

  window.randomDiary = function (diaryIds) {
    if (diaryIds && diaryIds.length > 0) {
      const randomId = diaryIds[Math.floor(Math.random() * diaryIds.length)];
      window.location.href = `index.php?action=diary_detail&id=${randomId}`;
    } else {
      alert('這個月還沒有日記可以隨機回憶喔！');
    }
  };
});
