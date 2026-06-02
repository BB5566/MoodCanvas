<?php
// app/controllers/AIController.php
// Pioneer AI integration - simplified direct API calls

namespace App\Controllers;

use Exception;

class AIController
{
    private $pioneerApiKey;
    private $replicateApiKey;
    private $pioneerBaseUrl = 'https://api.pioneer.ai/v1';
    private $replicateBaseUrl = 'https://api.replicate.com/v1';

    public function __construct()
    {
        // Ensure user is logged in
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => '未經授權的存取']);
            exit;
        }
        
        // Load Pioneer API key from environment
        $this->pioneerApiKey = getenv('PIONEER_API_KEY');
        if (empty($this->pioneerApiKey)) {
            throw new Exception('PIONEER_API_KEY not configured in environment');
        }
        
        // Load Replicate API key from environment
        $this->replicateApiKey = getenv('REPLICATE_API_KEY');
        if (empty($this->replicateApiKey)) {
            throw new Exception('REPLICATE_API_KEY not configured in environment');
        }
    }

    /**
     * Generate an image based on diary content using Pioneer vision model.
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

        $requestHash = md5($baseText . $style . $mood);
        $sessionKey = 'last_image_request_' . $requestHash;
        if (isset($_SESSION[$sessionKey]) && (time() - $_SESSION[$sessionKey]) < 30) {
            echo json_encode(['success' => false, 'message' => '請稍等片刻再生成新圖片']);
            return;
        }
        $_SESSION[$sessionKey] = time();

        try {
            $optimizedPrompt = $this->getOptimizedImagePrompt($baseText, $style, $mood);
            
            // Use Pioneer's vision model to generate image description
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

    /**
     * Optimize image prompt using Pioneer chat model
     */
    private function getOptimizedImagePrompt(string $baseText, string $style, string $mood): string
    {
        $systemPrompt = "You are an expert visual artist prompt engineer. Generate a concise, vivid image generation prompt in English based on the user's diary content. The prompt should be detailed, evocative, and suitable for image generation models.";
        
        $userPrompt = "Create an image prompt for a diary entry with:\nContent: {$baseText}\nStyle: {$style}\nMood: {$mood}\n\nGenerate only the image prompt, no explanations.";

        try {
            $optimizedPrompt = $this->callPioneerChat($userPrompt, $systemPrompt);
            if (empty($optimizedPrompt)) {
                throw new Exception("Empty prompt generated");
            }
            return trim($optimizedPrompt);
        } catch (Exception $e) {
            error_log("Prompt optimization failed: " . $e->getMessage());
            // Fallback: use raw content as prompt
            return $baseText . " in " . $style . " style";
        }
    }

    /**
     * Generate image using Replicate's google/imagen-4
     */
    private function generateImageFromPrompt(string $prompt, string $style): array
    {
        try {
            // Call Replicate google/imagen-4 API
            $predictionId = $this->callReplicateImageGeneration($prompt, $style);
            
            // Poll for completion (timeout: 60 seconds)
            $maxAttempts = 30;  // 30 attempts × 2 seconds = 60 seconds
            $imageUrl = null;
            
            for ($i = 0; $i < $maxAttempts; $i++) {
                sleep(2);  // Wait 2 seconds between polls
                
                $result = $this->getReplicatePredictionStatus($predictionId);
                $status = $result['status'] ?? null;
                
                if ($status === 'succeeded') {
                    $imageUrl = $result['output'];
                    break;
                } elseif ($status === 'failed') {
                    throw new Exception('Replicate image generation failed: ' . ($result['error'] ?? 'Unknown error'));
                }
            }
            
            if (!$imageUrl) {
                throw new Exception('Image generation timed out after 60 seconds');
            }
            
            // Download the image and save locally
            $imagePath = $this->downloadAndSaveImage($imageUrl, $prompt, $style);
            
            return ['imageUrl' => $imagePath, 'generatedBy' => 'Replicate google/imagen-4'];
            
        } catch (Exception $e) {
            error_log("Replicate image generation error: " . $e->getMessage());
            throw new Exception("Image generation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Call Replicate google/imagen-4 API to start image generation
     */
    private function callReplicateImageGeneration(string $prompt, string $style): string
    {
        $url = $this->replicateBaseUrl . '/models/google/imagen-4/predictions';
        
        $payload = json_encode([
            'input' => [
                'prompt' => $prompt,
                'aspect_ratio' => '1:1',
                'image_size' => '1K',
                'output_format' => 'jpg',
                'safety_filter_level' => 'block_only_high'
            ]
        ]);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Token ' . $this->replicateApiKey
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception("cURL error: " . $curlError);
        }
        
        if ($httpCode !== 201 && $httpCode !== 200) {
            error_log("Replicate API error (HTTP {$httpCode}): " . $response);
            throw new Exception("Replicate API error: HTTP {$httpCode}");
        }
        
        $result = json_decode($response, true);
        if (!isset($result['id'])) {
            error_log("Invalid Replicate response: " . $response);
            throw new Exception("Invalid response from Replicate API");
        }
        
        return $result['id'];
    }
    
    /**
     * Get Replicate prediction status
     */
    private function getReplicatePredictionStatus(string $predictionId): array
    {
        $url = $this->replicateBaseUrl . '/predictions/' . $predictionId;
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Token ' . $this->replicateApiKey
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Failed to get prediction status: HTTP {$httpCode}");
        }
        
        $result = json_decode($response, true);
        return [
            'status' => $result['status'] ?? 'unknown',
            'output' => $result['output'] ?? null,
            'error' => $result['error'] ?? null
        ];
    }
    
    /**
     * Download image from URL and save locally
     */
    private function downloadAndSaveImage(string $imageUrl, string $prompt, string $style): string
    {
        // Create storage directory
        $imageDir = '/var/www/html/public/storage/generated_images';
        if (!is_dir($imageDir)) {
            mkdir($imageDir, 0755, true);
        }
        
        // Generate unique filename
        $imageId = uniqid('mood_') . '.jpg';
        $imagePath = $imageDir . '/' . $imageId;
        
        // Download image
        $ch = curl_init($imageUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || empty($imageData)) {
            throw new Exception("Failed to download image from Replicate: HTTP {$httpCode}");
        }
        
        // Save to filesystem
        file_put_contents($imagePath, $imageData);
        
        // Save metadata
        $metadataPath = str_replace('.jpg', '.json', $imagePath);
        file_put_contents($metadataPath, json_encode([
            'prompt' => $prompt,
            'style' => $style,
            'generated_by' => 'Replicate google/imagen-4',
            'timestamp' => time(),
            'original_url' => $imageUrl
        ]));
        
        // Return URL path for web access
        return '/mood/public/storage/generated_images/' . $imageId;
    }

    /**
     * Generate text (quote/analysis) using Pioneer
     */
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
            $systemPrompt = "You are a thoughtful and empathetic assistant who creates inspiring quotes and reflections based on diary content. Respond in Traditional Chinese.";
            $userPrompt = "Create an inspiring and thoughtful reflection based on this diary entry:\nMood: {$mood}\nContent: {$content}\n\nProvide only the reflection, no explanations.";
            
            $generatedText = $this->callPioneerChat($userPrompt, $systemPrompt);

            echo json_encode(['success' => true, 'quote' => trim($generatedText)]);
        } catch (Exception $e) {
            error_log("AI Text Generation Failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'AI 文字生成失敗: ' . $e->getMessage()]);
        }
    }

    /**
     * Generate dashboard insight using Pioneer
     */
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

            $userPrompt = "請扮演一位專業且富有同理心的心理諮商師或心靈導師。以下是一位使用者最近的日記，記錄了他的心情和想法：\n\n" .
                $diaryText .
                "\n\n請根據以上所有日記內容，提供一段溫暖、正面且富有洞察力的分析與總結。你的分析應該：\n" .
                "1. 綜合評估使用者近期的整體情緒趨勢。\n" .
                "2. 指出任何可能的情緒波動模式或重複出現的主題。\n" .
                "3. 根據內容給予一些具體、正面且可行的心理學建議，例如正念練習、感恩練習或認知行為療法(CBT)的簡單技巧。\n" .
                "4. 語言風格需溫暖、鼓勵，像朋友一樣，但要保持專業性。\n" .
                "5. 最後用一句鼓舞人心的話作結。\n\n請將你的分析總結在 200-300 字之間。";

            $systemPrompt = "You are a compassionate psychological counselor and spiritual mentor. Provide warm, insightful analysis in Traditional Chinese.";

            $insight = $this->callPioneerChat($userPrompt, $systemPrompt);

            echo json_encode(['success' => true, 'insight' => trim($insight)]);
        } catch (Exception $e) {
            error_log("AI Dashboard Insight Failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'AI 洞察生成失敗，請稍後再試。']);
        }
    }

    /**
     * Direct Pioneer API call using curl
     */
    private function callPioneerChat(string $userMessage, string $systemMessage = ""): string
    {
        $url = $this->pioneerBaseUrl . '/chat/completions';
        
        $messages = [];
        if (!empty($systemMessage)) {
            $messages[] = ['role' => 'system', 'content' => $systemMessage];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $payload = json_encode([
            'model' => 'claude-haiku-4-5',  // Use Claude Haiku (only Claude model available on Pioneer)
            'messages' => $messages,
            'max_tokens' => 1000,
            'temperature' => 0.7
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->pioneerApiKey
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("cURL error: " . $curlError);
        }

        if ($httpCode !== 200) {
            error_log("Pioneer API error (HTTP {$httpCode}): " . $response);
            throw new Exception("Pioneer API error: HTTP {$httpCode}");
        }

        $result = json_decode($response, true);
        if (!isset($result['choices'][0]['message']['content'])) {
            error_log("Invalid Pioneer response: " . $response);
            throw new Exception("Invalid response from Pioneer API");
        }

        return $result['choices'][0]['message']['content'];
    }
}
