<?php

namespace App\Models;

use Exception;

class GeminiImageAdapter
{
  private $apiKey;
  private $baseUrl;
  private $model;

  public function __construct()
  {
    $this->apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? constant('GEMINI_API_KEY') : null);
    $this->baseUrl = getenv('GEMINI_API_URL') ?: (defined('GEMINI_API_URL') ? constant('GEMINI_API_URL') : 'https://generativelanguage.googleapis.com/v1beta');
    $this->model = getenv('GEMINI_IMAGE_MODEL') ?: (defined('GEMINI_IMAGE_MODEL') ? constant('GEMINI_IMAGE_MODEL') : 'gemini-2.5-flash-image');

    if (empty($this->apiKey) || $this->apiKey === 'your_gemini_api_key_here') {
      error_log("Gemini Image API key is not configured.");
    }
  }

  /**
   * Generate image using Gemini generateContent (image model).
   * Returns full URL to stored image in public/storage/generated_images or null on failure.
   */
  public function generateImage(string $prompt, array $options = []): ?string
  {
    $thinkingBudget = isset($options['thinkingBudget']) ? (int)$options['thinkingBudget'] : 0;
    $width = isset($options['width']) ? (int)$options['width'] : 512;
    $height = isset($options['height']) ? (int)$options['height'] : 512;
    $samples = isset($options['samples']) ? (int)$options['samples'] : 1;

    $payload = [
      'contents' => [
        [
          'parts' => [
            ['text' => $prompt]
          ]
        ]
      ],
      'config' => [
        'thinkingConfig' => [
          'thinkingBudget' => $thinkingBudget
        ],
        // model-specific generation options may be ignored by some Gemini image models
        'imageConfig' => [
          'width' => $width,
          'height' => $height,
          'samples' => $samples
        ]
      ]
    ];

    $url = rtrim($this->baseUrl, '/') . '/models/' . $this->model . ':generateContent';

    $resp = $this->makeApiCall($url, $payload);

    // Response parsing - look for inline_data in candidates -> content -> parts
    if (is_array($resp) && isset($resp['candidates']) && is_array($resp['candidates'])) {
      $candidate = $resp['candidates'][0] ?? null;
      if ($candidate && isset($candidate['content']['parts']) && is_array($candidate['content']['parts'])) {
        foreach ($candidate['content']['parts'] as $part) {
          if (isset($part['inline_data']) && isset($part['inline_data']['data'])) {
            $b64 = $part['inline_data']['data'];
            $mime = $part['inline_data']['mime_type'] ?? 'image/png';
            $ext = $this->mimeToExt($mime);
            $filename = $this->saveBase64Image($b64, $ext);
            if ($filename) return $this->getImageUrl($filename);
          }
        }
      }
    }

    // Fallbacks: check for common fields
    if (is_array($resp)) {
      if (isset($resp['imageUrl'])) {
        return $resp['imageUrl'];
      }
      if (isset($resp['output']) && is_string($resp['output'])) {
        // sometimes models return direct url or base64 in output
        return $resp['output'];
      }
    }

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
        error_log('GeminiImageAdapter error: ' . $e->getMessage());
      }
      if ($attempts < $maxRetries) sleep(2);
    }
    error_log('GeminiImageAdapter reached max retries');
    return null;
  }

  private function mimeToExt(string $mime): string
  {
    $map = [
      'image/png' => 'png',
      'image/jpeg' => 'jpg',
      'image/jpg' => 'jpg',
      'image/webp' => 'webp',
      'image/gif' => 'gif'
    ];
    return $map[$mime] ?? 'png';
  }

  private function saveBase64Image(string $b64, string $ext): ?string
  {
    $data = base64_decode($b64);
    if ($data === false) return null;
    $filename = 'ai_' . time() . '_' . uniqid() . '.' . $ext;
    $storageDir = defined('IMAGE_STORAGE_PATH') ? IMAGE_STORAGE_PATH : (BASE_PATH . '/public/storage/generated_images');
    if (!is_dir($storageDir)) mkdir($storageDir, 0775, true);
    $filepath = $storageDir . '/' . $filename;
    if (file_put_contents($filepath, $data)) return $filename;
    return null;
  }

  private function getImageUrl(string $filename): string
  {
    $baseUrl = defined('APP_URL') ? APP_URL : '';
    return $baseUrl . '/public/storage/generated_images/' . $filename;
  }

  private function makeApiCall(string $url, array $payload)
  {
    $ch = curl_init();
    $json = json_encode($payload);
    $headers = [
      'x-goog-api-key: ' . ($this->apiKey ?: ''),
      'Content-Type: application/json',
      'Accept: application/json'
    ];
    curl_setopt_array($ch, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $json,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_TIMEOUT => 90,
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
      throw new Exception('HTTP Error: ' . $httpCode . ' - ' . substr($resp, 0, 200));
    }
    $decoded = json_decode($resp, true);
    return $decoded === null ? $resp : $decoded;
  }
}
