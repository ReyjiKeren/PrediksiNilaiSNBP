<?php
// AJAX: Ambil kode prodi berikutnya untuk PTN tertentu
if (isset($_GET['action']) && $_GET['action'] === 'get_next_kode') {
    require_once __DIR__ . '/../koneksi/config.php';
    $id_univ = intval($_GET['id_univ'] ?? 0);
    if ($id_univ > 0) {
        $res = $conn->query("SELECT kode_univ FROM tb_universitas WHERE id_univ = $id_univ");
        $row = $res ? $res->fetch_assoc() : null;
        if ($row) {
            $kodeUniv = $row['kode_univ'];
            // Hitung jumlah prodi yang sudah ada di PTN ini
            $cnt = $conn->query("SELECT COUNT(*) AS c FROM tb_prodi_univ WHERE id_univ = $id_univ");
            $cRow = $cnt->fetch_assoc();
            $next = intval($cRow['c']) + 1;
            $kodeProdi = $kodeUniv . str_pad($next, 4, '0', STR_PAD_LEFT);
            echo json_encode(['kode' => $kodeProdi, 'prefix' => $kodeUniv, 'next' => $next]);
        } else {
            echo json_encode(['kode' => '', 'prefix' => '', 'next' => 1]);
        }
    } else {
        echo json_encode(['kode' => '', 'prefix' => '', 'next' => 1]);
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'get_historis') {
    require_once __DIR__ . '/../koneksi/config.php';
    $id_prodi = intval($_GET['id_prodi'] ?? 0);
    $histList = [];
    if ($id_prodi > 0) {
        $resHist = $conn->query("SELECT tahun, peminat, daya_tampung, keketatan, nilai_minimal FROM tb_historis WHERE id_prodi = $id_prodi ORDER BY tahun ASC");
        if ($resHist && $resHist->num_rows > 0) {
            while ($h = $resHist->fetch_assoc()) {
                $histList[] = [
                    'tahun'     => intval($h['tahun']),
                    'peminat'   => intval($h['peminat']),
                    'daya'      => intval($h['daya_tampung']),
                    'keketatan' => number_format(floatval($h['keketatan']), 2) . '%',
                    'nilai'     => number_format(floatval($h['nilai_minimal']), 2),
                ];
            }
        }
    }
    echo json_encode($histList);
    exit;
}
// admin/prodi.php - Halaman Data Program Studi
// Menampilkan daftar program studi dengan filter PTN, search, modal detail, modal tambah, edit, dan hapus

require_once __DIR__ . '/../koneksi/config.php';
require_once __DIR__ . '/components/auth.php';

$activePage = 'prodi';
$pageTitle  = 'Data Program Studi';
$adminName  = 'Admin';

// =========================================================================
// PROSES CRUD (TAMBAH / EDIT / HAPUS)
// =========================================================================
$aksi = $_GET['aksi'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // -------------------------------------------------------------
    // 1. TAMBAH PRODI
    // -------------------------------------------------------------
    if ($aksi === 'tambah') {
        $id_univ       = intval($_POST['id_univ'] ?? 0);
        $nama_prodi    = trim($_POST['nama_prodi'] ?? '');
        $kode_prodi    = trim($_POST['kode_prodi'] ?? '');
        $jenjang       = trim($_POST['jenjang'] ?? 'S1');
        $daya_tampung  = intval($_POST['daya_tampung'] ?? 0);
        $nilai_minimal = floatval($_POST['nilai_minimal'] ?? 0);
        $portofolio    = trim($_POST['portofolio'] ?? 'Tidak Ada');

        if ($id_univ > 0 && !empty($nama_prodi)) {
            // Ambil kode_univ jika kode_prodi tidak diisi manual
            if (empty($kode_prodi)) {
                $uRes = $conn->query("SELECT kode_univ FROM tb_universitas WHERE id_univ = $id_univ");
                $uRow = $uRes ? $uRes->fetch_assoc() : null;
                $kUniv = $uRow ? $uRow['kode_univ'] : '1000';
                $kode_prodi = $kUniv . mt_rand(1000, 9999);
            }

            // 1. Cek atau masukkan ke tb_master_prodi
            $stmtM = $conn->prepare("SELECT id_master_prodi FROM tb_master_prodi WHERE nama_prodi = ?");
            $stmtM->bind_param("s", $nama_prodi);
            $stmtM->execute();
            $resM = $stmtM->get_result();

            if ($rowM = $resM->fetch_assoc()) {
                $id_master_prodi = $rowM['id_master_prodi'];
            } else {
                $stmtInsM = $conn->prepare("INSERT INTO tb_master_prodi (nama_prodi) VALUES (?)");
                $stmtInsM->bind_param("s", $nama_prodi);
                $stmtInsM->execute();
                $id_master_prodi = $conn->insert_id;
            }

            // 2. Insert ke tb_prodi_univ
            $stmtP = $conn->prepare("INSERT INTO tb_prodi_univ (id_univ, id_master_prodi, kode_prodi, jenjang, daya_tampung, nilai_minimal, portofolio) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtP->bind_param("iissids", $id_univ, $id_master_prodi, $kode_prodi, $jenjang, $daya_tampung, $nilai_minimal, $portofolio);

            if ($stmtP->execute()) {
                setFlash('success', "Program Studi '{$nama_prodi}' berhasil ditambahkan!");
            } else {
                setFlash('error', "Gagal menambahkan prodi: " . $conn->error);
            }
        } else {
            setFlash('error', "PTN dan Nama Program Studi wajib diisi!");
        }
        header("Location: prodi.php");
        exit;
    }

    // -------------------------------------------------------------
    // 2. EDIT PRODI
    // -------------------------------------------------------------
    if ($aksi === 'edit') {
        $id_prodi      = intval($_POST['id_prodi'] ?? 0);
        $id_univ       = intval($_POST['id_univ'] ?? 0);
        $nama_prodi    = trim($_POST['nama_prodi'] ?? '');
        $kode_prodi    = trim($_POST['kode_prodi'] ?? '');
        $jenjang       = trim($_POST['jenjang'] ?? 'S1');
        $daya_tampung  = intval($_POST['daya_tampung'] ?? 0);
        $nilai_minimal = floatval($_POST['nilai_minimal'] ?? 0);
        $portofolio    = trim($_POST['portofolio'] ?? 'Tidak Ada');

        if ($id_prodi > 0 && $id_univ > 0 && !empty($nama_prodi)) {
            // Cek atau masukkan ke tb_master_prodi
            $stmtM = $conn->prepare("SELECT id_master_prodi FROM tb_master_prodi WHERE nama_prodi = ?");
            $stmtM->bind_param("s", $nama_prodi);
            $stmtM->execute();
            $resM = $stmtM->get_result();

            if ($rowM = $resM->fetch_assoc()) {
                $id_master_prodi = $rowM['id_master_prodi'];
            } else {
                $stmtInsM = $conn->prepare("INSERT INTO tb_master_prodi (nama_prodi) VALUES (?)");
                $stmtInsM->bind_param("s", $nama_prodi);
                $stmtInsM->execute();
                $id_master_prodi = $conn->insert_id;
            }

            // Update tb_prodi_univ
            $stmtP = $conn->prepare("UPDATE tb_prodi_univ SET id_univ=?, id_master_prodi=?, kode_prodi=?, jenjang=?, daya_tampung=?, nilai_minimal=?, portofolio=? WHERE id_prodi=?");
            $stmtP->bind_param("iissidsi", $id_univ, $id_master_prodi, $kode_prodi, $jenjang, $daya_tampung, $nilai_minimal, $portofolio, $id_prodi);

            if ($stmtP->execute()) {
                setFlash('success', "Program Studi '{$nama_prodi}' berhasil diperbarui!");
            } else {
                setFlash('error', "Gagal memperbarui prodi: " . $conn->error);
            }
        } else {
            setFlash('error', "Data yang diinput tidak lengkap!");
        }
        header("Location: prodi.php");
        exit;
    }

    // -------------------------------------------------------------
    // 3. HAPUS PRODI
    // -------------------------------------------------------------
    if ($aksi === 'hapus') {
        $id_prodi = intval($_POST['id_prodi'] ?? 0);
        if ($id_prodi > 0) {
            // Hapus data historis terkait
            $conn->query("DELETE FROM tb_historis WHERE id_prodi = $id_prodi");

            $stmt = $conn->prepare("DELETE FROM tb_prodi_univ WHERE id_prodi = ?");
            $stmt->bind_param("i", $id_prodi);

            if ($stmt->execute()) {
                setFlash('success', "Program Studi berhasil dihapus!");
            } else {
                setFlash('error', "Gagal menghapus prodi: " . $conn->error);
            }
        }
        header("Location: prodi.php");
        exit;
    }
}

// =========================================================================
// AMBIL DATA DARI DATABASE
// =========================================================================

// 1. Ambil daftar PTN untuk dropdown filter dan form
$ptnQuery = $conn->query("SELECT id_univ, kode_univ, nama_univ FROM tb_universitas ORDER BY nama_univ ASC");
$daftarPTN = [];
if ($ptnQuery) {
    while ($row = $ptnQuery->fetch_assoc()) {
        $daftarPTN[] = $row;
    }
}

// 2. Ambil daftar Program Studi lengkap dengan JOIN
$sqlProdi = "
    SELECT 
        p.id_prodi,
        p.id_univ,
        p.id_master_prodi,
        p.kode_prodi,
        p.jenjang,
        p.daya_tampung,
        p.nilai_minimal,
        p.portofolio,
        u.nama_univ,
        u.kode_univ,
        mp.nama_prodi
    FROM tb_prodi_univ p
    JOIN tb_universitas u ON p.id_univ = u.id_univ
    JOIN tb_master_prodi mp ON p.id_master_prodi = mp.id_master_prodi
    ORDER BY p.kode_prodi ASC
";
$resProdi = $conn->query($sqlProdi);

$prodiList = [];
if ($resProdi) {
    while ($p = $resProdi->fetch_assoc()) {
        $prodiList[] = [
            'id'       => intval($p['id_prodi']),
            'id_univ'  => intval($p['id_univ']),
            'kode'     => $p['kode_prodi'],
            'nama'     => $p['nama_prodi'],
            'ptn'      => $p['nama_univ'],
            'jenjang'  => $p['jenjang'],
            'daya'     => intval($p['daya_tampung']),
            'nilai'    => floatval($p['nilai_minimal']),
            'porto'    => $p['portofolio'] ?? 'Tidak Ada',
        ];
    }
}

$prodiJSON = json_encode($prodiList);
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="description" content="Data Program Studi - Kelola program studi berdasarkan PTN di SNBP Admin 2026"/>
    <title>Data Program Studi — SNBP Admin 2026</title>
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

            <!-- Flash Message Toast -->
            <?php if ($flash): ?>
            <div class="mb-5 p-4 rounded-xl flex items-center justify-between <?= $flash['type'] === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800' ?>">
                <div class="flex items-center gap-3">
                    <?php if ($flash['type'] === 'success'): ?>
                        <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    <?php else: ?>
                        <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    <?php endif; ?>
                    <span class="text-sm font-medium"><?= htmlspecialchars($flash['text']) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <?php endif; ?>

            <!-- Judul Halaman -->
            <div class="page-header">
                <h1>Data Program Studi</h1>
                <p>Kelola data program studi berdasarkan PTN (Total: <?= count($prodiList) ?> Program Studi)</p>
            </div>

            <!-- ===== CARD TABEL PRODI ===== -->
            <div class="card">

                <!-- Header: filter PTN + search + tombol tambah -->
                <div class="card-header flex-wrap gap-3">
                    <!-- Dropdown filter PTN -->
                    <div class="w-64 shrink-0">
                        <select class="form-select" id="filterPTN" onchange="filterProdi()">
                            <option value="Semua PTN">-- Semua PTN --</option>
                            <?php foreach ($daftarPTN as $u): ?>
                                <option value="<?= htmlspecialchars($u['nama_univ']) ?>">
                                    <?= htmlspecialchars($u['nama_univ']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Search bar -->
                    <div class="search-input-wrap flex-1 min-w-0 max-w-xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                        </svg>
                        <input id="searchProdi"
                               type="text"
                               class="form-input"
                               placeholder="Cari program studi / kode..."
                               oninput="filterProdi()"/>
                    </div>

                    <!-- Tombol Tambah Program Studi -->
                    <button onclick="openModalTambah()"
                            class="btn btn-green shrink-0"
                            id="btnTambahProdi">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4v16m8-8H4"/>
                        </svg>
                        Tambah Program Studi
                    </button>
                </div>

                <!-- Tabel Program Studi -->
                <div class="overflow-x-auto">
                    <table class="data-table" id="tableProdi">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Program Studi & PTN</th>
                                <th class="text-center">Jenjang</th>
                                <th class="text-right">Daya Tampung</th>
                                <th class="text-right">Nilai Min</th>
                                <th class="text-center">Portofolio</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tableProdiBody">
                            <!-- Diisi secara cepat & ringan oleh JavaScript renderProdiTable() -->
                        </tbody>
                    </table>
                </div>

                <!-- Legenda warna tombol & Pagination -->
                <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between flex-wrap gap-4">
                    <div class="flex items-center gap-4">
                        <p class="text-sm text-gray-500">Menampilkan <span id="prodiCount" class="font-semibold text-gray-800">0</span> program studi</p>
                        <div class="flex items-center gap-2 bg-gray-100 px-3 py-1 rounded-lg">
                            <button id="prevPageBtn" onclick="changePage(-1)" class="px-2 py-0.5 text-xs font-semibold rounded bg-white text-gray-700 hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed shadow-sm" disabled>&larr; Prev</button>
                            <span id="pageInfo" class="text-xs font-semibold text-gray-600 px-1">Hlmn 1 dari 1</span>
                            <button id="nextPageBtn" onclick="changePage(1)" class="px-2 py-0.5 text-xs font-semibold rounded bg-white text-gray-700 hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed shadow-sm">&rarr; Next</button>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-1.5">
                            <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                            <span class="text-xs text-gray-500">Detail</span>
                        </div>
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
            <!-- ===== AKHIR CARD TABEL PRODI ===== -->

        </div>
    </main>


    <!-- ==========================================
         MODAL: DETAIL PROGRAM STUDI
         ========================================== -->
    <div id="modalDetail" class="modal-overlay hidden">
        <div class="modal-box max-w-2xl">

            <!-- Header hijau -->
            <div class="modal-header-green">
                <div>
                    <h3 class="text-lg font-bold" id="detail_nama">-</h3>
                    <p class="text-sm text-green-100 mt-0.5" id="detail_sub">-</p>
                </div>
                <button onclick="closeModal('modalDetail')"
                        class="text-white hover:text-green-200 transition-colors ml-4 shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="modal-body">
                <!-- 4 Info Card -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                    <div class="info-card">
                        <p class="text-xs text-gray-500 mb-1">Kode Prodi</p>
                        <p class="text-base font-semibold text-gray-800" id="detail_kode">-</p>
                    </div>
                    <div class="info-card">
                        <p class="text-xs text-gray-500 mb-1">Jenjang</p>
                        <p class="text-base font-semibold text-gray-800" id="detail_jenjang">-</p>
                    </div>
                    <div class="info-card">
                        <p class="text-xs text-gray-500 mb-1">Daya Tampung</p>
                        <p class="text-base font-semibold text-gray-800" id="detail_daya">-</p>
                    </div>
                    <div class="info-card">
                        <p class="text-xs text-gray-500 mb-1">Portofolio</p>
                        <p class="text-base font-semibold text-gray-800" id="detail_porto">-</p>
                    </div>
                </div>

                <!-- Tabel Historis -->
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center gap-2">
                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0
                                002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0
                                002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        <h4 class="text-sm font-semibold text-gray-800">Data Historis</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Tahun</th>
                                    <th class="text-right">Peminat</th>
                                    <th class="text-right">Daya Tampung</th>
                                    <th class="text-right">Keketatan</th>
                                    <!--<th class="text-right">Nilai Minimal</th> -->
                                </tr>
                            </thead>
                            <tbody id="detail_historis_body">
                                <!-- Diisi oleh JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tombol tutup -->
                <div class="flex justify-end mt-5">
                    <button onclick="closeModal('modalDetail')" class="btn btn-gray">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>


    <!-- ==========================================
         MODAL: TAMBAH PROGRAM STUDI
         ========================================== -->
    <div id="modalTambah" class="modal-overlay hidden">
        <div class="modal-box max-w-lg">
            <div class="modal-header-green">
                <div>
                    <h3 class="text-base font-semibold">Tambah Program Studi</h3>
                    <p class="text-xs text-green-100 mt-0.5">Isi data program studi baru</p>
                </div>
                <button type="button" onclick="closeModal('modalTambah')"
                        class="text-white hover:text-green-200 transition-colors ml-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="modal-body">
                <form method="POST" action="prodi.php?aksi=tambah">
                    <div class="space-y-4">
                        <div>
                            <label class="form-label">PTN <span class="text-red-500">*</span></label>
                            <select name="id_univ" class="form-select" id="tambah_ptn" required onchange="autoFillKode(this)">
                                <option value="">-- Pilih PTN --</option>
                                <?php foreach ($daftarPTN as $u): ?>
                                    <option value="<?= $u['id_univ'] ?>" data-kode="<?= htmlspecialchars($u['kode_univ']) ?>">
                                        <?= htmlspecialchars($u['nama_univ']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Kode Prodi</label>
                                <input type="text" name="kode_prodi" class="form-input" id="tambah_kode"
                                       placeholder="Contoh: 13611003"/>
                            </div>
                            <div>
                                <label class="form-label">Jenjang</label>
                                <select name="jenjang" class="form-select" id="tambah_jenjang">
                                    <option value="S1">S1</option>
                                    <option value="D4">D4</option>
                                    <option value="D3">D3</option>
                                    <option value="S2">S2</option>
                                    <option value="S3">S3</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Nama Program Studi <span class="text-red-500">*</span></label>
                            <input type="text" name="nama_prodi" class="form-input" id="tambah_nama"
                                   placeholder="Contoh: Psikologi" required/>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Daya Tampung</label>
                                <input type="number" name="daya_tampung" class="form-input" id="tambah_daya"
                                       placeholder="Contoh: 68" value="50"/>
                            </div>
                            <div>
                                <label class="form-label">Nilai Minimal</label>
                                <input type="number" step="0.01" name="nilai_minimal" class="form-input" id="tambah_nilai"
                                       placeholder="Contoh: 91.90" value="80.00"/>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Portofolio</label>
                            <select name="portofolio" class="form-select" id="tambah_porto">
                                <option value="Tidak Ada">Tidak Ada</option>
                                <option value="Wajib">Wajib</option>
                                <option value="Opsional">Opsional</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" onclick="closeModal('modalTambah')" class="btn btn-gray">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-green">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"/>
                            </svg>
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- ==========================================
         MODAL: EDIT PROGRAM STUDI
         ========================================== -->
    <div id="modalEdit" class="modal-overlay hidden">
        <div class="modal-box max-w-lg">
            <div class="modal-header-green">
                <div>
                    <h3 class="text-base font-semibold">Edit Program Studi</h3>
                    <p class="text-xs text-green-100 mt-0.5">Ubah data program studi</p>
                </div>
                <button type="button" onclick="closeModal('modalEdit')"
                        class="text-white hover:text-green-200 transition-colors ml-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="modal-body">
                <form method="POST" action="prodi.php?aksi=edit">
                    <!-- Hidden ID Prodi -->
                    <input type="hidden" name="id_prodi" id="edit_id" value=""/>

                    <div class="space-y-4">
                        <div>
                            <label class="form-label">PTN <span class="text-red-500">*</span></label>
                            <select name="id_univ" class="form-select" id="edit_ptn" required>
                                <?php foreach ($daftarPTN as $u): ?>
                                    <option value="<?= $u['id_univ'] ?>">
                                        <?= htmlspecialchars($u['nama_univ']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Kode Prodi</label>
                                <input type="text" name="kode_prodi" class="form-input" id="edit_kode" required/>
                            </div>
                            <div>
                                <label class="form-label">Jenjang</label>
                                <select name="jenjang" class="form-select" id="edit_jenjang">
                                    <option value="S1">S1</option>
                                    <option value="D4">D4</option>
                                    <option value="D3">D3</option>
                                    <option value="S2">S2</option>
                                    <option value="S3">S3</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Nama Program Studi <span class="text-red-500">*</span></label>
                            <input type="text" name="nama_prodi" class="form-input" id="edit_nama" required/>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Daya Tampung</label>
                                <input type="number" name="daya_tampung" class="form-input" id="edit_daya"/>
                            </div>
                            <div>
                                <label class="form-label">Nilai Minimal</label>
                                <input type="number" step="0.01" name="nilai_minimal" class="form-input" id="edit_nilai"/>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Portofolio</label>
                            <select name="portofolio" class="form-select" id="edit_porto">
                                <option value="Tidak Ada">Tidak Ada</option>
                                <option value="Wajib">Wajib</option>
                                <option value="Opsional">Opsional</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" onclick="closeModal('modalEdit')" class="btn btn-gray">
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
         MODAL: HAPUS PROGRAM STUDI
         ========================================== -->
    <div id="modalHapus" class="modal-overlay hidden">
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
                    <h3 class="text-base font-semibold text-gray-900">Hapus Program Studi</h3>
                </div>
                <button type="button" onclick="closeModal('modalHapus')"
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="modal-body text-center">
                <form method="POST" action="prodi.php?aksi=hapus">
                    <!-- Hidden ID Prodi -->
                    <input type="hidden" name="id_prodi" id="hapus_id" value=""/>

                    <p class="text-sm text-gray-600 mb-4">
                        Apakah Anda yakin ingin menghapus program studi berikut?
                    </p>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4 text-left">
                        <p class="font-semibold text-gray-900" id="hapus_nama">-</p>
                        <p class="text-xs text-green-700 mt-1 font-medium" id="hapus_ptn">-</p>
                    </div>
                    <p class="text-xs text-red-500 mb-6">
                        ⚠️ Data historis prodi ini juga akan dihapus. Tindakan tidak dapat dibatalkan.
                    </p>
                    <div class="flex gap-3">
                        <button type="button" onclick="closeModal('modalHapus')" class="btn btn-gray flex-1 justify-center">
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
    // Data prodi dari PHP (JSON)
    var prodiData = <?= $prodiJSON ?>;

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

    // Tutup modal saat klik overlay
    document.querySelectorAll('.modal-overlay').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (e.target === el) {
                el.classList.add('hidden');
                document.body.style.overflow = '';
            }
        });
    });

    // ===== Cari prodi berdasarkan ID =====
    function findProdi(id) {
        return prodiData.find(function(p) { return p.id === id; });
    }

    // ===== Modal Detail =====
    function openModalDetail(id) {
        var p = findProdi(id);
        if (!p) return;

        document.getElementById('detail_nama').textContent    = p.nama;
        document.getElementById('detail_sub').textContent     = p.ptn + ' — ' + p.jenjang;
        document.getElementById('detail_kode').textContent    = p.kode;
        document.getElementById('detail_jenjang').textContent = p.jenjang;
        document.getElementById('detail_daya').textContent    = p.daya + ' kursi';
        document.getElementById('detail_porto').textContent   = p.porto;

        // Isi tabel data historis via AJAX
        var tbody = document.getElementById('detail_historis_body');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-gray-400">Memuat data historis...</td></tr>';

        openModal('modalDetail');

        fetch('prodi.php?action=get_historis&id_prodi=' + id)
            .then(function(r) { return r.json(); })
            .then(function(historis) {
                tbody.innerHTML = '';
                if (historis && historis.length > 0) {
                    historis.forEach(function(h) {
                        var tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td class="font-medium text-gray-800">' + h.tahun + '</td>' +
                            '<td class="text-right text-gray-700">' + Number(h.peminat).toLocaleString('id-ID') + '</td>' +
                            '<td class="text-right text-gray-700">' + h.daya + '</td>' +
                            '<td class="text-right font-semibold text-green-700">' + h.keketatan + '</td>' +
                            '<td class="text-right font-semibold text-gray-800">' + (h.nilai || '-') + '</td>';
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-gray-400">Belum ada data historis untuk prodi ini</td></tr>';
                }
            })
            .catch(function() {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-red-400">Gagal memuat data historis</td></tr>';
            });
    }

    // ===== Modal Tambah =====
    function openModalTambah() {
        document.getElementById('tambah_ptn').value     = '';
        document.getElementById('tambah_kode').value    = '';
        document.getElementById('tambah_nama').value    = '';
        document.getElementById('tambah_jenjang').value = 'S1';
        document.getElementById('tambah_daya').value    = '50';
        document.getElementById('tambah_nilai').value   = '80.00';
        document.getElementById('tambah_porto').value   = 'Tidak Ada';
        openModal('modalTambah');
    }

    // ===== Modal Edit =====
    function openModalEdit(id) {
        var p = findProdi(id);
        if (!p) return;

        document.getElementById('edit_id').value      = p.id;
        document.getElementById('edit_ptn').value     = p.id_univ;
        document.getElementById('edit_kode').value    = p.kode;
        document.getElementById('edit_nama').value    = p.nama;
        document.getElementById('edit_jenjang').value = p.jenjang;
        document.getElementById('edit_daya').value    = p.daya;
        document.getElementById('edit_nilai').value   = p.nilai;
        document.getElementById('edit_porto').value   = p.porto;

        openModal('modalEdit');
    }

    // ===== Modal Hapus =====
    function openModalHapus(id, nama, ptn) {
        document.getElementById('hapus_id').value     = id;
        document.getElementById('hapus_nama').textContent = nama;
        document.getElementById('hapus_ptn').textContent  = ptn;
        openModal('modalHapus');
    }

    // ===== Filter Tabel Prodi =====
    // Auto-isi kode prodi saat PTN dipilih (sequential dari database)
    function autoFillKode(sel) {
        var idUniv = sel.value;
        var kodeField = document.getElementById('tambah_kode');
        if (!idUniv || !kodeField) return;

        kodeField.value = 'Memuat...';
        kodeField.disabled = true;

        fetch('prodi.php?action=get_next_kode&id_univ=' + idUniv)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                kodeField.disabled = false;
                if (data.kode) {
                    kodeField.value = data.kode;
                    kodeField.placeholder = 'Kode otomatis: ' + data.kode;
                } else {
                    kodeField.value = '';
                    kodeField.placeholder = 'Isi kode prodi...';
                }
            })
            .catch(function() {
                kodeField.disabled = false;
                kodeField.value = '';
            });
    }

    // ===== Render Tabel Prodi & Pagination =====
    var currentPage = 1;
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
        var selectedPTN = document.getElementById('filterPTN').value;
        var search      = document.getElementById('searchProdi').value.toLowerCase().trim();

        return prodiData.filter(function(p) {
            var matchPTN    = (selectedPTN === 'Semua PTN') || (p.ptn === selectedPTN);
            var matchSearch = !search || 
                              p.nama.toLowerCase().includes(search) || 
                              p.kode.toLowerCase().includes(search) || 
                              p.ptn.toLowerCase().includes(search);
            return matchPTN && matchSearch;
        });
    }

    function renderProdiTable() {
        var filtered   = getFilteredData();
        var totalItems = filtered.length;
        var totalPages = Math.ceil(totalItems / itemsPerPage) || 1;

        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        var start = (currentPage - 1) * itemsPerPage;
        var end   = start + itemsPerPage;
        var pageItems = filtered.slice(start, end);

        var tbody = document.getElementById('tableProdiBody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (pageItems.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-8 text-gray-500">Tidak ada program studi yang cocok dengan filter.</td></tr>';
        } else {
            pageItems.forEach(function(row) {
                var tr = document.createElement('tr');
                tr.className = 'prodi-row';
                tr.setAttribute('data-ptn', row.ptn);
                tr.innerHTML = 
                    '<td><span class="font-mono text-xs text-gray-600 bg-gray-100 px-2 py-0.5 rounded">' + escapeHtml(row.kode) + '</span></td>' +
                    '<td><div class="font-medium text-gray-800">' + escapeHtml(row.nama) + '</div><div class="text-xs text-green-700 font-medium">' + escapeHtml(row.ptn) + '</div></td>' +
                    '<td class="text-center"><span class="badge badge-green">' + escapeHtml(row.jenjang) + '</span></td>' +
                    '<td class="text-right font-medium text-gray-700">' + Number(row.daya).toLocaleString('id-ID') + '</td>' +
                    '<td class="text-right font-semibold text-gray-800">' + Number(row.nilai).toFixed(2) + '</td>' +
                    '<td class="text-center"><span class="badge ' + (row.porto === 'Tidak Ada' ? 'badge-gray' : 'badge-orange') + '">' + escapeHtml(row.porto) + '</span></td>' +
                    '<td>' +
                        '<div class="flex items-center justify-center gap-1.5">' +
                            '<button onclick="openModalDetail(' + row.id + ')" class="btn btn-sm btn-blue" title="Lihat Detail"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg> Detail</button>' +
                            '<button onclick="openModalEdit(' + row.id + ')" class="btn btn-sm btn-yellow" title="Edit"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg> Edit</button>' +
                            '<button onclick="openModalHapus(' + row.id + ', \'' + escapeJs(row.nama) + '\', \'' + escapeJs(row.ptn) + '\')" class="btn btn-sm btn-red" title="Hapus"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg> Hapus</button>' +
                        '</div>' +
                    '</td>';
                tbody.appendChild(tr);
            });
        }

        var countEl = document.getElementById('prodiCount');
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
        renderProdiTable();
    }

    function filterProdi() {
        currentPage = 1;
        renderProdiTable();
    }

    document.addEventListener('DOMContentLoaded', function() {
        renderProdiTable();
    });
    </script>

</body>
</html>