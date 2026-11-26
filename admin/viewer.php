<?php
// admin/viewer.php - Halaman Data Viewer
// Menampilkan statistik aktivitas pemilih jurusan dari tb_viewer dalam bentuk Pie Chart

require_once __DIR__ . '/../koneksi/config.php';
require_once __DIR__ . '/components/auth.php';

$activePage = 'viewer';
$pageTitle  = 'Data Viewer';
$adminName  = 'Admin';

// Array nama bulan dalam Bahasa Indonesia
$namaBulanIndo = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// ============================================================
// Ambil parameter filter bulan & tahun dari URL
// Default: bulan & tahun sekarang
// ============================================================
$filterBulan = isset($_GET['bulan']) ? intval($_GET['bulan']) : intval(date('n'));
$filterTahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : intval(date('Y'));

// Validasi range bulan
if ($filterBulan < 1 || $filterBulan > 12) $filterBulan = intval(date('n'));
// Validasi range tahun (2020 - tahun sekarang)
$tahunSekarang = intval(date('Y'));
if ($filterTahun < 2020 || $filterTahun > $tahunSekarang) $filterTahun = $tahunSekarang;

$bulanTahunDipilih = $namaBulanIndo[$filterBulan] . ' ' . $filterTahun;
$isCurrentMonth    = ($filterBulan === intval(date('n')) && $filterTahun === $tahunSekarang);

// ============================================================
// Query 1: Hitung viewer per jurusan BULAN YANG DIPILIH
// ============================================================
$viewerBulanan = ['RPL' => 0, 'TKR' => 0, 'TITL' => 0, 'TAV' => 0];
$sqlBulanan = "
    SELECT jurusan, COUNT(*) AS total
    FROM tb_viewer
    WHERE MONTH(time) = ? AND YEAR(time) = ?
    GROUP BY jurusan
";
$stmtBulanan = $conn->prepare($sqlBulanan);
$stmtBulanan->bind_param('ii', $filterBulan, $filterTahun);
$stmtBulanan->execute();
$resBulanan = $stmtBulanan->get_result();
if ($resBulanan) {
    while ($row = $resBulanan->fetch_assoc()) {
        if (isset($viewerBulanan[$row['jurusan']])) {
            $viewerBulanan[$row['jurusan']] = intval($row['total']);
        }
    }
}
$totalBulanan = array_sum($viewerBulanan);

// ============================================================
// Query 2: Hitung viewer per jurusan HARI INI saja
// ============================================================
$viewerHariIni = ['RPL' => 0, 'TKR' => 0, 'TITL' => 0, 'TAV' => 0];
$sqlHariIni = "
    SELECT jurusan, COUNT(*) AS total
    FROM tb_viewer
    WHERE DATE(time) = CURDATE()
    GROUP BY jurusan
";
$resHariIni = $conn->query($sqlHariIni);
if ($resHariIni) {
    while ($row = $resHariIni->fetch_assoc()) {
        if (isset($viewerHariIni[$row['jurusan']])) {
            $viewerHariIni[$row['jurusan']] = intval($row['total']);
        }
    }
}
$totalHariIni = array_sum($viewerHariIni);

