<?php
namespace App\Models;

use Exception;

/**
 * GeminiTextAdapter
 *
 * A lightweight PHP adapter that calls a Gemini 2.5 (GenAI) REST endpoint.
 * It demonstrates how to pass a thinking_config (thinking_budget) to enable/disable "thinking".
 *
 * NOTES:
 * - Configure GEMINI_API_KEY and GEMINI_API_URL in your .env or config/config.php.
 * - The exact REST path and payload shape can vary by GenAI version; adjust GEMINI_API_URL if needed.
 */
class GeminiTextAdapter
{
    private $apiKey;
    private $baseUrl; // e.g. https://api.gen.ai/v1 or https://generativelanguage.googleapis.com/v1
    private $model;

    public function __construct()
    {
    // Prefer environment variables; fall back to defined global constants if present.
    $this->apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? constant('GEMINI_API_KEY') : null);
    $this->baseUrl = getenv('GEMINI_API_URL') ?: (defined('GEMINI_API_URL') ? constant('GEMINI_API_URL') : 'https://generativelanguage.googleapis.com/v1beta');
    $this->model = getenv('GEMINI_TEXT_MODEL') ?: (defined('GEMINI_TEXT_MODEL') ? constant('GEMINI_TEXT_MODEL') : 'gemini-2.5-flash');

        if (empty($this->apiKey) || $this->apiKey === 'your_gemini_api_key_here') {
            error_log("Gemini API key is not configured.");
        }
    }

    /**
     * Generate a short quote / text using Gemini.
     *
     * $data can include:
     * - content: string (required)
     * - emoji: string (optional)
     * - thinking_budget: int (optional) -> set 0 to disable thinking
     */
    public function generateQuote(array $data): string
    {
        $content = $data['content'] ?? '';
        $emoji = $data['emoji'] ?? '';
        $thinkingBudget = isset($data['thinking_budget']) ? (int)$data['thinking_budget'] : 0; // default disable thinking

        if (empty($content)) {
            throw new Exception('Missing content for Gemini text generation');
        }

        // Build a compact prompt similar to existing PerplexityAdapter expectations
        $userText = "Diary Entry: " . $content;
        if (!empty($emoji)) {
            $userText .= "\nMood: " . $emoji;
        }

        // Build payload following Gemini REST examples: contents -> parts -> { text }
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [ 'text' => $userText ]
                    ]
                ]
            ],
            'config' => [
                'thinkingConfig' => [
                    'thinkingBudget' => $thinkingBudget
                ]
            ]
        ];

        $url = rtrim($this->baseUrl, '/') . '/models/' . $this->model . ':generateContent';

        $response = $this->makeApiCall($url, $payload);

        // Normalize response: attempt to extract text from common fields
        if (is_array($response)) {
            // If SDK-style response with 'candidates' or 'output' fields
            if (isset($response['candidates']) && is_array($response['candidates']) && !empty($response['candidates'][0]['content'])) {
                return trim($response['candidates'][0]['content']);
            }
            if (isset($response['output']) && is_string($response['output'])) {
                return trim($response['output']);
            }
            if (isset($response['text'])) {
                return trim($response['text']);
            }
            // Try to stringify
            return trim(json_encode($response));
        }

        throw new Exception('Invalid response from Gemini');
    }

    /**
     * Low-level HTTP POST to Gemini endpoint using curl
     */
    private function makeApiCall(string $url, array $payload)
    {
        $ch = curl_init();
        $json = json_encode($payload);

        // Gemini REST requires x-goog-api-key header per docs
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
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            error_log('Gemini API curl error: ' . $err);
            throw new Exception('Network error while calling Gemini API: ' . $err);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('Gemini API HTTP ' . $httpCode . ' - response: ' . $resp);
            throw new Exception('Gemini API returned HTTP ' . $httpCode);
        }

        $decoded = json_decode($resp, true);
        if ($decoded === null) {
            // Return raw text if cannot decode
            return $resp;
        }
        return $decoded;
    }
}

?>
