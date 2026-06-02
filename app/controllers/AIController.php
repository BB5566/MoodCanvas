<?php
// app/controllers/AIController.php

namespace App\Controllers;

use App\Models\PerplexityAdapter;
use App\Models\GeminiTextAdapter;
use App\Models\GeminiImageAdapter;
use Exception;

class AIController
{

    private $perplexityAdapter;
    private $geminiTextAdapter;
    private $geminiImageAdapter;

    public function __construct()
    {
        // Ensure user is logged in
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => '未經授權的存取']);
            exit;
        }
        
        // Instantiate all AI models
        $this->perplexityAdapter = new PerplexityAdapter();

        // Initialize Gemini Text Adapter
        try {
            $isGeminiTextEnabled = getenv('GEMINI_TEXT_MODEL') || (defined('GEMINI_ENABLED') && GEMINI_ENABLED);
            if ($isGeminiTextEnabled) {
                $this->geminiTextAdapter = new GeminiTextAdapter();
            }
        } catch (Exception $e) {
            error_log('GeminiTextAdapter init error: ' . $e->getMessage());
            $this->geminiTextAdapter = null;
        }

        // Initialize Gemini Image Adapter (Vertex AI)
        try {
            $isVertexAiEnabled = getenv('GCP_PROJECT_ID') || (defined('VERTEX_AI_ENABLED') && VERTEX_AI_ENABLED);
            if ($isVertexAiEnabled) {
                $this->geminiImageAdapter = new GeminiImageAdapter();
            }
        } catch (Exception $e) {
            error_log('GeminiImageAdapter (Vertex AI) init error: ' . $e->getMessage());
            $this->geminiImageAdapter = null;
        }
    }

    /**
     * Generate an image based on diary content.
     */
    public function generateImage()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => '僅接受 POST 請求']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $baseText = $data['content'] ?? '';
        $style = $data['style'] ?? 'default';
        $mood = $data['mood'] ?? '😊';

        if (empty($baseText)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '缺少日記內容或提示詞']);
            return;
        }

        // ⚡ P1 優化：改進 rate limit（跨 session，基于 content hash）
        $contentHash = \Cache::hashContent($baseText, $style, $mood);
        $rateLimitKey = sprintf(\Cache::KEY_RATE_LIMIT, $contentHash);
        
        $lastTime = \Cache::get($rateLimitKey);
        if ($lastTime && (time() - $lastTime) < 30) {
            echo json_encode(['success' => false, 'message' => '請稍等片刻再生成新圖片']);
            return;
        }
        \Cache::set($rateLimitKey, time(), \Cache::TTL_RATE_LIMIT);

        try {
            $optimizedPrompt = $this->getOptimizedImagePrompt($baseText, $style, $mood);
            
            $generationResult = $this->generateImageFromPrompt($optimizedPrompt, $style);
            $imageUrl = $generationResult['imageUrl'];
            $generatedBy = $generationResult['generatedBy'];

            echo json_encode([
                'success' => true,
                'imageUrl' => $imageUrl,
                'prompt' => $optimizedPrompt,
                'imageId' => basename($imageUrl, '.png'),
                'generatedBy' => $generatedBy
            ]);

        } catch (Exception $e) {
            error_log("AI Image Generation Failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'AI 圖片生成失敗: ' . $e->getMessage()]);
        }
    }
    private function getOptimizedImagePrompt(string $baseText, string $style, string $mood): string
    {
        // 準備 prompt 數據
        $promptData = ['content' => $baseText, 'style' => $style, 'emoji' => $mood];
        
        // ⚡ P1 優化：快取 prompt 結果（相同內容+風格+心情 = 相同 prompt）
        $promptHash = \Cache::hashContent($baseText, $style, $mood);
        $cacheKey = sprintf(\Cache::KEY_PROMPT, $promptHash);
        
        $cachedPrompt = \Cache::get($cacheKey);
        if ($cachedPrompt !== null) {
            error_log("🔥 Prompt cache HIT: $promptHash");
            return $cachedPrompt;
        }

        $optimizedPrompt = null;
        $errorMessages = [];

        try {
            // Primary provider: Gemini
            if (!empty($this->geminiTextAdapter)) {
                $optimizedPrompt = $this->geminiTextAdapter->generateImagePrompt($promptData);
            } else {
                throw new Exception("Gemini TextAdapter not available");
            }
        } catch (Exception $e) {
            $errorMessages[] = "Gemini failed: " . $e->getMessage();
            // Fallback provider: Perplexity
            try {
                $optimizedPrompt = $this->perplexityAdapter->generateImagePrompt($promptData);
            } catch (Exception $pe) {
                $errorMessages[] = "Perplexity fallback also failed: " . $pe->getMessage();
            }
        }

        if (empty($optimizedPrompt)) {
            // If both failed, throw an exception with detailed internal error messages.
            $combinedErrors = implode(" | ", $errorMessages);
            throw new Exception("AI prompt optimization failed. Details: [ " . $combinedErrors . " ]");
        }
        
        // ⚡ 快取結果（1 天）
        \Cache::set($cacheKey, $optimizedPrompt, \Cache::TTL_PROMPT);
        error_log("💾 Prompt cache SET: $promptHash");
        
        return $optimizedPrompt;
    }

    private function generateImageFromPrompt(string $prompt, string $style): array
    {
        $imageUrl = null;
        $generatedBy = 'Unknown';

        if (!empty($this->geminiImageAdapter)) {
            try {
                error_log("Attempting image generation with Vertex AI.");
                $imageUrl = $this->geminiImageAdapter->generateImageWithRetry($prompt);
                if ($imageUrl) {
                    $generatedBy = 'Vertex AI';
                }
            } catch (Exception $e) {
                error_log("Vertex AI image generation failed: " . $e->getMessage());
            }
        }


        if (empty($imageUrl)) {
            throw new Exception("Image generation failed from all providers.");
        }
        
        return ['imageUrl' => $imageUrl, 'generatedBy' => $generatedBy];
    }

    public function generateText()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => '僅接受 POST 請求']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $content = $data['content'] ?? '';
        $mood = $data['mood'] ?? '😊';

        if (empty($content)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '缺少必要的 content 參數']);
            return;
        }

        try {
            $generatedText = null;
            if (!empty($this->geminiTextAdapter)) {
                try {
                    $generatedText = $this->geminiTextAdapter->generateQuote(['content' => $content, 'emoji' => $mood]);
                } catch (Exception $e) {
                    error_log('Gemini text generation failed: ' . $e->getMessage());
                }
            }

            if (empty($generatedText)) {
                error_log("Falling back to Perplexity for text generation.");
                $generatedText = $this->perplexityAdapter->generateQuote(['content' => $content, 'emoji' => $mood]);
            }

            echo json_encode(['success' => true, 'quote' => $generatedText]);
        } catch (Exception $e) {
            error_log("AI Text Generation Failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'AI 文字生成失敗: ' . $e->getMessage()]);
        }
    }

    public function getDashboardInsight()
    {
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
            $diaryText = "";
            foreach ($diaries as $diary) {
                $diaryText .= "日期: " . ($diary['date'] ?? 'N/A') . ", 心情分數: " . ($diary['mood_score'] ?? 'N/A') . ", 內容: " . ($diary['content'] ?? 'N/A') . "\n\n";
            }

            $prompt = "請扮演一位專業且富有同理心的心理諮商師或心靈導師。" .
                "以下是一位使用者最近的日記，記錄了他的心情和想法：\n\n" .
                $diaryText .
                "\n\n請根據以上所有日記內容，提供一段溫暖、正面且富有洞察力的分析與總結。" .
                "你的分析應該：\n" .
                "1. 綜合評估使用者近期的整體情緒趨勢。\n" .
                "2. 指出任何可能的情緒波動模式或重複出現的主題。\n" .
                "3. 根據內容給予一些具體、正面且可行的心理學建議，例如正念練習、感恩練習或認知行為療法(CBT)的簡單技巧。\n" .
                "4. 語言風格需溫暖、鼓勵，像朋友一樣，但要保持專業性。\n" .
                "5. 最後用一句鼓舞人心的話作結。\n" .
                "請將你的分析總結在 200-300 字之間。";

            $insight = $this->generateDashboardInsightText($prompt);

            echo json_encode(['success' => true, 'insight' => $insight]);
        } catch (Exception $e) {
            error_log("AI Dashboard Insight Failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'AI 洞察生成失敗，請稍後再試。']);
        }
    }

    private function generateDashboardInsightText(string $prompt): string
    {
        $insight = null;
        $errorMessages = [];

        try {
            if (!empty($this->geminiTextAdapter)) {
                $insight = $this->geminiTextAdapter->generateQuote(['content' => $prompt]);
            } else {
                throw new Exception("Gemini TextAdapter not available");
            }
        } catch (Exception $e) {
            $errorMessages[] = "Gemini failed: " . $e->getMessage();
            try {
                $insight = $this->perplexityAdapter->generateQuote(['content' => $prompt]);
            } catch (Exception $pe) {
                $errorMessages[] = "Perplexity fallback also failed: " . $pe->getMessage();
            }
        }

        if (empty($insight)) {
            $combinedErrors = implode(" | ", $errorMessages);
            throw new Exception("AI dashboard insight generation failed. Details: [ " . $combinedErrors . " ]");
        }

        return $insight;
    }
}
