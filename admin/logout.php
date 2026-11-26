<?php
// admin/logout.php
// Menghancurkan session admin dan redirect ke halaman login.

// Mulai session (menggunakan pengecekan aman)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Kosongkan semua data session
$_SESSION = [];

// 2. Hapus cookie session dari browser (opsional tapi lebih aman)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 3. Hancurkan session di server
session_destroy();

// 4. Redirect ke halaman login
header("Location: login.php");
exit;
?>
