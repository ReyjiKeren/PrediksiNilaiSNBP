<?php
// admin/ptn.php - Halaman Data PTN
// Menampilkan daftar Perguruan Tinggi Negeri dengan fitur CRUD lengkap

require_once __DIR__ . '/../koneksi/config.php';
require_once __DIR__ . '/components/auth.php';

$activePage = 'ptn';
$pageTitle  = 'Data PTN';
$adminName  = 'Admin';

// =========================================================================
// PROSES CRUD (TAMBAH / EDIT / HAPUS)
// =========================================================================
$aksi = $_GET['aksi'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($aksi === 'tambah') {
        $nama    = trim($_POST['nama_ptn'] ?? '');
        $jenis   = trim($_POST['jenis_ptn'] ?? 'PTN Akademik');
        $kota    = trim($_POST['kota_ptn'] ?? '');
        $provinsi = trim($_POST['provinsi_ptn'] ?? '');
        $lokasi  = trim($kota . (!empty($provinsi) ? ((!empty($kota) ? ', ' : '') . $provinsi) : ''));
        if (empty($lokasi)) $lokasi = '-';
        $website = trim($_POST['website_ptn'] ?? '-');

        if (!empty($nama)) {
            $kode_univ = preg_replace('/[^0-9A-Za-z]/', '', trim($_POST['kode_univ'] ?? ''));
        if (empty($kode_univ)) {
            setFlash('error', "Kode PTN wajib diisi!");
            header("Location: ptn.php");
            exit;
        }
        // Cek apakah kode sudah dipakai
        $chk = $conn->query("SELECT id_univ FROM tb_universitas WHERE kode_univ = '$kode_univ'");
        if ($chk && $chk->num_rows > 0) {
            setFlash('error', "Kode PTN '$kode_univ' sudah digunakan oleh PTN lain!");
            header("Location: ptn.php");
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO tb_universitas (kode_univ, nama_univ, jenis_ptn, lokasi, website) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $kode_univ, $nama, $jenis, $lokasi, $website);
            
            if ($stmt->execute()) {
                setFlash('success', "PTN '{$nama}' berhasil ditambahkan!");
            } else {
                setFlash('error', "Gagal menambahkan PTN: " . $conn->error);
            }
        } else {
            setFlash('error', "Nama PTN wajib diisi!");
        }
        header("Location: ptn.php");
        exit;
    }

    if ($aksi === 'edit') {
        $id        = intval($_POST['id_univ'] ?? 0);
        $kode_univ = trim($_POST['kode_univ'] ?? '');
        $nama      = trim($_POST['nama_ptn'] ?? '');
        $jenis     = trim($_POST['jenis_ptn'] ?? 'PTN Akademik');
        $kota      = trim($_POST['kota_ptn'] ?? '');
        $provinsi  = trim($_POST['provinsi_ptn'] ?? '');
        $lokasi    = trim($kota . (!empty($provinsi) ? ((!empty($kota) ? ', ' : '') . $provinsi) : ''));
        if (empty($lokasi)) $lokasi = '-';
        $website   = trim($_POST['website_ptn'] ?? '-');

        if ($id > 0 && !empty($nama)) {
            $stmt = $conn->prepare("UPDATE tb_universitas SET kode_univ=?, nama_univ=?, jenis_ptn=?, lokasi=?, website=? WHERE id_univ=?");
            $stmt->bind_param("sssssi", $kode_univ, $nama, $jenis, $lokasi, $website, $id);
            
            if ($stmt->execute()) {
                setFlash('success', "Data PTN '{$nama}' berhasil diperbarui!");
            } else {
                setFlash('error', "Gagal memperbarui PTN: " . $conn->error);
            }
        } else {
            setFlash('error', "ID atau Nama PTN tidak valid!");
        }
        header("Location: ptn.php");
        exit;
    }

    if ($aksi === 'hapus') {
        $id = intval($_POST['id_univ'] ?? 0);
        if ($id > 0) {
            // Cek apakah ada prodi terdaftar di PTN ini
            $chkProdi = $conn->query("SELECT COUNT(*) AS total FROM tb_prodi_univ WHERE id_univ = $id");
            $rowProdi = $chkProdi->fetch_assoc();

            if ($rowProdi['total'] > 0) {
                setFlash('error', "Tidak dapat menghapus PTN ini karena masih memiliki {$rowProdi['total']} program studi terdaftar. Hapus prodinya terlebih dahulu.");
            } else {
                $stmt = $conn->prepare("DELETE FROM tb_universitas WHERE id_univ=?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    setFlash('success', "PTN berhasil dihapus!");
                } else {
                    setFlash('error', "Gagal menghapus PTN: " . $conn->error);
                }
            }
        }
        header("Location: ptn.php");
        exit;
    }
}

