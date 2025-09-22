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
            'ghibli' => 'Studio Ghibli style, hand-drawn animation, whimsical, fantastical, vibrant colors, lush landscapes, dreamy, poetic, masterpiece, evocative',
            'pixel-art' => 'pixel art style, 8-bit, retro gaming, pixelated, crisp edges, limited color palette, nostalgic, detailed pixel work, masterpiece',
            '3d-render' => 'Pixar style, 3D rendered, CGI animation, soft lighting, vibrant colors, smooth textures, family-friendly, whimsical, high quality, masterpiece',
            'flat-illustration' => 'flat design, minimal illustration, clean lines, bold colors, geometric shapes, modern design, simple, elegant, vector art style',
            'sketch' => 'hand-drawn sketch, pencil drawing, artistic lines, rough sketches, expressive strokes, monochrome or light colors, artistic, detailed',
            'ink-wash' => 'Chinese ink wash painting, traditional watercolor, flowing brushstrokes, monochromatic, artistic gradients, serene, poetic, masterpiece',
            'default' => 'high quality, detailed, visually stunning, poetic, evocative, masterpiece'
        ];
    }

    /**
     * 生成圖像提示詞
     */
    public function generateImagePrompt(array $data): string
    {
        if (!$this->apiKey) {
            throw new Exception("Perplexity API key not configured, cannot generate prompt.");
        }

        try {
            $query = $this->buildImagePromptQuery($data);
            $postData = [
                'model' => defined('PERPLEXITY_MODEL') ? PERPLEXITY_MODEL : 'llama-3.1-sonar-large-128k-online',
                'messages' => [
                    ['role' => 'system', 'content' => $this->getImagePromptSystemPrompt()],
                    ['role' => 'user', 'content' => $query]
                ],
                'max_tokens' => 250,
                'temperature' => 0.7,
                'top_p' => 0.8,
                'stream' => false
            ];

            $response = $this->makeApiCall($postData);
            if ($response) {
                return $this->cleanAndSimplifyPrompt($response);
            } else {
                throw new Exception("Perplexity API call did not return a valid response.");
            }
        } catch (Exception $e) {
            error_log("Perplexity API error for image prompt: " . $e->getMessage());
            // Re-throw the exception to be caught by the controller
            throw $e;
        }
    }

    /**
     * 增強版圖片提示詞系統提示 - 專為日記內容優化
     */
    private function getImagePromptSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert AI prompt engineer specializing in transforming diary entries into cinematic, emotionally resonant image prompts. Your goal is to create prompts that generate images capturing both the narrative essence and emotional depth of personal diary moments.

**MISSION:** Transform diary text into vivid, specific visual scenes that feel authentic and emotionally connected to the writer's experience.

**OPTIMIZATION STRATEGY:**

1. **EMOTIONAL INTELLIGENCE MAPPING:**
   - 😊😄😆 (Joy/Achievement): golden hour lighting, warm glowing screens, triumphant gestures, bright environments
   - 😢😔😞 (Sadness/Melancholy): soft blue-grey tones, rain textures, contemplative poses, muted environments
   - 😤😠😡 (Frustration/Anger): dramatic shadows, red accents, tense body language, chaotic elements
   - 🤔😐😑 (Neutral/Thoughtful): balanced lighting, focused expressions, clean compositions
   - 😴😪🥱 (Tired/Exhausted): dim warm lighting, relaxed postures, cozy environments
   - 🥰😍☺️ (Love/Affection): soft romantic lighting, warm colors, intimate settings

2. **SCENE CONSTRUCTION FORMULA:**
   Subject + Action + Environment + Emotion + Lighting + Style

3. **DIARY-SPECIFIC ELEMENTS:**
   - Personal moments: "person writing at desk", "someone looking thoughtful by window"
   - Work/Study: "focused individual at computer", "student with books and notes"
   - Daily life: "person cooking in kitchen", "someone walking in park"
   - Relationships: "friends laughing together", "family gathering around table"

