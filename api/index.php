<?php
// api/index.php - Router Tunggal untuk Vercel Serverless
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = ltrim($uri, '/');

// Set direktori kerja ke root project agar relative path & include berjalan normal
chdir(__DIR__ . '/..');

// Jika root, jalankan index.php
if ($file === '' || $file === '/') {
    require 'index.php';
    exit;
}

// Cek target di root
if (file_exists($file) && is_file($file)) {
    require $file;
    exit;
}

// Jika folder (misal: admin), jalankan admin/index.php
if (is_dir($file) && file_exists($file . '/index.php')) {
    require $file . '/index.php';
    exit;
}

// Fallback jika URL tanpa ekstensi .php (misal: /catat_viewer?jurusan=RPL)
if (file_exists($file . '.php')) {
    require $file . '.php';
    exit;
}

// 404
http_response_code(404);
echo "404 Not Found";
