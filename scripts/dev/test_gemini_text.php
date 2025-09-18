<?php
// Development-only: Temporary test endpoint for local Gemini text smoke test
// Moved from public/ to scripts/dev/ to avoid exposing test endpoints in production.

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/autoloader.php';

if (PHP_SAPI !== 'cli' && (!defined('DEV_MODE') || DEV_MODE !== true)) {
  echo "This script is for development only. Set DEV_MODE=true in config or run from CLI." . PHP_EOL;
  exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['user_id'] = $_SESSION['user_id'] ?? 1;

try {
  $controllerClass = '\\App\\Controllers\\AIController';
  if (!class_exists($controllerClass)) {
    require_once APP_PATH . '/controllers/AIController.php';
  }

  $inputData = json_encode([
    'content' => "Today I felt grateful for a long walk in the park, the weather was calm and my heart was light.",
    'mood' => '\uD83D\uDE0A'
  ]);

  $perplexity = new \App\Models\PerplexityAdapter();
  $geminiText = null;
  try {
    if (defined('GEMINI_ENABLED') && GEMINI_ENABLED) {
      $geminiText = new \App\Models\GeminiTextAdapter();
    } else {
      $gTextModel = getenv('GEMINI_TEXT_MODEL') ?: (defined('GEMINI_TEXT_MODEL') ? constant('GEMINI_TEXT_MODEL') : null);
      if (!empty($gTextModel)) $geminiText = new \App\Models\GeminiTextAdapter();
    }
  } catch (Exception $e) {
    error_log('GeminiTextAdapter init error (test): ' . $e->getMessage());
    $geminiText = null;
  }

  $generated = null;
  if (!empty($geminiText)) {
    try {
      $generated = $geminiText->generateQuote(['content' => json_decode($inputData, true)['content'], 'emoji' => '\uD83D\uDE0A', 'thinking_budget' => 0]);
    } catch (Exception $e) {
      error_log('Gemini test generation error: ' . $e->getMessage());
      $generated = null;
    }
  }
  if (empty($generated)) {
    $generated = $perplexity->generateQuote(['content' => json_decode($inputData, true)['content'], 'emoji' => '\uD83D\uDE0A']);
  }

  header('Content-Type: application/json');
  echo json_encode(['success' => true, 'quote' => $generated]);
} catch (Exception $e) {
  http_response_code(500);
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'message' => 'Test endpoint error: ' . $e->getMessage()]);
}
