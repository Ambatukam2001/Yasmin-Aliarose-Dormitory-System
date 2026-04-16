<?php
// Vercel Router
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($uri === '/' || $uri === '') {
    $uri = '/index.php';
}

$file = __DIR__ . '/../public' . $uri;

if (file_exists($file)) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        require $file;
    } else {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'json' => 'application/json',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        ];
        
        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
        }
        readfile($file);
    }
} else {
    // Attempt fallback routing (e.g. clean URLs without .php)
    if (file_exists($file . '.php')) {
        require $file . '.php';
    } else {
        header("HTTP/1.0 404 Not Found");
        echo "404 Not Found";
    }
}
