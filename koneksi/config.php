<?php
// koneksi/config.php - File Koneksi Database (Mendukung Local XAMPP & Cloud Environment Variables)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fungsi pembantu untuk mengambil Environment Variable dari berbagai sumber PHP
function getEnvVal($key, $default = '') {
    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return $val;
    }
    if (!empty($_ENV[$key])) {
        return $_ENV[$key];
    }
    if (!empty($_SERVER[$key])) {
        return $_SERVER[$key];
    }
    return $default;
}

// Ambil kredensial database (Default fallback ke TiDB Cloud jika di cloud, atau localhost jika di XAMPP)
$is_vercel = !empty($_ENV['VERCEL']) || !empty($_SERVER['VERCEL']) || getenv('VERCEL');

$default_host = $is_vercel ? "gateway01.ap-southeast-1.prod.aws.tidbcloud.com" : "localhost";
$default_port = $is_vercel ? 4000 : 3306;
$default_user = $is_vercel ? "3PNg9c8a8hPwUFs.root" : "root";
$default_pass = $is_vercel ? "AdRG1WtXWoL5O2oq" : "";
$default_name = "db_univ";

$db_host = getEnvVal('DB_HOST', $default_host);
$db_port = intval(getEnvVal('DB_PORT', $default_port));
$db_user = getEnvVal('DB_USER', $default_user);
$db_pass = getEnvVal('DB_PASS', $default_pass);
$db_name = getEnvVal('DB_NAME', $default_name);

// Nonaktifkan mysqli reporting exception agar koneksi error bisa ditangani dengan pesan ramah
mysqli_report(MYSQLI_REPORT_OFF);

// Inisialisasi koneksi MySQLi
$conn = mysqli_init();

// TiDB Cloud mewajibkan koneksi terenkripsi (SSL)
if ($db_port === 4000 || strpos($db_host, 'tidbcloud.com') !== false) {
    // Flag SSL bawaan tanpa perlu file sertifikat lokal
    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
    $connected = @$conn->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port, NULL, MYSQLI_CLIENT_SSL);
} else {
    $connected = @$conn->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
}

if (!$connected) {
    die("Koneksi Database Gagal: (" . $conn->connect_errno . ") " . $conn->connect_error . "<br><small>Pastikan koneksi database cloud sudah aktif.</small>");
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
