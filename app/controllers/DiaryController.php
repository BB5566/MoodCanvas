<?php
// app/controllers/DiaryController.php — v3 重構版
// 合併重複邏輯、CSRF 驗證、編輯功能、速率限制

namespace App\Controllers;

use App\Models\Diary;
use Exception;

// 安全輔助層（CSRF 驗證 + 輸入驗證）
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/validator.php';
require_once __DIR__ . '/../helpers/demo_diaries.php';
require_once __DIR__ . '/../helpers/replicate.php';

class DiaryController
{
    private $diaryModel;

    public function __construct()
    {
        $this->diaryModel = new Diary();
    }

    // ============================================================
    // 頁面：日曆首頁
    // ============================================================
    public function index()
    {
        $user_id = $_SESSION['user_id'] ?? 0;
        $year = (int)($_GET['year'] ?? date('Y'));
        $month = str_pad((int)($_GET['month'] ?? date('m')), 2, '0', STR_PAD_LEFT);

        // 註冊的使用者只看自己的；訪客（含試寫過一篇的訪客）看範例日記加上自己寫的
        $own = $user_id > 0 ? $this->diaryModel->getDiariesByMonth($user_id, $year, $month) : [];
        if (isRegisteredUser()) {
            $diaries = $own;
        } else {
            // 訪客自己寫了的那天就不放範例，免得同一天變兩篇
            $ownDates = array_column($own, 'diary_date');
            $demos = array_filter(demoDiaries($year, $month), fn($d) => !in_array($d['diary_date'], $ownDates, true));
            $diaries = array_merge(array_values($demos), $own);
        }
        $pageTitle = '心情日曆';
        include BASE_PATH . '/app/views/diary/calendar.php';
    }

