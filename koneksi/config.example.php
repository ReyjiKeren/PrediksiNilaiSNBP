<?php
// koneksi/config.example.php - Template Konfigurasi Database
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "db_univ";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Helper flash message
function setFlash($type, $message) {
    $_SESSION['flash_msg'] = [
        'type' => $type, // 'success', 'error', 'warning'
        'text' => $message
    ];
}

function getFlash() {
    if (isset($_SESSION['flash_msg'])) {
        $msg = $_SESSION['flash_msg'];
        unset($_SESSION['flash_msg']);
        return $msg;
    }
    return null;
}
?>
