<?php
// koneksi/config.example.php - Template Konfigurasi Database (Mendukung Local & Cloud Env)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? "localhost");
$db_user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? "root");
$db_pass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? "");
$db_name = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? "db_univ");
$db_port = intval(getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? 3306));

$conn = mysqli_init();
$connected = @mysqli_real_connect($conn, $db_host, $db_user, $db_pass, $db_name, $db_port);

if (!$connected) {
    die("Koneksi Database Gagal: " . mysqli_connect_error());
}

$conn->set_charset("utf8mb4");

function setFlash($type, $message) {
    $_SESSION['flash_msg'] = [
        'type' => $type,
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
