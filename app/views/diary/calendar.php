<?php
// app/views/diary/calendar.php

// 標題和描述要在載入頁首之前設好，<title> 和分享預覽才拿得到
$pageTitle = '心情日曆';
$metaDescription = '寫一篇日記，AI 讀完畫成一張拍立得、再寫一句話給你。不用註冊就能試。';
require_once BASE_PATH . '/app/views/layout/header.php';

// 處理顯示訊息
$message = $_GET['message'] ?? null;
$showSuccessMessage = false;
$successMessage = '';

if ($message === 'diary_deleted') {
    $showSuccessMessage = true;
    $successMessage = '日記已刪除';
}

// --- PHP 日曆生成邏輯 (與之前相同) ---
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$date = new DateTime("$year-$month-01");
$monthName = $date->format('Y') . ' 年 ' . $date->format('n') . ' 月';
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
$prevMonthDays = (int)$prevMonth->format('t');
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
    <div class="alert alert-success text-center">
        <?php echo htmlspecialchars($successMessage); ?>
    </div>
<?php endif; ?>

<?php if (!isRegisteredUser()): ?>
    <!-- 第一次來的人：先看到成品長什麼樣，再給一個按鈕 -->
    <section class="mc-hero" aria-labelledby="mc-hero-title">
        <a class="mc-hero-card" href="index.php?action=diary_detail&id=-1&year=<?php echo (int) $year; ?>&month=<?php echo (int) $month; ?>">
            <img src="<?php echo APP_URL; ?>/public/assets/images/demo/demo-1.jpg" width="768" height="768"
                 alt="範例：AI 讀完「早餐店阿姨記得我」這篇日記畫的圖">
            <span class="mc-hero-cap">早餐店阿姨記得我</span>
        </a>
        <div class="mc-hero-text">
            <h1 id="mc-hero-title">把今天的日記，<br>變成一張拍立得</h1>
            <p>寫幾句今天發生的事，AI 會讀完畫成一張圖，再寫一句話給你。不用註冊，大約 10 秒。</p>
            <div class="mc-hero-actions">
                <a href="index.php?action=diary_create" class="btn btn-primary btn-lg">試寫一篇</a>
                <a href="#calendar" class="mc-hero-link">先看 6 篇範例</a>
            </div>
        </div>
    </section>
<?php endif; ?>

