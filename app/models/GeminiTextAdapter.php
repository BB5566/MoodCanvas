<?php

namespace App\Models;

use Exception;

class GeminiTextAdapter
{
  // --- 新增：使用常數管理設定 ---
  private const DEFAULT_MODEL = 'gemini-1.5-flash-latest';
  private const BASE_API_URL = 'https://generativelanguage.googleapis.com/v1beta';

  private $apiKey;
  private $baseUrl;
  private $model;

  public function __construct()
  {
    $this->apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? constant('GEMINI_API_KEY') : null);
    $this->baseUrl = getenv('GEMINI_API_URL') ?: self::BASE_API_URL;
    $this->model = getenv('GEMINI_TEXT_MODEL') ?: (defined('GEMINI_TEXT_MODEL') ? constant('GEMINI_TEXT_MODEL') : self::DEFAULT_MODEL);

    if (empty($this->apiKey) || $this->apiKey === 'your_gemini_api_key_here') {
      error_log("Gemini Text API key is not configured.");
    }
  }

  /**
   * --- 核心優化：新增系統提示詞，指導 AI 行為 ---
   * 這會告訴 AI 它的角色和必須遵守的規則，是生成高品質內容的關鍵。
   */
  private function getSystemPrompt(): string
  {
    return <<<'SYS'
You are a creative and empathetic Quote Writer. Your task is to read a user's diary entry and their mood, then write a single, short, original, and warm sentence that can be used as an `ai_generated_text` for the diary.

RULES:
1.  **Output only one single line of text.** No extra explanations, no quotes, no markdown. Just the sentence itself.
2.  **Match the language of the diary.** If the diary contains Chinese characters, output in Traditional Chinese. Otherwise, output in English.
3.  **Strict Length Limit:** For Chinese, keep it between 8 and 40 characters. For English, keep it between 6 and 30 words.
4.  **Match the Tone:** The tone must reflect the user's mood (from emoji or content). For accomplishment -> uplifting; for challenges -> encouraging; for sadness -> gentle and comforting.
5.  **Be Original:** Do NOT use famous quotes or proverbs unless the user's text explicitly asks for it. Create a unique sentence that fits the context.
6.  **Safety First:** Do not generate violent, hateful, explicit, or personally identifiable information.
7.  **No Extras:** Do not include emojis, URLs, or code in the output.
SYS;
  }

  public function generateQuote(array $data): string
  {
    $content = $data['content'] ?? '';
    $emoji = $data['emoji'] ?? '';

    if (empty($content)) {
      throw new Exception('Missing content for Gemini text generation');
    }

    // 簡潔的使用者提示
    $userPrompt = "Diary Content: \"{$content}\"\nMood Emoji: {$emoji}";

    // --- 優化：建構包含系統提示詞的 Payload ---
    $payload = [
      // Gemini 透過 contents 陣列來處理多輪對話或系統指令
      'contents' => [
        // 第一部分是我們的系統指令
        ['role' => 'user', 'parts' => [['text' => $this->getSystemPrompt()]]],
        // 第二部分是 "模型" 的回應，表示它已理解指令
        ['role' => 'model', 'parts' => [['text' => 'Okay, I am ready to be a Quote Writer. Please provide the diary entry and mood.']]],
        // 第三部分是真正的使用者輸入
        ['role' => 'user', 'parts' => [['text' => $userPrompt]]],
      ],
      // 使用標準的 generationConfig 參數
      'generationConfig' => [
        'temperature' => 0.7,
        'topP' => 0.8,
        'maxOutputTokens' => 100,
      ],
      // 建議加入安全設定
      'safetySettings' => [
        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
      ]
    ];

    $url = rtrim($this->baseUrl, '/') . '/models/' . $this->model . ':generateContent?key=' . $this->apiKey;

    $response = $this->makeApiCall($url, $payload);

    // --- 優化：更可靠地從標準 API 回應結構中提取文字 ---
    $rawText = '';
    if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
      $rawText = $response['candidates'][0]['content']['parts'][0]['text'];
    } else {
      // 如果找不到預期的文字，記錄錯誤並拋出異常
      $error_detail = json_encode($response);
      error_log("GeminiTextAdapter: Could not find text in response: " . $error_detail);
      throw new Exception('Invalid or empty response from Gemini API.');
    }

    // 清理並返回結果
    return $this->cleanGeneratedQuote($rawText, $content);
  }

  // (cleanGeneratedQuote, makeApiCall 方法與您原有的版本相似，此處省略以求簡潔)
  // ... 您可以將原有的 cleanGeneratedQuote 和 makeApiCall 方法貼到此處 ...
  // ... 為求完整，下方提供參考實作 ...

  private function cleanGeneratedQuote(string $response, string $originalContent = ''): string
  {
    $cleaned = preg_replace('/^(Here is|Here\'s|以下是|這是|根據)[:：\s]*/iu', '', $response);
    $cleaned = preg_replace('/^[\d\-\*\.\s]+/u', '', $cleaned);
    $lines = preg_split('/\r?\n/', trim($cleaned));
    $cleaned = trim($lines[0] ?? '');
    $cleaned = preg_replace('/\[\d+\]/', '', $cleaned);
    $cleaned = trim($cleaned, " \t\n\r\0\x0B\"'.,;:!?。！？、");

    $isCJK = preg_match('/[\x{4e00}-\x{9fff}]/u', $originalContent) || preg_match('/[\x{4e00}-\x{9fff}]/u', $cleaned);

    if ($isCJK) {
      if (mb_strlen($cleaned) > 40) $cleaned = mb_substr($cleaned, 0, 40);
    } else {
      $words = preg_split('/\s+/', $cleaned);
      if (count($words) > 30) $cleaned = implode(' ', array_slice($words, 0, 30));
    }
    return $cleaned;
  }

  private function makeApiCall(string $url, array $payload)
  {
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
