<?php
// app/controllers/DiaryController.php

namespace App\Controllers;

use App\Models\Diary;
use App\Models\PerplexityAdapter;
use App\Models\GeminiImageAdapter;
use App\Models\GeminiTextAdapter;
use Exception;
use ReflectionClass;

class DiaryController
{

    private $diaryModel;
    private $perplexityAdapter;
    private $geminiImageAdapter;
    private $geminiTextAdapter;

    public function __construct()
    {
        // 確保 config.php 已被載入
        if (!defined('DB_HOST')) {
            // config.php 應該由 index.php 載入，理論上這裡不需要
            // 但為防萬一，保留一個檢查
            // require_once __DIR__ . '/../../config/config.php';
        }
        $this->diaryModel = new Diary();
        $this->perplexityAdapter = new PerplexityAdapter();

        // Initialize Gemini Image Adapter (Vertex AI)
        try {
            $gcpProjectId = getenv('GCP_PROJECT_ID') ?: (defined('GCP_PROJECT_ID') ? GCP_PROJECT_ID : null);
            if ($gcpProjectId) {
                $this->geminiImageAdapter = new GeminiImageAdapter();
                logMessage("GeminiImageAdapter 在 DiaryController 中初始化成功", 'INFO');
            } else {
                $this->geminiImageAdapter = null;
                logMessage("GCP_PROJECT_ID 未設定，跳過 GeminiImageAdapter 初始化", 'INFO');
            }
        } catch (Exception $e) {
            error_log('GeminiImageAdapter init error in DiaryController: ' . $e->getMessage());
            $this->geminiImageAdapter = null;
        }

        // Initialize Gemini Text Adapter
        try {
            $geminiApiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : null);
            if ($geminiApiKey && $geminiApiKey !== 'your_gemini_api_key_here') {
                $this->geminiTextAdapter = new GeminiTextAdapter();
                logMessage("GeminiTextAdapter 在 DiaryController 中初始化成功", 'INFO');
            } else {
                $this->geminiTextAdapter = null;
                logMessage("GEMINI_API_KEY 未設定，跳過 GeminiTextAdapter 初始化", 'INFO');
            }
        } catch (Exception $e) {
            error_log('GeminiTextAdapter init error in DiaryController: ' . $e->getMessage());
            $this->geminiTextAdapter = null;
        }
    }
    /**
     * 顯示日曆頁面 (首頁)
     */
    public function index()
    {
        // 支援訪客預覽：若未登入，使用示範用戶（PUBLIC_DEMO_USER_ID 或預設 1）來顯示日曆
        $is_guest_view = false;
        if (!isset($_SESSION['user_id'])) {
            $is_guest_view = true;
            $user_id = defined('PUBLIC_DEMO_USER_ID') ? constant('PUBLIC_DEMO_USER_ID') : 1;
        } else {
            $user_id = $_SESSION['user_id'];
        }

        // 獲取年月參數，預設為當前月份
        $year = $_GET['year'] ?? date('Y');
        $month = $_GET['month'] ?? date('m');

        // 確保年月格式正確
        $year = (int)$year;
        $month = str_pad((int)$month, 2, '0', STR_PAD_LEFT);

        // 獲取指定月份的日記
        $diaries = $this->diaryModel->getDiariesByMonth($user_id, $year, $month);

        logMessage("載入日曆: 用戶 $user_id, 年月 $year-$month, 日記數量 " . count($diaries), 'INFO');
        // 載入日曆視圖
        $pageTitle = '心情日曆';
        include BASE_PATH . '/app/views/diary/calendar.php';
    }

    /**
     * 顯示創建日記頁面或處理創建邏輯
     */
    public function create()
    {
        // 檢查使用者是否已登入
        if (!isset($_SESSION['user_id'])) {
            $this->redirectToLogin();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->handleCreateDiary();
        } else {
            $this->showCreateForm();
        }
    }

    /**
     * API: 快速建立日記 (for calendar view)
     */
    public function quickCreate()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => '需要認證']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $user_id = $_SESSION['user_id'];
        $diary_date = $input['diary_date'] ?? null;
        $mood = $input['mood'] ?? '📝';
        $title = $input['title'] ?? '快速記錄';
        $content = $input['content'] ?? '';

        if (!$diary_date) {
            echo json_encode(['success' => false, 'message' => '日期為必填項']);
            return;
        }

        try {
            $diary_id = $this->diaryModel->create($user_id, $title, $content, $mood, $diary_date, null, null);
            if ($diary_id) {
                logMessage("快速日記建立成功: 用戶 $user_id, 日期 $diary_date, ID $diary_id", 'INFO');
                echo json_encode(['success' => true, 'diary_id' => $diary_id]);
            } else {
                logMessage("快速日記建立失敗 (Model回傳false): 用戶 $user_id, 日期 $diary_date", 'ERROR');
                echo json_encode(['success' => false, 'message' => '建立日記項目失敗']);
            }
        } catch (Exception $e) {
            logMessage("快速日記建立異常: " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'message' => '發生錯誤: ' . $e->getMessage()]);
        }
    }

    /**
     * 顯示單篇日記詳情
     */
    public function show()
    {
        // 顯示單篇日記：允許訪客預覽示範日記（示範用戶 id），但未登入者不得編輯或刪除
        $is_guest_view = false;
        if (!isset($_SESSION['user_id'])) {
            $is_guest_view = true;
            $user_id = defined('PUBLIC_DEMO_USER_ID') ? constant('PUBLIC_DEMO_USER_ID') : 1;
        } else {
            $user_id = $_SESSION['user_id'];
        }

        $diary_id = $_GET['id'] ?? null;
        if (!$diary_id) {
            $this->showError('無效的日記ID');
            return;
        }

        $diary = $this->diaryModel->findById($diary_id, $user_id);

        if (!$diary) {
            $this->showError('找不到該日記或您沒有權限查看');
            return;
        }

        $pageTitle = '日記詳情';
        // 是否為該日記擁有者（用於控制刪除/編輯按鈕顯示）
        $is_owner = isset($_SESSION['user_id']) && ($_SESSION['user_id'] == ($diary['user_id'] ?? null));
        include BASE_PATH . '/app/views/diary/detail.php';
    }

    /**
     * 處理創建日記的邏輯
     */
    private function handleCreateDiary()
    {
        $user_id = $_SESSION['user_id'];
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $mood = $_POST['mood'] ?? '';
        $diary_date = $_POST['diary_date'] ?? date('Y-m-d');

        // 基本驗證
        if (empty($content)) {
            $this->showError('日記內容不能為空！');
            return;
        }

        if (empty($title)) {
            $title = '無標題日記';
        }        // 建立日記
        $diary_id = $this->diaryModel->create($user_id, $title, $content, $mood, $diary_date, null, null);

        if ($diary_id) {
            // 重定向到日記詳情頁面
            header('Location: ' . APP_URL . '/public/index.php?action=diary_detail&id=' . $diary_id);
            exit;
        } else {
            $this->showError('建立日記失敗，請稍後再試');
        }
    }

    /**
     * 顯示創建日記表單
     */
    private function showCreateForm()
    {
        $pageTitle = '新增日記';
        include BASE_PATH . '/app/views/diary/create.php';
    }

    /**
     * 顯示錯誤訊息
     */
    private function showError($message)
    {
        $error = $message;
        $pageTitle = '錯誤';
        include BASE_PATH . '/app/views/diary/create.php';
    }

    /**
     * 重定向到登入頁面
     */
    private function redirectToLogin()
    {
        header('Location: ' . APP_URL . '/public/index.php?action=login');
        exit;
    }

    /**
     * 顯示心情觀測儀表板
     */
    public function dashboard()
    {
        if (!isset($_SESSION['user_id'])) {
            $this->redirectToLogin();
            return;
        }

        $user_id = $_SESSION['user_id'];

        // 獲取所有日記數據用於分析
        $diaries = $this->diaryModel->findAllByUserId($user_id);

        logMessage("載入儀表板: 用戶 $user_id, 日記總數 " . count($diaries), 'INFO');

        $pageTitle = '心情觀測儀表板';
        include BASE_PATH . '/app/views/dashboard/index.php';
    }

    /**
     * API: 生成預覽圖和提示詞
     */
    public function generatePreview()
    {
        // 設定 JSON 回應標頭
        header('Content-Type: application/json');

        try {
            // 檢查使用者是否已登入
            if (!isset($_SESSION['user_id'])) {
                echo json_encode([
                    'success' => false,
                    'error' => '請先登入'
                ]);
                return;
            }

            // 獲取 POST 資料
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input) {
                // 如果 JSON 解析失敗，嘗試從 $_POST 獲取
                $input = $_POST;
            }

            if (!$input) {
                echo json_encode([
                    'success' => false,
                    'error' => '無效的請求資料'
                ]);
                return;
            }

            // 驗證必要欄位
            $content = trim($input['content'] ?? '');
            $style = $input['style'] ?? 'digital-modern';
            $emoji = $input['emoji'] ?? '😊';

            if (empty($content)) {
                echo json_encode([
                    'success' => false,
                    'error' => '日記內容不能為空'
                ]);
                return;
            }

            // 記錄調試信息
            logMessage("開始生成預覽 - 內容長度: " . strlen($content) . ", 風格: $style, 表情: $emoji", 'INFO');

            // 組合提示詞要素
            $data = [
                'content' => $content,
                'style' => $style,
                'emoji' => $emoji
            ];

            // 記錄原始風格選擇（用於隨機風格追蹤）
            $originalStyle = $style;

            // 1. 生成提示詞 (Gemini 優先)
            logMessage("開始生成提示詞", 'INFO');
            $prompt = $this->generatePromptWithGeminiFirst($data);

            if (empty($prompt)) {
                throw new Exception('提示詞生成失敗');
            }

            // 2. 生成圖片 (Gemini 優先)
            logMessage("開始生成圖片，提示詞長度: " . strlen($prompt), 'INFO');
            $imageUrl = $this->generateImageWithGeminiFirst($prompt, $style);

            // 3. 生成文字註解 (Gemini 優先)
            logMessage("開始生成文字註解", 'INFO');
            $annotation = $this->generateQuoteWithGeminiFirst([
                'content' => $content,
                'emoji' => $emoji
            ]);

            if (empty($annotation)) {
                $annotation = '今天是美好的一天。'; // 備用註解
            }

            // 準備回應
            $response = [
                'success' => true,
                'prompt' => $prompt,
                'imageUrl' => $imageUrl,
                'annotation' => $annotation,
                'fallback' => !$imageUrl, // 標記是否使用了佔位圖
                'selectedStyle' => $style,
                'originalStyle' => $style
            ];

            logMessage("預覽生成完成: " . json_encode($response, JSON_UNESCAPED_UNICODE), 'INFO');
            echo json_encode($response);
        } catch (Exception $e) {
            logMessage("生成預覽錯誤: " . $e->getMessage(), 'ERROR');
            logMessage("錯誤堆疊: " . $e->getTraceAsString(), 'ERROR');
            echo json_encode([
                'success' => false,
                'error' => '生成失敗：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 使用 Gemini 優先的圖片生成邏輯
     */
    private function generateImageWithGeminiFirst(string $prompt, string $style): ?string
    {
        $imageUrl = null;

        // 優先嘗試 Vertex AI (Gemini)
        if (!empty($this->geminiImageAdapter)) {
            try {
                logMessage("嘗試使用 Vertex AI 生成圖片", 'INFO');
                $imageUrl = $this->geminiImageAdapter->generateImageWithRetry($prompt);
                if ($imageUrl) {
                    logMessage("Vertex AI 圖片生成成功", 'INFO');
                    return $imageUrl;
                }
            } catch (Exception $e) {
                logMessage("Vertex AI 圖片生成失敗: " . $e->getMessage(), 'ERROR');
            }
        } else {
            logMessage("Vertex AI 未初始化，跳過", 'INFO');
        }

        // StabilityAI 已移除，無其他備用方案

        // 如果都失敗，返回 null
        logMessage("所有圖片生成服務都失敗", 'ERROR');
        return null;
    }

    /**
     * 使用 Gemini 優先的提示詞生成邏輯
     */
    private function generatePromptWithGeminiFirst(array $data): ?string
    {
        // 優先嘗試 Gemini Text Adapter
        if (!empty($this->geminiTextAdapter)) {
            try {
                logMessage("嘗試使用 Gemini 生成提示詞", 'INFO');
                $prompt = $this->geminiTextAdapter->generateImagePrompt($data);
                if (!empty($prompt)) {
                    logMessage("Gemini 提示詞生成成功", 'INFO');
                    return $prompt;
                }
            } catch (Exception $e) {
                logMessage("Gemini 提示詞生成失敗: " . $e->getMessage(), 'ERROR');
            }
        } else {
            logMessage("Gemini Text Adapter 未初始化，跳過", 'INFO');
        }

        // 回退到 Perplexity
        try {
            logMessage("回退到 Perplexity 生成提示詞", 'INFO');
            $prompt = $this->perplexityAdapter->generatePrompt($data);
            if (!empty($prompt)) {
                logMessage("Perplexity 提示詞生成成功", 'INFO');
                return $prompt;
            }
        } catch (Exception $e) {
            logMessage("Perplexity 提示詞生成失敗: " . $e->getMessage(), 'ERROR');
        }

        logMessage("所有提示詞生成服務都失敗", 'ERROR');
        return null;
    }

    /**
     * 使用 Gemini 優先的文字註解生成邏輯
     */
    private function generateQuoteWithGeminiFirst(array $data): ?string
    {
        // 優先嘗試 Gemini Text Adapter
        if (!empty($this->geminiTextAdapter)) {
            try {
                logMessage("嘗試使用 Gemini 生成文字註解", 'INFO');
                $quote = $this->geminiTextAdapter->generateQuote($data);
                if (!empty($quote)) {
                    logMessage("Gemini 文字註解生成成功", 'INFO');
                    return $quote;
                }
            } catch (Exception $e) {
                logMessage("Gemini 文字註解生成失敗: " . $e->getMessage(), 'ERROR');
            }
        } else {
            logMessage("Gemini Text Adapter 未初始化，跳過", 'INFO');
        }

        // 回退到 Perplexity
        try {
            logMessage("回退到 Perplexity 生成文字註解", 'INFO');
            $quote = $this->perplexityAdapter->generateQuote($data);
            if (!empty($quote)) {
                logMessage("Perplexity 文字註解生成成功", 'INFO');
                return $quote;
            }
        } catch (Exception $e) {
            logMessage("Perplexity 文字註解生成失敗: " . $e->getMessage(), 'ERROR');
        }

        logMessage("所有文字註解生成服務都失敗", 'ERROR');
        return null;
    }

    /**
     * 儲存日記 (更新版本，支援 AI 生成內容)
     */
    public function store()
    {
        // 檢查使用者是否已登入
        if (!isset($_SESSION['user_id'])) {
            $this->redirectToLogin();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=diary_create');
            exit;
        }

        $user_id = $_SESSION['user_id'];

        // 檢查用戶是否在資料庫中存在
        try {
            $db = getDbConnection();
            $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if (!$user) {
                logMessage("用戶 ID $user_id 在資料庫中不存在", 'ERROR');
                $this->showError('用戶驗證失敗，請重新登入');
                return;
            }
        } catch (Exception $e) {
            logMessage("檢查用戶存在性時出錯: " . $e->getMessage(), 'ERROR');
            $this->showError('系統錯誤，請稍後再試');
            return;
        }

        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $mood = $_POST['mood'] ?? '';
        $diary_date = $_POST['diary_date'] ?? date('Y-m-d');

        // AI 生成的內容
        $generated_image_id = $_POST['generated_image_id'] ?? null;
        $generated_quote = $_POST['generated_quote'] ?? null;
        $image_prompt = $_POST['image_prompt'] ?? null;

        // 處理圖片路徑
        $generated_image_url = null;
        if (!empty($generated_image_id)) {
            // 構建完整的圖片路徑
            $generated_image_url = 'storage/generated_images/' . $generated_image_id;
        }

        // 基本驗證
        if (empty($content)) {
            $this->showError('日記內容不能為空！');
            return;
        }

        if (empty($title)) {
            $title = '無標題日記 - ' . date('Y-m-d', strtotime($diary_date));
        }

        // 記錄調試信息
        logMessage("開始儲存日記 - 用戶: $user_id, 標題: $title", 'INFO');

        try {
            // 建立日記 - 修正參數名稱對應資料庫欄位
            $diary_id = $this->diaryModel->create(
                $user_id,
                $title,
                $content,
                $mood,
                $diary_date,
                $generated_quote,    // 對應 ai_generated_text
                $generated_image_url // 對應 image_path
            );

            if ($diary_id) {
                // 記錄日誌
                logMessage("用戶 {$user_id} 成功建立日記 {$diary_id}", 'INFO');

                // 重定向到日記詳情頁面
                header('Location: index.php?action=diary_detail&id=' . $diary_id);
                exit;
            } else {
                logMessage("日記建立失敗 - 未知錯誤", 'ERROR');
                $this->showError('建立日記失敗，請稍後再試');
            }
        } catch (Exception $e) {
            logMessage("建立日記失敗: " . $e->getMessage(), 'ERROR');
            logMessage("錯誤堆疊: " . $e->getTraceAsString(), 'ERROR');
            $this->showError('系統錯誤，請稍後再試：' . $e->getMessage());
        }
    }

    /**
     * 刪除日記
     */
    public function delete()
    {
        // 檢查使用者是否已登入
        if (!isset($_SESSION['user_id'])) {
            $this->redirectToLogin();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=home');
            exit;
        }

        // 驗證 CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $this->showError('無效的請求token');
            return;
        }

        $user_id = $_SESSION['user_id'];
        $diary_id = $_POST['diary_id'] ?? null;

        if (!$diary_id) {
            $this->showError('無效的日記ID');
            return;
        }

        try {
            // 先獲取日記信息，檢查權限並獲取圖片路徑用於刪除
            $diary = $this->diaryModel->findById($diary_id, $user_id);

            if (!$diary) {
                $this->showError('找不到該日記或您沒有權限刪除');
                return;
            }

            // 刪除關聯的圖片檔案
            if (!empty($diary['image_path'])) {
                $imagePath = Diary::getActualImagePath($diary['image_path']);
                if ($imagePath) {
                    if (Diary::deleteImageFile($diary['image_path'])) {
                        logMessage("已成功刪除圖片檔案: {$imagePath}", 'INFO');
                    } else {
                        logMessage("無法刪除圖片檔案: {$imagePath}", 'ERROR');
                    }
                } else {
                    logMessage("圖片檔案不存在，無需刪除: {$diary['image_path']}", 'INFO');
                }
            }

            // 從資料庫刪除日記
            $success = $this->diaryModel->delete($diary_id, $user_id);

            if ($success) {
                logMessage("用戶 {$user_id} 成功刪除日記 {$diary_id}", 'INFO');
                header('Location: index.php?action=home&message=diary_deleted');
                exit;
            } else {
                $this->showError('刪除日記失敗，請稍後再試');
            }
        } catch (Exception $e) {
            logMessage("刪除日記失敗: " . $e->getMessage(), 'ERROR');
            $this->showError('系統錯誤，請稍後再試：' . $e->getMessage());
        }
    }

    /**
     * 顯示指定日期的所有日記
     */
    public function showByDate()
    {
        // 支援訪客預覽：若未登入，使用示範用戶來顯示該日期的日記列表
        $is_guest_view = false;
        if (!isset($_SESSION['user_id'])) {
            $is_guest_view = true;
            $user_id = defined('PUBLIC_DEMO_USER_ID') ? constant('PUBLIC_DEMO_USER_ID') : 1;
        } else {
            $user_id = $_SESSION['user_id'];
        }
        $date = $_GET['date'] ?? null;

        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $_SESSION['error'] = '無效的日期格式';
            header('Location: index.php?action=home');
            exit;
        }

        $diaries = $this->diaryModel->getDiariesByDate($user_id, $date);

        // 如果只有一篇日記，直接跳轉到詳情頁
        if (count($diaries) === 1) {
            header('Location: index.php?action=diary_detail&id=' . $diaries[0]['id']);
            exit;
        }

        // 如果沒有日記，跳轉到首頁
        if (count($diaries) === 0) {
            $_SESSION['error'] = '該日期沒有日記';
            header('Location: index.php?action=home');
            exit;
        }

        // 多篇日記，顯示列表頁面
        $title = '日記列表 - ' . date('Y年m月d日', strtotime($date));
        extract(['date' => $date, 'diaries' => $diaries, 'title' => $title]);
        include BASE_PATH . '/app/views/diary/date_list.php';
    }
}
