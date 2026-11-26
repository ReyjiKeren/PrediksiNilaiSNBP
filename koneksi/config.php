<?php
// koneksi/config.php - File Koneksi Database (Mendukung Local XAMPP & Cloud Environment Variables)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Baca kredensial dari Environment Variables (Vercel/Hosting Cloud), fallback ke localhost (XAMPP)
$db_host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? "localhost");
$db_user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? "root");
$db_pass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? "");
$db_name = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? "db_univ");
$db_port = intval(getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? 3306));

// Inisialisasi koneksi MySQLi
$conn = mysqli_init();

// Jika menggunakan SSL di cloud (opsional sesuai provider cloud)
$ssl_ca = getenv('DB_SSL_CA') ?: ($_ENV['DB_SSL_CA'] ?? null);
if ($ssl_ca) {
    mysqli_ssl_set($conn, NULL, NULL, $ssl_ca, NULL, NULL);
}

// Buka koneksi dengan opsi port
$connected = @mysqli_real_connect($conn, $db_host, $db_user, $db_pass, $db_name, $db_port);

if (!$connected) {
    die("Koneksi Database Gagal: " . mysqli_connect_error() . "<br><small>Pastikan Environment Variables database sudah dikonfigurasi dengan benar di Vercel.</small>");
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
