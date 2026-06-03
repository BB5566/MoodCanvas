<?php
// app/views/dashboard/index.php

require_once BASE_PATH . '/app/views/layout/header.php';

?>

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

<!-- 引入圖表和詞雲函式庫 -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
<script src="https://cdn.jsdelivr.net/npm/d3@7"></script>
<script src="https://cdn.jsdelivr.net/npm/d3-cloud@1.2.7/build/d3.layout.cloud.js"></script>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // 接收從 PHP 傳來的數據
    const diaries = <?php echo json_encode($diaries ?? []); ?>;

    if (diaries.length > 0) {
        // 處理日記資料，確保日期格式正確並排序
        const processedData = diaries.map(d => ({
            date: new Date(d.date),
            mood_score: parseInt(d.mood_score, 10),
            content: d.content,
            tags: d.tags ? d.tags.split(',') : []
        })).sort((a, b) => a.date - b.date);

        generateHeatmap(processedData);
        generateMoodChart(processedData);
        generateWordCloud(processedData);
        fetchAIInsight(diaries);
    } else {
        document.getElementById('heatmap').innerHTML = '<p class="placeholder-text">沒有足夠的日記資料來產生熱力圖</p>';
        const chartContainer = document.getElementById('mood-chart-container');
        if(chartContainer) chartContainer.innerHTML = '<h3>📈 心情趨勢</h3><p class="placeholder-text">沒有足夠的日記資料來產生趨勢圖</p>';
        document.getElementById('wordcloud').innerHTML = '<p class="placeholder-text">沒有足夠的日記資料來產生詞雲</p>';
        document.getElementById('ai-insight-text').innerHTML = '沒有足夠的日記資料來產生 AI 洞察。';
    }
    // 儀表板初始化完成
});

// 心情顏色對應
const moodColor = d3.scaleLinear()
    .domain([1, 2, 3, 4, 5])
    .range(["#e74c3c", "#f39c12", "#f1c40f", "#2ecc71", "#3498db"]); // 1:差 -> 5:優

/**
 * 生成心情熱力圖 (D3.js)
 */
function generateHeatmap(data) {
    const container = d3.select("#heatmap");
    if (container.empty() || !container.node().getBoundingClientRect) return;
    
    const width = container.node().getBoundingClientRect().width;
    if (width <= 0) return; // 如果容器不可見，則不渲染

    const cellSize = (width - 30) / 53; // 減去 padding
    const yearHeight = cellSize * 7 + 25;

    const dataByYear = d3.group(data, d => d.date.getFullYear());
    const years = Array.from(dataByYear.keys()).sort((a, b) => b - a);

    container.html(""); // 清空 placeholder

    for (const year of years) {
        const yearData = dataByYear.get(year);
        
        const svg = container.append("svg")
            .attr("width", width)
            .attr("height", yearHeight)
            .append("g")
            .attr("transform", `translate(30, 20)`); // 留出左側年份文字空間

        svg.append("text")
            .attr("transform", `translate(-15, ${cellSize * 3.5})rotate(-90)`)
            .attr("font-family", "sans-serif")
            .attr("font-size", 12)
            .attr("text-anchor", "middle")
            .text(year);

        const rect = svg.selectAll(".day")
            .data(d3.timeDays(new Date(year, 0, 1), new Date(year + 1, 0, 1)))
            .enter().append("rect")
            .attr("class", "day")
            .attr("width", cellSize - 1.5)
            .attr("height", cellSize - 1.5)
            .attr("x", d => d3.timeWeek.count(d3.timeYear(d), d) * cellSize)
            .attr("y", d => d.getDay() * cellSize)
            .attr("rx", 2).attr("ry", 2) // 圓角
            .datum(d3.timeFormat("%Y-%m-%d"));

        const dataByDate = d3.rollup(yearData, v => v[0].mood_score, d => d3.timeFormat("%Y-%m-%d")(d.date));

        rect.filter(d => dataByDate.has(d))
            .style("fill", d => moodColor(dataByDate.get(d)))
            .append("title")
            .text(d => `${d}: 心情 ${dataByDate.get(d)}`);
            
        rect.filter(d => !dataByDate.has(d))
            .style("fill", "#efefef")
            .append("title")
            .text(d => `${d}: 無紀錄`);
    }
}


/**
 * 生成心情趨勢圖 (Chart.js)
 */
