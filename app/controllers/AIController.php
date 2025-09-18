<?php
// app/controllers/AIController.php

namespace App\Controllers;

use App\Models\PerplexityAdapter;
use App\Models\StabilityAI;
use App\Models\GeminiTextAdapter;
use App\Models\GeminiImageAdapter;
use Exception;

class AIController {

    private $perplexityAdapter;
    private $stabilityAI;
    private $geminiImageAdapter;
    private $geminiTextAdapter;

    public function __construct() {
        // 確保使用者已登入，防止未經授權的 API 呼叫
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            http_response_code(403); // Forbidden
            echo json_encode(['success' => false, 'message' => '未經授權的存取']);
            exit;
        }
        // Instantiate the necessary models
        $this->perplexityAdapter = new PerplexityAdapter();
        $this->stabilityAI = new StabilityAI();

        // Optionally initialize Gemini text adapter
        try {
            if (defined('GEMINI_ENABLED') && GEMINI_ENABLED) {
                $this->geminiTextAdapter = new GeminiTextAdapter();
            } else {
                $gTextModel = getenv('GEMINI_TEXT_MODEL') ?: (defined('GEMINI_TEXT_MODEL') ? constant('GEMINI_TEXT_MODEL') : null);
                if (!empty($gTextModel)) {
                    $this->geminiTextAdapter = new GeminiTextAdapter();
                }
            }
        } catch (Exception $e) {
            error_log('GeminiTextAdapter init error: ' . $e->getMessage());
            $this->geminiTextAdapter = null;
        }

