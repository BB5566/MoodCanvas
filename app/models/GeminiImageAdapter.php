<?php

namespace App\Models;

use Exception;

class GeminiImageAdapter
{
  // --- 新增：使用常數管理設定，更易維護 ---
  private const DEFAULT_MODEL = 'gemini-pro-vision'; // 專門用於圖片理解與生成的模型
  private const BASE_API_URL = 'https://generativelanguage.googleapis.com/v1beta';

  private $apiKey;
  private $baseUrl;
  private $model;

  public function __construct()
  {
    $this->apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? constant('GEMINI_API_KEY') : null);
    $this->baseUrl = getenv('GEMINI_API_URL') ?: self::BASE_API_URL;
    // 注意：官方文件指出圖片生成通常透過特定模型或 Vertex AI 進行，這裡使用 gemini-pro-vision 作為範例
    // 您可能需要根據您的 API 權限調整為正確的圖片生成模型
    $this->model = getenv('GEMINI_IMAGE_MODEL') ?: (defined('GEMINI_IMAGE_MODEL') ? constant('GEMINI_IMAGE_MODEL') : self::DEFAULT_MODEL);

    if (empty($this->apiKey) || $this->apiKey === 'your_gemini_api_key_here') {
      error_log("Gemini Image API key is not configured.");
    }
  }

  /**
   * 使用 Gemini 生成圖像。
   * 注意：Gemini 的公開 API (generativelanguage.googleapis.com) 主要用於多模態理解，
   * 而非像 DALL-E 或 Stable Diffusion 那樣的文字到圖像生成。
   * 此處的程式碼假設您使用的模型支援基於文字提示的圖像響應，
   * 如果不行，您可能需要切換到 Google Cloud Vertex AI 的 Imagen 模型。
   *
   * @param string $prompt 英文提示詞
   * @param array $options 選項
   * @return string|null 儲存圖片的相對路徑或 null
   */
  public function generateImage(string $prompt, array $options = []): ?string
  {
    // --- 優化：修正 Payload 結構，使其符合 Gemini API 標準 ---
    $payload = [
      'contents' => [
        [
          'parts' => [
            ['text' => "Generate an image based on this prompt: " . $prompt]
          ]
        ]
      ],
      // 將 generationConfig 放在頂層，並使用標準參數
      'generationConfig' => [
        'temperature' => 0.8,
        'maxOutputTokens' => 2048, // 圖片生成可能需要較大的 token 限制
        'candidateCount' => 1,
      ],
      // 安全設定是建議的實踐
      'safetySettings' => [
        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
      ]
    ];

    $url = rtrim($this->baseUrl, '/') . '/models/' . $this->model . ':generateContent?key=' . $this->apiKey;

    $resp = $this->makeApiCall($url, $payload);

    // --- 優化：更精準地解析 API 回應 ---
    if (isset($resp['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
      $part = $resp['candidates'][0]['content']['parts'][0];
      $b64 = $part['inlineData']['data'];
      $mime = $part['inlineData']['mime_type'] ?? 'image/png';
      $ext = $this->mimeToExt($mime);
      $filename = $this->saveBase64Image($b64, $ext);
      if ($filename) {
        // 返回相對於 public 目錄的路徑
        return 'storage/generated_images/' . $filename;
      }
    }

    // 記錄未成功解析的回應以供除錯
    error_log('GeminiImageAdapter: Unexpected API response structure: ' . json_encode($resp));
    return null;
  }

  public function generateImageWithRetry(string $prompt, array $options = [], int $maxRetries = 2): ?string
  {
    $attempts = 0;
    while ($attempts < $maxRetries) {
      $attempts++;
      try {
        $result = $this->generateImage($prompt, $options);
        if ($result) return $result;
      } catch (Exception $e) {
        error_log('GeminiImageAdapter error on attempt ' . $attempts . ': ' . $e->getMessage());
      }
      if ($attempts < $maxRetries) sleep(2);
    }
    error_log('GeminiImageAdapter reached max retries and failed.');
    return null;
  }

  private function mimeToExt(string $mime): string
  {
    $map = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    return $map[$mime] ?? 'png';
  }

  private function saveBase64Image(string $b64, string $ext): ?string
  {
    $data = base64_decode($b64);
    if ($data === false) return null;
    $filename = 'ai_' . time() . '_' . uniqid() . '.' . $ext;
    $storageDir = defined('IMAGE_STORAGE_PATH') ? IMAGE_STORAGE_PATH : (BASE_PATH . '/public/storage/generated_images');
    if (!is_dir($storageDir)) @mkdir($storageDir, 0775, true);
    $filepath = $storageDir . '/' . $filename;
    if (file_put_contents($filepath, $data)) return $filename;
    return null;
  }

  private function makeApiCall(string $url, array $payload)
  {
    $ch = curl_init();
    $json = json_encode($payload);
    $headers = ['Content-Type: application/json']; // API Key 已在 URL 中

    curl_setopt_array($ch, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $json,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_TIMEOUT => 120, // 圖像生成可能需要更長的時間
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
      // --- 優化：提供更詳細的錯誤日誌 ---
      $errorDetails = "HTTP Error: {$httpCode}. URL: {$url}. Payload: {$json}. Response: " . substr($resp, 0, 500);
      error_log('Gemini API Error: ' . $errorDetails);
      throw new Exception('Gemini API returned HTTP ' . $httpCode);
    }
    return json_decode($resp, true);
  }
}
