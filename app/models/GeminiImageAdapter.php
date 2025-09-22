<?php

namespace App\Models;

use Exception;
use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\Credentials\ServiceAccountJwtAccessCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class GeminiImageAdapter
{
    private $projectId;
    private $region;
    private $model = 'imagen-4.0-generate-001'; // Upgraded to Imagen 4 with resolution control
    private $apiEndpoint;

    public function __construct()
    {
        $this->projectId = defined('GCP_PROJECT_ID') ? GCP_PROJECT_ID : getenv('GCP_PROJECT_ID');
        $this->region = defined('GCP_REGION') ? GCP_REGION : getenv('GCP_REGION');

        if (empty($this->projectId) || empty($this->region)) {
            throw new Exception("GCP_PROJECT_ID and GCP_REGION must be configured for Vertex AI.");
        }

        // Imagen 4 model endpoint
        $this->apiEndpoint = "https://{$this->region}-aiplatform.googleapis.com/v1/projects/{$this->projectId}/locations/{$this->region}/publishers/google/models/{$this->model}:predict";
    }

    private function getAuthToken(): string
    {
        if (isset($_SESSION['gcp_vertex_token']) && isset($_SESSION['gcp_token_expires_at']) && time() < $_SESSION['gcp_token_expires_at']) {
            return $_SESSION['gcp_vertex_token'];
        }

        $keyFilePath = defined('GCP_SERVICE_ACCOUNT_KEY_PATH') ? GCP_SERVICE_ACCOUNT_KEY_PATH : '';

        if (!file_exists($keyFilePath)) {
            throw new Exception("Google Cloud service account key file not found at: {$keyFilePath}");
        }
        if (!is_readable($keyFilePath)) {
            throw new Exception("Google Cloud service account key file is not readable at: {$keyFilePath}");
        }
        $fileContent = file_get_contents($keyFilePath);
        if ($fileContent === false) {
            throw new Exception("Failed to read content from Google Cloud service account key file at: {$keyFilePath}");
        }
        $jsonDecoded = json_decode($fileContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error decoding Google Cloud service account key file: " . json_last_error_msg());
        }
        if (!isset($jsonDecoded['client_email'])) {
            throw new Exception("Google Cloud service account key file is missing the client_email field after decoding.");
        }

        try {
            $scopes = ['https://www.googleapis.com/auth/cloud-platform'];
            
            $credentials = new ServiceAccountJwtAccessCredentials($jsonDecoded, $scopes);
            
            $token = $credentials->fetchAuthToken();

            if (empty($token['access_token'])) {
                throw new Exception("Failed to get GCP auth token from service account.");
            }

            $_SESSION['gcp_vertex_token'] = $token['access_token'];
            $_SESSION['gcp_token_expires_at'] = time() + ($token['expires_in'] ?? 3500) - 100; // Add a small buffer

            return $token['access_token'];

        } catch (Exception $e) {
            error_log('GCP Auth Error: ' . $e->getMessage());
            throw new Exception("Failed to authenticate with Google Cloud: " . $e->getMessage());
        }
    }

    public function generateImage(string $prompt, array $options = []): ?string
    {
        $token = $this->getAuthToken();

        // Imagen 4 model payload structure with resolution control
        $payload = [
            'instances' => [
                ['prompt' => $prompt]
            ],
            'parameters' => [
                'sampleCount' => $options['samples'] ?? 1,
                'aspectRatio' => $options['aspectRatio'] ?? '1:1',
                'sampleImageSize' => $options['resolution'] ?? '1K', // 1K 或 2K，1K 更小省 token
                'negativePrompt' => $options['negativePrompt'] ?? 'blurry, deformed, watermark, text, signature',
            ]
        ];

        $response = $this->makeApiCall($token, $payload);

        // Imagen 4 model response parsing
        if (isset($response['predictions'][0]['bytesBase64Encoded'])) {
            $b64 = $response['predictions'][0]['bytesBase64Encoded'];
            $filename = $this->saveBase64Image($b64, 'png');
            if ($filename) {
                return 'storage/generated_images/' . $filename;
            }
        }

        throw new Exception('Unexpected API response from Vertex AI. Full response: ' . json_encode($response));
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
                error_log("Vertex AI generation error on attempt {$attempts}: " . $e->getMessage());
            }
            if ($attempts < $maxRetries) sleep(3);
        }
        error_log('Vertex AI reached max retries and failed.');
        return null;
    }

    private function saveBase64Image(string $b64, string $ext): ?string
    {
        try {
            $imageData = base64_decode($b64);
            if ($imageData === false) return null;

            $storageDir = defined('IMAGE_STORAGE_PATH') ? IMAGE_STORAGE_PATH : (BASE_PATH . '/public/storage/generated_images');

            if (!is_dir($storageDir)) {
                @mkdir($storageDir, 0775, true);
            }

            // 建立臨時 PNG 檔案
            $tempPng = tempnam(sys_get_temp_dir(), 'ai_vertex_temp_') . '.png';
            file_put_contents($tempPng, $imageData);

            // 壓縮並轉換為 JPEG
            $filename = 'ai_vertex_' . time() . '_' . uniqid() . '.jpg';
            $filepath = $storageDir . '/' . $filename;

            if ($this->convertAndCompress($tempPng, $filepath)) {
                unlink($tempPng); // 刪除臨時檔案
                return $filename;
            }

            // 如果壓縮失敗，保存原始 PNG
            unlink($tempPng);
            $filename = 'ai_vertex_' . time() . '_' . uniqid() . '.png';
            $filepath = $storageDir . '/' . $filename;

            if (file_put_contents($filepath, $imageData)) {
                return $filename;
            }

            return null;
        } catch (Exception $e) {
            error_log("Vertex AI save image error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 壓縮圖片並轉換格式
     */
    private function convertAndCompress(string $sourcePath, string $outputPath): bool {
        try {
            // 使用 GD 擴展進行壓縮
            if (extension_loaded('gd')) {
                $image = imagecreatefrompng($sourcePath);
                if ($image) {
                    // 設定 JPEG 品質為 75%，大幅減少檔案大小
                    $result = imagejpeg($image, $outputPath, 75);
                    imagedestroy($image);
                    return $result;
                }
            }

            // 如果 GD 不可用，檢查 Imagick
            if (extension_loaded('imagick') && class_exists('Imagick')) {
                $imagick = new \Imagick($sourcePath);
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(75);
                $result = $imagick->writeImage($outputPath);
                $imagick->destroy();
                return $result;
            }

            return false;
        } catch (Exception $e) {
            error_log("Vertex AI image compression error: " . $e->getMessage());
            return false;
        }
    }

    private function makeApiCall(string $token, array $payload)
    {
        $ch = curl_init();
        $json = json_encode($payload);
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiEndpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 120,
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
            $errorDetails = "HTTP Error: {$httpCode}. URL: {$this->apiEndpoint}. Response: " . substr($resp, 0, 1000);
            error_log('Vertex AI API Error: ' . $errorDetails);
            throw new Exception('Vertex AI API returned HTTP ' . $httpCode . '. Response: ' . substr($resp, 0, 200));
        }

        if (empty($resp)) {
            throw new Exception('Empty response from Vertex AI API');
        }

        return json_decode($resp, true);
    }
}