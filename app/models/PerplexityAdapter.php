<?php

namespace App\Models;

use Exception;

class PerplexityAdapter
{

    private $apiKey;
    private $baseUrl;
    private $styleKeywords;

    public function __construct()
    {
        $this->apiKey = defined('PERPLEXITY_API_KEY') ? PERPLEXITY_API_KEY : null;
        $this->baseUrl = 'https://api.perplexity.ai/chat/completions';
        $this->initializeStyleKeywords();

        if (empty($this->apiKey) || $this->apiKey === 'your_perplexity_api_key_here') {
            error_log("Perplexity API key is not configured correctly.");
        }
    }

    /**
     * 初始化風格關鍵詞知識庫
     */
    private function initializeStyleKeywords()
    {
        $this->styleKeywords = [
            'photographic' => 'photorealistic, realistic, masterpiece, evocative, poetic, 8K, sharp focus, detailed, professional photography',
            'van-gogh' => 'in the style of Vincent van Gogh, expressive brushstrokes, swirling, thick impasto, vibrant colors, post-impressionist, poetic, masterpiece, evocative',
            'monet' => 'in the style of Claude Monet, impressionist, soft light, pastel colors, poetic, masterpiece, evocative',
            'picasso' => 'in the style of Pablo Picasso, abstract, cubism, bold shapes, poetic, masterpiece, evocative',
            'hokusai' => 'in the style of Hokusai, ukiyo-e, Japanese art, woodblock print, poetic, masterpiece, evocative',
            'dali' => 'in the style of Salvador Dalí, surreal, dreamlike, poetic, masterpiece, evocative',
            'kandinsky' => 'in the style of Kandinsky, abstract, vibrant colors, poetic, masterpiece, evocative',
            'pollock' => 'in the style of Jackson Pollock, abstract expressionism, energetic, poetic, masterpiece, evocative',
            'default' => 'high quality, detailed, visually stunning, poetic, evocative, masterpiece'
        ];
    }

    /**
     * 生成圖像提示詞 - 簡化多樣性處理
     */
    public function generateImagePrompt(array $data): string
    {
        if (!$this->apiKey) {
            error_log("Perplexity API key not available, using fallback prompt.");
            return $this->fallbackPrompt($data);
        }

        try {
            $query = $this->buildSimplifiedImagePromptQuery($data);
            $postData = [
                'model' => defined('PERPLEXITY_MODEL') ? PERPLEXITY_MODEL : 'llama-3.1-sonar-large-128k-online',
                'messages' => [
                    ['role' => 'system', 'content' => $this->getSimplifiedSystemPrompt()],
                    ['role' => 'user', 'content' => $query]
                ],
                'max_tokens' => 200,
                'temperature' => 0.7,
                'top_p' => 0.8,
                'stream' => false
            ];

            $response = $this->makeApiCall($postData);
            if ($response) {
                return $this->cleanAndSimplifyPrompt(trim($response, '" \n\r\t\v\x00'));
            } else {
                error_log("Perplexity API call failed, using fallback prompt.");
                return $this->fallbackPrompt($data);
            }
        } catch (Exception $e) {
            error_log("Perplexity API error for image prompt: " . $e->getMessage());
            return $this->fallbackPrompt($data);
        }
    }

    /**
     * 簡化的系統提示詞 - 避免過度複雜化
     */
    private function getSimplifiedSystemPrompt(): string
    {
        return "You are an expert prompt engineer for text-to-image AI. Your task is to convert a user's diary entry into a clear, focused English prompt.

GUIDELINES:
1. Focus on the main subject and activity from the diary
2. Keep the scene simple and clear - avoid multiple people unless explicitly mentioned
3. If a person is mentioned, use neutral descriptors unless specific traits are clearly stated
4. Integrate the artistic style naturally
5. Output a single, comma-separated paragraph
6. No explanations or quotation marks

IMPORTANT: Keep prompts focused and avoid overcomplicating with too many elements.";
    }