        // Optionally initialize Gemini image adapter if enabled or model configured
        try {
            if (defined('GEMINI_ENABLED') && GEMINI_ENABLED) {
                $this->geminiImageAdapter = new GeminiImageAdapter();
            } else {
                // If a Gemini image model is explicitly set in env, still try to initialize
                $gModel = getenv('GEMINI_IMAGE_MODEL') ?: (defined('GEMINI_IMAGE_MODEL') ? constant('GEMINI_IMAGE_MODEL') : null);
                if (!empty($gModel)) {
                    $this->geminiImageAdapter = new GeminiImageAdapter();
                }
            }
        } catch (Exception $e) {
            // Initialization errors should not break the controller; log and continue with StabilityAI
            error_log('GeminiImageAdapter init error: ' . $e->getMessage());
            $this->geminiImageAdapter = null;
        }
    }

    /**
     * 根據文字提示生成圖片，並在生成前優化提示詞
     */
    public function generateImage() {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); // Method Not Allowed
            echo json_encode(['success' => false, 'message' => '僅接受 POST 請求']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $userInput = $data['prompt'] ?? ''; // 舊版相容性：直接提示詞
        $diaryContent = $data['content'] ?? ''; // 新版：完整的日記內容
        $style = $data['style'] ?? 'digital-art'; // 藝術風格
        $mood = $data['mood'] ?? '😊'; // 心情 emoji

        // 優先使用日記內容，如果沒有則使用直接提示詞
        $baseText = !empty($diaryContent) ? $diaryContent : $userInput;

        if (empty($baseText)) {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => '缺少日記內容或提示詞']);
            return;
        }

        // 檢查是否有重複請求（使用 session 來避免短時間內重複生成）
        $requestHash = md5($baseText . $style . $mood);
        $sessionKey = 'last_image_request_' . $requestHash;
        $now = time();
        
        if (isset($_SESSION[$sessionKey]) && ($now - $_SESSION[$sessionKey]) < 30) {
            // 30秒內的相同請求視為重複
            echo json_encode(['success' => false, 'message' => '請稍等片刻再生成新圖片']);
            return;
        }
        
        // 記錄當前請求時間
        $_SESSION[$sessionKey] = $now;

        try {
            // 步驟 1: 使用 PerplexityAdapter 將日記內容優化為專業的英文繪圖提示詞
            $optimizedPrompt = $this->perplexityAdapter->generateImagePrompt([
                'content' => $baseText,
                'style' => $style,
                'emoji' => $mood
            ]);

            // 步驟 2: 使用優化後的提示詞和指定的風格來生成圖片
            $options = ['style_preset' => $this->stabilityAI->getStylePreset($style)];

            // Try GeminiImageAdapter first if available
            $imageUrl = null;
            if (!empty($this->geminiImageAdapter)) {
                try {
                    $gOptions = [
                        'thinkingBudget' => 0, // disable thinking to save tokens
                        'width' => 512,
                        'height' => 512,
                        'samples' => 1
                    ];
                    $imageUrl = $this->geminiImageAdapter->generateImageWithRetry($optimizedPrompt, $gOptions);
                } catch (Exception $e) {
                    error_log('Gemini generation failed: ' . $e->getMessage());
                    $imageUrl = null;
                }
            }

            // Fallback to StabilityAI if Gemini not available or generation failed
            if (empty($imageUrl)) {
                $imageUrl = $this->stabilityAI->generateImageWithRetry($optimizedPrompt, $options);
            }

            if ($imageUrl) {
                // 返回成功訊息、圖片 URL 和使用的提示詞，方便偵錯和顯示
                echo json_encode([
                    'success' => true, 
                    'imageUrl' => $imageUrl,
                    'prompt' => $optimizedPrompt,
                    'imageId' => basename($imageUrl, '.png') // 從 URL 中提取圖片 ID
                ]);
            } else {
                throw new Exception("StabilityAI 服務未返回有效的圖片路徑。");
            }
        } catch (Exception $e) {
            error_log("AI 圖片生成失敗: " . $e->getMessage());
            http_response_code(500); // Internal Server Error
            echo json_encode(['success' => false, 'message' => 'AI 圖片生成失敗: ' . $e->getMessage()]);
        }
    }

    /**
     * 根據情緒或文字生成詩句/名言 (using PerplexityAdapter)
     */
    public function generateText() {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => '僅接受 POST 請求']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $content = $data['content'] ?? '';
        $mood = $data['mood'] ?? '😊'; // 修正參數名稱

        if (empty($content)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '缺少必要的 content 參數']);
            return;
        }

        try {
            // Prefer GeminiTextAdapter if available, otherwise use PerplexityAdapter
            $generatedText = null;
            if (!empty($this->geminiTextAdapter)) {
                try {
                    $generatedText = $this->geminiTextAdapter->generateQuote(['content' => $content, 'emoji' => $mood, 'thinking_budget' => 0]);
                } catch (Exception $e) {
                    error_log('Gemini text generation failed: ' . $e->getMessage());
                    $generatedText = null;
                }
            }

            if (empty($generatedText)) {
                $generatedText = $this->perplexityAdapter->generateQuote(['content' => $content, 'emoji' => $mood]);
            }

            echo json_encode(['success' => true, 'quote' => $generatedText]); // 修正回應欄位名稱
        } catch (Exception $e) {
            error_log("AI Text Generation Failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'AI 文字生成失敗: ' . $e->getMessage()]);
        }
    }

    /**
     * 分析日記資料並提供 AI 洞察
     */
    public function getDashboardInsight() {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => '僅接受 POST 請求']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $diaries = $data['diaries'] ?? [];

        if (empty($diaries)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '缺少日記資料']);
            return;
        }

        try {
            // 將日記資料格式化為一個連貫的文本
            $diaryText = "";
            foreach ($diaries as $diary) {
                $diaryText .= "日期: " . ($diary['date'] ?? 'N/A') . ", 心情分數: " . ($diary['mood_score'] ?? 'N/A') . ", 內容: " . ($diary['content'] ?? 'N/A') . "\n\n";
            }
            
            // 建立一個複雜的提示，要求 AI 扮演特定角色
            $prompt = "請扮演一位專業且富有同理心的心理諮商師或心靈導師。".
                      "以下是一位使用者最近的日記，記錄了他的心情和想法：\n\n" .
                      $diaryText .
                      "\n\n請根據以上所有日記內容，提供一段溫暖、正面且富有洞察力的分析與總結。".
                      "你的分析應該：\n".
                      "1. 綜合評估使用者近期的整體情緒趨勢。\n".
                      "2. 指出任何可能的情緒波動模式或重複出現的主題。\n".
                      "3. 根據內容給予一些具體、正面且可行的心理學建議，例如正念練習、感恩練習或認知行為療法(CBT)的簡單技巧。\n".
                      "4. 語言風格需溫暖、鼓勵，像朋友一樣，但要保持專業性。\n".
                      "5. 最後用一句鼓舞人心的話作結。\n".
                      "請將你的分析總結在 200-300 字之間。";

            // 使用 PerplexityAdapter 產生分析
            $insight = $this->perplexityAdapter->generateQuote(['content' => $prompt]);

            echo json_encode(['success' => true, 'insight' => $insight]);

        } catch (Exception $e) {
            error_log("AI Dashboard Insight Failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'AI 洞察生成失敗，請稍後再試。']);
        }
    }
}
?>
