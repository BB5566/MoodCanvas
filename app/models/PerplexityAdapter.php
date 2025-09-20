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
            'ghibli' => 'Studio Ghibli style, hand-drawn animation, whimsical, fantastical, vibrant colors, lush landscapes, dreamy, poetic, masterpiece, evocative',
            'kandinsky' => 'in the style of Wassily Kandinsky, abstract, vibrant colors, geometric shapes, spiritual, poetic, masterpiece, evocative',
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
                'model' => PERPLEXITY_MODEL,
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
        return <<<'PROMPT'
You are an expert prompt engineer for text-to-image models. Convert the user's diary entry and their chosen mood into a single, focused English prompt suitable for image generation.

RULES:
1) Output only one single, comma-separated prompt string and nothing else (no explanation, no quotes).
2) Prioritize the main subject and its action (who/what and doing). Keep the scene concise.
3) Include setting details (indoor/outdoor, desk, cafe), and time/lighting if mentioned (e.g., warm golden afternoon light).
4) If the diary mentions '日曆', 'calendar' or '日曆功能', ensure the prompt explicitly mentions a device showing a calendar UI (e.g., laptop displaying calendar UI with diary entries).
5) **Crucially, integrate the emotional tone conveyed by the mood emoji (e.g., 😊 for joyful, 😢 for melancholic, 😡 for intense) into the scene description.**
6) Append style keywords from the provided Style Keywords (photorealistic, Ghibli style, etc.) at the end.
7) Optionally include camera/view shorthand when useful (close-up, medium shot, wide shot) and 1-2 small props (coffee cup, notebook) if referenced.
8) Avoid listing many unrelated elements; keep prompt length moderate (approx. 10-40 words).
9) Do not invent people names, brands, or on-screen readable text. Avoid watermarks.

FORMAT EXAMPLE:
Diary Entry: <user text>, Mood: 😊 -> Prompt: person coding on laptop, close-up, warm golden afternoon light, laptop displaying calendar UI with diary entries, smiling with a sense of accomplishment, joyful atmosphere, photorealistic, high detail
PROMPT;
    }

    /**
     * 建立簡化的圖片提示詞查詢
     */
    private function buildSimplifiedImagePromptQuery(array $data): string
    {
        $content = $data['content'] ?? 'A peaceful day';
        $style = $data['style'] ?? 'default';
        $mood = $data['mood'] ?? '😊'; // Get the mood emoji
        $keywords = $this->styleKeywords[$style] ?? $this->styleKeywords['default'];

        // 分析內容，提取關鍵信息
        $personContext = $this->extractPersonContext($content);

        return sprintf(
            "Diary Entry: \"%s\", Mood: %s\n\nStyle Keywords: \"%s\"\n\nFocus: Create a simple, clear scene. %s",
            $content,
            $mood, // Pass mood to the prompt
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

        // 偵測日曆/日曆功能等關鍵字，給出更具體的上下文
        if (strpos($content, '日曆') !== false || strpos($content, '日曆功能') !== false || stripos($content, 'calendar') !== false) {
            return "Focus on a laptop displaying a calendar UI with diary entries.";
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
                'model' => PERPLEXITY_MODEL,
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
        return <<<'SYS'
你是短句/引言寫作專家（Quote Writer）。任務：根據日記內容與情緒，產出一行簡短、原創且具溫度的短句，適合作為日記的 ai_generated_text。

規則：
1) 僅輸出一行文字（single line），不要多行、不要多餘說明、不要引號或額外標點。只要句子本身。
2) 輸出語言請與日記語言一致（若內容包含中文漢字則輸出中文，否則輸出英文）。
3) 長度限制：中文請控制在 8–40 字；英文請控制在 6–30 個詞（words）。
4) 語氣要呼應情緒（emoji 或內容關鍵字），例如：成就感 -> uplifting, 挑戰 -> encouraging, 傷感 -> gentle/comforting。
5) 優先產出原創短句；若輸入要求一定要使用「世界名言」，回傳必須標明作者並且不得超過 120 字，但預設情況下請不要回傳長名言或歌詞等可能受版權保護的長引文。
6) 禁止暴力、仇恨、色情或個資（PII）輸出。
7) 不包含 emoji（除非特別要求），不包含 URL、程式碼或可識別的個人名稱。

