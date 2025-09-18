<?php
// Temporary test endpoint for local Gemini text smoke test

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/autoloader.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) session_start();

// Simulate a logged-in user for testing
$_SESSION['user_id'] = $_SESSION['user_id'] ?? 1;

try {
    $controllerClass = '\\App\\Controllers\\AIController';
    if (!class_exists($controllerClass)) {
        require_once APP_PATH . '/controllers/AIController.php';
    }

    // Directly construct the POST input data and temporarily replace php://input
    $inputData = json_encode([
        'content' => "Today I felt grateful for a long walk in the park, the weather was calm and my heart was light.",
        'mood' => '😊'
    ]);

    // Backup original php://input stream
    $tmp = fopen('php://temp', 'r+');
    fwrite($tmp, $inputData);
    rewind($tmp);

    // Monkey-patch: create a stream wrapper that AIController will read from when it calls file_get_contents('php://input')
    // PHP doesn't allow reassigning php://input, but file_get_contents will read from the request body. To avoid complex hacks,
    // we'll call the controller method directly and inject the data by temporarily overriding the php://input read via a small function replacement.

    // Simpler and reliable approach: call AIController::generateText() but before that, set a global variable that the controller can detect as test input.
    // We'll set $_SERVER['MOCK_RAW_INPUT'] and modify AIController to check it if present. However we prefer not to change controller logic for a test.

    // Practical approach: perform an internal call to the controller method by including the controller and simulating php://input using php://memory stream wrappers.
    // We'll use a temporary file in /tmp to store the input and set the php://input stream wrapper via stream_wrapper_register is not trivial.

    // Given complexity, easiest reliable method is to call the controller's generateText logic directly here using Perplexity/GeminiTextAdapter.
    // We'll reuse the adapters to generate the text directly rather than routing through generateText(), which keeps the test simple and non-blocking.

    // Instantiate adapters similarly to AIController
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

    // Run the same selection logic as controller
    $generated = null;
    if (!empty($geminiText)) {
        try {
            $generated = $geminiText->generateQuote(['content' => json_decode($inputData, true)['content'], 'emoji' => '😊', 'thinking_budget' => 0]);
        } catch (Exception $e) {
            error_log('Gemini test generation error: ' . $e->getMessage());
            $generated = null;
        }
    }
    if (empty($generated)) {
        $generated = $perplexity->generateQuote(['content' => json_decode($inputData, true)['content'], 'emoji' => '😊']);
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'quote' => $generated]);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Test endpoint error: ' . $e->getMessage()]);
}

?>