    /**
     * 建立簡化的圖片提示詞查詢
     */
    private function buildSimplifiedImagePromptQuery(array $data): string
    {
        $content = $data['content'] ?? 'A peaceful day';
        $style = $data['style'] ?? 'default';
        $keywords = $this->styleKeywords[$style] ?? $this->styleKeywords['default'];

        // 分析內容，提取關鍵信息
        $personContext = $this->extractPersonContext($content);

        return sprintf(
            "Diary Entry: \"%s\"\n\nStyle Keywords: \"%s\"\n\nFocus: Create a simple, clear scene. %s",
            $content,
            $keywords,
            $personContext
        );
    }

    /**
     * 提取人物上下文 - 簡化版
     */
    private function extractPersonContext(string $content): string
    {
        // 檢測明確的人物描述
        if (strpos($content, '媽媽') !== false || strpos($content, '母親') !== false) {
            return "Focus on a mother figure in the scene.";
        }

        if (strpos($content, '爸爸') !== false || strpos($content, '父親') !== false) {
            return "Focus on a father figure in the scene.";
        }

        if (strpos($content, '寶寶') !== false || strpos($content, '嬰兒') !== false) {
            return "Include a baby in the scene.";
        }

        if (strpos($content, '程式設計') !== false || strpos($content, '開發') !== false || strpos($content, '程式') !== false) {
            return "Focus on a developer/programmer in the scene.";
        }

        return "Focus on the main activity described in the diary.";
    }

    /**
     * 清理和簡化提示詞
     */
    private function cleanAndSimplifyPrompt(string $prompt): string
    {
        // 移除過度複雜的描述
        $prompt = preg_replace('/diverse group of[^,]*,?/i', '', $prompt);
        $prompt = preg_replace('/including[^,]*,?/i', '', $prompt);
        $prompt = preg_replace('/various[^,]*,?/i', '', $prompt);

        // 清理多餘空白和逗號
        $prompt = preg_replace('/,\s*,+/', ',', $prompt);
        $prompt = preg_replace('/\s*,\s*/', ', ', $prompt);
        $prompt = trim($prompt, ', ');

        return $prompt;
    }

    /**
     * 生成引言/註解 - 強化版
     */
    public function generateQuote(array $data): string
    {
        if (!$this->apiKey) {
            error_log("Perplexity API key not available, using fallback quote");
            return $this->enhancedFallbackQuote($data);
        }

        try {
            $query = $this->buildEnhancedQuoteQuery($data);
            $postData = [
                'model' => 'llama-3.1-sonar-large-128k-online',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getStrictQuoteSystemPrompt()
                    ],
                    [
                        'role' => 'user',
                        'content' => $query
                    ]
                ],
                'max_tokens' => 100,
                'temperature' => 0.6,
                'top_p' => 0.7,
                'frequency_penalty' => 0.8,
                'presence_penalty' => 0.5,
                'stream' => false
            ];

