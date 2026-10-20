<?php
/**
 * Vercel Serverless PHP Router / Front Controller
 * Routes all incoming requests to the appropriate script or serves static assets
 */

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = urldecode($uri ?: '/');

$rootDir = dirname(__DIR__);

// Handle static assets fallback if requested through the router
if (preg_match('#^/assets/(.+)#', $uri, $matches)) {
    $filePath = $rootDir . '/assets/' . $matches[1];
    if (file_exists($filePath) && is_file($filePath)) {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'css'  => 'text/css; charset=UTF-8',
            'js'   => 'application/javascript; charset=UTF-8',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2',
            'ttf'  => 'font/ttf',
            'json' => 'application/json'
        ];
        $contentType = $mimeTypes[$ext] ?? mime_content_type($filePath) ?: 'application/octet-stream';
        header('Content-Type: ' . $contentType);
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($filePath);
        exit;
    }
}

// Normalize routing targets
$targetFile = null;

if ($uri === '/' || $uri === '' || $uri === '/index.php' || $uri === '/index') {
    $targetFile = $rootDir . '/index.php';
} elseif (preg_match('#^/api/(.+)$#', $uri, $m)) {
    // API scripts inside api/
    $scriptName = $m[1];
    if (!str_ends_with($scriptName, '.php')) {
        $scriptName .= '.php';
    }
    // Avoid recursion
    if ($scriptName !== 'index.php') {
        $candidate = __DIR__ . '/' . $scriptName;
        if (file_exists($candidate)) {
            $targetFile = $candidate;
        }
    }
} elseif (preg_match('#^/admin/?(.*)$#', $uri, $m)) {
    // Admin scripts
    $scriptName = $m[1] ?: 'dashboard.php';
    if (!str_ends_with($scriptName, '.php')) {
        $scriptName .= '.php';
    }
    $candidate = $rootDir . '/admin/' . $scriptName;
    if (file_exists($candidate)) {
        $targetFile = $candidate;
    }
} else {
    // Top-level scripts (e.g. /check-in.php, /login.php, /winners.php)
    $cleanUri = ltrim($uri, '/');
    $candidate = $rootDir . '/' . $cleanUri;
    if (is_file($candidate)) {
        $targetFile = $candidate;
    } elseif (is_file($candidate . '.php')) {
        $targetFile = $candidate . '.php';
    }
}

if ($targetFile && file_exists($targetFile)) {
    $_SERVER['SCRIPT_FILENAME'] = $targetFile;
    $_SERVER['SCRIPT_NAME'] = '/' . ltrim(str_replace('\\', '/', substr($targetFile, strlen($rootDir))), '/');
    chdir(dirname($targetFile));
    require $targetFile;
    exit;
}

// 404 Handler if route not matched
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Halaman Tidak Ditemukan - HUT 30 BNI Life</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #0B2240; color: #fff; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card-404 { background: rgba(255,255,255,0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.15); border-radius: 20px; padding: 2.5rem; text-align: center; max-width: 480px; }
        .btn-orange { background: #F37021; color: #fff; font-weight: bold; border-radius: 10px; padding: 0.6rem 1.5rem; border: none; text-decoration: none; }
        .btn-orange:hover { background: #d45a10; color: #fff; }
    </style>
</head>
<body>
    <div class="card-404 shadow-lg">
        <h1 class="display-1 fw-black text-warning mb-2">404</h1>
        <h4 class="fw-bold mb-3">Halaman Tidak Ditemukan</h4>
        <p class="text-white-50 mb-4">Halaman <code><?= htmlspecialchars($uri) ?></code> tidak tersedia di server.</p>
        <a href="/" class="btn-orange">Kembali ke Beranda</a>
    </div>
</body>
</html>
