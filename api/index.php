<?php
// api/index.php - Router Tunggal untuk Vercel Serverless
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = ltrim($uri, '/');

// Jika root, buka index.php
if ($file === '' || $file === '/') {
    require __DIR__ . '/../index.php';
    exit;
}

$target = __DIR__ . '/../' . $file;

// Jika file PHP langsung ada, jalankan
if (file_exists($target) && is_file($target)) {
    require $target;
    exit;
}

// Jika merujuk ke folder (misal: /admin), cari index.php di dalamnya
if (is_dir($target) && file_exists($target . '/index.php')) {
    require $target . '/index.php';
    exit;
}

// Fallback jika tidak ada ekstensi .php (misal: /hasil -> /hasil.php)
if (file_exists($target . '.php')) {
    require $target . '.php';
    exit;
}

// 404
http_response_code(404);
echo "404 Not Found";