            $response = $this->makeApiCall($postData);
            if ($response) {
                return $this->cleanQuoteResponse($response);
            } else {
                error_log("Perplexity API call for quote failed, using fallback");
                return $this->enhancedFallbackQuote($data);
            }
        } catch (Exception $e) {
            error_log("Perplexity API error (quote generation): " . $e->getMessage());
            return $this->enhancedFallbackQuote($data);
        }
    }

    /**
     * 嚴格的引言系統提示詞
     */
    private function getStrictQuoteSystemPrompt(): string
    {
        return "你是引言專家。規則：
1. 絕對禁止使用「智慧觀察」風格
2. 只能使用「世界名言」或「電影/書籍語錄」
3. 格式必須是：「引文內容」— 作者名 或 「引文內容」— 《作品名》
4. 直接輸出引言，不要任何解釋";
    }

    /**
     * 建構增強版引言查詢
     */
    private function buildEnhancedQuoteQuery(array $data): string
    {
        $content = $data['content'] ?? '';
        $emoji = $data['emoji'] ?? '😊';

        // 強制指定風格
        $forceStyle = $this->determineQuoteStyle($content);

        return "日記內容：'{$content}'\n情緒：{$emoji}\n\n必須使用：{$forceStyle}\n直接輸出引言：";
    }

    /**
     * 決定引言風格
     */
    private function determineQuoteStyle(string $content): string
    {
        if (
            strpos($content, '媽媽') !== false || strpos($content, '寶寶') !== false ||
            strpos($content, '家庭') !== false || strpos($content, '親子') !== false
        ) {
            return "世界名言 - 關於母愛、家庭、成長的經典語錄";
        }

        if (
            strpos($content, '程式') !== false || strpos($content, '開發') !== false ||
            strpos($content, '學習') !== false || strpos($content, '成長') !== false
        ) {
            return "世界名言 - 關於學習、成長、堅持的名人語錄";
        }

        if (strpos($content, '挑戰') !== false || strpos($content, '困難') !== false) {
            return "世界名言 - 關於克服困難、堅持不懈的勵志語錄";
        }

        return "世界名言 - 人生哲理相關的經典語錄";
    }

    /**
     * 增強版本地備案註解 - 統一前台 emoji
     */
    private function enhancedFallbackQuote(array $data): string
    {
        $content = $data['content'] ?? '';
        $emoji = $data['emoji'] ?? '😊';

        // 根據內容強制選擇合適的世界名言
        if (strpos($content, '媽媽') !== false || strpos($content, '寶寶') !== false) {
            $quotes = [
                "「母愛是世間最偉大的力量。」— 米爾",
                "「家庭是我們最初的學校，母親是我們最初的老師。」— 富蘭克林",
                "「成為母親，是學會把以前不知道自己具備的力量發掘出來。」— 林達·沃爾夫"
            ];
            return $quotes[array_rand($quotes)];
        }

        if (strpos($content, '程式') !== false || strpos($content, '學習') !== false) {
            $quotes = [
                "「學而時習之，不亦說乎。」— 孔子",
                "「知識就是力量。」— 培根",
                "「成功不是終點，失敗不是末日，繼續前進的勇氣才最可貴。」— 邱吉爾"
            ];
            return $quotes[array_rand($quotes)];
        }

        if (strpos($content, '挑戰') !== false || strpos($content, '困難') !== false) {
            $quotes = [
                "「困難像彈簧，你弱它就強。」— 葉挺",
                "「山重水複疑無路，柳暗花明又一村。」— 陸游",
                "「寶劍鋒從磨礪出，梅花香自苦寒來。」— 古詩"
            ];
            return $quotes[array_rand($quotes)];
        }

        // 預設世界名言 - 只包含前台支援的 emoji
        $defaultQuotes = [
            '😊' => "「今天是你餘生的第一天。」— 阿比·霍夫曼",
            '😢' => "「眼淚是靈魂的彩虹。」— 珀西·雪萊",
            '😡' => "「憤怒是懲罰自己的毒藥。」— 佛陀",
            '😍' => "「愛是生命的靈魂。」— 羅曼·羅蘭",
            '😴' => "「休息是為了走更長遠的路。」— 古諺",
            '🤔' => "「思考是人類最大的樂趣。」— 亞里斯多德",
            '😂' => "「笑是兩個人之間最短的距離。」— 維克多·博格",
            '😰' => "「勇氣不是沒有恐懼，而是面對恐懼依然前行。」— 尼爾森·曼德拉",
            '🥰' => "「愛是生命的靈魂。」— 羅曼·羅蘭",
            '🙄' => "「生活就像一杯茶，不會苦一輩子，但總會苦一陣子。」— 佚名"
        ];

        return $defaultQuotes[$emoji] ?? "「生活就像一杯茶，不會苦一輩子，但總會苦一陣子。」— 佚名";
    }

    /**
     * 處理隨機風格選擇
     */
    private function handleRandomStyle(array $data): array
    {
        if (($data['style'] ?? null) === 'random') {
            $availableStyles = [
                'photographic',
                'van-gogh',
                'monet',
                'picasso',
                'hokusai',
                'dali',
                'kandinsky',
                'pollock'
            ];
            $data['style'] = $availableStyles[array_rand($availableStyles)];
            $data['original_style'] = 'random';
            error_log("Random style selected: " . $data['style']);
        }

        return $data;
    }

    /**
     * 本地備案提示詞 - 統一前台 emoji
     */
    private function fallbackPrompt(array $data): string
    {
        $data = $this->handleRandomStyle($data);
        $style = $data['style'];
        $emoji = $data['emoji'] ?? '😊';
        $content = $data['content'] ?? '';

        // 只包含前台支援的 emoji
        $moodMap = [
            '😊' => 'warm golden lighting, uplifting atmosphere, joyful energy',
            '😢' => 'melancholic blue tones, soft shadows, emotional depth',
            '😡' => 'dramatic contrast, intense colors, powerful expression',
            '😍' => 'romantic soft lighting, dreamy atmosphere, loving warmth',
            '😴' => 'peaceful pastels, serene mood, tranquil feeling',
            '🤔' => 'thoughtful composition, balanced lighting, contemplative mood',
            '😂' => 'vibrant energetic colors, dynamic composition, joyful lighting',
            '😰' => 'muted anxious colors, uncertain lighting, tense atmosphere',
            '🥰' => 'warm loving colors, soft romantic lighting, affectionate atmosphere',
            '🙄' => 'ironic detached mood, neutral tones, subtle expression'
        ];

        $styleKeywords = $this->styleKeywords[$style] ?? $this->styleKeywords['default'];
        $mood = $moodMap[$emoji] ?? 'balanced harmonious lighting';

        // 簡化的場景描述
        $sceneDescription = $this->getSimpleSceneDescription($content);

        return "{$sceneDescription}, {$mood}, {$styleKeywords}, masterpiece, high quality, detailed artwork";
    }

    /**
     * 獲取簡單場景描述
     */
    private function getSimpleSceneDescription(string $content): string
    {
        if (strpos($content, '媽媽') !== false && strpos($content, '程式') !== false) {
            return 'mother working on computer with baby nearby';
        }

        if (strpos($content, '程式') !== false) {
            return 'person coding on computer';
        }

        if (strpos($content, '咖啡') !== false) {
            return 'person in cozy cafe setting';
        }

        return 'peaceful everyday scene';
    }

    /**
     * 清理註解回應
     */
    private function cleanQuoteResponse(string $response): string
    {
        $cleaned = preg_replace('/^(以下是|這是|根據)/u', '', $response);
        $cleaned = preg_replace('/^[\d\.\-\*\s]+/u', '', $cleaned);
        $cleaned = trim($cleaned);
        $cleaned = preg_replace('/\[\d+\]/', '', $cleaned);
        return $cleaned;
    }

    /**
     * API 調用方法
     */
    private function makeApiCall(array $postData): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: " . $httpCode . " - " . $response);
        }

        $result = json_decode($response, true);
        if (isset($result['choices'][0]['message']['content'])) {
            return trim($result['choices'][0]['message']['content']);
        }

        return null;
    }

    /**
     * 測試連接性
     */
    public function testConnection(): array
    {
        if (!$this->apiKey) {
            return [
                'success' => false,
                'message' => 'API key not configured',
                'model' => null
            ];
        }

        try {
            $testData = [
                'model' => defined('PERPLEXITY_MODEL') ? PERPLEXITY_MODEL : 'llama-3.1-sonar-large-128k-online',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Hello, please respond with "Connection successful"'
                    ]
                ],
                'max_tokens' => 50
            ];

            $response = $this->makeApiCall($testData);
            return [
                'success' => true,
                'message' => 'Connection successful',
                'model' => defined('PERPLEXITY_MODEL') ? PERPLEXITY_MODEL : 'llama-3.1-sonar-large-128k-online',
                'response' => $response
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'model' => defined('PERPLEXITY_MODEL') ? PERPLEXITY_MODEL : 'llama-3.1-sonar-large-128k-online'
            ];
        }
    }

    /**
     * 保持原有的 generatePrompt 方法以維持兼容性
     */
    public function generatePrompt(array $data): string
    {
        return $this->generateImagePrompt($data);
    }
}
