<?php
// Development-only: Temporary test endpoint for local Gemini image smoke test
// Moved from public/ to scripts/dev/ to avoid exposing test endpoints in production.

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/autoloader.php';

if (PHP_SAPI !== 'cli' && (!defined('DEV_MODE') || DEV_MODE !== true)) {
  echo "This script is for development only. Set DEV_MODE=true in config or run from CLI." . PHP_EOL;
  exit;
}

// Simulate a logged-in user for testing when run via web in dev mode
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['user_id'] = $_SESSION['user_id'] ?? 1;

try {
  $controllerClass = '\\App\\Controllers\\AIController';
  if (!class_exists($controllerClass)) {
    require_once APP_PATH . '/controllers/AIController.php';
  }

  $c = new $controllerClass();
  $c->generateImage();
} catch (Exception $e) {
  http_response_code(500);
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'message' => 'Test endpoint error: ' . $e->getMessage()]);
}
