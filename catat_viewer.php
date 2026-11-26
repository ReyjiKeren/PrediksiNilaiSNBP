<?php
// catat_viewer.php
// Tugas file ini:
// 1. Terima parameter 'jurusan' dari URL
// 2. Simpan ke tabel tb_viewer
// 3. Redirect user ke nilai.php?jurusan=...

require_once __DIR__ . '/koneksi/config.php';

// Daftar jurusan yang diizinkan (untuk keamanan)
$jurusan_valid = ['RPL', 'TKR', 'TITL', 'TAV'];

// Ambil nilai jurusan dari URL, contoh: catat_viewer.php?jurusan=RPL
$jurusan = $_GET['jurusan'] ?? '';

// Cek apakah jurusan valid
if (!in_array($jurusan, $jurusan_valid)) {
    // Jika tidak valid, langsung ke halaman utama
    header('Location: index.php');
    exit;
}

// Simpan ke database menggunakan prepared statement (aman dari SQL Injection)
// Kolom 'time' otomatis terisi karena DEFAULT CURRENT_TIMESTAMP
$stmt = $conn->prepare("INSERT INTO tb_viewer (jurusan) VALUES (?)");
$stmt->bind_param("s", $jurusan);
$stmt->execute();
$stmt->close();

// Redirect ke nilai.php dengan membawa parameter jurusan
header('Location: nilai.php?jurusan=' . urlencode($jurusan));
exit;
