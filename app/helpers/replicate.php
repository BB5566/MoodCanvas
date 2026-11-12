<?php
// app/helpers/replicate.php
// 所有 AI 功能都走 Replicate：生圖用 Flux Schnell，文字（畫面描述、心情短語、心情洞察）用 GPT-5 nano（便宜、夠快）。
// 只需要一把 REPLICATE_API_KEY。

const REPLICATE_IMAGE_MODEL = 'black-forest-labs/flux-schnell';
const REPLICATE_TEXT_MODEL = 'openai/gpt-5-nano';

// 跑一次模型，回傳 output；先同步等最多 60 秒，還沒好就每 2 秒查一次，最多再等 30 秒
function replicateRun(string $model, array $input)
{
    $apiKey = getenv('REPLICATE_API_KEY');
    if (empty($apiKey)) throw new Exception('REPLICATE_API_KEY not configured');

    $data = replicateRequest("https://api.replicate.com/v1/models/$model/predictions", $apiKey, [
        'input' => $input,
    ]);

    for ($i = 0; $i < 15 && in_array($data['status'] ?? '', ['starting', 'processing'], true); $i++) {
        sleep(2);
        $data = replicateRequest("https://api.replicate.com/v1/predictions/{$data['id']}", $apiKey);
    }

    if (($data['status'] ?? '') !== 'succeeded') {
        throw new Exception("Replicate $model: " . ($data['status'] ?? 'no status') . ' ' . json_encode($data['error'] ?? ''));
    }
    return $data['output'] ?? null;
}

// 送一個 Replicate 請求；有 body 就是 POST 建立預測，沒有就是 GET 查狀態
// 被限流（429）就照 Retry-After 等一下再試，最多 3 次
function replicateRequest(string $url, string $apiKey, ?array $body = null, int $attempt = 1): array
{
    $ch = curl_init($url);
    $retryAfter = 0;
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $line) use (&$retryAfter) {
        if (stripos($line, 'retry-after:') === 0) $retryAfter = (int) trim(substr($line, 12));
        return strlen($line);
    });
    $headers = ["Authorization: Bearer $apiKey", 'Content-Type: application/json'];
    if ($body !== null) {
        $headers[] = 'Prefer: wait=60';
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body)]);
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 75,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 429 && $attempt < 3) {
        sleep(min(max($retryAfter, 3), 15));
        return replicateRequest($url, $apiKey, $body, $attempt + 1);
    }
    if (!in_array($httpCode, [200, 201, 202], true)) { // 202＝已收到、還在算，交給上面的輪詢
        throw new Exception("Replicate HTTP $httpCode");
    }
    return json_decode((string) $response, true) ?: [];
}

// 文字模型：回傳整段文字（Replicate 的文字輸出是一串 token，要接起來）
// GPT-5 nano 是推理模型：推理開到最低才快；max_completion_tokens 要含推理用量，所以給寬一點
function replicateText(string $prompt, int $maxTokens = 300): string
{
    $output = replicateRun(REPLICATE_TEXT_MODEL, [
        'prompt' => $prompt,
        'reasoning_effort' => 'minimal',
        'verbosity' => 'low',
        'max_completion_tokens' => $maxTokens + 300,
    ]);
    return trim(is_array($output) ? implode('', $output) : (string) $output);
}

// 從模型回覆裡拿出第一個 JSON 物件（模型常會包在 ```json 裡）
function extractJsonObject(string $text): ?array
{
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start === false || $end === false || $end < $start) return null;
    $data = json_decode(substr($text, $start, $end - $start + 1), true);
    return is_array($data) ? $data : null;
}
