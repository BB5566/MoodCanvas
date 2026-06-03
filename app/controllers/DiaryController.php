<?php
// app/controllers/DiaryController.php — v3 重構版
// 合併重複邏輯、CSRF 驗證、編輯功能、速率限制

namespace App\Controllers;

use App\Models\Diary;
use Exception;

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
        $user_id = $_SESSION['user_id'] ?? 1;
        $year = (int)($_GET['year'] ?? date('Y'));
        $month = str_pad((int)($_GET['month'] ?? date('m')), 2, '0', STR_PAD_LEFT);

        $diaries = $this->diaryModel->getDiariesByMonth($user_id, $year, $month);
        $pageTitle = '心情日曆';
        include BASE_PATH . '/app/views/diary/calendar.php';
    }

    // ============================================================
    // 頁面：建立／處理日記
    // ============================================================
    public function create()
    {
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
        if (!verifyCsrfToken($token)) {
            $this->showError('安全驗證失敗，請重新整理頁面');
            return;
        }

        $user_id = $_SESSION['user_id'] ?? 1;
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $mood = $_POST['mood'] ?? '😊';
        $diary_date = $_POST['diary_date'] ?? date('Y-m-d');

        if (empty($content)) {
            $this->showError('日記內容不能為空');
            return;
        }
        if (mb_strlen($content) > 1000) {
            $this->showError('日記內容不能超過 1000 字');
            return;
        }
        if (empty($title)) {
            $title = '無標題日記';
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
            $this->showError('系統錯誤：' . $e->getMessage());
        }
    }

    // ============================================================
    // 頁面：編輯日記
    // ============================================================
    public function edit()
    {
        $user_id = $_SESSION['user_id'] ?? 1;
        $diary_id = $_GET['id'] ?? null;

        if (!$diary_id) {
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
            if (!verifyCsrfToken($token)) {
                $this->showError('安全驗證失敗');
                return;
            }

            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $mood = $_POST['mood'] ?? $diary['mood'];

            if (empty($content)) {
                $error = '日記內容不能為空';
                $pageTitle = '編輯日記';
                include BASE_PATH . '/app/views/diary/edit.php';
                return;
            }

            $this->diaryModel->update($diary_id, [
                'title' => $title ?: '無標題日記',
                'content' => $content,
                'mood' => $mood,
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
        $user_id = $_SESSION['user_id'] ?? 1;
        $diary_id = $_GET['id'] ?? null;

        if (!$diary_id) {
            $this->showError('無效的日記 ID');
            return;
        }

        $diary = $this->diaryModel->findById($diary_id, $user_id);
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
        $user_id = $_SESSION['user_id'] ?? 1;
        $date = $_GET['date'] ?? date('Y-m-d');

        $diaries = $this->diaryModel->getDiariesByDate($user_id, $date);
        $pageTitle = $date . ' 的日記';
        include BASE_PATH . '/app/views/diary/date_list.php';
    }

    // ============================================================
    // 刪除日記
    // ============================================================
    public function delete()
    {
        $user_id = $_SESSION['user_id'] ?? 1;
        $diary_id = $_GET['id'] ?? null;

        if (!$diary_id) {
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

        $input = json_decode(file_get_contents('php://input'), true);
        $user_id = $_SESSION['user_id'] ?? 1;
        $diary_date = $input['diary_date'] ?? null;
        $mood = $input['mood'] ?? '📝';

        if (!$diary_date) {
            echo json_encode(['success' => false, 'message' => '日期為必填項']);
            return;
        }

        try {
            $diary_id = $this->diaryModel->create(
                $user_id,
                $input['title'] ?? '快速記錄',
                $input['content'] ?? '',
                $mood,
                $diary_date,
                null,
                null
            );
            echo json_encode(['success' => true, 'diary_id' => $diary_id]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ============================================================
    // API：非同步生成卡片內容（圖片 + 心情短語）
    // ============================================================
    public function generateCardContent()
    {
        header('Content-Type: application/json');

        $diary_id = $_GET['id'] ?? ($_POST['diary_id'] ?? null);
        if (!$diary_id) {
            echo json_encode(['success' => false, 'message' => '缺少日記 ID']);
            return;
        }

        // 速率限制：每 60 秒最多 1 次
        $user_id = $_SESSION['user_id'] ?? 1;
        $rateKey = 'ai_gen_last_' . $user_id;
        if (isset($_SESSION[$rateKey]) && (time() - $_SESSION[$rateKey]) < 60) {
            $wait = 60 - (time() - $_SESSION[$rateKey]);
            echo json_encode(['success' => false, 'message' => "請等待 {$wait} 秒再生成"]);
            return;
        }
        $_SESSION[$rateKey] = time();

        $diary = $this->diaryModel->findById($diary_id, $user_id);
        if (!$diary) {
            echo json_encode(['success' => false, 'message' => '找不到日記']);
            return;
        }

        $content = $diary['content'] ?? '';
        $mood = $diary['mood'] ?? '😊';
        $style = $_SESSION['diary_art_style'] ?? 'random';
        unset($_SESSION['diary_art_style']);

        $results = ['success' => true, 'image_url' => null, 'quote' => null];

        // 生成圖片
        try {
            $img = $this->callAIImageGeneration($content, $style, $mood);
            if ($img) {
                $results['image_url'] = $img;
                $this->diaryModel->update($diary_id, ['image_path' => $img]);
            }
        } catch (Exception $e) {
            logMessage("AI 圖片失敗: " . $e->getMessage(), 'ERROR');
            $results['image_error'] = $e->getMessage();
        }

        // 生成短語
        try {
            $quote = $this->callAIQuoteGeneration($content, $mood);
            if ($quote) {
                $results['quote'] = $quote;
                $this->diaryModel->update($diary_id, ['ai_generated_text' => $quote]);
            }
        } catch (Exception $e) {
            logMessage("AI 短語失敗: " . $e->getMessage(), 'ERROR');
            $results['quote_error'] = $e->getMessage();
        }

        echo json_encode($results);
    }

    // ============================================================
    // 儀表板
    // ============================================================
    public function dashboard()
    {
        $user_id = $_SESSION['user_id'] ?? 1;
        $diaries = $this->diaryModel->findAllByUserId($user_id);
        $pageTitle = '心情觀測儀表板';
        include BASE_PATH . '/app/views/dashboard/index.php';
    }

    // ============================================================
    // 內部：顯示表單／錯誤
    // ============================================================
    private function showCreateForm()
    {
        $pageTitle = '新增日記';
        include BASE_PATH . '/app/views/diary/create.php';
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
    // 內部：AI 圖片生成（Replicate + 本地儲存）
    // ============================================================
    private function callAIImageGeneration($content, $style, $mood)
    {
        $apiKey = getenv('REPLICATE_API_KEY');
        if (empty($apiKey)) throw new Exception('REPLICATE_API_KEY not configured');

        $prompt = $this->buildImagePrompt($content, $style, $mood);

        $ch = curl_init('https://api.replicate.com/v1/predictions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'version' => '19335492dbe879d4b5983bff2149f597db8314ccc7fe374e6313af7c2b52792f',
                'input' => [
                    'prompt' => $prompt,
                    'aspect_ratio' => '1:1',
                    'safety_filter_level' => 'block_medium_and_above',
                ],
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!in_array($httpCode, [200, 201])) {
            throw new Exception("Replicate API HTTP $httpCode");
        }

        $data = json_decode($response, true);
        $predictionId = $data['id'] ?? null;
        if (!$predictionId) throw new Exception('No prediction ID');

        $imageUrl = $this->pollReplicatePrediction($predictionId, $apiKey);
        if (!$imageUrl) throw new Exception('Image generation timed out');

        return $this->downloadImage($imageUrl);
    }

    private function pollReplicatePrediction($predictionId, $apiKey)
    {
        for ($i = 0; $i < 30; $i++) {
            sleep(2);
            $ch = curl_init("https://api.replicate.com/v1/predictions/$predictionId");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($response, true);

            $status = $data['status'] ?? 'processing';
            if ($status === 'succeeded') {
                $output = $data['output'] ?? null;
                return is_array($output) ? ($output[0] ?? null) : $output;
            }
            if (in_array($status, ['failed', 'canceled'])) return null;
        }
        return null;
    }

    private function downloadImage($url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!$imageData || $httpCode !== 200) {
            logMessage("圖片下載失敗 HTTP $httpCode: $error", 'ERROR');
            return null;
        }

        $imageId = uniqid('img_') . '.png';
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
    // 內部：AI 心情短語生成（DeepSeek V4 Flash）
    // ============================================================
    private function callAIQuoteGeneration($content, $mood)
    {
        $apiKey = getenv('DEEPSEEK_API_KEY');
        if (empty($apiKey)) throw new Exception('DEEPSEEK_API_KEY not configured');

        $prompt = "請根據以下日記內容，生成一句溫暖、有詩意的心情短語（不超過 40 字）。\n\n"
                . "日記內容：{$content}\n心情：{$mood}\n\n只需回傳短語本身，不要加任何說明。";

        $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'deepseek-v4-flash',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'max_tokens' => 100,
                'temperature' => 0.9,
            ]),
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return trim($data['choices'][0]['message']['content'] ?? '') ?: null;
    }

    // ============================================================
    // 內部：建立圖片提示詞
    // ============================================================
    private function buildImagePrompt($content, $style, $mood)
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
        $styleMap = [
            'photographic' => 'photorealistic, detailed',
            'ghibli' => 'Studio Ghibli style, hand-drawn animation, soft colors, dreamy',
            'pixel-art' => 'pixel art, 16-bit style, retro game aesthetic',
            '3d-render' => 'Pixar-style 3D render, soft lighting, charming',
            'flat-illustration' => 'flat design illustration, minimalist, clean lines',
            'sketch' => 'hand-drawn pencil sketch, artistic',
            'ink-wash' => 'traditional Chinese ink wash painting, sumi-e style',
        ];

        $moodDesc = $moodMap[$mood] ?? 'balanced, neutral';
        $styleDesc = $styleMap[$style] ?? '';
        $shortContent = mb_substr($content, 0, 300);

        return "A beautiful artistic illustration based on this diary entry. "
             . "Mood: {$moodDesc}. "
             . ($styleDesc ? "Style: {$styleDesc}. " : "")
             . "The scene should evoke the emotion of: \"{$shortContent}\". "
             . "No text, no words, no watermarks. Clean composition, high quality.";
    }
}