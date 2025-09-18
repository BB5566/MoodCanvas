<?php
// Root index.php - redirect to public/index.php while preserving query string
$qs = $_SERVER['QUERY_STRING'] ?? '';
$target = 'public/index.php' . ($qs ? '?' . $qs : '');

// If request is directly for a file in public (like assets), rewrite to that path
$uri = $_SERVER['REQUEST_URI'] ?? '/';
// Normalize
$uriPath = parse_url($uri, PHP_URL_PATH);
$publicPath = __DIR__ . '/public' . $uriPath;
if ($uriPath !== '/' && file_exists($publicPath) && is_file($publicPath)) {
  // serve static file directly
  // Use fastcgi or webserver to serve; fallback to readfile for CLI
  header('Content-Type: ' . mime_content_type($publicPath));
  readfile($publicPath);
  exit;
}

// Otherwise, forward to public index
header('Location: ' . $target);
exit;
