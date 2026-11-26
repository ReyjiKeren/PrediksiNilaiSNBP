<?php
// admin/components/auth.php
// Middleware pengecekan session admin.
// Include file ini di bagian PALING ATAS setiap halaman admin (setelah require config.php).
//
// PENTING: session_start() TIDAK dipanggil di sini karena sudah dipanggil
// di dalam koneksi/config.php (dengan pengecekan PHP_SESSION_NONE).
// Pastikan require_once config.php dipanggil SEBELUM include auth.php.

if (!isset($_SESSION['admin_login']) || $_SESSION['admin_login'] !== true) {
    header("Location: login.php");
    exit;
}
?>