4. **TECHNICAL REQUIREMENTS:**
   - Output: Single line, comma-separated English
   - Length: 20-60 words for rich detail
   - NO quotes, explanations, or meta-text
   - Always end with provided art style keywords

5. **ENHANCED EXAMPLES:**
   - Work Achievement: "a developer celebrating at their desk, multiple monitors showing completed code, warm golden light from window, sense of accomplishment, coffee cup nearby, modern office"
   - Family Time: "a mother reading bedtime story to child, soft warm lamp light, cozy bedroom, peaceful atmosphere, gentle expressions"
   - Personal Reflection: "person journaling by window, soft natural light, rain drops on glass, contemplative mood, notebooks and pen scattered on table"

**OUTPUT FORMAT:** [subject with emotion] + [specific action] + [detailed environment] + [lighting/atmosphere] + [relevant objects] + [art style keywords]
PROMPT;
    }

    /**
     * 增強版圖片提示詞查詢建構 - 包含內容分析
     */
    private function buildImagePromptQuery(array $data): string
    {
        $content = $data['content'] ?? 'A peaceful day';
        $style = $data['style'] ?? 'default';
        $mood = $data['mood'] ?? '😊';
        $styleKeywords = $this->styleKeywords[$style] ?? $this->styleKeywords['default'];

        // 分析日記內容類別，提供額外上下文
        $contentAnalysis = $this->analyzeContentContext($content);

        return sprintf(
            "**DIARY ANALYSIS:**\nContent: \"%s\"\nMood Emoji: %s\nContent Type: %s\nArt Style Required: %s\n\n**TASK:** Create a cinematic image prompt that captures this diary moment.",
            $content,
            $mood,
            $contentAnalysis,
            $styleKeywords
        );
    }

    /**
     * 分析日記內容類型，提供更好的提示詞上下文
     */
    private function analyzeContentContext(string $content): string
    {
        $content = strtolower($content);

        // 工作相關關鍵詞
        $workKeywords = ['bug', '程式', '代碼', 'code', 'debug', '開發', 'project', '專案', '完成', 'finished', '工作', 'work', 'meeting', '會議'];

        // 生活相關關鍵詞
        $lifeKeywords = ['咖啡', 'coffee', '散步', 'walk', '公園', 'park', '家', 'home', '朋友', 'friend', '家人', 'family', '吃', 'eat', '做飯', 'cook'];

        // 學習相關關鍵詞
        $studyKeywords = ['學習', 'study', 'learn', '讀書', 'read', 'book', '課程', 'course', '考試', 'exam', '筆記', 'notes'];

        // 情感相關關鍵詞
        $emotionalKeywords = ['想念', 'miss', '愛', 'love', '難過', 'sad', '開心', 'happy', '擁抱', 'hug', '想', 'think', '感受', 'feel'];

        foreach ($workKeywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                return "Work/Professional Achievement";
            }
        }

        foreach ($studyKeywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                return "Learning/Study Session";
            }
        }

        foreach ($lifeKeywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                return "Daily Life/Leisure Activity";
            }
        }

        foreach ($emotionalKeywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                return "Personal Reflection/Emotional Moment";
            }
        }

        return "General Life Experience";
    }

    /**
     * 清理和簡化提示詞 (Corrected version)
     */
    private function cleanAndSimplifyPrompt(string $prompt): string
    {
        // Step 1: Remove leading/trailing whitespace
        $cleaned = trim($prompt);
        // Step 2: Remove leading/trailing quotes (single or double)
        $cleaned = trim($cleaned, '\'"');
        // Step 3: Normalize internal whitespace
        $cleaned = preg_replace('/\s+/s', ' ', $cleaned);
        // Step 4: Normalize comma spacing
        $cleaned = preg_replace('/\s*,\s*/', ', ', $cleaned);
        return $cleaned;
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
                'model' => defined('PERPLEXITY_MODEL') ? PERPLEXITY_MODEL : 'llama-3.1-sonar-large-128k-online',
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