function generateMoodChart(data) {
    const ctx = document.getElementById('mood-chart');
    if (!ctx) return;

    const labels = data.map(d => d.date.toLocaleDateString('zh-TW', { month: 'numeric', day: 'numeric' }));
    const scores = data.map(d => d.mood_score);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: '心情分數',
                data: scores,
                fill: true,
                borderColor: 'rgba(52, 152, 219, 0.8)',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                tension: 0.3,
                pointBackgroundColor: scores.map(score => moodColor(score)),
                pointRadius: 4,
                pointHoverRadius: 7,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: false,
                    min: 1,
                    max: 5,
                    ticks: { stepSize: 1 }
                },
                x: {
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45,
                        autoSkip: true,
                        maxTicksLimit: 10 // 限制 X 軸標籤數量
                    }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `心情分數: ${context.parsed.y}`;
                        }
                    }
                }
            }
        }
    });
}

/**
 * 生成心情詞雲 (d3-cloud)
 */
function generateWordCloud(data) {
    const container = d3.select("#wordcloud");
    if (container.empty() || !container.node().getBoundingClientRect) return;

    const text = data.map(d => d.content + " " + (d.tags ? d.tags.join(" ") : "")).join(" ");
    
    // 簡易中文分詞，並計算詞頻
    const words = text.split(/[\s,.;!?()，。；！？（）「」【】]+/g)
        .filter(w => w.length > 1 && !/^[0-9a-zA-Z]+$/.test(w)) // 過濾掉短詞、純英文和數字
        .reduce((acc, word) => {
            const w = word.toLowerCase();
            acc[w] = (acc[w] || 0) + 1;
            return acc;
        }, {});

    const sortedWords = Object.entries(words)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 80) // 最多 80 個詞
        .map(([text, size]) => ({ text, size: 10 + Math.sqrt(size) * 8 }));

    if (sortedWords.length < 5) { // 如果詞太少，也不顯示
        container.html('<p class="placeholder-text">沒有足夠的中文文字內容可生成詞雲</p>');
        return;
    }

    const width = container.node().getBoundingClientRect().width;
    const height = 300;

    const layout = d3.layout.cloud()
        .size([width, height])
        .words(sortedWords)
        .padding(5)
        .rotate(() => (~~(Math.random() * 2) * 90)) // 0 或 90 度
        .font("sans-serif")
        .fontSize(d => d.size)
        .on("end", draw);

    layout.start();

    function draw(words) {
        container.html(""); // 清空 placeholder
        d3.select("#wordcloud").append("svg")
            .attr("width", layout.size()[0])
            .attr("height", layout.size()[1])
            .append("g")
            .attr("transform", "translate(" + layout.size()[0] / 2 + "," + layout.size()[1] / 2 + ")")
            .selectAll("text")
            .data(words)
            .enter().append("text")
            .style("font-size", d => d.size + "px")
            .style("font-family", "'Noto Sans TC', sans-serif")
            .style("fill", (d, i) => d3.schemeCategory10[i % 10])
            .attr("text-anchor", "middle")
            .attr("transform", d => `translate(${d.x}, ${d.y})rotate(${d.rotate})`)
            .text(d => d.text);
    }
}

/**
 * 獲取 AI 洞察
 */
async function fetchAIInsight(diaries) {
    const insightContainer = document.getElementById('ai-insight-text');
    insightContainer.innerHTML = '🤖 AI 正在深度分析您的心情... 請稍候片刻... <div class="spinner"></div>';

    try {
        // 使用 APP_URL 來建立完整的 API 路徑
        const apiUrl = `<?php echo rtrim(APP_URL, '/'); ?>/public/index.php?action=get_ai_insight`;
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ diaries: diaries.map(d => ({ date: d.date.toISOString().split('T')[0], mood_score: d.mood_score, content: d.content })) }) // 只傳送必要的資料
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ message: '無法解析伺服器回應' }));
            throw new Error(errorData.message || `伺服器錯誤: ${response.status}`);
        }

        const result = await response.json();

        if (result.success) {
            // Typing effect
            let i = 0;
            insightContainer.innerHTML = "";
            function typeWriter() {
                if (i < result.insight.length) {
                    insightContainer.innerHTML += result.insight.charAt(i);
                    i++;
                    setTimeout(typeWriter, 20); // 調整打字速度
                }
            }
            typeWriter();
        } else {
            throw new Error(result.message || 'AI 分析失敗');
        }

    } catch (error) {
        console.error('Fetch AI Insight Error:', error);
        insightContainer.innerHTML = `😔 無法載入 AI 洞察。 <br>(${error.message})`;
        insightContainer.classList.add('error-message');
    }
}
</script>

<?php

require_once BASE_PATH . '/app/views/layout/footer.php';

?>