// ============================================================
// Query 3: Hitung total pemilih per jurusan (Semua Waktu) untuk kartu atas
// ============================================================
$viewerTotal = ['RPL' => 0, 'TKR' => 0, 'TITL' => 0, 'TAV' => 0];
$resViewerTotal = $conn->query("
    SELECT jurusan, COUNT(*) AS total
    FROM tb_viewer
    GROUP BY jurusan
");
if ($resViewerTotal) {
    while ($row = $resViewerTotal->fetch_assoc()) {
        if (isset($viewerTotal[$row['jurusan']])) {
            $viewerTotal[$row['jurusan']] = intval($row['total']);
        }
    }
}

// ============================================================
// Query 4: Ambil riwayat aktivitas dari tb_viewer berdasarkan FILTER
// ============================================================
$daftarTimestamp = [];
$sqlRiwayat = "
    SELECT jurusan, time
    FROM tb_viewer
    WHERE MONTH(time) = ? AND YEAR(time) = ?
    ORDER BY time DESC
";
$stmtRiwayat = $conn->prepare($sqlRiwayat);
$stmtRiwayat->bind_param('ii', $filterBulan, $filterTahun);
$stmtRiwayat->execute();
$resTimestamp = $stmtRiwayat->get_result();
if ($resTimestamp) {
    while ($row = $resTimestamp->fetch_assoc()) {
        $daftarTimestamp[] = [
            'jurusan' => $row['jurusan'],
            'waktu'   => $row['time']
        ];
    }
}

// ============================================================
// Query 5: Ambil daftar tahun yang tersedia di database
// ============================================================
$daftarTahun = [];
$resTahun = $conn->query("SELECT DISTINCT YEAR(time) AS tahun FROM tb_viewer ORDER BY tahun DESC");
if ($resTahun) {
    while ($row = $resTahun->fetch_assoc()) {
        $daftarTahun[] = intval($row['tahun']);
    }
}
// Pastikan tahun sekarang selalu ada
if (!in_array($tahunSekarang, $daftarTahun)) {
    array_unshift($daftarTahun, $tahunSekarang);
}

$totalAktivitas = count($daftarTimestamp);
$tanggalHariIni = date('d F Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="description" content="Data Viewer - Statistik aktivitas pemilih jurusan SNBP 2026"/>
    <title>Data Viewer — SNBP Admin 2026</title>
    <link rel="shortcut icon" href="../img/logo.png" type="image/png"/>

    <!-- Google Fonts: Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>

    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="../src/output.css"/>

    <!-- CSS Kustom Layout Grid (Pastikan Chart Berdampingan Kanan & Kiri) -->
    <style>
        .stat-cards-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        @media (min-width: 640px) {
            .stat-cards-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        .charts-grid {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        @media (min-width: 768px) {
            .charts-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1.5rem;
            }
        }

        .rincian-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            width: 100%;
            margin-top: 1.25rem;
        }

        /* Container Scrollbar Khusus Tabel Riwayat Aktivitas */
        .riwayat-scroll-container {
            max-height: 320px;
            overflow-y: auto;
            overflow-x: auto;
        }

        .riwayat-scroll-container table {
            width: 100%;
            border-collapse: collapse;
        }

        .riwayat-scroll-container thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #f9fafb !important;
            box-shadow: 0 1px 0 #e5e7eb;
        }

        .riwayat-scroll-container::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .riwayat-scroll-container::-webkit-scrollbar-track {
            background: #f8fafc;
            border-radius: 4px;
        }

        .riwayat-scroll-container::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 4px;
            border: 1px solid #f8fafc;
        }

        .riwayat-scroll-container::-webkit-scrollbar-thumb:hover {
            background-color: #94a3b8;
        }

        /* ===== FILTER DROPDOWN STYLES ===== */
        .filter-wrapper {
            position: relative;
            display: inline-block;
        }

        .filter-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            font-size: 0.75rem;
            font-weight: 500;
            font-family: 'Poppins', sans-serif;
            color: #4f46e5;
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s ease;
            white-space: nowrap;
        }

        .filter-btn:hover {
            background: #e0e7ff;
            border-color: #a5b4fc;
        }

        .filter-btn svg {
            flex-shrink: 0;
            transition: transform 0.2s ease;
        }

        .filter-btn.open svg.chevron {
            transform: rotate(180deg);
        }

        .filter-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 6px);
            z-index: 50;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.10), 0 2px 8px rgba(0,0,0,0.06);
            padding: 14px;
            min-width: 220px;
        }

        .filter-dropdown.open {
            display: block;
        }

        .filter-dropdown label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }

        .filter-dropdown select {
            width: 100%;
            padding: 6px 10px;
            font-size: 0.8rem;
            font-family: 'Poppins', sans-serif;
            color: #1f2937;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            outline: none;
            cursor: pointer;
            appearance: auto;
            margin-bottom: 10px;
            transition: border-color 0.15s;
        }

        .filter-dropdown select:focus {
            border-color: #818cf8;
            background: #fff;
        }

        .filter-apply-btn {
            width: 100%;
            padding: 7px;
            font-size: 0.8rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            color: #fff;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: opacity 0.15s;
        }

        .filter-apply-btn:hover {
            opacity: 0.9;
        }

        .filter-reset-link {
            display: block;
            text-align: center;
            font-size: 0.7rem;
            color: #9ca3af;
            margin-top: 6px;
            text-decoration: none;
            transition: color 0.15s;
        }

        .filter-reset-link:hover {
            color: #6366f1;
        }
    </style>

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="bg-gray-50 min-h-screen">

    <?php include 'components/sidebar.php'; ?>
    <?php include 'components/header.php'; ?>

    <main class="lg:ml-64 pt-16 min-h-screen">
        <div class="p-6">

            <!-- Judul Halaman -->
            <div class="page-header">
                <h1>Data Viewer</h1>
                <p>Statistik aktivitas pemilih jurusan (Total: <?= number_format($totalAktivitas, 0, ',', '.') ?> aktivitas pada <?= htmlspecialchars($bulanTahunDipilih) ?>)</p>
            </div>

            <!-- ===== KARTU RINGKASAN TOTAL ALL TIME ===== -->
            <div class="stat-cards-grid">

                <!-- RPL -->
                <div class="stat-card">
                    <img src="../img/rpl.png" alt="RPL" style="width: 44px; height: 44px; object-fit: contain; flex-shrink: 0;" />
                    <div>
                        <div class="text-2xl font-bold text-gray-900"><?= number_format($viewerTotal['RPL'], 0, ',', '.') ?></div>
                        <div class="text-sm font-medium text-gray-700">RPL</div>
                        <div class="text-xs text-gray-400">Total pemilih</div>
                    </div>
                </div>

                <!-- TKR -->
                <div class="stat-card">
                    <img src="../img/tkr.png" alt="TKR" style="width: 44px; height: 44px; object-fit: contain; flex-shrink: 0;" />
                    <div>
                        <div class="text-2xl font-bold text-gray-900"><?= number_format($viewerTotal['TKR'], 0, ',', '.') ?></div>
                        <div class="text-sm font-medium text-gray-700">TKR</div>
                        <div class="text-xs text-gray-400">Total pemilih</div>
                    </div>
                </div>

                <!-- TITL -->
                <div class="stat-card">
                    <img src="../img/titl.png" alt="TITL" style="width: 44px; height: 44px; object-fit: contain; flex-shrink: 0;" />
                    <div>
                        <div class="text-2xl font-bold text-gray-900"><?= number_format($viewerTotal['TITL'], 0, ',', '.') ?></div>
                        <div class="text-sm font-medium text-gray-700">TITL</div>
                        <div class="text-xs text-gray-400">Total pemilih</div>
                    </div>
                </div>

                <!-- TAV -->
                <div class="stat-card">
                    <img src="../img/tav.png" alt="TAV" style="width: 44px; height: 44px; object-fit: contain; flex-shrink: 0;" />
                    <div>
                        <div class="text-2xl font-bold text-gray-900"><?= number_format($viewerTotal['TAV'], 0, ',', '.') ?></div>
                        <div class="text-sm font-medium text-gray-700">TAV</div>
                        <div class="text-xs text-gray-400">Total pemilih</div>
                    </div>
                </div>

            </div>
            <!-- ===== AKHIR KARTU RINGKASAN ===== -->


            <!-- ===== GRAFIK PIE CHART: 2 KOLOM BERDAMPINGAN (KANAN & KIRI) ===== -->
            <div class="charts-grid">

                <!-- 1. Pie Chart Data Viewer Bulanan (dengan Filter) -->
                <div class="card flex flex-col justify-between">
                    <div class="card-header border-b border-gray-100 pb-3">
                        <h2 class="text-base font-semibold text-gray-800">
                            Data Viewer Bulan <?= htmlspecialchars($bulanTahunDipilih) ?>
                        </h2>

                        <!-- ===== FILTER DROPDOWN ===== -->
                        <div class="filter-wrapper">
                            <button type="button" class="filter-btn" id="filterToggleBtn" onclick="toggleFilterDropdown()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                                </svg>
                                <?php if (!$isCurrentMonth): ?>
                                    <?= htmlspecialchars($bulanTahunDipilih) ?>
                                <?php else: ?>
                                    Filter Bulan
                                <?php endif; ?>
                                <svg class="chevron" xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </button>

                            <div class="filter-dropdown" id="filterDropdown">
                                <form method="GET" action="viewer.php" id="filterForm">
                                    <label for="selectBulan">Bulan</label>
                                    <select name="bulan" id="selectBulan">
                                        <?php foreach ($namaBulanIndo as $num => $nama): ?>
                                        <option value="<?= $num ?>" <?= ($num === $filterBulan) ? 'selected' : '' ?>>
                                            <?= $nama ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <label for="selectTahun">Tahun</label>
                                    <select name="tahun" id="selectTahun">
                                        <?php foreach ($daftarTahun as $thn): ?>
                                        <option value="<?= $thn ?>" <?= ($thn === $filterTahun) ? 'selected' : '' ?>>
                                            <?= $thn ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <button type="submit" class="filter-apply-btn">Terapkan Filter</button>
                                </form>
                                <?php if (!$isCurrentMonth): ?>
                                <a href="viewer.php" class="filter-reset-link">Kembali ke bulan ini</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- ===== AKHIR FILTER DROPDOWN ===== -->
                    </div>

                    <div class="p-5 flex-1 flex flex-col justify-center items-center">
                        <div style="position: relative; height: 240px; width: 100%; max-width: 320px;">
                            <canvas id="chartBulanan"></canvas>
                        </div>

                        <!-- Perincian Viewer per Jurusan Bulanan -->
                        <div class="rincian-grid text-xs">
                            <?php
                            $colorsJurusan = [
                                'RPL'  => 'bg-orange-500',
                                'TKR'  => 'bg-red-500',
                                'TITL' => 'bg-blue-500',
                                'TAV'  => 'bg-yellow-500',
                            ];
                            foreach ($viewerBulanan as $jur => $jumlah):
                                $persen = ($totalBulanan > 0) ? round(($jumlah / $totalBulanan) * 100, 1) : 0;
                            ?>
                            <div class="flex items-center justify-between p-2 rounded bg-gray-50 border border-gray-100">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full <?= $colorsJurusan[$jur] ?>"></span>
                                    <span class="font-semibold text-gray-700"><?= $jur ?></span>
                                </div>
                                <div class="text-right">
                                    <span class="font-bold text-gray-800"><?= $jumlah ?></span>
                                    <span class="text-gray-500 text-[10px]"> (<?= $persen ?>%)</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Total Viewer Bulanan -->
                    <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 text-center rounded-b-xl">
                        <span class="text-xs text-gray-500">Total Viewer <?= htmlspecialchars($bulanTahunDipilih) ?>: </span>
                        <span class="text-sm font-bold text-gray-800"><?= $totalBulanan ?> Viewer</span>
                    </div>
                </div>

                <!-- 2. Pie Chart Data Viewer Hari Ini -->
                <div class="card flex flex-col justify-between">
                    <div class="card-header border-b border-gray-100 pb-3">
                        <h2 class="text-base font-semibold text-gray-800">Data Viewer Hari Ini</h2>
                        <span class="text-xs text-gray-400"><?= htmlspecialchars($tanggalHariIni) ?></span>
                    </div>

                    <div class="p-5 flex-1 flex flex-col justify-center items-center">
                        <div style="position: relative; height: 240px; width: 100%; max-width: 320px;">
                            <canvas id="chartHariIni"></canvas>
                        </div>

                        <!-- Perincian Viewer per Jurusan Hari Ini -->
                        <div class="rincian-grid text-xs">
                            <?php
                            foreach ($viewerHariIni as $jur => $jumlah):
                                $persen = ($totalHariIni > 0) ? round(($jumlah / $totalHariIni) * 100, 1) : 0;
                            ?>
                            <div class="flex items-center justify-between p-2 rounded bg-gray-50 border border-gray-100">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full <?= $colorsJurusan[$jur] ?>"></span>
                                    <span class="font-semibold text-gray-700"><?= $jur ?></span>
                                </div>
                                <div class="text-right">
                                    <span class="font-bold text-gray-800"><?= $jumlah ?></span>
                                    <span class="text-gray-500 text-[10px]"> (<?= $persen ?>%)</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Total Viewer Hari Ini -->
                    <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 text-center rounded-b-xl">
                        <span class="text-xs text-gray-500">Total Viewer Hari Ini: </span>
                        <span class="text-sm font-bold text-gray-800"><?= $totalHariIni ?> Viewer</span>
                    </div>
                </div>

            </div>
            <!-- ===== AKHIR GRAFIK PIE CHART ===== -->


            <!-- ===== TABEL AKTIVITAS VIEWER ===== -->
            <div class="card">
                <div class="card-header border-b border-gray-100">
                    <h2 class="text-base font-semibold text-gray-800">Riwayat Aktivitas Viewer</h2>
                    <span class="text-xs text-gray-400">
                        <?= $totalAktivitas ?> aktivitas &bull; <?= htmlspecialchars($bulanTahunDipilih) ?>
                    </span>
                </div>
                <div class="riwayat-scroll-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="w-16 text-center">NO</th>
                                <th>JURUSAN</th>
                                <th>TIMESTAMP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daftarTimestamp)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-8 text-gray-400">
                                    Belum ada aktivitas viewer pada <?= htmlspecialchars($bulanTahunDipilih) ?>.
                                </td>
                            </tr>
                            <?php endif; ?>

                            <?php
                            // Warna badge jurusan
                            $warnaBadge = [
                                'RPL'  => 'bg-orange-100 text-orange-700 border border-orange-200',
                                'TKR'  => 'bg-red-100 text-red-700 border border-red-200',
                                'TITL' => 'bg-blue-100 text-blue-700 border border-blue-200',
                                'TAV'  => 'bg-yellow-100 text-yellow-700 border border-yellow-200',
                            ];
                            ?>
                            <?php foreach ($daftarTimestamp as $no => $item): ?>
                            <tr>
                                <td class="text-gray-400 text-sm text-center"><?= $no + 1 ?></td>
                                <td>
                                    <?php $kelas = $warnaBadge[$item['jurusan']] ?? 'bg-gray-100 text-gray-700'; ?>
                                    <span class="badge <?= $kelas ?> font-semibold px-2.5 py-1 rounded">
                                        <?= htmlspecialchars($item['jurusan']) ?>
                                    </span>
                                </td>
                                <td class="text-gray-600 text-xs font-mono"><?= htmlspecialchars($item['waktu']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- ===== AKHIR TABEL AKTIVITAS VIEWER ===== -->

        </div>
    </main>

    <script>
    // ===== Sidebar mobile =====
    function openSidebar() {
        document.getElementById('sidebar').classList.remove('-translate-x-full');
        document.getElementById('sidebarOverlay').classList.remove('hidden');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.add('-translate-x-full');
        document.getElementById('sidebarOverlay').classList.add('hidden');
    }

    // ===== Toggle Filter Dropdown =====
    function toggleFilterDropdown() {
        var dropdown = document.getElementById('filterDropdown');
        var btn      = document.getElementById('filterToggleBtn');
        var isOpen   = dropdown.classList.contains('open');

        if (isOpen) {
            dropdown.classList.remove('open');
            btn.classList.remove('open');
        } else {
            dropdown.classList.add('open');
            btn.classList.add('open');
        }
    }

    // Tutup dropdown jika klik di luar
    document.addEventListener('click', function(e) {
        var wrapper  = document.querySelector('.filter-wrapper');
        var dropdown = document.getElementById('filterDropdown');
        var btn      = document.getElementById('filterToggleBtn');
        if (wrapper && !wrapper.contains(e.target)) {
            dropdown.classList.remove('open');
            btn.classList.remove('open');
        }
    });

    // Label jurusan (sama untuk kedua Pie Chart)
    var labelJurusan = ['RPL', 'TKR', 'TITL', 'TAV'];

    // Warna resmi konsisten per jurusan:
    // RPL  -> Orange (#f97316)
    // TKR  -> Red    (#ef4444)
    // TITL -> Blue   (#3b82f6)
    // TAV  -> Yellow (#eab308)
    var warnaJurusan = ['#f97316', '#ef4444', '#3b82f6', '#eab308'];

    // Helper tooltip agar saat kursor diarahkan ke Pie Chart muncul Jumlah + Persentase
    var configTooltip = {
        callbacks: {
            label: function(context) {
                var label = context.label || '';
                var value = context.parsed || 0;
                var dataset = context.dataset.data;
                var total = dataset.reduce(function(acc, curr) { return acc + curr; }, 0);
                var percentage = total > 0 ? ((value / total) * 100).toFixed(1) + '%' : '0%';
                return ' ' + label + ': ' + value + ' viewer (' + percentage + ')';
            }
        }
    };

    // ===== 1. PIE CHART BULANAN =====
    new Chart(document.getElementById('chartBulanan'), {
        type: 'pie',
        data: {
            labels: labelJurusan,
            datasets: [{
                data: [
                    <?= $viewerBulanan['RPL'] ?>,
                    <?= $viewerBulanan['TKR'] ?>,
                    <?= $viewerBulanan['TITL'] ?>,
                    <?= $viewerBulanan['TAV'] ?>
                ],
                backgroundColor: warnaJurusan,
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 12,
                        usePointStyle: true,
                        font: { family: 'Poppins', size: 12 }
                    }
                },
                tooltip: configTooltip
            }
        }
    });

    // ===== 2. PIE CHART HARI INI =====
    new Chart(document.getElementById('chartHariIni'), {
        type: 'pie',
        data: {
            labels: labelJurusan,
            datasets: [{
                data: [
                    <?= $viewerHariIni['RPL'] ?>,
                    <?= $viewerHariIni['TKR'] ?>,
                    <?= $viewerHariIni['TITL'] ?>,
                    <?= $viewerHariIni['TAV'] ?>
                ],
                backgroundColor: warnaJurusan,
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 12,
                        usePointStyle: true,
                        font: { family: 'Poppins', size: 12 }
                    }
                },
                tooltip: configTooltip
            }
        }
    });
    </script>

</body>
</html>
