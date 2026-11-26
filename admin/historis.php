<?php
// admin/historis.php - Halaman Data Historis
// Menampilkan data historis program studi dengan filter PTN, Tahun, search, pagination, dan CRUD lengkap

require_once __DIR__ . '/../koneksi/config.php';
require_once __DIR__ . '/components/auth.php';

$activePage = 'historis';
$pageTitle  = 'Data Historis';
$adminName  = 'Admin';

// =========================================================================
// PROSES CRUD (TAMBAH / EDIT / HAPUS)
// =========================================================================
$aksi = $_GET['aksi'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($aksi === 'tambah') {
        $id_prodi      = intval($_POST['id_prodi'] ?? 0);
        $tahun         = intval($_POST['tahun'] ?? 2025);
        $peminat       = intval($_POST['peminat'] ?? 0);
        $daya_tampung  = intval($_POST['daya_tampung'] ?? 0);
        $nilai_minimal = floatval($_POST['nilai_minimal'] ?? 0);
        $keketatan     = $peminat > 0 ? round(($daya_tampung / $peminat) * 100, 2) : 0;

        if ($id_prodi > 0 && $tahun > 2000) {
            $stmt = $conn->prepare("
                INSERT INTO tb_historis (id_prodi, tahun, peminat, daya_tampung, keketatan, nilai_minimal)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    peminat = VALUES(peminat),
                    daya_tampung = VALUES(daya_tampung),
                    keketatan = VALUES(keketatan),
                    nilai_minimal = VALUES(nilai_minimal)
            ");
            $stmt->bind_param("iiiidd", $id_prodi, $tahun, $peminat, $daya_tampung, $keketatan, $nilai_minimal);
            
            if ($stmt->execute()) {
                setFlash('success', "Data historis tahun {$tahun} berhasil disimpan!");
            } else {
                setFlash('error', "Gagal menyimpan data historis: " . $conn->error);
            }
        } else {
            setFlash('error', "Program studi dan Tahun wajib dipilih!");
        }
        header("Location: historis.php");
        exit;
    }

    if ($aksi === 'edit') {
        $id_historis   = intval($_POST['id_historis'] ?? 0);
        $id_prodi      = intval($_POST['id_prodi'] ?? 0);
        $tahun         = intval($_POST['tahun'] ?? 2025);
        $peminat       = intval($_POST['peminat'] ?? 0);
        $daya_tampung  = intval($_POST['daya_tampung'] ?? 0);
        $nilai_minimal = floatval($_POST['nilai_minimal'] ?? 0);
        $keketatan     = $peminat > 0 ? round(($daya_tampung / $peminat) * 100, 2) : 0;

        if ($id_historis > 0 && $id_prodi > 0) {
            $stmt = $conn->prepare("
                UPDATE tb_historis 
                SET id_prodi=?, tahun=?, peminat=?, daya_tampung=?, keketatan=?, nilai_minimal=? 
                WHERE id_historis=?
            ");
            $stmt->bind_param("iiiiddi", $id_prodi, $tahun, $peminat, $daya_tampung, $keketatan, $nilai_minimal, $id_historis);

            if ($stmt->execute()) {
                setFlash('success', "Data historis berhasil diperbarui!");
            } else {
                setFlash('error', "Gagal memperbarui data historis: " . $conn->error);
            }
        } else {
            setFlash('error', "ID data historis tidak valid!");
        }
        header("Location: historis.php");
        exit;
    }

    if ($aksi === 'hapus') {
        $id_historis = intval($_POST['id_historis'] ?? 0);
        if ($id_historis > 0) {
            $stmt = $conn->prepare("DELETE FROM tb_historis WHERE id_historis=?");
            $stmt->bind_param("i", $id_historis);
            if ($stmt->execute()) {
                setFlash('success', "Data historis berhasil dihapus!");
            } else {
                setFlash('error', "Gagal menghapus data historis: " . $conn->error);
            }
        }
        header("Location: historis.php");
        exit;
    }
}

// =========================================================================
// AMBIL DATA DARI DATABASE
// =========================================================================

// 1. Ambil daftar PTN untuk filter
$ptnRes = $conn->query("SELECT DISTINCT nama_univ FROM tb_universitas ORDER BY nama_univ ASC");
$daftarPTN = ['Semua PTN'];
while ($r = $ptnRes->fetch_assoc()) {
    $daftarPTN[] = $r['nama_univ'];
}

// 2. Ambil daftar tahun untuk filter
$thnRes = $conn->query("SELECT DISTINCT tahun FROM tb_historis ORDER BY tahun DESC");
$daftarTahun = ['Semua Tahun'];
while ($r = $thnRes->fetch_assoc()) {
    $daftarTahun[] = $r['tahun'];
}
if (count($daftarTahun) === 1) {
    $daftarTahun = ['Semua Tahun', 2025, 2024, 2023, 2022, 2021];
}

// 3. Ambil daftar prodi aktif untuk dropdown modal tambah/edit
$prodiOptRes = $conn->query("
    SELECT p.id_prodi, p.kode_prodi, mp.nama_prodi, u.nama_univ 
    FROM tb_prodi_univ p
    JOIN tb_universitas u ON p.id_univ = u.id_univ
    JOIN tb_master_prodi mp ON p.id_master_prodi = mp.id_master_prodi
    ORDER BY u.nama_univ ASC, mp.nama_prodi ASC
");
$daftarProdiOpt = [];
while ($po = $prodiOptRes->fetch_assoc()) {
    $daftarProdiOpt[] = $po;
}

// 4. Ambil data historis lengkap
$sqlHist = "
    SELECT 
        h.id_historis,
        h.id_prodi,
        h.tahun,
        h.peminat,
        h.daya_tampung,
        h.keketatan,
        h.nilai_minimal,
        p.kode_prodi,
        mp.nama_prodi,
        u.kode_univ,
        u.nama_univ
    FROM tb_historis h
    JOIN tb_prodi_univ p ON h.id_prodi = p.id_prodi
    JOIN tb_universitas u ON p.id_univ = u.id_univ
    JOIN tb_master_prodi mp ON p.id_master_prodi = mp.id_master_prodi
    ORDER BY h.tahun DESC, u.nama_univ ASC, mp.nama_prodi ASC
";
$resHist = $conn->query($sqlHist);
$historisList = [];
while ($row = $resHist->fetch_assoc()) {
    $historisList[] = [
        'id'        => intval($row['id_historis']),
        'id_prodi'  => intval($row['id_prodi']),
        'ptn'       => $row['nama_univ'],
        'prodi'     => $row['nama_prodi'],
        'kode'      => $row['kode_prodi'],
        'kode_univ' => $row['kode_univ'],
        'tahun'     => intval($row['tahun']),
        'peminat'   => intval($row['peminat']),
        'daya'      => intval($row['daya_tampung']),
        'keketatan' => floatval($row['keketatan']),
        'nilai'     => floatval($row['nilai_minimal']),
    ];
}

$historisJSON = json_encode($historisList);
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="description" content="Data Historis - Analisis data historis SNBP 5 tahun terakhir"/>
    <title>Data Historis — SNBP Admin 2026</title>
    <link rel="shortcut icon" href="../img/logo.png" type="image/png"/>

    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="../src/output.css"/>
</head>
<body class="bg-gray-50 min-h-screen">

    <?php include 'components/sidebar.php'; ?>
    <?php include 'components/header.php'; ?>

    <!-- FLOATING TOAST NOTIFICATION CONTAINER -->
    <div id="toastContainer" class="fixed top-20 right-5 z-50 flex flex-col gap-3 max-w-sm w-full pointer-events-none"></div>

    <main class="lg:ml-64 pt-16 min-h-screen">
        <div class="p-6">

            <!-- Judul Halaman -->
            <div class="page-header">
                <h1>Data Historis</h1>
                <p>Data peminat, daya tampung, dan keketatan (Total: <?= count($historisList) ?> Data Historis)</p>
            </div>

            <!-- ===== CARD TABEL HISTORIS ===== -->
            <div class="card">

                <!-- Header: Filter PTN + Filter Tahun + Search + Tombol Tambah -->
                <div class="card-header flex-wrap gap-3">

                    <!-- Filter PTN -->
                    <select class="form-select w-56 shrink-0" id="filterPTNHistoris"
                            onchange="filterHistoris()">
                        <?php foreach ($daftarPTN as $p): ?>
                            <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Filter Tahun -->
                    <select class="form-select w-40 shrink-0" id="filterTahun"
                            onchange="filterHistoris()">
                        <?php foreach ($daftarTahun as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Search -->
                    <div class="search-input-wrap flex-1 min-w-0 max-w-xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                        </svg>
                        <input type="text" class="form-input" id="searchHistoris"
                               placeholder="Cari program studi / PTN..."
                               oninput="filterHistoris()"/>
                    </div>

                    <!-- Tombol Tambah -->
                    <button onclick="openModalTambahHistoris()"
                            class="btn btn-green shrink-0"
                            id="btnTambahHistoris">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4v16m8-8H4"/>
                        </svg>
                        Tambah Data
                    </button>
                </div>

                <!-- Tabel Historis -->
                <div class="overflow-x-auto">
                    <table class="data-table" id="tableHistoris">
                        <thead>
                            <tr>
                                <th class="w-10 text-center">No</th>
                                <th class="w-20">Kode PTN</th>
                                <th>PTN</th>
                                <th class="w-24">Kode Prodi</th>
                                <th>Program Studi</th>
                                <th class="text-center">Tahun</th>
                                <th class="text-right">Peminat</th>
                                <th class="text-right">Daya Tampung</th>
                                <th class="text-right">Keketatan</th>
                                <th class="text-right">Nilai Min</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tableHistorisBody">
                            <!-- Diisi cepat via JavaScript renderHistorisTable() -->
                        </tbody>
                    </table>
                </div>

                <!-- Footer info data & Pagination -->
                <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between flex-wrap gap-4">
                    <div class="flex items-center gap-4">
                        <p class="text-sm text-gray-500">Menampilkan <span id="historisCount" class="font-semibold text-gray-800">0</span> data historis</p>
                        <div class="flex items-center gap-2 bg-gray-100 px-3 py-1 rounded-lg">
                            <button id="prevPageBtn" onclick="changePage(-1)" class="px-2 py-0.5 text-xs font-semibold rounded bg-white text-gray-700 hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed shadow-sm" disabled>&larr; Prev</button>
                            <span id="pageInfo" class="text-xs font-semibold text-gray-600 px-1">Hlmn 1 dari 1</span>
                            <button id="nextPageBtn" onclick="changePage(1)" class="px-2 py-0.5 text-xs font-semibold rounded bg-white text-gray-700 hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed shadow-sm">&rarr; Next</button>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-1.5">
                            <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                            <span class="text-xs text-gray-500">Edit</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-3 h-3 rounded-full bg-red-500"></div>
                            <span class="text-xs text-gray-500">Hapus</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>


    <!-- ==========================================
         MODAL: TAMBAH DATA HISTORIS
         ========================================== -->
    <div id="modalTambahHistoris" class="modal-overlay hidden">
        <div class="modal-box max-w-lg">
            <div class="modal-header-green">
                <div>
                    <h3 class="text-base font-semibold">Tambah Data Historis</h3>
                    <p class="text-xs text-green-100 mt-0.5">Isi data historis program studi</p>
                </div>
                <button type="button" onclick="closeModal('modalTambahHistoris')"
                        class="text-white hover:text-green-200 transition-colors ml-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="modal-body">
                <form method="POST" action="historis.php?aksi=tambah">
                    <div class="space-y-4">
                        <div>
                            <label class="form-label">Pilih PTN & Program Studi <span class="text-red-500">*</span></label>
                            <select name="id_prodi" class="form-select" id="tambah_h_prodi" required>
                                <option value="">-- Pilih Program Studi --</option>
                                <?php foreach ($daftarProdiOpt as $po): ?>
                                    <option value="<?= $po['id_prodi'] ?>">
                                        <?= htmlspecialchars($po['nama_univ']) ?> - <?= htmlspecialchars($po['nama_prodi']) ?> (<?= htmlspecialchars($po['kode_prodi']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Tahun SNBP <span class="text-red-500">*</span></label>
                            <select name="tahun" class="form-select" id="tambah_h_tahun" required>
                                <?php foreach (['2026', '2025', '2024', '2023', '2022', '2021', '2020'] as $t): ?>
                                    <option value="<?= $t ?>" <?= $t === '2025' ? 'selected' : '' ?>><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="form-label">Peminat <span class="text-red-500">*</span></label>
                                <input type="number" name="peminat" class="form-input" id="tambah_h_peminat" placeholder="Contoh: 500" required/>
                            </div>
                            <div>
                                <label class="form-label">Daya Tampung <span class="text-red-500">*</span></label>
                                <input type="number" name="daya_tampung" class="form-input" id="tambah_h_daya" placeholder="Contoh: 50" required/>
                            </div>
                            <div>
                                <label class="form-label">Nilai Min</label>
                                <input type="number" step="0.01" name="nilai_minimal" class="form-input" id="tambah_h_nilai" placeholder="Contoh: 85.50" value="85.00"/>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" onclick="closeModal('modalTambahHistoris')" class="btn btn-gray">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-green">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"/>
                            </svg>
                            Simpan Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- ==========================================
         MODAL: EDIT DATA HISTORIS
         ========================================== -->
    <div id="modalEditHistoris" class="modal-overlay hidden">
        <div class="modal-box max-w-lg">
            <div class="modal-header-green">
                <div>
                    <h3 class="text-base font-semibold">Edit Data Historis</h3>
                    <p class="text-xs text-green-100 mt-0.5">Ubah data peminat & daya tampung</p>
                </div>
                <button type="button" onclick="closeModal('modalEditHistoris')"
                        class="text-white hover:text-green-200 transition-colors ml-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="modal-body">
                <form method="POST" action="historis.php?aksi=edit">
                    <!-- Hidden ID Historis -->
                    <input type="hidden" name="id_historis" id="edit_h_id" value=""/>

                    <div class="space-y-4">
                        <div>
                            <label class="form-label">Pilih PTN & Program Studi <span class="text-red-500">*</span></label>
                            <select name="id_prodi" class="form-select" id="edit_h_prodi" required>
                                <?php foreach ($daftarProdiOpt as $po): ?>
                                    <option value="<?= $po['id_prodi'] ?>">
                                        <?= htmlspecialchars($po['nama_univ']) ?> - <?= htmlspecialchars($po['nama_prodi']) ?> (<?= htmlspecialchars($po['kode_prodi']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Tahun SNBP <span class="text-red-500">*</span></label>
                            <select name="tahun" class="form-select" id="edit_h_tahun" required>
                                <?php foreach (['2026', '2025', '2024', '2023', '2022', '2021', '2020'] as $t): ?>
                                    <option value="<?= $t ?>"><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="form-label">Peminat <span class="text-red-500">*</span></label>
                                <input type="number" name="peminat" class="form-input" id="edit_h_peminat" required/>
                            </div>
                            <div>
                                <label class="form-label">Daya Tampung <span class="text-red-500">*</span></label>
                                <input type="number" name="daya_tampung" class="form-input" id="edit_h_daya" required/>
                            </div>
                            <div>
                                <label class="form-label">Nilai Min</label>
                                <input type="number" step="0.01" name="nilai_minimal" class="form-input" id="edit_h_nilai"/>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" onclick="closeModal('modalEditHistoris')" class="btn btn-gray">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-green">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"/>
                            </svg>
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- ==========================================
         MODAL: HAPUS DATA HISTORIS
         ========================================== -->
    <div id="modalHapusHistoris" class="modal-overlay hidden">
        <div class="modal-box max-w-sm">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667
                                1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34
                                16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <h3 class="text-base font-semibold text-gray-900">Hapus Data Historis</h3>
                </div>
                <button type="button" onclick="closeModal('modalHapusHistoris')"
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="modal-body text-center">
                <form method="POST" action="historis.php?aksi=hapus">
                    <!-- Hidden ID Historis -->
                    <input type="hidden" name="id_historis" id="hapus_h_id" value=""/>

                    <p class="text-sm text-gray-600 mb-4">
                        Apakah Anda yakin ingin menghapus data historis berikut?
                    </p>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 text-left">
                        <p class="font-semibold text-gray-900" id="hapus_h_prodi">-</p>
                        <p class="text-xs text-green-700 mt-0.5" id="hapus_h_ptn">-</p>
                        <p class="text-xs text-gray-500 mt-1">Tahun: <span class="font-semibold text-gray-800" id="hapus_h_tahun">-</span></p>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" onclick="closeModal('modalHapusHistoris')" class="btn btn-gray flex-1 justify-center">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-red flex-1 justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0
                                    01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1
                                    1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Hapus
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script>
    // Data historis dari PHP (JSON)
    var historisData = <?= $historisJSON ?>;

    // ===== Sidebar Mobile =====
    function openSidebar() {
        document.getElementById('sidebar').classList.remove('-translate-x-full');
        document.getElementById('sidebarOverlay').classList.remove('hidden');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.add('-translate-x-full');
        document.getElementById('sidebarOverlay').classList.add('hidden');
    }

    // ===== Helper: buka/tutup modal =====
    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.modal-overlay').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (e.target === el) {
                el.classList.add('hidden');
                document.body.style.overflow = '';
            }
        });
    });

    function openModalTambahHistoris() {
        document.getElementById('tambah_h_prodi').value   = '';
        document.getElementById('tambah_h_tahun').value   = '2025';
        document.getElementById('tambah_h_peminat').value = '';
        document.getElementById('tambah_h_daya').value    = '';
        document.getElementById('tambah_h_nilai').value   = '85.00';
        openModal('modalTambahHistoris');
    }

    function openModalEditHistoris(id, id_prodi, tahun, peminat, daya, nilai) {
        document.getElementById('edit_h_id').value      = id;
        document.getElementById('edit_h_prodi').value   = id_prodi;
        document.getElementById('edit_h_tahun').value   = tahun;
        document.getElementById('edit_h_peminat').value = peminat;
        document.getElementById('edit_h_daya').value    = daya;
        document.getElementById('edit_h_nilai').value   = nilai;
        openModal('modalEditHistoris');
    }

    function openModalHapusHistoris(id, prodi, ptn, tahun) {
        document.getElementById('hapus_h_id').value     = id;
        document.getElementById('hapus_h_prodi').textContent = prodi;
        document.getElementById('hapus_h_ptn').textContent   = ptn;
        document.getElementById('hapus_h_tahun').textContent = tahun;
        openModal('modalHapusHistoris');
    }

    // ===== TOAST NOTIFICATION SYSTEM =====
    function showToast(type, text) {
        var container = document.getElementById('toastContainer');
        if (!container) return;

        var toast = document.createElement('div');
        toast.className = 'pointer-events-auto bg-white rounded-xl shadow-xl border p-4 flex items-start gap-3 transition-all duration-300 transform translate-x-10 opacity-0 ' + 
            (type === 'success' ? 'border-green-200 border-l-4 border-l-green-500' : 'border-red-200 border-l-4 border-l-red-500');

        var iconHtml = type === 'success' 
            ? '<div class="w-6 h-6 rounded-full bg-green-100 text-green-600 flex items-center justify-center shrink-0 mt-0.5"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg></div>'
            : '<div class="w-6 h-6 rounded-full bg-red-100 text-red-600 flex items-center justify-center shrink-0 mt-0.5"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><line x1="12" y1="8" x2="12" y2="12" stroke-width="2"/><line x1="12" y1="16" x2="12.01" y2="16" stroke-width="2.5"/></svg></div>';

        toast.innerHTML = iconHtml + 
            '<div class="flex-1 text-xs font-semibold text-gray-800 leading-snug pt-0.5">' + text + '</div>' + 
            '<button type="button" class="text-gray-400 hover:text-gray-600 transition-colors p-0.5 font-bold text-xs" onclick="dismissToast(this.parentElement)">✕</button>';

        container.appendChild(toast);

        setTimeout(function() {
            toast.classList.remove('translate-x-10', 'opacity-0');
        }, 10);

        setTimeout(function() {
            dismissToast(toast);
        }, 4000);
    }

    function dismissToast(toast) {
        if (!toast || toast.dataset.dismissed) return;
        toast.dataset.dismissed = 'true';
        toast.classList.add('translate-x-10', 'opacity-0');
        setTimeout(function() {
            if (toast.parentElement) toast.parentElement.removeChild(toast);
        }, 300);
    }

    // ===== RENDER TABEL HISTORIS & PAGINATION =====
    var currentPage  = 1;
    var itemsPerPage = 50;

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeJs(str) {
        if (!str) return '';
        return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    }

    function getFilteredData() {
        var selectedPTN   = document.getElementById('filterPTNHistoris').value;
        var selectedTahun = document.getElementById('filterTahun').value;
        var search        = document.getElementById('searchHistoris').value.toLowerCase().trim();

        return historisData.filter(function(row) {
            var matchPTN   = (selectedPTN === 'Semua PTN') || (row.ptn === selectedPTN);
            var matchTahun = (selectedTahun === 'Semua Tahun') || (String(row.tahun) === selectedTahun);
            var matchSearch = !search || 
                              row.prodi.toLowerCase().includes(search) || 
                              row.ptn.toLowerCase().includes(search) ||
                              row.kode.toLowerCase().includes(search) ||
                              row.kode_univ.toLowerCase().includes(search);

            return matchPTN && matchTahun && matchSearch;
        });
    }

    function renderHistorisTable() {
        var filtered   = getFilteredData();
        var totalItems = filtered.length;
        var totalPages = Math.ceil(totalItems / itemsPerPage) || 1;

        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        var start = (currentPage - 1) * itemsPerPage;
        var end   = start + itemsPerPage;
        var pageItems = filtered.slice(start, end);

        var tbody = document.getElementById('tableHistorisBody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (pageItems.length === 0) {
            tbody.innerHTML = '<tr><td colspan="11" class="text-center py-8 text-gray-500">Tidak ada data historis yang cocok dengan filter.</td></tr>';
        } else {
            pageItems.forEach(function(row, idx) {
                var trNum = start + idx + 1;
                var tr = document.createElement('tr');
                var keketatanClass = row.keketatan < 5 ? 'text-red-600' : 'text-green-700';

                tr.innerHTML = 
                    '<td class="text-gray-400 text-sm text-center">' + trNum + '</td>' +
                    '<td><span class="font-mono text-xs font-semibold text-blue-700 bg-blue-50 px-2 py-0.5 rounded">' + escapeHtml(row.kode_univ) + '</span></td>' +
                    '<td class="text-sm text-gray-600">' + escapeHtml(row.ptn) + '</td>' +
                    '<td><span class="font-mono text-xs font-semibold text-green-700 bg-green-50 px-2 py-0.5 rounded">' + escapeHtml(row.kode) + '</span></td>' +
                    '<td><div class="font-medium text-gray-800">' + escapeHtml(row.prodi) + '</div></td>' +
                    '<td class="text-center"><span class="badge badge-green">' + row.tahun + '</span></td>' +
                    '<td class="text-right text-gray-700">' + Number(row.peminat).toLocaleString('id-ID') + '</td>' +
                    '<td class="text-right text-gray-700">' + Number(row.daya).toLocaleString('id-ID') + '</td>' +
                    '<td class="text-right"><span class="font-semibold ' + keketatanClass + '">' + Number(row.keketatan).toFixed(2) + '%</span></td>' +
                    '<td class="text-right font-semibold text-gray-800">' + Number(row.nilai).toFixed(2) + '</td>' +
                    '<td>' +
                        '<div class="flex items-center justify-center gap-1.5">' +
                            '<button onclick="openModalEditHistoris(' + row.id + ', ' + row.id_prodi + ', ' + row.tahun + ', ' + row.peminat + ', ' + row.daya + ', ' + row.nilai + ')" class="btn btn-sm btn-yellow" title="Edit Data"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>' +
                            '<button onclick="openModalHapusHistoris(' + row.id + ', \'' + escapeJs(row.prodi) + '\', \'' + escapeJs(row.ptn) + '\', ' + row.tahun + ')" class="btn btn-sm btn-red" title="Hapus Data"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>' +
                        '</div>' +
                    '</td>';
                tbody.appendChild(tr);
            });
        }

        var countEl = document.getElementById('historisCount');
        if (countEl) countEl.textContent = totalItems.toLocaleString('id-ID');

        var pageInfo = document.getElementById('pageInfo');
        if (pageInfo) pageInfo.textContent = 'Hlmn ' + currentPage + ' dari ' + totalPages;

        var prevBtn = document.getElementById('prevPageBtn');
        var nextBtn = document.getElementById('nextPageBtn');
        if (prevBtn) prevBtn.disabled = (currentPage <= 1);
        if (nextBtn) nextBtn.disabled = (currentPage >= totalPages);
    }

    function changePage(delta) {
        currentPage += delta;
        renderHistorisTable();
    }

    function filterHistoris() {
        currentPage = 1;
        renderHistorisTable();
    }

    document.addEventListener('DOMContentLoaded', function() {
        renderHistorisTable();
    });
    </script>

    <!-- TRIGER TOAST DARI PHP -->
    <script>
    <?php if ($flash): ?>
        showToast(<?= json_encode($flash['type']) ?>, <?= json_encode($flash['text']) ?>);
    <?php endif; ?>
    </script>
</body>
</html>