// Ambil data PTN dari database
$ptnResult = $conn->query("SELECT * FROM tb_universitas ORDER BY id_univ ASC");
$ptnList = [];
if ($ptnResult) {
    while ($row = $ptnResult->fetch_assoc()) {
        $ptnList[] = [
            'id'        => intval($row['id_univ']),
            'kode_univ' => $row['kode_univ'],
            'nama'      => $row['nama_univ'],
            'jenis'     => $row['jenis_ptn'] ?? 'PTN Akademik',
            'lokasi'    => $row['lokasi'] ?? '-',
            'website'   => $row['website'] ?? '-',
        ];
    }
}

$ptnJSON = json_encode($ptnList);
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="description" content="Data PTN - Kelola Perguruan Tinggi Negeri di SNBP Admin 2026"/>
    <title>Data PTN — SNBP Admin 2026</title>
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
                <h1>Data PTN</h1>
                <p>Kelola data Perguruan Tinggi Negeri (Total: <?= count($ptnList) ?> Universitas)</p>
            </div>

            <!-- ===== CARD TABEL PTN ===== -->
            <div class="card">

                <!-- Header card: search + tombol tambah -->
                <div class="card-header flex-wrap gap-3">
                    <!-- Search bar -->
                    <div class="search-input-wrap flex-1 min-w-0 max-w-xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                        </svg>
                        <input id="searchPTN"
                               type="text"
                               class="form-input"
                               placeholder="Cari universitas..."
                               oninput="filterTable(this.value)"/>
                    </div>

                    <!-- Tombol Tambah PTN -->
                    <button onclick="openModalTambahPTN()"
                            class="btn btn-green shrink-0"
                            id="btnTambahPTN">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4v16m8-8H4"/>
                        </svg>
                        Tambah PTN
                    </button>
                </div>

                <!-- Tabel responsive -->
                <div class="overflow-x-auto">
                    <table class="data-table" id="tablePTN">
                        <thead>
                            <tr>
                                <th class="w-12">No</th>
                                <th class="w-24">Kode</th>
                                <th>Nama PTN</th>
                                <th>Jenis</th>
                                <th>Lokasi</th>
                                <th>Website</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tablePTNBody">
                            <?php if (empty($ptnList)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-8 text-gray-500">
                                    Belum ada data PTN. Klik <strong>Tambah PTN</strong> untuk memasukkan data baru.
                                </td>
                            </tr>
                            <?php endif; ?>

                            <?php foreach ($ptnList as $index => $row): ?>
                            <tr class="ptn-row">
                                <td class="text-gray-400 text-sm"><?= $index + 1 ?></td>
                                <td>
                                    <span class="font-mono text-sm font-semibold text-green-700 bg-green-50 px-2 py-0.5 rounded">
                                        <?= htmlspecialchars($row['kode_univ']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="font-medium text-gray-800"><?= htmlspecialchars($row['nama']) ?></div>
                                </td>
                                <td>
                                    <span class="badge badge-blue"><?= htmlspecialchars($row['jenis']) ?></span>
                                </td>
                                <td class="text-gray-600 text-sm"><?= htmlspecialchars($row['lokasi']) ?></td>
                                <td>
                                    <?php if ($row['website'] !== '-'): ?>
                                    <a href="http://<?= htmlspecialchars($row['website']) ?>"
                                       target="_blank"
                                       class="text-green-600 hover:text-green-700 hover:underline text-sm inline-flex items-center gap-1">
                                        <?= htmlspecialchars($row['website']) ?>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-gray-400 text-sm">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex items-center justify-center gap-1.5">
                                        <!-- Tombol Edit (kuning) -->
                                        <button onclick="openModalEditPTN(<?= $row['id'] ?>)"
                                                class="btn btn-sm btn-yellow"
                                                title="Edit Data">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0
                                                    002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828
                                                    15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Edit
                                        </button>

                                        <!-- Tombol Hapus (merah) -->
                                        <button onclick="openModalHapusPTN(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['nama'])) ?>')"
                                                class="btn btn-sm btn-red"
                                                title="Hapus Data">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0
                                                    01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1
                                                    1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Footer info jumlah data -->
                <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between flex-wrap gap-3">
                    <p class="text-sm text-gray-500">Menampilkan <?= count($ptnList) ?> perguruan tinggi negeri</p>
                </div>

            </div>
            <!-- ===== AKHIR CARD TABEL PTN ===== -->

        </div>
    </main>


    <!-- ==========================================
         MODAL: TAMBAH PTN
         ========================================== -->
    <div id="modalTambahPTN" class="modal-overlay hidden">
        <div class="modal-box max-w-lg">
            <div class="modal-header-green">
                <div>
                    <h3 class="text-base font-semibold">Tambah PTN Baru</h3>
                    <p class="text-xs text-green-100 mt-0.5">Isi data perguruan tinggi negeri</p>
                </div>
                <button type="button" onclick="closeModal('modalTambahPTN')"
                        class="text-white hover:text-green-200 transition-colors ml-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="modal-body">
                <form method="POST" action="ptn.php?aksi=tambah">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Kode PTN <span class="text-red-500">*</span></label>
                                <input type="text" name="kode_univ" class="form-input" id="tambah_kode_ptn"
                                       placeholder="Contoh: 1321" maxlength="10" required/>
                                <p class="text-xs text-gray-400 mt-1">Kode unik 4-10 digit angka/huruf</p>
                            </div>
                            <div>
                                <label class="form-label">Jenis PTN</label>
                            <select name="jenis_ptn" class="form-select" id="tambah_jenis_ptn">
                                <option value="PTN Akademik">PTN Akademik</option>
                                <option value="PTN Vokasi">PTN Vokasi</option>
                                <option value="PTN BH">PTN BH (Badan Hukum)</option>
                                <option value="PTN BLU">PTN BLU</option>
                            </select>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Nama PTN <span class="text-red-500">*</span></label>
                            <input type="text" name="nama_ptn" class="form-input" id="tambah_nama_ptn"
                                   placeholder="Contoh: Universitas Indonesia" required/>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Kota / Kabupaten</label>
                                <input type="text" name="kota_ptn" class="form-input" id="tambah_kota_ptn"
                                       list="kotaUnivList"
                                       placeholder="Ketik nama kota..."
                                       autocomplete="off"/>
                            </div>
                            <div>
                                <label class="form-label">Provinsi</label>
                                <input type="text" name="provinsi_ptn" class="form-input" id="tambah_provinsi_ptn"
                                       list="provinsiList"
                                       placeholder="Ketik nama provinsi..."
                                       autocomplete="off"/>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Website</label>
                            <input type="text" name="website_ptn" class="form-input" id="tambah_website_ptn"
                                   placeholder="Contoh: ui.ac.id"/>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" onclick="closeModal('modalTambahPTN')" class="btn btn-gray">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-green">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"/>
                            </svg>
                            Simpan PTN
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- ==========================================
         MODAL: EDIT PTN
         ========================================== -->
    <div id="modalEditPTN" class="modal-overlay hidden">
        <div class="modal-box max-w-lg">
            <div class="modal-header-green">
                <div>
                    <h3 class="text-base font-semibold">Edit Data PTN</h3>
                    <p class="text-xs text-green-100 mt-0.5">Ubah data perguruan tinggi negeri</p>
                </div>
                <button type="button" onclick="closeModal('modalEditPTN')"
                        class="text-white hover:text-green-200 transition-colors ml-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="modal-body">
                <form method="POST" action="ptn.php?aksi=edit">
                    <!-- Hidden ID PTN -->
                    <input type="hidden" name="id_univ" id="edit_id_ptn" value=""/>

                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Kode PTN <span class="text-red-500">*</span></label>
                                <input type="text" name="kode_univ" class="form-input" id="edit_kode_ptn"
                                       placeholder="Contoh: 1321" maxlength="10" required/>
                            </div>
                            <div>
                                <label class="form-label">Jenis PTN</label>
                                <select name="jenis_ptn" class="form-select" id="edit_jenis_ptn">
                                    <option value="PTN Akademik">PTN Akademik</option>
                                    <option value="PTN Vokasi">PTN Vokasi</option>
                                    <option value="PTN BH">PTN BH (Badan Hukum)</option>
                                    <option value="PTN BLU">PTN BLU</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Nama PTN <span class="text-red-500">*</span></label>
                            <input type="text" name="nama_ptn" class="form-input" id="edit_nama_ptn"
                                   placeholder="Nama PTN..." required/>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Kota / Kabupaten</label>
                                <input type="text" name="kota_ptn" class="form-input" id="edit_kota_ptn"
                                       list="kotaUnivList" placeholder="Ketik nama kota..." autocomplete="off"/>
                            </div>
                            <div>
                                <label class="form-label">Provinsi</label>
                                <input type="text" name="provinsi_ptn" class="form-input" id="edit_provinsi_ptn"
                                       list="provinsiList" placeholder="Ketik nama provinsi..." autocomplete="off"/>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Website</label>
                            <input type="text" name="website_ptn" class="form-input" id="edit_website_ptn"
                                   placeholder="Contoh: ui.ac.id"/>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" onclick="closeModal('modalEditPTN')" class="btn btn-gray">
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
         MODAL: HAPUS PTN
         ========================================== -->
    <div id="modalHapusPTN" class="modal-overlay hidden">
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
                    <h3 class="text-base font-semibold text-gray-900">Hapus PTN</h3>
                </div>
                <button type="button" onclick="closeModal('modalHapusPTN')"
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="modal-body text-center">
                <form method="POST" action="ptn.php?aksi=hapus">
                    <!-- Hidden ID PTN yang akan dihapus -->
                    <input type="hidden" name="id_univ" id="hapus_ptn_id_input" value=""/>

                    <p class="text-sm text-gray-600 mb-4">
                        Apakah Anda yakin ingin menghapus perguruan tinggi berikut?
                    </p>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                        <p class="font-semibold text-gray-900 uppercase" id="hapus_ptn_nama">-</p>
                        <p class="text-sm text-gray-500 mt-1">ID: <span id="hapus_ptn_id">-</span></p>
                    </div>
                    <p class="text-xs text-red-500 mb-4">
                        ⚠️ Tindakan ini tidak dapat dibatalkan.
                    </p>
                    <div class="flex gap-3">
                        <button type="button" onclick="closeModal('modalHapusPTN')" class="btn btn-gray flex-1 justify-center">
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

    // Tutup modal saat klik overlay (background gelap)
    document.querySelectorAll('.modal-overlay').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (e.target === el) {
                el.classList.add('hidden');
                document.body.style.overflow = '';
            }
        });
    });

    // Data PTN dari PHP (JSON)
    var ptnData = <?= $ptnJSON ?>;

    function findPTN(id) {
        if (!ptnData || !Array.isArray(ptnData)) return null;
        return ptnData.find(function(p) { return Number(p.id) === Number(id); });
    }

    // ===== Modal Tambah PTN =====
    function openModalTambahPTN() {
        document.getElementById('tambah_nama_ptn').value    = '';
        document.getElementById('tambah_jenis_ptn').value   = 'PTN Akademik';
        document.getElementById('tambah_kota_ptn').value     = '';
        document.getElementById('tambah_provinsi_ptn').value = '';
        document.getElementById('tambah_website_ptn').value = '';
        openModal('modalTambahPTN');
    }

    // ===== Modal Edit PTN =====
    function openModalEditPTN(id, nama, jenis, lokasi, website) {
        var p = findPTN(id);
        var kodeVal    = (p && p.kode_univ) ? p.kode_univ : '';
        var namaVal    = (p && p.nama) ? p.nama : (nama || '');
        var jenisVal   = (p && p.jenis) ? p.jenis : (jenis || 'PTN Akademik');
        var lokasiVal  = (p && p.lokasi) ? p.lokasi : (lokasi || '');
        var websiteVal = (p && p.website) ? p.website : (website || '');

        document.getElementById('edit_id_ptn').value = id;
        document.getElementById('edit_kode_ptn').value = kodeVal;
        document.getElementById('edit_nama_ptn').value = namaVal;
        document.getElementById('edit_jenis_ptn').value = jenisVal;

        // Split lokasi 'Kota, Provinsi' ke 2 field (mendukung pemisah koma dengan atau tanpa spasi)
        var cleanLokasi = (lokasiVal && lokasiVal !== '-') ? lokasiVal : '';
        var lokasiParts = cleanLokasi ? cleanLokasi.split(/,\s*/) : ['', ''];
        document.getElementById('edit_kota_ptn').value = lokasiParts[0] || '';
        document.getElementById('edit_provinsi_ptn').value = (lokasiParts.length > 1) ? lokasiParts.slice(1).join(', ') : '';
        document.getElementById('edit_website_ptn').value = (websiteVal && websiteVal !== '-') ? websiteVal : '';

        openModal('modalEditPTN');
    }

    // ===== Modal Hapus PTN =====
    function openModalHapusPTN(id, nama) {
        document.getElementById('hapus_ptn_id_input').value   = id;
        document.getElementById('hapus_ptn_nama').textContent = nama;
        document.getElementById('hapus_ptn_id').textContent   = id;
        openModal('modalHapusPTN');
    }

    // ===== Filter tabel (search) =====
    function filterTable(keyword) {
        var rows = document.querySelectorAll('.ptn-row');
        var kw = keyword.toLowerCase();
        rows.forEach(function(row) {
            var text = row.innerText.toLowerCase();
            row.style.display = text.includes(kw) ? '' : 'none';
        });
    }
    </script>
    <!-- Datalist Kota Universitas Indonesia -->
    <datalist id="kotaUnivList">
        <option value="Ambon">
        <option value="Badung">
        <option value="Balikpapan">
        <option value="Banda Aceh">
        <option value="Bandar Lampung">
        <option value="Bandung">
        <option value="Banjarbaru">
        <option value="Banjarmasin">
        <option value="Banyuwangi">
        <option value="Batam">
        <option value="Bekasi">
        <option value="Bengkulu">
        <option value="Binjai">
        <option value="Bitung">
        <option value="Blitar">
        <option value="Bogor">
        <option value="Bontang">
        <option value="Bukittinggi">
        <option value="Cianjur">
        <option value="Cilegon">
        <option value="Cimahi">
        <option value="Cirebon">
        <option value="Denpasar">
        <option value="Depok">
        <option value="Dumai">
        <option value="Garut">
        <option value="Gianyar">
        <option value="Gorontalo">
        <option value="Gowa">
        <option value="Jakarta Barat">
        <option value="Jakarta Pusat">
        <option value="Jakarta Selatan">
        <option value="Jakarta Timur">
        <option value="Jakarta Utara">
        <option value="Jambi">
        <option value="Jayapura">
        <option value="Jember">
        <option value="Karawang">
        <option value="Kediri">
        <option value="Kendari">
        <option value="Kudus">
        <option value="Kupang">
        <option value="Langsa">
        <option value="Lhokseumawe">
        <option value="Lubuklinggau">
        <option value="Madiun">
        <option value="Magelang">
        <option value="Makassar">
        <option value="Malang">
        <option value="Mamuju">
        <option value="Manado">
        <option value="Manokwari">
        <option value="Mataram">
        <option value="Medan">
        <option value="Metro">
        <option value="Mojokerto">
        <option value="Padang">
        <option value="Palangka Raya">
        <option value="Palembang">
        <option value="Palopo">
        <option value="Palu">
        <option value="Pangkalpinang">
        <option value="Parepare">
        <option value="Pasuruan">
        <option value="Payakumbuh">
        <option value="Pekalongan">
        <option value="Pekanbaru">
        <option value="Pematangsiantar">
        <option value="Pontianak">
        <option value="Probolinggo">
        <option value="Purwakarta">
        <option value="Purwokerto">
        <option value="Salatiga">
        <option value="Samarinda">
        <option value="Semarang">
        <option value="Serang">
        <option value="Sibolga">
        <option value="Singkawang">
        <option value="Sorong">
        <option value="Sukabumi">
        <option value="Sumedang">
        <option value="Surabaya">
        <option value="Surakarta">
        <option value="Tangerang">
        <option value="Tangerang Selatan">
        <option value="Tanjungpinang">
        <option value="Tarakan">
        <option value="Tasikmalaya">
        <option value="Tegal">
        <option value="Ternate">
        <option value="Tulungagung">
        <option value="Yogyakarta">
    </datalist>

    <!-- Datalist Provinsi Indonesia -->
    <datalist id="provinsiList">
        <option value="Aceh">
        <option value="Bali">
        <option value="Banten">
        <option value="Bengkulu">
        <option value="DI Yogyakarta">
        <option value="DKI Jakarta">
        <option value="Gorontalo">
        <option value="Jambi">
        <option value="Jawa Barat">
        <option value="Jawa Tengah">
        <option value="Jawa Timur">
        <option value="Kalimantan Barat">
        <option value="Kalimantan Selatan">
        <option value="Kalimantan Tengah">
        <option value="Kalimantan Timur">
        <option value="Kalimantan Utara">
        <option value="Kepulauan Bangka Belitung">
        <option value="Kepulauan Riau">
        <option value="Lampung">
        <option value="Maluku">
        <option value="Maluku Utara">
        <option value="Nusa Tenggara Barat">
        <option value="Nusa Tenggara Timur">
        <option value="Papua">
        <option value="Papua Barat">
        <option value="Papua Barat Daya">
        <option value="Papua Pegunungan">
        <option value="Papua Selatan">
        <option value="Papua Tengah">
        <option value="Riau">
        <option value="Sulawesi Barat">
        <option value="Sulawesi Selatan">
        <option value="Sulawesi Tengah">
        <option value="Sulawesi Tenggara">
        <option value="Sulawesi Utara">
        <option value="Sumatera Barat">
        <option value="Sumatera Selatan">
        <option value="Sumatera Utara">
    </datalist>
</body>
</html>