<?php
// app/views/diary/calendar.php

// 包含新的頁首
require_once BASE_PATH . '/app/views/layout/header.php';

// 設定動態 $pageTitle 與 $metaDescription
$pageTitle = '心情日曆';
$metaDescription = '瀏覽本月所有心情日記，點擊日期可查看多篇日記與詳細內容。';

// 處理顯示訊息
$message = $_GET['message'] ?? null;
$showSuccessMessage = false;
$successMessage = '';

if ($message === 'diary_deleted') {
    $showSuccessMessage = true;
    $successMessage = '✅ 日記已成功刪除！';
}

// --- PHP 日曆生成邏輯 (與之前相同) ---
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$date = new DateTime("$year-$month-01");
$monthName = $date->format('F Y');
$firstDayOfWeek = (int)$date->format('w');
$daysInMonth = (int)$date->format('t');

$diariesOnDate = [];
if (isset($diaries)) {
    foreach ($diaries as $diary) {
        $diaryDay = (new DateTime($diary['diary_date']))->format('j');
        if (!isset($diariesOnDate[$diaryDay])) {
            $diariesOnDate[$diaryDay] = [];
        }
        $diariesOnDate[$diaryDay][] = $diary;
    }
}

$prevMonth = (new DateTime("$year-$month-01"))->modify('-1 month');
$prevMonthLink = 'index.php?action=home&year=' . $prevMonth->format('Y') . '&month=' . $prevMonth->format('m');

$nextMonth = (new DateTime("$year-$month-01"))->modify('+1 month');
$nextMonthLink = 'index.php?action=home&year=' . $nextMonth->format('Y') . '&month=' . $nextMonth->format('m');

$todayYear = date('Y');
$todayMonth = date('m');
$todayDay = date('j');

// 統計數據
$totalDiaries = count($diaries ?? []);
$daysWithDiaries = count($diariesOnDate);
$moodStats = [];
if (isset($diaries)) {
    foreach ($diaries as $diary) {
        $mood = $diary['mood'] ?? '📝';
        $moodStats[$mood] = ($moodStats[$mood] ?? 0) + 1;
    }
    arsort($moodStats);
}
?>

<?php if ($showSuccessMessage): ?>
<div class="alert alert-success" style="text-align: center; margin: 1rem auto; max-width: 600px; padding: 1rem; background: rgba(76, 175, 80, 0.2); border: 1px solid rgba(76, 175, 80, 0.5); border-radius: 8px; color: #4CAF50;">
    <?php echo htmlspecialchars($successMessage); ?>
</div>
<?php endif; ?>

