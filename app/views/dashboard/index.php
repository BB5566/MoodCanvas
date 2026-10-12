<?php
// app/views/dashboard/index.php

$pageTitle = $pageTitle ?? '心情觀測儀表板';
$metaDescription = '分析並觀測心情日記趨勢、年度熱力圖與 AI 心情洞察。';

require_once BASE_PATH . '/app/views/layout/header.php';
?>

<div class="dashboard-header" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <h1 class="display-title" style="font-size: 1.75rem; margin: 0;">📊 心情觀測儀表板</h1>
    <a href="index.php?action=home" class="btn btn-secondary">← 返回日曆</a>
</div>

<div class="bento-grid dashboard-grid">
    <div class="bento-card" id="heatmap-container">
        <h3>📅 心情熱力圖 (年度)</h3>
        <div id="heatmap" role="img" aria-label="心情熱力圖"></div>
    </div>
    <div class="bento-card" id="mood-chart-container">
        <h3>📈 心情趨勢</h3>
        <canvas id="mood-chart" role="img" aria-label="心情趨勢圖"></canvas>
    </div>
    <div class="bento-card" id="mood-wordcloud-container">
        <h3>☁️ 心情詞雲</h3>
        <div id="wordcloud" role="img" aria-label="關鍵字文字雲"></div>
    </div>
    <div class="bento-card" id="ai-insights-container">
        <h3>🤖 AI 洞察</h3>
        <p id="ai-insight-text">分析中...</p>
    </div>
</div>

<div id="dashboard-container" style="display:none;"
     data-diaries="<?php echo htmlspecialchars(json_encode($diaries ?? [])); ?>"
     data-api-url="<?php echo rtrim(APP_URL, '/'); ?>/public/index.php?action=get_ai_insight">
</div>

<!-- 引入圖表和詞雲函式庫 -->
<script src="<?php echo APP_URL; ?>/public/assets/js/vendor/chart.umd.js" defer></script>
<script src="<?php echo APP_URL; ?>/public/assets/js/vendor/d3.min.js" defer></script>
<script src="<?php echo APP_URL; ?>/public/assets/js/vendor/d3.layout.cloud.js" defer></script>
<script src="<?php echo APP_URL; ?>/public/assets/js/dashboard.js" defer></script>

<?php
require_once BASE_PATH . '/app/views/layout/footer.php';
?>
