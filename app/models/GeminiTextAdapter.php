<?php

namespace App\Models;

use Exception;

class GeminiTextAdapter
{
    private const DEFAULT_MODEL = 'gemini-1.5-flash';
    private const BASE_API_URL = 'https://generativelanguage.googleapis.com/v1beta';

    private $apiKey;
    private $baseUrl;
    private $model;
    private $styleKeywords; // Add styleKeywords property

    public function __construct()
    {
        $this->apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? constant('GEMINI_API_KEY') : null);
        $this->baseUrl = getenv('GEMINI_API_URL') ?: self::BASE_API_URL;
        $this->model = getenv('GEMINI_TEXT_MODEL') ?: (defined('GEMINI_TEXT_MODEL') ? constant('GEMINI_TEXT_MODEL') : self::DEFAULT_MODEL);
        $this->initializeStyleKeywords(); // Initialize the styles

        if (empty($this->apiKey) || $this->apiKey === 'your_gemini_api_key_here') {
            error_log("Gemini Text API key is not configured.");
        }
    }

    /**
     * Add style keyword mapping to ensure consistency with PerplexityAdapter
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

    // --- Method for generating quotes ---
    public function generateQuote(array $data): string
    {
        $content = $data['content'] ?? '';
        $emoji = $data['emoji'] ?? '';

        if (empty($content)) {
            throw new Exception('Missing content for Gemini text generation');
        }

        $userPrompt = "Diary Content: \"{$content}\"\nMood Emoji: {$emoji}";

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $this->getQuoteSystemPrompt()]]],
                ['role' => 'model', 'parts' => [['text' => 'Okay, I am ready to be a Quote Writer.']]],
                ['role' => 'user', 'parts' => [['text' => $userPrompt]]],
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topP' => 0.8,
                'maxOutputTokens' => 1000,
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ]
        ];

        $url = rtrim($this->baseUrl, '/') . '/models/' . $this->model . ':generateContent?key=' . $this->apiKey;
        $response = $this->makeApiCall($url, $payload);

        // 檢查回應結構和內容
        if (!isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            $errorDetails = json_encode($response);
            error_log('Gemini API Quote Response Structure Issue: ' . $errorDetails);

            // 檢查是否因為 safety filter 或其他問題
            $finishReason = $response['candidates'][0]['finishReason'] ?? 'unknown';
            throw new Exception("Gemini API response missing text content. Finish reason: {$finishReason}. Full response logged.");
        }

        $rawText = $response['candidates'][0]['content']['parts'][0]['text'];

        return $this->cleanGeneratedQuote($rawText, $content);
    }

    /**
     * --- Method for generating image prompts ---
     */
    public function generateImagePrompt(array $data): string
    {
        if (empty($this->apiKey) || $this->apiKey === 'your_gemini_api_key_here') {
            throw new Exception(get_class($this) . " API key is not configured.");
        }

        $content = $data['content'] ?? 'A peaceful day';
        $style = $data['style'] ?? 'default';
        $mood = $data['mood'] ?? '😊';
        $styleKeywords = $this->styleKeywords[$style] ?? $this->styleKeywords['default'];

        // 分析日記內容類別，提供額外上下文
        $contentAnalysis = $this->analyzeContentContext($content);

        // 增強版用戶提示詞，包含內容分析
        $userPrompt = sprintf(
            "**DIARY ANALYSIS:**\nContent: \"%s\"\nMood Emoji: %s\nContent Type: %s\nArt Style Required: %s\n\n**TASK:** Transform this diary moment into a cinematic, emotionally resonant image prompt.",
            $content,
            $mood,
            $contentAnalysis,
            $styleKeywords
        );

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $this->getImagePromptSystemPrompt()]]],
                ['role' => 'model', 'parts' => [['text' => 'Okay, I am ready to act as an expert prompt engineer for text-to-image models.']]],
                ['role' => 'user', 'parts' => [['text' => $userPrompt]]],
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topP' => 0.9,
                'maxOutputTokens' => 800,
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ]
        ];

        $url = rtrim($this->baseUrl, '/') . '/models/' . $this->model . ':generateContent?key=' . $this->apiKey;
        $response = $this->makeApiCall($url, $payload);

        // 檢查回應結構和內容
        if (!isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            $errorDetails = json_encode($response);
            error_log('Gemini API Image Prompt Response Structure Issue: ' . $errorDetails);

            // 檢查是否因為 safety filter 或其他問題
            $finishReason = $response['candidates'][0]['finishReason'] ?? 'unknown';
            throw new Exception("Gemini API response missing text content for image prompt. Finish reason: {$finishReason}. Full response logged.");
        }

        $rawText = $response['candidates'][0]['content']['parts'][0]['text'];

        return $this->cleanImagePrompt($rawText);
    }

    // --- System Prompts ---

    private function getQuoteSystemPrompt(): string
    {
        return <<<'SYS'
You are a creative and empathetic Quote Writer. Your task is to read a user's diary entry and their mood, then write a single, short, original, and warm sentence that can be used as an `ai_generated_text` for the diary.

RULES:
1.  **Output only one single line of text.** No extra explanations, no quotes, no markdown. Just the sentence itself.
2.  **Match the language of the diary.** If the diary contains Chinese characters, output in Traditional Chinese. Otherwise, output in English.
3.  **Strict Length Limit:** For Chinese, keep it between 8 and 40 characters. For English, keep it between 6 and 30 words.
4.  **Match the Tone:** The tone must reflect the user's mood (from emoji or content). For accomplishment -> uplifting; for challenges -> encouraging; for sadness -> gentle and comforting.
5.  **Be Original:** Do NOT use famous quotes or proverbs. Create a unique sentence that fits the context.
6.  **Safety First:** Do not generate violent, hateful, explicit, or personally identifiable information.
7.  **No Extras:** Do not include emojis, URLs, or code in the output.
SYS;
    }

    private function getImagePromptSystemPrompt(): string
    {
        return <<<'SYS'
You are an elite visual storyteller and prompt architect, specializing in transforming intimate diary moments into breathtaking, emotionally authentic image prompts that capture the soul of personal experiences.

**CORE MISSION:** Transform diary entries into cinematic image prompts that feel like captured memories - vivid, emotionally resonant, and deeply personal.

**ADVANCED EMOTIONAL MAPPING:**

🌟 **Joy & Achievement** (😊😄😆🎉):
- Lighting: warm golden hour, bright glowing screens, soft rim lighting
- Expressions: genuine smiles, relaxed postures, triumphant gestures
- Environment: celebratory, accomplished, bright and inviting

💔 **Sadness & Melancholy** (😢😔😞💧):
- Lighting: soft blue-grey tones, gentle window light, muted colors
- Expressions: contemplative, gentle sadness, introspective gazes
- Environment: rain on windows, cozy corners, peaceful solitude

🔥 **Frustration & Determination** (😤😠🤬💪):
- Lighting: dramatic shadows, high contrast, intense focus lighting
- Expressions: concentrated, determined, slightly tense
- Environment: cluttered workspaces, multiple screens, coffee cups

💭 **Reflection & Peace** (🤔😌🧘☮️):
- Lighting: soft natural light, balanced exposure, calm atmosphere
- Expressions: peaceful, thoughtful, serene
- Environment: organized, minimal, harmonious

❤️ **Love & Connection** (🥰😍❤️👨‍👩‍👧‍👦):
- Lighting: warm romantic glow, soft intimate lighting
- Expressions: loving gazes, gentle touches, heartfelt moments
- Environment: comfortable, personal, shared spaces

**ENHANCED SCENE ARCHITECTURE:**
[Character with specific emotion] + [precise action/gesture] + [detailed environment] + [atmospheric lighting] + [meaningful objects] + [artistic style integration]

**DIARY-TO-VISUAL TRANSLATION GUIDE:**
- Work moments → person at desk/computer with specific emotional lighting
- Family time → intimate gatherings with warm, soft lighting
- Personal achievements → celebratory poses with accomplishment details
- Quiet reflection → solitary figure with peaceful, contemplative atmosphere
- Daily activities → natural, candid moments with environmental storytelling

**TECHNICAL EXCELLENCE:**
- Output: Pure prompt text, no explanations
- Length: 25-65 words for rich visual detail
- Include specific lighting, expressions, and environmental details
- End with provided art style seamlessly integrated
- NO quotation marks, brand names, or readable text elements

**MASTERFUL EXAMPLES:**
- Achievement: "developer leaning back in chair with satisfied smile, multiple monitors displaying completed code, warm afternoon sunlight streaming through office window, coffee mug and notes scattered on desk, sense of accomplishment filling the air"
- Family: "parent reading bedtime story to sleepy child, soft yellow lamp creating warm circle of light, cozy bedroom with stuffed animals, peaceful evening atmosphere, gentle expressions of love"
- Reflection: "person writing in journal by rain-streaked window, soft grey daylight, thoughtful expression, pen in hand, scattered notebooks on wooden table, contemplative solitude"

Transform the diary entry into a visual masterpiece that captures not just what happened, but how it felt.
SYS;
    }

    // --- Helper Methods ---

    /**
     * 分析日記內容類型，提供更好的提示詞上下文
     */
    private function analyzeContentContext(string $content): string
    {
        $content = strtolower($content);

        // 工作相關關鍵詞
        $workKeywords = ['bug', '程式', '代碼', 'code', 'debug', '開發', 'project', '專案', '完成', 'finished', '工作', 'work', 'meeting', '會議', 'ui', 'api', 'database', '資料庫'];

        // 生活相關關鍵詞
        $lifeKeywords = ['咖啡', 'coffee', '散步', 'walk', '公園', 'park', '家', 'home', '朋友', 'friend', '家人', 'family', '吃', 'eat', '做飯', 'cook', '買菜', 'shopping', '電影', 'movie'];

        // 學習相關關鍵詞
        $studyKeywords = ['學習', 'study', 'learn', '讀書', 'read', 'book', '課程', 'course', '考試', 'exam', '筆記', 'notes', '練習', 'practice', '研究', 'research'];

        // 情感相關關鍵詞
        $emotionalKeywords = ['想念', 'miss', '愛', 'love', '難過', 'sad', '開心', 'happy', '擁抱', 'hug', '想', 'think', '感受', 'feel', '回憶', 'memory', '夢', 'dream'];

        // 運動健康關鍵詞
        $healthKeywords = ['運動', 'exercise', '跑步', 'running', '健身', 'gym', '瑜伽', 'yoga', '游泳', 'swimming', '爬山', 'hiking', '健康', 'health'];

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

        foreach ($healthKeywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                return "Health/Exercise Activity";
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

    private function cleanGeneratedQuote(string $response, string $originalContent = ''): string
    {
        $cleaned = preg_replace('/^(Here is|Here\'s|以下是|這是|根據)[:：\s]*/iu', '', $response);
        $cleaned = preg_replace('/^[\d\-\*\]\s]+/u', '', $cleaned);
        $lines = preg_split('/\r?\n/', trim($cleaned));
        $cleaned = trim($lines[0] ?? '');
        $cleaned = preg_replace('/[\d+]/', '', $cleaned);
        $cleaned = trim($cleaned, " 	\n\r\0\x0B\"\'.,;:!?。！？、");

        $isCJK = preg_match('/[\x{4e00}-\x{9fff}]/u', $originalContent) || preg_match('/[\x{4e00}-\x{9fff}]/u', $cleaned);

        if ($isCJK) {
            if (mb_strlen($cleaned) > 40) $cleaned = mb_substr($cleaned, 0, 40);
        } else {
            $words = preg_split('/\s+/', $cleaned);
            if (count($words) > 30) $cleaned = implode(' ', array_slice($words, 0, 30));
        }
        return $cleaned;
    }

    private function cleanImagePrompt(string $prompt): string
    {
        $prompt = trim($prompt, " 	\n\r\0\x0B\"'" );
        $prompt = preg_replace('/\s+/', ' ', $prompt);
        $prompt = preg_replace('/\s*,\s*/', ', ', $prompt);
        return $prompt;
    }

    private function makeApiCall(string $url, array $payload)
    {
        if (empty($this->apiKey) || $this->apiKey === 'your_gemini_api_key_here') {
            throw new Exception(get_class($this) . " API key is not configured.");
        }
        
        $ch = curl_init();
        $json = json_encode($payload);
        $headers = ['Content-Type: application/json'];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            throw new Exception('cURL Error: ' . $err);
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            $errorDetails = "HTTP Error: {$httpCode}. URL: {$url}. Payload: {$json}. Response: " . substr($resp, 0, 500);
            error_log('Gemini API Error: ' . $errorDetails);
            throw new Exception('Gemini API returned HTTP ' . $httpCode);
        }
        return json_decode($resp, true);
    }
}