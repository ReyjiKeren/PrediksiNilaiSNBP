<?php
// admin/index.php - Halaman Dashboard Utama
// Menampilkan ringkasan statistik dan aktivitas data terbaru

require_once __DIR__ . '/../koneksi/config.php';
require_once __DIR__ . '/components/auth.php';

$activePage = 'dashboard';
$pageTitle = 'Dashboard';
$adminName = 'Admin';

// Ambil Statistik dari Database
$totalPTN = 0;
$resPTN = $conn->query("SELECT COUNT(*) AS total FROM tb_universitas");
if ($resPTN && $row = $resPTN->fetch_assoc()) {
    $totalPTN = intval($row['total']);
}

$totalProdi = 0;
$resProdi = $conn->query("SELECT COUNT(*) AS total FROM tb_prodi_univ");
if ($resProdi && $row = $resProdi->fetch_assoc()) {
    $totalProdi = intval($row['total']);
}

$totalHistoris = 0;
$resHist = $conn->query("SELECT COUNT(*) AS total FROM tb_historis");
if ($resHist && $row = $resHist->fetch_assoc()) {
    $totalHistoris = intval($row['total']);
}

$stats = [
    ['label' => 'Total PTN', 'sub' => 'Universitas Terdaftar', 'value' => $totalPTN, 'color' => 'green', 'icon' => 'university'],
    ['label' => 'Total Program Studi', 'sub' => 'Program Studi Aktif', 'value' => $totalProdi, 'color' => 'blue', 'icon' => 'book'],
    ['label' => 'Total Data Historis', 'sub' => 'Rekam Jejak 5 Tahun', 'value' => $totalHistoris, 'color' => 'orange', 'icon' => 'chart'],
];

// Ambil 5 Data Prodi Terbaru
$sqlTerbaru = "
    SELECT 
        p.id_prodi,
        p.kode_prodi,
        p.jenjang,
        p.updated_at,
        u.nama_univ,
        mp.nama_prodi
    FROM tb_prodi_univ p
    JOIN tb_universitas u ON p.id_univ = u.id_univ
    JOIN tb_master_prodi mp ON p.id_master_prodi = mp.id_master_prodi
    ORDER BY p.updated_at DESC, p.id_prodi DESC
    LIMIT 6
";
$resTerbaru = $conn->query($sqlTerbaru);
$dataTerbaru = [];
if ($resTerbaru) {
    while ($row = $resTerbaru->fetch_assoc()) {
        $timestamp = !empty($row['updated_at']) ? strtotime($row['updated_at']) : time();
        $formattedDate = date('d M Y, H:i', $timestamp);
        $dataTerbaru[] = [
            'id' => $row['id_prodi'],
            'ptn' => $row['nama_univ'],
            'prodi' => $row['nama_prodi'],
            'jenjang' => $row['jenjang'],
            'kode' => $row['kode_prodi'],
            'waktu' => $formattedDate
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description"
        content="Dashboard Admin - Prediksi Nilai SNBP 2026. Kelola data PTN, Program Studi, dan Data Historis." />
    <title>Dashboard — SNBP Admin 2026</title>
    <link rel="shortcut icon" href="../img/logo.png" type="image/png" />

    <!-- Google Fonts: Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Tailwind CSS yang sudah di-compile -->
    <link rel="stylesheet" href="../src/output.css" />
</head>

<body class="bg-gray-50 min-h-screen">

    <?php include 'components/sidebar.php'; ?>
    <?php include 'components/header.php'; ?>

    <main class="lg:ml-64 pt-16 min-h-screen">
        <div class="p-6">

            <!-- Judul Halaman -->
            <div class="page-header">
                <h1>Dashboard</h1>
                <p>Selamat datang kembali di Sistem Administrasi SNBP 2026!</p>
            </div>

            <!-- ===== KARTU STATISTIK ===== -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">

                <?php foreach ($stats as $stat): ?>
                    <div class="stat-card">

                        <!-- Icon -->
                        <div class="stat-icon
                        <?php
                        if ($stat['color'] === 'green')
                            echo 'bg-green-100';
                        elseif ($stat['color'] === 'blue')
                            echo 'bg-blue-100';
                        else
                            echo 'bg-orange-100';
                        ?>">

                            <?php if ($stat['icon'] === 'university'): ?>
                                <svg class="w-5 h-5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9
                                    0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0
                                    011 1v5m-4 0h4" />
                                </svg>

                            <?php elseif ($stat['icon'] === 'book'): ?>
                                <svg class="w-5 h-5 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3
                                    6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168
                                    5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477
                                    18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>

                            <?php else: ?>
                                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0
                                    002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0
                                    002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                            <?php endif; ?>
                        </div>

                        <!-- Teks -->
                        <div>
                            <div class="text-2xl font-bold text-gray-900"><?= number_format($stat['value'], 0, ',', '.') ?>
                            </div>
                            <div class="text-sm font-medium text-gray-700"><?= htmlspecialchars($stat['label']) ?></div>
                            <div class="text-xs text-gray-400"><?= htmlspecialchars($stat['sub']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
            <!-- ===== AKHIR KARTU STATISTIK ===== -->


            <!-- ===== TABEL DATA TERBARU ===== -->
            <div class="card">
                <div class="card-header">
                    <h2 class="text-base font-semibold text-gray-800">Data Program Studi Terbaru</h2>
                    <a href="prodi.php" class="text-sm text-green-600 font-medium hover:text-green-700">
                        Lihat Semua →
                    </a>
                </div>

                <!-- Tabel responsive -->
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="w-12">No</th>
                                <th>PTN</th>
                                <th>Program Studi</th>
                                <th>Jenjang</th>
                                <th>Kode</th>
                                <th>Terakhir Diubah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dataTerbaru)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-8 text-gray-400">Belum ada data program studi.
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($dataTerbaru as $index => $row): ?>
                                <tr>
                                    <td class="text-gray-400 text-sm"><?= $index + 1 ?></td>
                                    <td class="text-sm font-medium text-gray-800"><?= htmlspecialchars($row['ptn']) ?></td>
                                    <td class="text-sm text-gray-700"><?= htmlspecialchars($row['prodi']) ?></td>
                                    <td><span class="badge badge-green"><?= htmlspecialchars($row['jenjang']) ?></span></td>
                                    <td class="font-mono text-xs text-gray-500"><?= htmlspecialchars($row['kode']) ?></td>
                                    <td class="text-gray-500 text-xs"><?= htmlspecialchars($row['waktu']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- ===== AKHIR TABEL DATA TERBARU ===== -->

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