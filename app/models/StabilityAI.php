<?php
namespace App\Models;

use Exception;

class StabilityAI {
    
    private $apiKey;
    private $apiUrl;

    public function __construct() {
        $this->apiKey = defined('STABILITY_API_KEY') ? STABILITY_API_KEY : null;
        $this->apiUrl = defined('STABILITY_API_URL') ? STABILITY_API_URL : 'https://api.stability.ai';
        
        if (empty($this->apiKey) || $this->apiKey === 'your_stability_api_key_here') {
            throw new Exception("Stability AI API key is not configured correctly.");
        }
    }

    /**
     * 根據風格自動優化 AI 圖片提示詞 - 簡化版本
     */
    private function buildAIPrompt(string $basePrompt, string $style): string {
        $stylePrompts = [
            'photographic' => 'photographic, realistic, masterpiece, evocative, poetic',
            'van-gogh' => 'in the style of Vincent van Gogh, expressive brushstrokes, poetic, masterpiece, evocative',
            'monet' => 'in the style of Claude Monet, impressionist, soft light, poetic, masterpiece, evocative',
            'picasso' => 'in the style of Pablo Picasso, abstract, cubism, poetic, masterpiece, evocative',
            'hokusai' => 'in the style of Hokusai, ukiyo-e, Japanese art, poetic, masterpiece, evocative',
            'dali' => 'in the style of Salvador Dalí, surreal, dreamlike, poetic, masterpiece, evocative',
            'kandinsky' => 'in the style of Kandinsky, abstract, vibrant colors, poetic, masterpiece, evocative',
            'pollock' => 'in the style of Jackson Pollock, abstract expressionism, energetic, poetic, masterpiece, evocative',
        ];
        
        $extra = $stylePrompts[$style] ?? '';
        if ($extra) {
            return $basePrompt . ', ' . $extra;
        }
        
        return $basePrompt;
    }

    /**
     * 生成圖像（自動優化 prompt）- 簡化版本
     */
    public function generateImage(string $prompt, array $options = []): ?string {
        // 自動優化 prompt
        $style = $options['user_style'] ?? null;
        if ($style) {
            $prompt = $this->buildAIPrompt($prompt, $style);
        }
        
        try {
            $imageData = $this->callStabilityAPI($prompt, $options);
            
            if ($imageData) {
                $filename = $this->saveImage($imageData);
                if ($filename) {
                    return $this->getImageUrl($filename);
                }
            }
            return null;
        } catch (Exception $e) {
            error_log("Stability AI Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 呼叫 Stability AI API
     */
    private function callStabilityAPI(string $prompt, array $options): ?string {
        $model = defined('STABILITY_MODEL') ? STABILITY_MODEL : 'stable-diffusion-xl-1024-v1-0';
        $apiUrl = "{$this->apiUrl}/v1/generation/{$model}/text-to-image";

        $postData = [
            'text_prompts' => [['text' => $prompt, 'weight' => 1]],
            'cfg_scale' => $options['cfg_scale'] ?? 7,
            'height' => $options['height'] ?? 1024,
            'width' => $options['width'] ?? 1024,
            'samples' => 1,
            'steps' => $options['steps'] ?? 30
        ];
        
        if (isset($options['style_preset']) && $options['style_preset']) {
            $postData['style_preset'] = $options['style_preset'];
        }
        
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 90,
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
            error_log("Stability AI API Error - HTTP Code: {$httpCode}, Response: {$response}");
            throw new Exception("HTTP Error: {$httpCode} - {$response}");
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['artifacts'][0]['base64'])) {
            return $result['artifacts'][0]['base64'];
        }
        
        return null;
    }

    /**
     * 儲存圖像到本地
     */
    private function saveImage(string $base64Data): ?string {
        try {
            $imageData = base64_decode($base64Data);
            $filename = 'ai_' . time() . '_' . uniqid() . '.png';
            $storageDir = BASE_PATH . '/public/storage/generated_images';
            $filepath = $storageDir . '/' . $filename;
            
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0775, true);
            }
            
            if (file_put_contents($filepath, $imageData)) {
                return $filename;
            }            
            return null;
        } catch (Exception $e) {
            error_log("Save image error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 獲取圖像 URL
     */
    private function getImageUrl(string $filename): string {
        $baseUrl = defined('APP_URL') ? APP_URL : '';
        return $baseUrl . '/public/storage/generated_images/' . $filename;
    }
    
    /**
     * 帶重試機制的圖像生成
     */
    public function generateImageWithRetry(string $prompt, array $options = [], int $maxRetries = 2): ?string {
        $attempts = 0;
        while ($attempts < $maxRetries) {
            $attempts++;
            $result = $this->generateImage($prompt, $options);
            if ($result) {
                return $result;
            }
            if ($attempts < $maxRetries) {
                sleep(2);
            }
        }
        error_log("Stability AI reached max retries, generation failed.");
        return null;
    }

    /**
     * 獲取預設佔位圖 URL
     */
    public function getPlaceholderImage(): string {
        return (defined('APP_URL') ? APP_URL : '') . '/public/assets/images/placeholder-image.svg';
    }

    /**
     * 根據風格獲取 Stability AI 風格預設值
     */
    public function getStylePreset(string $userStyle): ?string {
        $styleMap = [
            'photographic' => 'photographic',
            'van-gogh'    => 'enhance',
            'monet'       => 'enhance',
            'picasso'     => 'enhance',
            'hokusai'     => 'enhance',
            'dali'        => 'enhance',
            'kandinsky'   => 'enhance',
            'pollock'     => 'enhance',
        ];
        return $styleMap[$userStyle] ?? null;
    }
}
?>
