<?php
// admin/pengaturan.php - Halaman Pengaturan (placeholder)
require_once __DIR__ . '/../koneksi/config.php';
require_once __DIR__ . '/components/auth.php';

$activePage = 'pengaturan';
$pageTitle  = 'Pengaturan';
$adminName  = 'Admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Pengaturan – SNBP Admin 2026</title>
    <link rel="shortcut icon" href="../img/logo.png" type="image/png"/>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="../src/output.css"/>
</head>
<body class="bg-gray-50 min-h-screen">

    <?php include 'components/sidebar.php'; ?>
    <?php include 'components/header.php'; ?>

    <main class="lg:ml-64 pt-16 min-h-screen">
        <div class="p-6">
            <div class="page-header">
                <h1>Pengaturan</h1>
                <p>Konfigurasi sistem administrasi</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Card Profil -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="text-base font-semibold text-gray-800">Profil Sistem</h2>
                    </div>
                    <div class="card-body space-y-4">
                        <div>
                            <label class="form-label">Nama Sistem</label>
                            <input type="text" class="form-input" value="SNBP Admin 2026"/>
                        </div>
                        <div>
                            <label class="form-label">Deskripsi</label>
                            <input type="text" class="form-input" value="Prediksi Nilai SNBP 2026"/>
                        </div>
                        <div>
                            <label class="form-label">Tahun Aktif</label>
                            <input type="number" class="form-input" value="2026"/>
                        </div>
                        <div class="flex justify-end">
                            <button class="btn btn-green">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7"/>
                                </svg>
                                Simpan
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Card Ganti Password -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="text-base font-semibold text-gray-800">Ganti Password</h2>
                    </div>
                    <div class="card-body space-y-4">
                        <div>
                            <label class="form-label">Password Lama</label>
                            <input type="password" class="form-input" placeholder="••••••••"/>
                        </div>
                        <div>
                            <label class="form-label">Password Baru</label>
                            <input type="password" class="form-input" placeholder="••••••••"/>
                        </div>
                        <div>
                            <label class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" class="form-input" placeholder="••••••••"/>
                        </div>
                        <div class="flex justify-end">
                            <button class="btn btn-green">Ubah Password</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
    function openSidebar() {
        document.getElementById('sidebar').classList.remove('-translate-x-full');
        document.getElementById('sidebarOverlay').classList.remove('hidden');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.add('-translate-x-full');
        document.getElementById('sidebarOverlay').classList.add('hidden');
    }
    </script>
</body>
</html>