    // ============================================================
    // 頁面：建立／處理日記
    // ============================================================
    public function create()
    {
        // 訪客也能試寫：送出時才建立臨時訪客帳號（24 小時後自動清除）
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->saveDiary();
            return;
        }
        $this->showCreateForm();
    }

    // ============================================================
    // 表單處理（合併原 store + handleCreateDiary）
    // ============================================================
    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=diary_create');
            exit;
        }
        $this->saveDiary();
    }

    private function saveDiary()
    {
        // CSRF 驗證
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($token)) {
            $this->showError('安全驗證失敗，請重新整理頁面');
            return;
        }

        if (!isset($_SESSION['user_id'])) {
            $this->setupGuestAccount();
        }
        $user_id = $_SESSION['user_id'] ?? 1;

        $validation = $this->validateDiaryData($_POST);
        if (!$validation['success']) {
            $this->showError($validation['message']);
            return;
        }

        $title = $validation['title'];
        $content = $validation['content'];
        $mood = $validation['mood'];
        $diary_date = $validation['diary_date'];

        // 一天只能一篇：該日期已有日記則導向編輯既有日記，不再新增
        // 訪客例外：試寫第二篇不該被轉去編輯頁（編輯不會重畫、訪客又不能刪），變成死路
        $existing = isRegisteredUser() ? $this->diaryModel->getDiariesByDate($user_id, $diary_date) : [];
        if (!empty($existing)) {
            header('Location: ' . APP_URL . '/public/index.php?action=diary_edit&id=' . $existing[0]['id'] . '&notice=date_exists');
            exit;
        }

        try {
            $diary_id = $this->diaryModel->create($user_id, $title, $content, $mood, $diary_date, null, null);

            if ($diary_id) {
                $_SESSION['diary_art_style'] = $_POST['image_style'] ?? 'random';
                header('Location: ' . APP_URL . '/public/index.php?action=diary_detail&id=' . $diary_id . '&ai=generating');
                exit;
            }
            $this->showError('建立日記失敗，請稍後再試');
        } catch (Exception $e) {
            logMessage("建立日記失敗: " . $e->getMessage(), 'ERROR');
            $this->showError('系統發生錯誤，請稍後再試');
        }
    }

    // ============================================================
    // 頁面：編輯日記
    // ============================================================
    public function edit()
    {
        $user_id = $_SESSION['user_id'] ?? 0;
        if ($user_id <= 0) {
            header('Location: ' . APP_URL . '/public/index.php?action=login');
            exit;
        }

        $diary_id = sanitizeInt($_GET['id'] ?? null, 0);

        if ($diary_id <= 0) {
            header('Location: index.php?action=home');
            exit;
        }

        $diary = $this->diaryModel->findById($diary_id, $user_id);
        if (!$diary) {
            $this->showError('找不到該日記');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            if (!validateCsrfToken($token)) {
                $this->showError('安全驗證失敗');
                return;
            }

            // 若沒有傳入 diary_date，編輯時保留原本日期供驗證，雖然更新不改日期
            $postData = $_POST;
            $postData['diary_date'] = $diary['diary_date'] ?? date('Y-m-d');
            if (empty($postData['mood'])) $postData['mood'] = $diary['mood'];

            $validation = $this->validateDiaryData($postData);
            if (!$validation['success']) {
                $error = $validation['message'];
                $pageTitle = '編輯日記';
                include BASE_PATH . '/app/views/diary/edit.php';
                return;
            }

            $this->diaryModel->update($diary_id, [
                'title' => $validation['title'],
                'content' => $validation['content'],
                'mood' => $validation['mood'],
            ]);

            header('Location: ' . APP_URL . '/public/index.php?action=diary_detail&id=' . $diary_id);
            exit;
        }

        $pageTitle = '編輯日記';
        include BASE_PATH . '/app/views/diary/edit.php';
    }

    // ============================================================
    // 頁面：單篇日記詳情（含卡片翻轉）
    // ============================================================
    public function show()
    {
        $user_id = $_SESSION['user_id'] ?? 0;
        $diary_id = sanitizeInt($_GET['id'] ?? null, 0);

        if ($diary_id < 0) {
            // 範例日記（id 是負數）：誰都能看，唯讀
            $year = (int)($_GET['year'] ?? date('Y'));
            $month = (int)($_GET['month'] ?? date('m'));
            $diary = findDemoDiary($diary_id, $year, $month);
        } elseif ($diary_id > 0) {
            $diary = $this->diaryModel->findById($diary_id, $user_id);
        } else {
            $this->showError('無效的日記 ID');
            return;
        }

        if (!$diary) {
            $this->showError('找不到該日記');
            return;
        }

        $pageTitle = '日記詳情';
        $is_owner = ($_SESSION['user_id'] ?? 0) == ($diary['user_id'] ?? 0);
        $ai_generating = ($_GET['ai'] ?? '') === 'generating';
        include BASE_PATH . '/app/views/diary/detail.php';
    }

    // ============================================================
    // 頁面：指定日期的日記列表
    // ============================================================
    public function showByDate()
    {
        $user_id = $_SESSION['user_id'] ?? 0;
        $date = $_GET['date'] ?? date('Y-m-d');

        $diaries = $user_id > 0 ? $this->diaryModel->getDiariesByDate($user_id, $date) : [];
        $pageTitle = $date . ' 的日記';
        include BASE_PATH . '/app/views/diary/date_list.php';
    }

    // ============================================================
    // 刪除日記
    // ============================================================
    public function delete()
    {
        // 僅允許 POST，並驗證 CSRF token（與 saveDiary/edit 相同機制）
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showError('刪除請求方式錯誤');
            return;
        }
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($token)) {
            $this->showError('安全驗證失敗，請重新整理頁面');
            return;
        }

        $user_id = $_SESSION['user_id'] ?? 0;
        if ($user_id <= 0) {
            header('Location: ' . APP_URL . '/public/index.php?action=login');
            exit;
        }
        // 訪客不能刪除（試寫的日記 24 小時後會自動清掉）
        if (!isRegisteredUser()) {
            $this->showError('訪客不能刪除日記，註冊後才能管理自己的日記');
            return;
        }
        $diary_id = sanitizeInt($_GET['id'] ?? null, 0);
        if ($diary_id <= 0) {
            $diary_id = sanitizeInt($_POST['id'] ?? null, 0);
        }

        if ($diary_id <= 0) {
            header('Location: index.php?action=home');
            exit;
        }

        $diary = $this->diaryModel->findById($diary_id, $user_id);
        if ($diary && !empty($diary['image_path'])) {
            Diary::deleteImageFile($diary['image_path']);
        }

        $this->diaryModel->delete($diary_id, $user_id);
        header('Location: index.php?action=home&message=diary_deleted');
        exit;
    }

    // ============================================================
    // API：快速建立日記（日曆 AJAX）
    // ============================================================
    public function quickCreate()
    {
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $token = $input['csrf_token'] ?? '';
        if (!validateCsrfToken($token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => '安全驗證失敗']);
            return;
        }

        if (!isset($_SESSION['user_id'])) {
            $this->setupGuestAccount();
        }
        $user_id = $_SESSION['user_id'] ?? 1;

        // 快速紀錄預設標題
        if (empty($input['title'])) $input['title'] = '快速記錄';
        if (empty($input['content'])) $input['content'] = '快速記錄'; // 避免 content 為空

        $validation = $this->validateDiaryData($input);
        if (!$validation['success']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $validation['message']]);
            return;
        }

        $diary_date = $validation['diary_date'];
        $mood = $validation['mood'];

        // 一天只能一篇：該日期已有日記則回傳既有日記 id，不再新增
        $existing = $this->diaryModel->getDiariesByDate($user_id, $diary_date);
        if (!empty($existing)) {
            echo json_encode(['success' => false, 'message' => '該日期已有日記', 'existing_id' => $existing[0]['id']]);
            return;
        }

        try {
            $diary_id = $this->diaryModel->create(
                $user_id,
                $validation['title'],
                $validation['content'],
                $mood,
                $diary_date,
                null,
                null
            );
            echo json_encode(['success' => true, 'diary_id' => $diary_id]);
        } catch (Exception $e) {
            logMessage("快速建立日記失敗: " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'message' => '建立失敗，請稍後再試']);
        }
    }

    // ============================================================
    // API：非同步生成卡片內容（圖片 + 心情短語）
    // ============================================================
    public function generateCardContent()
    {
        header('Content-Type: application/json');

        $user_id = $_SESSION['user_id'] ?? 0;
        if ($user_id <= 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => '未授權']);
            return;
        }

        // AI 串接耗時，放寬執行時間並忽略使用者中斷，避免被 max_execution_time 截斷成非法 JSON
        @set_time_limit(0);
        ignore_user_abort(true);

        $diary_id = sanitizeInt($_GET['id'] ?? null, 0);
        if ($diary_id <= 0) {
            $diary_id = sanitizeInt($_POST['diary_id'] ?? null, 0);
        }
        if ($diary_id <= 0) {
            echo json_encode(['success' => false, 'message' => '缺少日記 ID']);
            return;
        }

        $user_id = $_SESSION['user_id'] ?? 1;
        $diary = $this->diaryModel->findById($diary_id, $user_id);
        if (!$diary) {
            echo json_encode(['success' => false, 'message' => '找不到日記']);
            return;
        }

        // 已經畫好了（例如重新整理頁面）：直接回傳，不再花一次
        if (!empty($diary['image_path']) && !empty($diary['ai_generated_text'])) {
            echo json_encode(['success' => true, 'image_url' => $diary['image_path'], 'quote' => $diary['ai_generated_text']]);
            return;
        }

        // 速率限制：每 60 秒最多 1 次
        $rateKey = 'ai_gen_last_' . $user_id;
        if (isset($_SESSION[$rateKey]) && (time() - $_SESSION[$rateKey]) < 60) {
            $wait = 60 - (time() - $_SESSION[$rateKey]);
            echo json_encode(['success' => false, 'message' => "請等待 {$wait} 秒再生成"]);
            return;
        }

        // AI 防護機制：每日及全站額度
        checkAILimit();

        $_SESSION[$rateKey] = time();

        $content = $diary['content'] ?? '';
        $mood = $diary['mood'] ?? '😊';
        $style = $_SESSION['diary_art_style'] ?? 'random';
        unset($_SESSION['diary_art_style']);

        // 釋放 session 鎖：後續 AI 工作可能耗時數十秒，期間不應阻塞使用者的其他請求
        session_write_close();

        $results = ['success' => true, 'image_url' => null, 'quote' => null];

        // 先讓文字模型讀日記：寫出英文畫面描述（給生圖用）與心情短語
        $reading = ['scene' => null, 'quote' => null];
        try {
            $reading = $this->callAIDiaryReading($content, $mood);
        } catch (Exception $e) {
            logMessage("AI 讀日記失敗: " . $e->getMessage(), 'ERROR');
            $results['quote_error'] = $e->getMessage();
        }

        // 生成圖片（讀日記失敗就只用心情與畫風生圖）
        try {
            $img = $this->callAIImageGeneration($reading['scene'], $style, $mood);
            if ($img) {
                $results['image_url'] = $img;
                $this->diaryModel->update($diary_id, ['image_path' => $img]);
            }
        } catch (Exception $e) {
            logMessage("AI 圖片失敗: " . $e->getMessage(), 'ERROR');
            $results['image_error'] = $e->getMessage();
        }

        if ($reading['quote']) {
            $results['quote'] = $reading['quote'];
            $this->diaryModel->update($diary_id, ['ai_generated_text' => $reading['quote']]);
        }

        // 沒畫出圖：次數還回去、解除 60 秒限制、留住畫風，讓「再試一次」馬上能用
        if (!$results['image_url']) {
            refundAILimit();
            session_start();
            unset($_SESSION[$rateKey]);
            $_SESSION['diary_art_style'] = $style;
            session_write_close();
        }

        echo json_encode($results);
    }

    // ============================================================
    // 儀表板
    // ============================================================
    public function dashboard()
    {
        $user_id = $_SESSION['user_id'] ?? 0;
        if ($user_id <= 0) {
            header('Location: ' . APP_URL . '/public/index.php?action=login');
            exit;
        }
        $diaries = $this->diaryModel->findAllByUserId($user_id);

        // 圖表需要 date 與 mood_score，但資料表只存 emoji mood / diary_date，
        // 在此補上：date = diary_date，mood_score = emoji 對應 1-5 分（1 差、5 優）
        foreach ($diaries as &$d) {
            $d['date'] = $d['diary_date'] ?? '';
            $d['mood_score'] = $this->moodToScore($d['mood'] ?? '');
        }
        unset($d);

        $pageTitle = '心情觀測儀表板';
        include BASE_PATH . '/app/views/dashboard/index.php';
    }

    // ============================================================
    // 內部：心情白名單驗證（與表單選項一致，防止任意字串寫入或送進 AI）
    // ============================================================
    private function isValidMood($mood)
    {
        $allowed = ['😊', '😢', '😡', '😍', '😴', '🤔', '😂', '😰', '🥰', '🙄'];
        return is_string($mood) && in_array($mood, $allowed, true);
    }

    // ============================================================
    // 內部：心情 emoji 對應 1-5 分數（給儀表板圖表用）
    // ============================================================
    private function moodToScore($mood)
    {
        $map = [
            '😂' => 5, '😍' => 5, '🥰' => 5, // 大笑 / 戀愛 / 溫暖
            '😊' => 4,                        // 開心
            '🤔' => 3, '🙄' => 3,             // 思考 / 無奈（中性）
            '😴' => 2, '😰' => 2,             // 疲憊 / 焦慮
            '😢' => 1, '😡' => 1,             // 傷心 / 憤怒
        ];
        return $map[$mood] ?? 3;
    }

    // ============================================================
    // 內部：共用日記資料驗證
    // ============================================================
    private function validateDiaryData($data)
    {
        $title = trim($data['title'] ?? '');
        $content = trim($data['content'] ?? '');
        $mood = $data['mood'] ?? '😊';
        if (!$this->isValidMood($mood)) {
            $mood = '😊'; // 不在白名單內 → 退回預設心情
        }
        $diary_date = $this->validateDiaryDate($data['diary_date'] ?? null);

        if (empty($content)) {
            return ['success' => false, 'message' => '日記內容不能為空'];
        }
        if (mb_strlen($content) > 1000) {
            return ['success' => false, 'message' => '日記內容不能超過 1000 字'];
        }
        if (empty($title)) {
            $title = '無標題日記';
        }

        return [
            'success' => true,
            'title' => $title,
            'content' => $content,
            'mood' => $mood,
            'diary_date' => $diary_date
        ];
    }

    // ============================================================
    // 內部：建立訪客帳號（限制建立頻率）
    // ============================================================
    private function setupGuestAccount()
    {
        // 限制每個 IP 建立訪客帳號的頻率：例如每小時最多 5 次
        $ip = rateLimitClientIp();
        $cacheFile = (defined('LOGS_PATH') ? LOGS_PATH : sys_get_temp_dir()) . '/guest_limit_' . md5($ip) . '.json';

        $now = time();
        $window = 3600; // 1 hr
        $maxGuests = 5;

        $entries = [];
        $fh = @fopen($cacheFile, 'c+');
        if ($fh && flock($fh, LOCK_EX)) {
            $raw = stream_get_contents($fh);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $entries = $decoded;
            }

            $entries = array_values(array_filter($entries, function ($ts) use ($now, $window) {
                return is_int($ts) && $ts > ($now - $window);
            }));

            if (count($entries) >= $maxGuests) {
                flock($fh, LOCK_UN);
                fclose($fh);
                http_response_code(429);
                header('Content-Type: application/json');
                die(json_encode(['success' => false, 'message' => '建立訪客帳號過於頻繁，請稍後再試。']));
            }

            $entries[] = $now;
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($entries));
            fflush($fh);
            flock($fh, LOCK_UN);
            fclose($fh);
        } elseif ($fh) {
            fclose($fh);
        }

        $guestId = (new \App\Models\User())->createGuest();
        if ($guestId) {
            $_SESSION['user_id'] = (int) $guestId;
            $_SESSION['username'] = 'guest_' . $guestId;
            $_SESSION['is_guest'] = true;
        } else {
            http_response_code(503);
            die('系統暫時無法建立訪客帳號，請稍後再試。');
        }
    }

    // ============================================================
    // 內部：顯示表單／錯誤
    // ============================================================
    private function showCreateForm()
    {
        // 一天只能一篇：若指定日期已有日記，直接導向編輯既有日記
        $user_id = $_SESSION['user_id'] ?? 0;
        $requestedDate = $_GET['date'] ?? date('Y-m-d');
        $existing = isRegisteredUser() ? $this->diaryModel->getDiariesByDate($user_id, $requestedDate) : []; // 訪客可以同一天多寫幾篇
        $aiRemaining = isRegisteredUser() ? null : aiLimitRemaining(); // 訪客看得到今天還能試幾次
        if (!empty($existing)) {
            header('Location: ' . APP_URL . '/public/index.php?action=diary_edit&id=' . $existing[0]['id'] . '&notice=date_exists');
            exit;
        }
        $pageTitle = '新增日記';
        include BASE_PATH . '/app/views/diary/create.php';
    }

    // ============================================================
    // 內部：驗證日記日期（Y-m-d 格式、合理範圍）
    // 不符合格式或超出範圍時回傳今日日期
    // ============================================================
    private function validateDiaryDate($input)
    {
        $today = date('Y-m-d');
        if (empty($input) || !is_string($input)) {
            return $today;
        }

        $dt = \DateTime::createFromFormat('!Y-m-d', $input); // ! 讓時間歸零，不然會帶現在的時分秒，比上限時差一天
        // 確認格式正確且為真實存在的日期（createFromFormat 會容許溢位，需再比對）
        if (!$dt || $dt->format('Y-m-d') !== $input) {
            return $today;
        }

        $year = (int)$dt->format('Y');
        $month = (int)$dt->format('m');
        $day = (int)$dt->format('d');
        if (!checkdate($month, $day, $year)) {
            return $today;
        }

        // 合理範圍：不早於 2000-01-01，不晚於今日 +1 天
        $min = new \DateTime('2000-01-01');
        $max = new \DateTime($today);
        $max->modify('+1 day');
        if ($dt < $min || $dt > $max) {
            return $today;
        }

        return $input;
    }

    private function showError($message, $emoji = '😅', $hint = '')
    {
        $error = $message;
        $pageTitle = '錯誤';
        if (file_exists(BASE_PATH . '/app/views/error/general.php')) {
            include BASE_PATH . '/app/views/error/general.php';
        } else {
            include BASE_PATH . '/app/views/diary/create.php';
        }
    }

    // ============================================================
    // 內部：AI 圖片生成（Replicate Flux Schnell + 本地儲存）
    // ============================================================
    private function callAIImageGeneration($scene, $style, $mood)
    {
        $input = [
            'prompt' => $this->buildImagePrompt($scene, $style, $mood),
            'aspect_ratio' => '1:1',
            'num_outputs' => 1,
            'output_format' => 'jpg',
        ];
        try {
            $output = replicateRun(REPLICATE_IMAGE_MODEL, $input);
        } catch (Exception $e) {
            // Flux 的安全檢查偶爾誤判（例如日記裡有小朋友），官方建議再跑一次：換個隨機種子重來一次就好
            if (stripos($e->getMessage(), 'NSFW') === false) throw $e;
            logMessage('Flux 安全檢查誤判，重跑一次', 'INFO');
            $output = replicateRun(REPLICATE_IMAGE_MODEL, $input + ['seed' => random_int(1, 2147483647)]);
        }
        $url = is_array($output) ? ($output[0] ?? null) : $output;
        if (!$url) throw new Exception('No image URL');
        return $this->downloadImage($url);
    }

    private function downloadImage($url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            // 啟用 SSL 憑證驗證；若在 CA 憑證庫不完整的主機上下載失敗，這即是原因
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!$imageData || $httpCode !== 200) {
            logMessage("圖片下載失敗 HTTP $httpCode: $error", 'ERROR');
            return null;
        }

        $imageId = uniqid('img_') . '.jpg';
        $dir = BASE_PATH . '/public/storage/generated_images/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $bytes = file_put_contents($dir . $imageId, $imageData);
        if (!$bytes) {
            logMessage("圖片寫入失敗: $dir$imageId", 'ERROR');
            return null;
        }
        return 'storage/generated_images/' . $imageId;
    }

    // ============================================================
    // 內部：讀日記（GPT-5 nano）→ 英文畫面描述＋繁中心情短語
    // 生圖模型看不懂中文，所以先把日記寫成具體的英文畫面，圖才會跟內容對得上
    // ============================================================
    private function callAIDiaryReading($content, $mood)
    {
        $prompt = "你是插畫導演，也是溫柔的朋友。讀下面這篇日記，只回傳一個 JSON 物件，不要其他文字。\n"
                . "欄位規則：\n"
                . "- scene：只能用英文（不可有任何中文字），一句 15～35 個字的畫面描述。只畫日記裡真的出現的人、動物、物品、地點與動作，不要自己加東西。"
                . "畫面裡不能有招牌、菜單、海報、螢幕上的字或任何文字，場景請描述成人物與物品的特寫。\n"
                . "- quote：繁體中文，12～22 字，像朋友讀完後寫在卡片背面的一句話：有一點詩意的比喻，貼著這篇的心情（好笑就輕快、緊張就安撫）。"
                . "不要重述日記內容，不要用「光」「溫暖」這類空泛字眼。\n"
                . '格式：{"scene": "...", "quote": "..."}' . "\n\n"
                . "日記（心情 {$mood}）：\n" . mb_substr($content, 0, 600);

        $data = extractJsonObject(replicateText($prompt, 300)) ?? [];
        $scene = trim((string) ($data['scene'] ?? ''));
        $quote = trim((string) ($data['quote'] ?? ''));
        return ['scene' => $scene ?: null, 'quote' => $quote ?: null];
    }

    // ============================================================
    // AI 洞察：根據近期日記摘要產生觀察與鼓勵（JSON 回應）
    // 路由 action=get_ai_insight，由儀表板以 POST 呼叫
    // ============================================================
    public function getAiInsight()
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $user_id = $_SESSION['user_id'] ?? 0;
            if ($user_id <= 0) {
                echo json_encode(['success' => false, 'message' => '未授權']);
                return;
            }

            // 速率限制：每 60 秒最多 1 次 (共用 rate_limiter，或是直接判斷)
            $rateKey = 'ai_insight_last_' . $user_id;
            if (isset($_SESSION[$rateKey]) && (time() - $_SESSION[$rateKey]) < 60) {
                $wait = 60 - (time() - $_SESSION[$rateKey]);
                echo json_encode(['success' => false, 'message' => "請等待 {$wait} 秒再分析"]);
                return;
            }

            // AI 防護機制：每日及全站額度
            checkAILimit();
            $_SESSION[$rateKey] = time();

            // 從資料庫讀取使用者的日記，而非信任 Client 傳入的 POST
            $diaries = $this->diaryModel->findAllByUserId($user_id);

            if (empty($diaries) || !is_array($diaries)) {
                echo json_encode(['success' => false, 'message' => '沒有足夠的日記資料']);
                return;
            }

            // 整理近期最多 30 筆餵給 AI，避免 prompt 過長
            $recent = array_slice($diaries, 0, 30);
            $summary = '';
            foreach ($recent as $d) {
                $date = $d['diary_date'] ?? '';
                $mood = $d['mood'] ?? '';
                $score = $this->moodToScore($mood);
                $text = mb_substr((string)($d['content'] ?? ''), 0, 60);
                $summary .= "- {$date}（心情分數 {$score}/5）：{$text}\n";
            }

            $insight = $this->callAIInsightGeneration($summary);
            if ($insight) {
                echo json_encode(['success' => true, 'insight' => $insight]);
            } else {
                echo json_encode(['success' => false, 'message' => 'AI 分析暫時無法使用']);
            }
        } catch (Exception $e) {
            logMessage('AI 洞察失敗: ' . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'message' => 'AI 分析失敗，請稍後再試']);
        }
    }

    // ============================================================
    // 內部：GPT-5 nano 產生心情洞察
    // ============================================================
    private function callAIInsightGeneration($summary)
    {
        $prompt = "你是一位溫暖的心理陪伴者。以下是使用者近期的心情日記摘要，"
                . "請用繁體中文寫一段 2-3 句、溫暖且具洞察力的觀察與鼓勵（不超過 120 字），"
                . "點出心情趨勢或值得留意的地方，語氣親切不說教。\n\n摘要：\n{$summary}\n\n只回傳觀察文字本身，不要加任何說明。";

        return replicateText($prompt, 250) ?: null;
    }

    // ============================================================
    // 內部：建立圖片提示詞
    // ============================================================
    private function buildImagePrompt($scene, $style, $mood)
    {
        $moodMap = [
            '😊' => 'joyful, bright, warm',
            '😢' => 'melancholic, soft, blue-toned',
            '😡' => 'intense, dramatic, dark red',
            '😍' => 'romantic, dreamy, pink-toned',
            '😴' => 'peaceful, quiet, twilight',
            '🤔' => 'contemplative, surreal, thoughtful',
            '😂' => 'playful, colorful, energetic',
            '😰' => 'atmospheric, moody, with tension',
            '🥰' => 'cozy, intimate, golden hour',
            '🙄' => 'subtle, muted, ironic',
        ];
        // 畫風只描述畫法，不寫任何工作室、作品或畫家的名字（避免侵權，也避免品牌名出現在公開作品上）
        $styleMap = [
            'ghibli' => 'hand-painted Japanese animated film look, lush watercolor background art, soft natural sunlight, gentle nostalgic summer feeling',
            'anime' => 'modern Japanese anime illustration, clean line art, vivid cel shading, bright clear sky, cinematic lighting',
            '3d-render' => '3D animated feature film still, soft global illumination, rounded charming characters, expressive faces',
            'watercolor' => "children's picture book watercolor illustration, soft color washes, visible paper texture, warm and tender",
            'film' => '35mm film photograph, nostalgic faded colors, soft film grain, warm afternoon light',
            'clay' => 'claymation stop-motion scene, handmade clay figures and props, soft studio lighting, playful',
            'pixel-art' => 'pixel art, 16-bit style, retro game aesthetic',
            'ink-wash' => 'ink wash painting, soft brush strokes, generous empty space',
            // 舊選項：已不在畫面上，留著讓舊連結還能用
            'photographic' => 'photorealistic, detailed',
            'flat-illustration' => 'flat design illustration, minimalist, clean lines',
            'sketch' => 'hand-drawn pencil sketch, artistic',
        ];

        $moodDesc = $moodMap[$mood] ?? 'balanced, neutral';
        // 「讓 AI 自己決定」：從畫面上那幾種隨機挑一種
        $styleDesc = $styleMap[$style] ?? $styleMap[array_rand(array_slice($styleMap, 0, 8))];
        $scene = $scene ?: 'a quiet everyday moment';

        // 不把中文原文塞進提示詞，也不提「日記」「印章」「書法」：Flux 沒有反向提示，寫了反而會畫出假字和印章
        return "{$styleDesc}. {$scene}. Mood: {$moodDesc}. "
             . "Wordless picture: plain walls, blank surfaces, clean uncluttered background, "
             . "nothing written anywhere in the image.";
    }
}