<div class="bento-grid">
    <!-- 日曆主區域 -->
    <div class="bento-card bento-calendar">
        <div class="calendar-container">
            <div class="calendar-header">
                <h2><?php echo $monthName; ?></h2>
                <div class="calendar-nav">
                    <a href="<?php echo $prevMonthLink; ?>" class="nav-arrow" title="上個月">‹</a>
                    <a href="index.php?action=home" class="today-link">今天</a>
                    <a href="<?php echo $nextMonthLink; ?>" class="nav-arrow" title="下個月">›</a>
                </div>
            </div>

            <table class="calendar">
                <thead>
                    <tr>
                        <th>日</th><th>一</th><th>二</th><th>三</th><th>四</th><th>五</th><th>六</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php
                        // 填充空白
                        for ($i = 0; $i < $firstDayOfWeek; $i++) echo "<td></td>";

                        $currentDay = 1;
                        while ($currentDay <= $daysInMonth) {
                            if (($currentDay + $firstDayOfWeek - 1) % 7 == 0 && $currentDay > 1) echo "</tr><tr>";
                            
                            $isToday = ($year == $todayYear && $month == $todayMonth && $currentDay == $todayDay);
                            $tdClass = $isToday ? 'today' : '';
                            
                            echo "<td class='{$tdClass}'>";
                            echo "<div class='day-number'>{$currentDay}</div>";
                            
                            if (isset($diariesOnDate[$currentDay])) {
                                $dayDiaries = $diariesOnDate[$currentDay];
                                $diaryCount = count($dayDiaries);
                                $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $currentDay);
                                
                                if ($diaryCount === 1) {
                                    // 只有一篇日記，直接顯示
                                    $diary = $dayDiaries[0];
                                    $moodEmoji = htmlspecialchars($diary['mood'] ?? '📝');
                                    echo "<a href='index.php?action=diary_detail&id={$diary['id']}' class='diary-entry' title='" . htmlspecialchars($diary['title']) . "'>";
                                    echo "<span class='mood-emoji'>{$moodEmoji}</span>";
                                    echo "</a>";
                                } else {
                                    // 多篇日記，點擊查看日期列表
                                    echo "<a href='index.php?action=diary_by_date&date={$currentDate}' class='multiple-diaries' title='查看該日的 {$diaryCount} 篇日記'>";
                                    foreach ($dayDiaries as $index => $diary) {
                                        if ($index >= 3) break; // 最多顯示3個
                                        $moodEmoji = htmlspecialchars($diary['mood'] ?? '📝');
                                        echo "<span class='diary-entry-small'>";
                                        echo "<span class='mood-emoji-small'>{$moodEmoji}</span>";
                                        echo "</span>";
                                    }
                                    if ($diaryCount > 3) {
                                        echo "<span class='more-diaries'>+{$diaryCount}</span>";
                                    }
                                    echo "</a>";
                                }
                            } else {
                                $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $currentDay);
                                // 只有已登入使用者才允許新增日記；訪客會看到提示
                                if (isset($_SESSION['user_id'])) {
                                    echo "<a href='index.php?action=diary_create&date={$currentDate}' class='no-diary' title='新增日記'>";
                                    echo "<span class='add-diary-btn'>+</span>";
                                    echo "</a>";
                                } else {
                                    echo "<div class='no-diary-guest' title='需登入才能新增日記' style='opacity:0.6;'>";
                                    echo "<span class='add-diary-btn'>+</span>";
                                    echo "</div>";
                                }
                            }
                            
                            echo "</td>";
                            $currentDay++;
                        }

                        // 填充結尾空白
                        $remainingDays = 7 - (($daysInMonth + $firstDayOfWeek) % 7);
                        if ($remainingDays < 7) for ($i = 0; $i < $remainingDays; $i++) echo "<td></td>";
                        ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 側邊欄 -->
    <div class="bento-sidebar">
        <!-- 月份統計 -->
        <div class="bento-card">
            <h3>📊 本月統計</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $totalDiaries; ?></div>
                    <div class="stat-label">總日記</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $daysWithDiaries; ?></div>
                    <div class="stat-label">記錄天數</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $daysInMonth > 0 ? round($daysWithDiaries / $daysInMonth * 100) : 0; ?>%</div>
                    <div class="stat-label">完成率</div>
                </div>
            </div>
        </div>

        <!-- 心情分佈 -->
        <?php if (!empty($moodStats)): ?>
        <div class="bento-card">
            <h3>💭 心情分佈</h3>
            <div class="mood-distribution">
                <?php foreach (array_slice($moodStats, 0, 4) as $mood => $count): ?>
                    <div class="mood-item">
                        <span class="mood-emoji-large"><?php echo htmlspecialchars($mood); ?></span>
                        <div class="mood-info">
                            <span class="mood-count"><?php echo $count; ?> 次</span>
                            <div class="mood-bar">
                                <div class="mood-fill" style="width: <?php echo ($count / max($moodStats)) * 100; ?>%"></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- 快速操作 -->
        <div class="bento-card">
             <h3>⚡ 快速操作</h3>
             <div class="quick-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                <a href="index.php?action=diary_create" class="quick-btn">
                    <span class="quick-btn-icon">✏️</span>
                    <span>寫新日記</span>
                </a>
                <a href="#" class="quick-btn">
                    <span class="quick-btn-icon">🎲</span>
                    <span>隨機回憶</span>
                </a>
                <?php else: ?>
                <div class="quick-btn" style="opacity:0.8; padding:0.55rem 1rem; border-radius:8px; display:inline-flex; align-items:center; gap:0.5rem;">
                    <span style="font-size:0.95rem;">需登入才能新增或刪除日記</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// 包含新的頁尾
require_once BASE_PATH . '/app/views/layout/footer.php';
?>