<div class="bento-grid">
    <!-- 日曆主區域 -->
    <div class="bento-card bento-calendar" id="calendar">
        <div class="calendar-container">
            <div class="calendar-header">
                <h2><?php echo $monthName; ?></h2>
                <div class="calendar-nav">
                    <a href="<?php echo $prevMonthLink; ?>" class="nav-arrow" title="上個月" aria-label="上個月">‹</a>
                    <a href="index.php?action=home" class="today-link" aria-label="返回今日">今天</a>
                    <a href="<?php echo $nextMonthLink; ?>" class="nav-arrow" title="下個月" aria-label="下個月">›</a>
                </div>
            </div>

            <?php if (($totalDiaries ?? 0) === 0): ?>
                <p class="empty-welcome__sub" style="font-size:0.85rem; margin-bottom: 0.5rem; text-align: center; color: var(--color-text-secondary);"><strong>這個月，還是空白的一頁</strong> — 點選任一日期寫下心情</p>
            <?php endif; ?>

            <table class="calendar" aria-label="<?php echo $monthName; ?> 心情日曆">
                <thead>
                    <tr>
                        <th scope="col">日</th>
                        <th scope="col">一</th>
                        <th scope="col">二</th>
                        <th scope="col">三</th>
                        <th scope="col">四</th>
                        <th scope="col">五</th>
                        <th scope="col">六</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php
                        // 填充空白
                        for ($i = 0; $i < $firstDayOfWeek; $i++) {
                            $prevDayNum = $prevMonthDays - $firstDayOfWeek + $i + 1;
                            echo "<td class='other-month'><div class='day-number'>{$prevDayNum}</div></td>";
                        }

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
                                    // 只有一篇日記，若有 image_path 顯示縮圖，否則顯示 mood emoji
                                    $diary = $dayDiaries[0];
                                    $diaryTitle = htmlspecialchars($diary['title'] ?? '無標題');
                                    $moodEmoji = htmlspecialchars(moodLabel($diary['mood'] ?? ''));
                                    $imagePath = $diary['image_path'] ?? '';
                                    // 範例日記的日期跟著月份走，連結要帶上年月
                                    $detailLink = "index.php?action=diary_detail&id={$diary['id']}";
                                    if (!empty($diary['is_demo'])) {
                                        $detailLink .= '&year=' . (int)$year . '&month=' . (int)$month;
                                    }
                                    if (!empty($imagePath)) {
                                        // 構建圖片 URL（如果是相對路徑則補上 public）
                                        $imgUrl = (strpos($imagePath, 'http') === 0) ? $imagePath : (strpos($imagePath, 'public/') === 0 ? APP_URL . '/' . $imagePath : APP_URL . '/public/' . $imagePath);
                                        echo "<a href='{$detailLink}' class='diary-entry' title='{$diaryTitle}' aria-label='查看日記：{$diaryTitle}'>";
                                        echo "<img class='diary-thumb' loading='lazy' src='" . htmlspecialchars($imgUrl) . "' alt='{$diaryTitle} 縮圖'/>";
                                        echo "</a>";
                                    } else {
                                        echo "<a href='{$detailLink}' class='diary-entry' title='{$diaryTitle}' aria-label='查看日記：{$diaryTitle}'>";
                                        echo "<span class='mood-word'>{$moodEmoji}</span>";
                                        echo "</a>";
                                    }
                                } else {
                                    // 多篇日記，點擊查看日期列表
                                    echo "<a href='index.php?action=diary_by_date&date={$currentDate}' class='multiple-diaries' title='查看該日的 {$diaryCount} 篇日記' aria-label='查看 {$currentDate} 的 {$diaryCount} 篇日記'>";
                                    foreach ($dayDiaries as $index => $diary) {
                                        if ($index >= 3) break; // 最多顯示3個
                                        echo "<span class='diary-dot' aria-hidden='true'></span>";
                                    }
                                    if ($diaryCount > 3) {
                                        echo "<span class='more-diaries'>+{$diaryCount}</span>";
                                    }
                                    echo "</a>";
                                }
                            } else {
                                $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $currentDay);
                                // 訪客也能試寫（不用註冊）；日記寫的是已經發生的事，未來的日子不給「+」
                                if ($currentDate <= date('Y-m-d')) {
                                    echo "<a href='index.php?action=diary_create&date={$currentDate}' class='no-diary' data-date='{$currentDate}' title='新增日記' aria-label='新增 {$currentDate} 日記'>";
                                    echo "<span class='add-diary-btn' aria-hidden='true'>+</span>";
                                    echo "</a>";
                                }
                            }

                            echo "</td>";
                            $currentDay++;
                        }

                        // 填充結尾空白
                        $remainingDays = 7 - (($daysInMonth + $firstDayOfWeek) % 7);
                        if ($remainingDays < 7) {
                            for ($i = 0; $i < $remainingDays; $i++) {
                                $nextDayNum = $i + 1;
                                echo "<td class='other-month'><div class='day-number'>{$nextDayNum}</div></td>";
                            }
                        }
                        ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 側邊欄 -->
    <div class="bento-sidebar">
        <!-- 這個月：一句話＋心情次數，不做 KPI 方塊 -->
        <div class="bento-card month-note">
            <h3><?php echo (int) $month; ?> 月</h3>
            <?php if ($totalDiaries > 0): ?>
                <p>寫了 <strong><?php echo $totalDiaries; ?></strong> 篇，<?php echo $daysWithDiaries; ?> 天有記錄。</p>
                <ul class="mood-tally" aria-label="心情次數">
                    <?php foreach ($moodStats as $m => $count): ?>
                        <li><span class="mood-word"><?php echo htmlspecialchars(moodLabel($m)); ?></span> <?php echo (int) $count; ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>這個月還沒寫。點日曆上的「+」寫第一篇。</p>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php
// 包含新的頁尾
require_once BASE_PATH . '/app/views/layout/footer.php';
?>
