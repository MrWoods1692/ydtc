<?php
// Simple PHP server to serve the Live2D page without CORS issues
$uri = $_SERVER['REQUEST_URI'];

// If requesting the root, serve index.html
if ($uri === '/' || $uri === '/index.html') {
    header('Content-Type: text/html');
    readfile('index.html');
} 
// If requesting assets, serve them with proper content types
elseif (strpos($uri, '/assets/') === 0) {
    $filepath = '.' . $uri;
    if (file_exists($filepath)) {
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        $contentTypes = [
            'json' => 'application/json',
            'png' => 'image/png',
            'moc' => 'application/octet-stream',
            'mtn' => 'application/octet-stream'
        ];
        
        $contentType = $contentTypes[$extension] ?? 'application/octet-stream';
        header('Content-Type: ' . $contentType);
        readfile($filepath);
    } else {
        http_response_code(404);
        echo "File not found";
    }
} 
// If requesting lib files
elseif (strpos($uri, '/lib/') === 0) {
    $filepath = '.' . $uri;
    if (file_exists($filepath)) {
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        $contentTypes = [
            'js' => 'application/javascript',
            'css' => 'text/css',
            'map' => 'application/json',
            'json' => 'application/json'
        ];
        
        $contentType = $contentTypes[$extension] ?? 'application/octet-stream';
        header('Content-Type: ' . $contentType);
        readfile($filepath);
    } else {
        http_response_code(404);
        echo "File not found";
    }
} 
// Fallback
else {
    http_response_code(404);
    echo "Page not found";
}
?>