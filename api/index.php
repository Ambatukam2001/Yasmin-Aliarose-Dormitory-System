<?php
// Vercel Router
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (strpos($uri, '/public') === 0) {
    $uri = substr($uri, 7);
}

if ($uri === '/' || $uri === '') {
    $uri = '/index.php';
}

// Simplified routing for speed
$public_path = __DIR__ . '/../public';
$file = $public_path . $uri;

// If explicitly requesting a directory or empty, go to index.php
if (is_dir($file) || $uri === '/' || $uri === '') {
    $file = $public_path . '/index.php';
}

// Fallback for missing .php extension
if (!file_exists($file)) {
    if (file_exists($file . '.php')) {
        $file .= '.php';
    } else {
        header("HTTP/1.0 404 Not Found");
        echo "404 Not Found";
        exit;
    }
}


if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
    $_SERVER['SCRIPT_FILENAME'] = $file;
    $_SERVER['SCRIPT_NAME'] = '/public' . $uri;
    $_SERVER['PHP_SELF'] = '/public' . $uri;
    $_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../public');
    chdir(dirname($file));
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