輸出範例（中文）：午後金光裡，看見努力變成了成果
輸出範例（英文）：After an afternoon of focus, the calendar finally showed the payoff
SYS;
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

        // 決定輸出語言（中文或英文）
        $langHint = (preg_match('/[\x{4e00}-\x{9fff}]/u', $content)) ? 'zh' : 'en';

        // 長度提示
        $lengthHint = ($langHint === 'zh') ? '請輸出 8-40 字的中文短句。' : 'Please output a short sentence of 6-30 words in English.';

        return "日記內容：'{$content}'\n情緒：{$emoji}\n\n必須使用：{$forceStyle}\n語言提示：{$langHint}\n長度提示：{$lengthHint}\n直接輸出一行短句：";
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
        $content = trim($data['content'] ?? '');
        $emoji = $data['emoji'] ?? '😊';

        // 決定語言：若內容包含 CJK，輸出中文；否則輸出英文
        $isCJK = preg_match('/[\x{4e00}-\x{9fff}]/u', $content);

        // 簡單抽取主題關鍵詞
        $topic = '';
        $topicMapZh = [
            '媽媽' => '為人母',
            '母親' => '為人母',
            '爸爸' => '為人父',
            '程式' => '程式開發',
            '開發' => '程式開發',
            '日曆' => '日曆功能',
            '專案' => '專案',
            '挑戰' => '挑戰',
            '學習' => '學習',
            '咖啡' => '咖啡時光'
        ];
        $topicMapEn = [
            'mother' => 'motherhood',
            'father' => 'fatherhood',
            'code' => 'coding',
            'develop' => 'development',
            'calendar' => 'calendar feature',
            'project' => 'project',
            'challenge' => 'challenge',
            'learning' => 'learning',
            'coffee' => 'coffee moment'
        ];

        foreach ($topicMapZh as $k => $v) {
            if (!$isCJK) break;
            if (strpos($content, $k) !== false) {
                $topic = $v;
                break;
            }
        }
        if (!$isCJK) {
            foreach ($topicMapEn as $k => $v) {
                if (stripos($content, $k) !== false) {
                    $topic = $v;
                    break;
                }
            }
        }

        // 時間或光線關鍵字
        $timePhrase = '';
        if ($isCJK) {
            if (strpos($content, '午後') !== false || strpos($content, '下午') !== false) $timePhrase = '午後金光中';
        } else {
            if (stripos($content, 'afternoon') !== false) $timePhrase = 'this afternoon';
        }

        // 情緒對應的關鍵詞
        $moodPhrasesZh = [
            '😊' => ['成就感溢於言表', '心裡暖暖的'],
            '😢' => ['溫柔地療癒自己', '靜靜感受情緒'],
            '😡' => ['把能量化為前進的力量', '激昂且堅定'],
            '😍' => ['被小確幸包圍', '心頭暖暖的愛意'],
            '😴' => ['給自己一個喘息', '放慢腳步休息一下'],
            '🤔' => ['思索與成長的片刻', '沉澱中前進'],
            '😂' => ['笑著翻過一頁', '輕快的喜悅'],
            '😰' => ['仍然在面對，但沒有放棄', '帶著不安繼續前行'],
            '🥰' => ['溫柔地被疼愛包圍', '愛與溫暖同行'],
            '🙄' => ['帶點無奈但仍然前行', '冷眼看世界，自己繼續做事']
        ];
        $moodPhrasesEn = [
            '😊' => ['a warm sense of accomplishment', 'a quiet satisfaction'],
            '😢' => ['a gentle healing moment', 'soft reflection'],
            '😡' => ['channeling energy into progress', 'fired up and determined'],
            '😍' => ['surrounded by small joys', 'heartfelt warmth'],
            '😴' => ['giving oneself a rest', 'slowing down to breathe'],
            '🤔' => ['a moment of thought and growth', 'quiet contemplation'],
            '😂' => ['smiling through it', 'lighthearted joy'],
            '😰' => ['still facing it, not giving up', 'uneasy but moving forward'],
            '🥰' => ['gently embraced by warmth', 'love and warmth accompany me'],
            '🙄' => ['slightly exasperated but moving on', 'wry acceptance and onward']
        ];

        // 選擇情緒片語
        if ($isCJK) {
            $moods = $moodPhrasesZh[$emoji] ?? [$emoji];
            $moodPhrase = $moods[array_rand($moods)];
        } else {
            $moods = $moodPhrasesEn[$emoji] ?? [$emoji];
            $moodPhrase = $moods[array_rand($moods)];
        }

        // 組合句子樣式（使用多種樣式以避免每次相同）
        if ($isCJK) {
            $patterns = [];
            if ($topic) $patterns[] = "%s，%s"; // e.g. "日曆功能，成就感溢於言表"
            if ($timePhrase) $patterns[] = "%s，%s"; // time + mood
            $patterns[] = "%s後，%s"; // after X, Y
            $patterns[] = "%s，%s"; // default: content summary + mood

            // 抽取一句簡短主題摘要（第一句或前 12 個字）
            $summary = mb_substr($content, 0, 12);
            $components = [$topic ?: $summary, $timePhrase ?: $topic ?: $summary, $moodPhrase];
            $pattern = $patterns[array_rand($patterns)];
            $result = sprintf($pattern, $components[0], $components[2]);
            // 最後修飾：保證 8-40 字
            $result = trim(preg_replace('/\s+/', ' ', $result));
            if (mb_strlen($result) > 40) $result = mb_substr($result, 0, 40);
            return $result;
        } else {
            $patterns = [
                "%s, %s", // topic, mood
                "After %s, %s", // after topic, mood
                "%s — %s", // topic — mood
                "%s with %s"
            ];
            $summary = mb_substr($content, 0, 60);
            $topicPart = $topic ?: $summary;
            $pattern = $patterns[array_rand($patterns)];
            $result = sprintf($pattern, $topicPart, $moodPhrase);
            // 截斷至 30 個詞
            $words = preg_split('/\s+/', trim($result));
            if (count($words) > 30) $result = implode(' ', array_slice($words, 0, 30));
            return trim($result);
        }
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
                'ghibli',
                'kandinsky'
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
        $sceneDescription = $this->getSimpleSceneDescription($content, $emoji);

        return "{$sceneDescription}, {$mood}, {$styleKeywords}, masterpiece, high quality, detailed artwork";
    }

    /**
     * 獲取簡單場景描述
     */
    private function getSimpleSceneDescription(string $content, string $emoji): string
    {
        // 建構更細緻的場景描述，並加入時間/情緒修飾
        $scene = '';

        if (strpos($content, '媽媽') !== false && strpos($content, '程式') !== false) {
            $scene = 'mother working on computer with baby nearby';
        } elseif (strpos($content, '日曆') !== false || strpos($content, '日曆功能') !== false || stripos($content, 'calendar') !== false) {
            $scene = 'laptop displaying calendar UI with diary entries';
        } elseif (strpos($content, '程式') !== false || strpos($content, '開發') !== false || strpos($content, '程式設計') !== false) {
            $scene = 'person coding on computer';
        } elseif (strpos($content, '咖啡') !== false) {
            $scene = 'person in cozy cafe setting';
        } else {
            $scene = 'peaceful everyday scene';
        }

        // 時間與光影情緒修飾
        if (strpos($content, '午後') !== false || strpos($content, '下午') !== false) {
            $scene .= ', warm golden afternoon light';
        }

        // 根據 emoji 添加情緒修飾
        switch ($emoji) {
            case '😊':
            case '😂':
            case '🥰':
                $scene .= ', joyful atmosphere';
                break;
            case '😢':
            case '😰':
                $scene .= ', melancholic atmosphere';
                break;
            case '😡':
                $scene .= ', intense atmosphere';
                break;
            case '😍':
                $scene .= ', romantic atmosphere';
                break;
            case '😴':
                $scene .= ', serene atmosphere';
                break;
            case '🤔':
                $scene .= ', contemplative atmosphere';
                break;
            case '🙄':
                $scene .= ', wry and detached atmosphere';
                break;
        }

        return $scene;
    }

    /**
     * 清理註解回應
     */
    private function cleanQuoteResponse(string $response): string
    {
        // 移除常見前綴與多行，保留單行
        $cleaned = preg_replace('/^(以下是|這是|根據)[:：\s]*/u', '', $response);
        // 移除編號或列點
        $cleaned = preg_replace('/^[\d\-\*\.\s]+/u', '', $cleaned);
        // 只取第一行
        $lines = preg_split('/\r?\n/', trim($cleaned));
        $cleaned = trim($lines[0] ?? '');
        // 移除方括號註記與多餘中英標點
        $cleaned = preg_replace('/\[\d+\]/', '', $cleaned);
        $cleaned = trim($cleaned, " \"'.,;:!?。！？、　\t\n\r");

        // 強制字數/詞數限制（簡單截斷保護）
        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $cleaned)) {
            // 中文：限制 40 字
            if (mb_strlen($cleaned) > 40) {
                $cleaned = mb_substr($cleaned, 0, 40);
            }
        } else {
            // 英文：限制 30 詞
            $words = preg_split('/\s+/', $cleaned);
            if (count($words) > 30) {
                $cleaned = implode(' ', array_slice($words, 0, 30));
            }
        }

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
                'model' => PERPLEXITY_MODEL,
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
                'model' => PERPLEXITY_MODEL,
                'response' => $response
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'model' => PERPLEXITY_MODEL
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
