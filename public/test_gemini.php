<?php
// Temporary test endpoint for local Gemini image smoke test

require_once __DIR__ . '/../config/config.php';
// Explicitly include the PSR-4 autoloader to ensure App\ namespace resolution works
require_once __DIR__ . '/../config/autoloader.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) session_start();

// Simulate a logged-in user for testing
$_SESSION['user_id'] = $_SESSION['user_id'] ?? 1;

// Create controller and invoke generateImage which reads POST body
try {
  $controllerClass = '\\App\\Controllers\\AIController';
  if (!class_exists($controllerClass)) {
    // attempt to require the file directly as a last resort
    require_once APP_PATH . '/controllers/AIController.php';
  }

  $c = new $controllerClass();
  $c->generateImage();
} catch (Exception $e) {
  http_response_code(500);
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'message' => 'Test endpoint error: ' . $e->getMessage()]);
}
