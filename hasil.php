<?php

$jurusan = $_GET['jurusan'] ?? '';
$rata_rata = $_GET['rata_rata'] ?? '';
$acuan = $_GET['acuan'] ?? '';
$ptn_dipilih = $_GET['ptn'] ?? '';
$nama = trim($_GET['nama'] ?? 'Anda');
if (empty($nama))
    $nama = 'Anda';

$jurusan_valid = ['RPL', 'TKR', 'TITL', 'TAV'];

if (empty($jurusan) || !in_array($jurusan, $jurusan_valid) || $rata_rata === '' || $acuan === '') {
    header("Location: index.php");
    exit;
}

$rata_rata = (float) $rata_rata;
$acuan = (float) $acuan;

require_once __DIR__ . '/koneksi/config.php';

// Ambil daftar PTN dari database
$resPTN = $conn->query("SELECT id_univ, kode_univ, nama_univ, jenis_ptn, lokasi FROM tb_universitas ORDER BY nama_univ ASC");
$daftar_ptn = [];
while ($row = $resPTN->fetch_assoc()) {
    $key = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $row['nama_univ']));
    if (empty($key))
        $key = 'univ_' . $row['id_univ'];

    $lokasi_raw = trim($row['lokasi'] ?? '-');
    $parts = ($lokasi_raw !== '-' && !empty($lokasi_raw)) ? preg_split('/,\s*/', $lokasi_raw) : ['-', '-'];
    $kota = $parts[0] ?? '-';
    $provinsi = (count($parts) > 1) ? implode(', ', array_slice($parts, 1)) : $kota;

    $daftar_ptn[$key] = [
        'id' => $row['id_univ'],
        'nama' => $row['nama_univ'],
        'lokasi' => $lokasi_raw,
        'kota' => $kota,
        'provinsi' => $provinsi,
        'tipe' => $row['jenis_ptn'] ?? 'PTN Akademik',
        'kode' => $row['kode_univ'],
    ];
}

// Default PTN dipilih (biarkan kosong jika user belum memilih PTN)
if (!empty($ptn_dipilih) && !isset($daftar_ptn[$ptn_dipilih])) {
    $ptn_dipilih = '';
}

// Ambil daftar Prodi dan data Historis HANYA untuk PTN yang dipilih
$daftar_prodi = [];
$info_ptn_dipilih = isset($daftar_ptn[$ptn_dipilih]) ? $daftar_ptn[$ptn_dipilih] : null;

if ($info_ptn_dipilih) {
    $target_id_univ = intval($info_ptn_dipilih['id']);
    
    // 1. Query prodi hanya untuk 1 PTN terpilih (super cepat)
    $stmtP = $conn->prepare("
        SELECT 
            p.id_prodi,
            p.id_univ,
            p.kode_prodi,
            p.jenjang,
            p.daya_tampung,
            p.nilai_minimal,
            p.portofolio,
            u.nama_univ,
            mp.nama_prodi
        FROM tb_prodi_univ p
        JOIN tb_universitas u ON p.id_univ = u.id_univ
        JOIN tb_master_prodi mp ON p.id_master_prodi = mp.id_master_prodi
        WHERE p.id_univ = ? AND p.nilai_minimal > 0
        ORDER BY mp.nama_prodi ASC
    ");
    $stmtP->bind_param("i", $target_id_univ);
    $stmtP->execute();
    $resP = $stmtP->get_result();

    $prodi_list_ptn = [];
    $prodi_ids = [];
    while ($p = $resP->fetch_assoc()) {
        $prodi_list_ptn[$p['id_prodi']] = $p;
        $prodi_ids[] = intval($p['id_prodi']);
    }

    // 2. Query data historis sekaligus 1 kali (bulk)
    $historis_map = [];
    if (!empty($prodi_ids)) {
        $ids_str = implode(',', $prodi_ids);
        $resH = $conn->query("SELECT id_prodi, tahun, peminat, daya_tampung, keketatan FROM tb_historis WHERE id_prodi IN ($ids_str) ORDER BY tahun ASC");
        if ($resH) {
            while ($h = $resH->fetch_assoc()) {
                $historis_map[$h['id_prodi']][] = [
                    'tahun' => intval($h['tahun']),
                    'peminat' => intval($h['peminat']),
                    'daya' => intval($h['daya_tampung']),
                    'keketatan' => number_format(floatval($h['keketatan']), 2) . '%'
                ];
            }
        }
    }

    // 3. Susun data prodi
    $daftar_prodi[$ptn_dipilih] = [];
    foreach ($prodi_list_ptn as $id_p => $p) {
        $daftar_prodi[$ptn_dipilih][] = [
            'id' => $p['id_prodi'],
            'kode' => $p['kode_prodi'],
            'nama' => $p['nama_prodi'],
            'jenjang' => $p['jenjang'],
            'daya_tampung' => intval($p['daya_tampung']),
            'nilai_minimal' => floatval($p['nilai_minimal']),
            'portofolio' => !empty($p['portofolio']) ? $p['portofolio'] : '-',
            'historis' => $historis_map[$id_p] ?? []
        ];
    }
}

$info_ptn_dipilih = isset($daftar_ptn[$ptn_dipilih]) ? $daftar_ptn[$ptn_dipilih] : null;
$prodi_ptn_dipilih = isset($daftar_prodi[$ptn_dipilih]) ? $daftar_prodi[$ptn_dipilih] : [];

$base_url = "hasil.php?jurusan=" . urlencode($jurusan)
    . "&rata_rata=" . urlencode($rata_rata)
    . "&acuan=" . urlencode($acuan);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Prediksi SNBP 2026</title>
    <meta name="description"
        content="Lihat prediksi peluang masuk PTN berdasarkan nilai rata-rata rapor semester 1-5 kamu.">

    <link rel="shortcut icon" href="./img/logo.png" type="image/png">
    <link href="./src/output.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #1a1a1a;
        }

        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #16a34a;
            border-radius: 10px;
        }

        .fade-in {
            animation: fadeIn 0.35s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .ptn-item-active {
            background-color: #16a34a !important;
            color: #ffffff !important;
            font-weight: 600;
        }

        .ptn-item-active .ptn-city-badge {
            background-color: rgba(255, 255, 255, 0.2) !important;
            color: #ffffff !important;
        }

        .badge-s1 {
            background-color: #16a34a;
            color: white;
            font-size: 11px;
            padding: 2px 10px;
            border-radius: 9999px;
            font-weight: 700;
            display: inline-block;
        }

        .nilai-aman {
            color: #16a34a;
            font-weight: 700;
        }

        .nilai-kurang {
            color: #dc2626;
            font-weight: 700;
        }

        .tabel-baris:hover {
            background-color: #f0fdf4;
        }

        .tabel-baris {
            cursor: pointer;
        }

        .ptn-item:hover:not(.ptn-item-active) {
            background-color: #f0fdf4;
        }

        .search-input:focus {
            outline: none;
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15);
        }

        /* --- Scroll Container Tabel Prodi --- */
        .tabel-scroll-container {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            overflow-x: auto;
            border-radius: 0;
        }

        .tabel-scroll-container table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
        }

        .tabel-scroll-container thead tr {
            background-color: #14532d !important;
        }

        .tabel-scroll-container thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #14532d !important;
            border: none !important;
            outline: none !important;
            box-shadow: 0 1px 0 rgba(0, 0, 0, 0.08);
        }

        /* Scrollbar Khusus Tabel Prodi */
        .tabel-scroll-container::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .tabel-scroll-container::-webkit-scrollbar-track {
            background: #f8fafc;
            border-radius: 4px;
        }

        .tabel-scroll-container::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 4px;
            border: 1px solid #f8fafc;
        }

        .tabel-scroll-container::-webkit-scrollbar-thumb:hover {
            background-color: #94a3b8;
        }

        /* --- Filter Provinsi --- */
        .filter-provinsi-wrap {
            padding: 0.6rem 0.75rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        .filter-provinsi-label {
            font-size: 0.62rem;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.35rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .filter-provinsi-select {
            width: 100%;
            padding: 0.42rem 0.6rem;
            border: 1.5px solid #d1fae5;
            border-radius: 8px;
            font-size: 0.75rem;
            font-family: 'Poppins', sans-serif;
            color: #374151;
            background: #ffffff;
            cursor: pointer;
            outline: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2316a34a' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 8px center;
            padding-right: 28px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .filter-provinsi-select:focus {
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15);
        }

        /* Badge jumlah PTN yang tampil */
        .ptn-count-badge {
            font-size: 0.62rem;
            font-weight: 700;
            background: #dcfce7;
            color: #15803d;
            padding: 1px 7px;
            border-radius: 9999px;
            margin-left: auto;
        }

        /* PTN tersembunyi saat filter aktif */
        .ptn-item-hidden {
            display: none !important;
        }

        /* Pesan kosong saat tidak ada hasil */
        #ptnKosong {
            display: none;
            padding: 0.85rem 1rem;
            font-size: 0.75rem;
            color: #9ca3af;
            text-align: center;
        }

        #modalOverlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.65);
            z-index: 9998;
            backdrop-filter: blur(2px);
        }

        #modalBox {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
            width: 90%;
            max-width: 580px;
            max-height: 85vh;
            overflow-y: auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            font-family: 'Poppins', sans-serif;
        }

        @keyframes modalMuncul {
            from {
                opacity: 0;
                transform: translate(-50%, -48%);
            }

            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        #modalBox.aktif {
            animation: modalMuncul 0.25s ease-out;
        }

        .scroll-tabel-historis {
            overflow-x: auto;
        }

        /* ===================================================
           MOBILE RESPONSIVE — hanya berlaku ≤767px
           Desktop (md:) TIDAK TERPENGARUH
        =================================================== */
        /* Style untuk dropdown PTN mobile (dipisah dari inline untuk menghindari conflict quotes) */
        .mobile-ptn-select {
            width: 100%;
            padding: 0.5rem 2rem 0.5rem 0.75rem;
            border: 1.5px solid #d1fae5;
            border-radius: 9px;
            font-size: 0.82rem;
            font-family: 'Poppins', sans-serif;
            color: #1f2937;
            background-color: #f9fafb;
            cursor: pointer;
            outline: none;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2316a34a' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .mobile-ptn-select:focus {
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15);
        }
        @media (max-width: 767px) {

            body,
            html {
                overflow-x: hidden;
            }

            .section-wrapper {
                padding: 0.75rem 0.75rem 1.25rem !important;
            }

            .header-desktop {
                padding: 0.85rem 1rem !important;
                border-radius: 12px !important;
            }

            .header-logo-wrap {
                width: 38px !important;
                height: 38px !important;
                border-radius: 9px !important;
                padding: 6px !important;
            }

            .header-title {
                font-size: 0.9rem !important;
                letter-spacing: 0 !important;
            }

            .header-subtitle {
                display: none !important;
            }

            .header-score {
                font-size: 1.75rem !important;
            }

            .header-score-label {
                font-size: 0.62rem !important;
            }

            /* --- Layout utama: flex column di mobile --- */
            .layout-main-flex {
                flex-direction: column !important;
                gap: 0.75rem !important;
                height: auto !important;
            }

            .fade-in {
                height: auto !important;
            }

            /* --- Sembunyikan sidebar di mobile --- */
            .sidebar-desktop {
                display: none !important;
            }

            /* --- Mobile PTN selector --- */
            .mobile-ptn-selector {
                display: block !important;
                width: 100%;
            }

            /* --- Main area full width di mobile --- */
            main {
                width: 100% !important;
                min-width: 0 !important;
            }

            /* --- Badge prodi count tampil di mobile --- */
            .mobile-prodi-badge {
                display: flex !important;
            }

            /* --- Card universitas mobile --- */
            .card-univ-header {
                padding: 1rem 1rem !important;
            }

            .card-univ-title {
                font-size: 1rem !important;
                letter-spacing: 0 !important;
            }

            .card-univ-subtitle {
                font-size: 0.75rem !important;
            }

            .card-legend {
                gap: 0.75rem !important;
                flex-wrap: wrap !important;
            }

            /* --- Search area mobile --- */
            /* --- Search area mobile --- */
            .search-area-wrap {
                padding: 0.75rem 0.85rem !important;
            }

            /* --- Tabel scroll: responsive & touch scroll --- */
            .tabel-scroll-container {
                max-height: 480px !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
                border-bottom: 1px solid #e5e7eb;
            }

            .tabel-scroll-container table {
                min-width: 540px !important;
            }

            /* Sel tabel lebih compact di mobile */
            .tabel-scroll-container td,
            .tabel-scroll-container th {
                padding: 0.55rem 0.45rem !important;
                font-size: 0.75rem !important;
            }

            /* Nama prodi boleh wrap */
            .nama-prodi {
                white-space: normal !important;
                min-width: 120px;
                max-width: 180px;
            }

            /* --- Toggle prodi mobile --- */
            .toggle-prodi-wrap {
                padding: 0.75rem 0.85rem !important;
            }

            /* --- Footer mobile --- */
            .footer-back-btn {
                padding: 0.55rem 1.25rem !important;
                font-size: 0.8rem !important;
            }

            /* --- Modal mobile --- */
            #modalBox {
                width: 93% !important;
                max-width: 93% !important;
                max-height: 88vh !important;
            }

            #btnTutupModal {
                width: 36px !important;
                height: 36px !important;
                font-size: 1.2rem !important;
            }
        }
    </style>
</head>

<body>

    <section class="section-wrapper"
        style="background-image: url('./img/bgtb.png'); background-size: cover; background-position: center; background-repeat: no-repeat; min-height: 100vh; position: relative; display: flex; flex-direction: column; align-items: center; padding: 2rem 1rem;">

        <div style="position: absolute; inset: 0; background-color: rgba(0,0,0,0.72);"></div>

        <div
            style="position: relative; z-index: 10; width: 100%; max-width: 1550px; display: flex; flex-direction: column; gap: 1.25rem;">

            <header class="header-desktop" style="
            background: linear-gradient(135deg, #14532d 0%, #166534 50%, #15803d 100%);
            border-radius: 16px;
            padding: 1.25rem 1.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 8px 32px rgba(0,0,0,0.35);
            border: 1px solid rgba(134, 239, 172, 0.15);
            gap: 1rem;
        ">
                <div style="display: flex; align-items: center; gap: 1rem;">

                    <div class="header-logo-wrap" style="
                    width: 52px; height: 52px;
                    background: rgba(255,255,255,0.15);
                    border-radius: 12px;
                    display: flex; align-items: center; justify-content: center;
                    flex-shrink: 0;
                    border: 1px solid rgba(255,255,255,0.2);
                    padding: 8px;
                ">
                        <img src="./img/logoputih.png" alt="Logo SNBP" style="width: 100%; height: 100%; object-fit: contain;">
                    </div>

                    <div>
                        <h1 class="header-title"
                            style="color: #ffffff; font-size: 1.2rem; font-weight: 800; margin: 0; letter-spacing: 0.5px; text-transform: uppercase;">
                            Prediksi Nilai SNBP 2026
                        </h1>
                        <p class="header-subtitle" style="color: rgba(134, 239, 172, 0.85); font-size: 0.75rem; margin: 2px 0 0 0;">
                            Informasi daya tampung program studi Seleksi Nasional Berdasarkan Prestasi
                        </p>
                    </div>
                </div>

                <div style="text-align: right; flex-shrink: 0;">
                    <div class="header-score" style="color: #ffffff; font-size: 2.5rem; font-weight: 800; line-height: 1;">
                        <?= number_format($rata_rata, 2) ?>
                    </div>
                    <div class="header-score-label"
                        style="color: rgba(134, 239, 172, 0.85); font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-top: 2px;">
                        Nilai <?= htmlspecialchars($nama) ?>
                    </div>
                </div>
            </header>

            <div class="layout-main-flex" style="display: flex; gap: 1.25rem; align-items: stretch; height: 640px;">

                <!-- =====================================================
                     MOBILE PTN SELECTOR — hanya tampil di layar ≤767px
                     Desktop: sidebar tetap digunakan (sidebar-desktop)
                ===================================================== -->
                <div class="mobile-ptn-selector" style="display: none;">
                    <div style="
                        background: #ffffff;
                        border-radius: 12px;
                        overflow: hidden;
                        box-shadow: 0 4px 16px rgba(0,0,0,0.18);
                        margin-bottom: 0;
                    ">
                        <div style="
                            background: linear-gradient(135deg, #14532d, #166534);
                            padding: 0.7rem 1rem;
                            display: flex;
                            align-items: center;
                            justify-content: space-between;
                            gap: 0.5rem;
                        ">
                            <span style="color: #ffffff; font-size: 0.78rem; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;">
                                <i class="fa-solid fa-building-columns" style="margin-right: 6px;"></i>Pilih PTN
                            </span>
                            <span style="
                                background: rgba(255,255,255,0.2);
                                color: #ffffff;
                                font-size: 0.65rem;
                                font-weight: 700;
                                padding: 2px 8px;
                                border-radius: 9999px;
                            "><?= count($daftar_ptn) ?> PTN</span>
                        </div>
                        <div style="padding: 0.6rem 0.85rem;">
                            <div style="position: relative; margin-bottom: 0.4rem;">
                                <i class="fa-solid fa-magnifying-glass" style="
                                    position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
                                    color: #9ca3af; font-size: 11px;
                                "></i>
                                <input type="text" id="searchPTNMobile" placeholder="Cari nama PTN..." oninput="filterPTNMobile()" style="
                                    width: 100%;
                                    padding: 0.4rem 0.6rem 0.4rem 1.85rem;
                                    border: 1.5px solid #d1fae5;
                                    border-radius: 8px;
                                    font-size: 0.75rem;
                                    font-family: 'Poppins', sans-serif;
                                    color: #374151;
                                    background: #f9fafb;
                                    outline: none;
                                    box-sizing: border-box;
                                ">
                            </div>
                            <select id="mobilePTNSelect"
                                class="mobile-ptn-select"
                                onchange="if(this.value) window.location.href=this.value">
                                <option value="" disabled <?= empty($ptn_dipilih) ? 'selected' : '' ?>>-- Pilih Universitas --</option>
                                <?php foreach ($daftar_ptn as $slug => $ptn): ?>
                                    <option value="<?= $base_url ?>&ptn=<?= urlencode($slug) ?>"
                                        <?= $slug === $ptn_dipilih ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ptn['nama']) ?>
                                        <?php if ($ptn['kota'] !== '-' && !empty($ptn['kota'])): ?>
                                            — <?= htmlspecialchars($ptn['kota']) ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <aside class="sidebar-desktop" style="
                width: 310px;
                height: 100%;
                flex-shrink: 0;
                background: #ffffff;
                border-radius: 14px;
                overflow: hidden;
                box-shadow: 0 4px 20px rgba(0,0,0,0.2);
                display: flex;
                flex-direction: column;
            ">
                    <div style="
                    background: linear-gradient(135deg, #14532d, #166534);
                    padding: 1rem 1.25rem;
                    color: white;
                    font-size: 0.85rem;
                    font-weight: 700;
                    letter-spacing: 0.5px;
                    text-transform: uppercase;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                ">
                        <span>
                            <i class="fa-solid fa-building-columns" style="margin-right: 8px;"></i>
                            Daftar PTN
                        </span>
                        <span style="font-size: 0.7rem; background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 9999px; font-weight: 600;">
                            <?= count($daftar_ptn) ?> PTN
                        </span>
                    </div>

                    <?php
                    // Kumpulkan semua provinsi unik dari $daftar_ptn, urut abjad
                    $semua_provinsi = [];
                    foreach ($daftar_ptn as $p) {
                        if (!in_array($p['provinsi'], $semua_provinsi)) {
                            $semua_provinsi[] = $p['provinsi'];
                        }
                    }
                    sort($semua_provinsi);
                    ?>

                    <!-- Search Bar PTN Sidebar -->
                    <div style="padding: 0.6rem 0.75rem 0.4rem 0.75rem; border-bottom: 1px solid #f3f4f6; background: #ffffff;">
                        <div style="position: relative;">
                            <i class="fa-solid fa-magnifying-glass" style="
                                position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
                                color: #9ca3af; font-size: 11px;
                            "></i>
                            <input type="text" id="searchPTN" placeholder="Cari nama PTN..." oninput="filterPTN()" style="
                                width: 100%;
                                padding: 0.42rem 0.6rem 0.42rem 1.85rem;
                                border: 1.5px solid #d1fae5;
                                border-radius: 8px;
                                font-size: 0.75rem;
                                font-family: 'Poppins', sans-serif;
                                color: #374151;
                                background: #f9fafb;
                                outline: none;
                                box-sizing: border-box;
                                transition: border-color 0.2s, box-shadow 0.2s;
                            " onfocus="this.style.borderColor='#16a34a'; this.style.boxShadow='0 0 0 3px rgba(22, 163, 74, 0.15)';" onblur="this.style.borderColor='#d1fae5'; this.style.boxShadow='none';">
                        </div>
                    </div>

                    <!-- Dropdown Filter Provinsi -->
                    <div class="filter-provinsi-wrap">
                        <div class="filter-provinsi-label">
                            <i class="fa-solid fa-map-location-dot" style="color: #16a34a;"></i>
                            Filter Provinsi
                            <span class="ptn-count-badge" id="ptnCountBadge"><?= count($daftar_ptn) ?></span>
                        </div>
                        <select class="filter-provinsi-select" id="filterProvinsi" onchange="filterPTN()">
                            <option value="">Semua Provinsi</option>
                            <?php foreach ($semua_provinsi as $prov): ?>
                                <option value="<?= htmlspecialchars($prov) ?>"><?= htmlspecialchars($prov) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <nav style="padding: 0.5rem 0; flex: 1; min-height: 0; overflow-y: auto;" id="daftarPTNNav">
                        <?php
                        foreach ($daftar_ptn as $slug => $ptn):
                            $is_aktif = ($slug === $ptn_dipilih);
                            ?>
                            <a href="<?= $base_url ?>&ptn=<?= $slug ?>" style="
                           display: block;
                           padding: 0.65rem 1rem;
                           text-decoration: none;
                           border-bottom: 1px solid #f3f4f6;
                           transition: background 0.2s;
                           <?= $is_aktif ? 'background-color: #16a34a;' : 'background-color: #ffffff;' ?>
                       " class="ptn-item <?= $is_aktif ? 'ptn-item-active' : '' ?>"
                                data-provinsi="<?= htmlspecialchars($ptn['provinsi']) ?>">

                                <div class="ptn-name" style="
                            font-size: 0.78rem;
                            font-weight: 600;
                            <?= $is_aktif ? 'color: #ffffff;' : 'color: #1f2937;' ?>
                            line-height: 1.3;
                            margin-bottom: 3px;
                        ">
                                    <?= htmlspecialchars($ptn['nama']) ?>
                                </div>

                                <div style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap;">
                                    <?php if ($ptn['kota'] !== '-' && !empty($ptn['kota'])): ?>
                                        <span class="ptn-city-badge" style="
                                font-size: 10px;
                                padding: 1px 8px;
                                border-radius: 9999px;
                                font-weight: 600;
                                <?= $is_aktif
                                    ? 'background-color: rgba(255,255,255,0.25); color: #ffffff;'
                                    : 'background-color: #dcfce7; color: #15803d;' ?>
                            ">
                                            <?= htmlspecialchars($ptn['kota']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($ptn['provinsi'] !== '-' && !empty($ptn['provinsi']) && $ptn['provinsi'] !== $ptn['kota']): ?>
                                        <span style="
                                font-size: 9.5px;
                                padding: 1px 6px;
                                border-radius: 9999px;
                                font-weight: 500;
                                <?= $is_aktif
                                    ? 'background-color: rgba(255,255,255,0.15); color: rgba(255,255,255,0.85);'
                                    : 'background-color: #f3f4f6; color: #6b7280;' ?>
                            ">
                                            <?= htmlspecialchars($ptn['provinsi']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>

                        <!-- Pesan jika tidak ada PTN yang cocok -->
                        <div id="ptnKosong">
                            <i class="fa-solid fa-triangle-exclamation" style="color: #d1d5db;"></i><br>
                            Tidak ada PTN di provinsi ini.
                        </div>
                    </nav>
                </aside>

                <main style="flex: 1; min-width: 0; height: 100%;">

                    <?php if ($info_ptn_dipilih === null): ?>

                        <div class="fade-in" style="
                            background: #ffffff;
                            border-radius: 14px;
                            overflow: hidden;
                            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                            height: 100%;
                            display: flex;
                            flex-direction: column;
                        ">
                            <div style="
                                background: linear-gradient(135deg, #14532d 0%, #166534 50%, #15803d 100%);
                                padding: 1.25rem 1.5rem;
                                color: #ffffff;
                                display: flex;
                                align-items: center;
                                gap: 0.75rem;
                            ">
                                <div style="
                                    width: 42px; height: 42px;
                                    background: rgba(255,255,255,0.15);
                                    border-radius: 10px;
                                    display: flex; align-items: center; justify-content: center;
                                    border: 1px solid rgba(255,255,255,0.2);
                                    flex-shrink: 0;
                                ">
                                    <i class="fa-solid fa-building-columns" style="font-size: 1.15rem; color: #ffffff;"></i>
                                </div>
                                <div>
                                    <h2 style="font-size: 1.1rem; font-weight: 800; text-transform: uppercase; margin: 0; letter-spacing: 0.5px; color: #ffffff;">
                                        PILIH UNIVERSITAS
                                    </h2>
                                    <p style="color: rgba(134, 239, 172, 0.85); font-size: 0.78rem; margin: 2px 0 0 0;">
                                        Informasi Program Studi & Daya Tampung PTN
                                    </p>
                                </div>
                            </div>

                            <div style="
                                flex: 1;
                                display: flex;
                                flex-direction: column;
                                align-items: center;
                                justify-content: center;
                                padding: 3rem 2rem;
                                text-align: center;
                            ">
                                <div style="
                                    width: 84px; height: 84px;
                                    background: linear-gradient(135deg, #dcfce7 0%, #f0fdf4 100%);
                                    border: 2px solid #bbf7d0;
                                    border-radius: 50%;
                                    display: flex; align-items: center; justify-content: center;
                                    margin-bottom: 1.25rem;
                                    box-shadow: 0 8px 24px rgba(22, 163, 74, 0.12);
                                ">
                                    <i class="fa-solid fa-hand-pointer" style="font-size: 32px; color: #16a34a;"></i>
                                </div>

                                <h2 style="color: #14532d; font-size: 1.25rem; font-weight: 800; margin: 0 0 0.5rem 0;">
                                    Pilih Universitas Terlebih Dahulu
                                </h2>
                                <p style="color: #6b7280; font-size: 0.88rem; max-width: 440px; margin: 0 auto 1.5rem auto; line-height: 1.6;">
                                    Silakan klik salah satu PTN pada daftar di sebelah kiri untuk melihat daftar program studi, daya tampung, dan prediksi peluang kelulusan kamu.
                                </p>

                                <div style="
                                    display: inline-flex;
                                    align-items: center;
                                    gap: 8px;
                                    background: #f0fdf4;
                                    border: 1px dashed #86efac;
                                    padding: 0.6rem 1.2rem;
                                    border-radius: 9999px;
                                    color: #15803d;
                                    font-size: 0.8rem;
                                    font-weight: 600;
                                ">
                                    <i class="fa-solid fa-arrow-left"></i>
                                    <span>Pilih PTN di sidebar sebelah kiri</span>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>

                        <div class="fade-in" style="
                    background: #ffffff;
                    border-radius: 14px;
                    overflow: hidden;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                    height: 100%;
                    display: flex;
                    flex-direction: column;
                ">
                            <div class="card-univ-header" style="
                        padding: 1.25rem 1.5rem;
                        border-bottom: 2px solid #f0fdf4;
                    ">
                                <h2 class="card-univ-title" style="
                            color: #14532d;
                            font-size: 1.2rem;
                            font-weight: 800;
                            text-transform: uppercase;
                            letter-spacing: 0.5px;
                            margin: 0 0 3px 0;
                        ">
                                    <?= htmlspecialchars($info_ptn_dipilih['nama']) ?>
                                </h2>
                                <p class="card-univ-subtitle" style="color: #6b7280; font-size: 0.8rem; margin: 0 0 6px 0;">
                                    <?= htmlspecialchars($info_ptn_dipilih['tipe']) ?>
                                    &nbsp;·&nbsp;
                                    <span style="color: #16a34a; font-weight: 600;">
                                        <?= htmlspecialchars($info_ptn_dipilih['lokasi']) ?>
                                    </span>
                                </p>
                                <div class="card-legend" style="
                                    display: flex;
                                    gap: 1.25rem;
                                    font-size: 0.75rem;
                                    color: #6b7280;
                                    margin-top: 4px;
                                ">
                                    <span>
                                        <span style="color: #16a34a; font-weight: 700;">■</span>
                                        Berpeluang
                                    </span>
                                    <span>
                                        <span style="color: #dc2626; font-weight: 700;">■</span>
                                        Tidak Memenuhi
                                    </span>
                                </div>
                            </div>

                            <?php
                            // 1. Separasi prodi berpeluang & tidak memenuhi
                            $prodi_berpeluang = [];
                            $prodi_tidak_memenuhi = [];

                            foreach ($prodi_ptn_dipilih as $prodi) {
                                if ($rata_rata >= $prodi['nilai_minimal']) {
                                    $prodi_berpeluang[] = $prodi;
                                } else {
                                    $prodi_tidak_memenuhi[] = $prodi;
                                }
                            }

                            // 2. Urutkan berdasarkan nilai minimal
                            usort($prodi_berpeluang, function($a, $b) {
                                return $a['nilai_minimal'] <=> $b['nilai_minimal'];
                            });

                            usort($prodi_tidak_memenuhi, function($a, $b) {
                                return $a['nilai_minimal'] <=> $b['nilai_minimal'];
                            });

                            $semua_prodi_terurut = array_merge($prodi_berpeluang, $prodi_tidak_memenuhi);
                            $jumlah_berpeluang = count($prodi_berpeluang);
                            $jumlah_tidak_memenuhi = count($prodi_tidak_memenuhi);
                            $jumlah_total_prodi = count($prodi_ptn_dipilih);
                            ?>

                            <div class="search-area-wrap" style="padding: 1rem 1.5rem; border-bottom: 1px solid #f3f4f6;">
                                <div style="position: relative;">
                                    <i class="fa-solid fa-magnifying-glass" style="
                                position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
                                color: #9ca3af; font-size: 14px;
                            "></i>
                                    <input type="text" id="searchProdi" placeholder="Cari program studi..."
                                        class="search-input" oninput="filterProdi()" style="
                                    width: 100%;
                                    padding: 0.55rem 1rem 0.55rem 2.25rem;
                                    border: 1.5px solid #d1fae5;
                                    border-radius: 10px;
                                    font-family: 'Poppins', sans-serif;
                                    font-size: 0.82rem;
                                    color: #374151;
                                    background: #f9fafb;
                                    box-sizing: border-box;
                                ">
                                </div>

                                <!-- Filter Controls Bar: Jumlah & Tombol Toggle Seluruh Prodi -->
                                <div class="prodi-filter-bar" style="margin-top: 0.75rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span style="font-size: 0.78rem; color: #4b5563; font-weight: 600;">
                                            <i class="fa-solid fa-list-check" style="color: #16a34a; margin-right: 4px;"></i>
                                            Program Studi:
                                        </span>
                                        <span style="background: #dcfce7; color: #15803d; font-size: 0.72rem; font-weight: 700; padding: 2px 10px; border-radius: 9999px;">
                                            <span id="countProdiTampil"><?= $jumlah_berpeluang ?></span> / <span id="countProdiTotal"><?= $jumlah_total_prodi ?></span> prodi
                                        </span>
                                    </div>

                                    <?php if ($jumlah_tidak_memenuhi > 0): ?>
                                    <button type="button" id="btnToggleProdiHeader" onclick="toggleProdiLain()" style="
                                        display: inline-flex;
                                        align-items: center;
                                        gap: 6px;
                                        background: #f0fdf4;
                                        border: 1.5px solid #86efac;
                                        color: #15803d;
                                        font-size: 0.75rem;
                                        font-weight: 700;
                                        padding: 0.38rem 0.85rem;
                                        border-radius: 9999px;
                                        cursor: pointer;
                                        transition: all 0.2s;
                                        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                                    ">
                                        <i class="fa-solid fa-eye" id="iconToggleProdiHeader"></i>
                                        <span id="teksToggleProdiHeader">Tampilkan Seluruh Prodi (<?= $jumlah_tidak_memenuhi ?> lainnya)</span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="tabel-scroll-container">
                                <table style="width: 100%; border-collapse: collapse;" id="tabelProdi">
                                    <thead>
                                        <tr style="background: #14532d;">
                                            <th
                                                style="color: #fff; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 0.75rem 1rem; text-align: center; width: 40px;">
                                                No</th>
                                            <th
                                                style="color: #fff; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 0.75rem 1rem; text-align: center;">
                                                Kode</th>
                                            <th
                                                style="color: #fff; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 0.75rem 1rem; text-align: left;">
                                                Program Studi</th>
                                            <th
                                                style="color: #fff; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 0.75rem 1rem; text-align: center;">
                                                Jenjang</th>
                                            <th
                                                style="color: #fff; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 0.75rem 1rem; text-align: center;">
                                                Daya Tampung</th>
                                            <th
                                                style="color: #fff; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 0.75rem 1rem; text-align: center;">
                                                Nilai Minimal</th>
                                        </tr>
                                    </thead>

                                    <tbody id="bodyProdi">
                                        <?php
                                        $nomor = 1;
                                        foreach ($semua_prodi_terurut as $prodi):
                                            $bisa_masuk = ($rata_rata >= $prodi['nilai_minimal']);
                                            $kelas_hidden = !$bisa_masuk ? 'prodi-tidak-memenuhi' : '';
                                            $style_hidden = !$bisa_masuk ? 'display: none;' : '';
                                            ?>
                                            <?php
                                            $historis_json = json_encode($prodi['historis']);
                                            $portofolio_safe = htmlspecialchars($prodi['portofolio'], ENT_QUOTES);
                                            $nama_safe = htmlspecialchars(strtoupper($prodi['nama']), ENT_QUOTES);
                                            $kode_safe = htmlspecialchars($prodi['kode'], ENT_QUOTES);
                                            $jenjang_safe = htmlspecialchars($prodi['jenjang'], ENT_QUOTES);
                                            $historis_b64 = base64_encode($historis_json);
                                            ?>
                                            <tr class="tabel-baris <?= $kelas_hidden ?>"
                                                style="<?= $style_hidden ?> border-bottom: 1px solid #f3f4f6; transition: background 0.15s; cursor: pointer;"
                                                data-nama="<?= $nama_safe ?>" data-kode="<?= $kode_safe ?>"
                                                data-jenjang="<?= $jenjang_safe ?>" data-daya="<?= $prodi['daya_tampung'] ?>"
                                                data-portofolio="<?= $portofolio_safe ?>" data-historis="<?= $historis_b64 ?>">
                                                <td
                                                    style="padding: 0.75rem 1rem; text-align: center; color: #6b7280; font-size: 0.82rem; font-weight: 500;">
                                                    <?= $nomor ?>
                                                </td>

                                                <td
                                                    style="padding: 0.75rem 1rem; text-align: center; color: #374151; font-size: 0.82rem; font-family: monospace; font-weight: 600;">
                                                    <?= htmlspecialchars($prodi['kode']) ?>
                                                </td>

                                                <td style="padding: 0.75rem 1rem; text-align: left; color: #111827; font-size: 0.85rem; font-weight: 600;"
                                                    class="nama-prodi">
                                                    <?= htmlspecialchars(strtoupper($prodi['nama'])) ?>
                                                </td>

                                                <td style="padding: 0.75rem 1rem; text-align: center;">
                                                    <span class="badge-s1"><?= htmlspecialchars($prodi['jenjang']) ?></span>
                                                </td>

                                                <td
                                                    style="padding: 0.75rem 1rem; text-align: center; color: #374151; font-size: 0.85rem; font-weight: 500;">
                                                    <?= number_format($prodi['daya_tampung']) ?>
                                                </td>

                                                <td style="padding: 0.75rem 1rem; text-align: center;">
                                                    <div
                                                        style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                                        <span class="<?= $bisa_masuk ? 'nilai-aman' : 'nilai-kurang' ?>"
                                                            style="font-size: 0.9rem;">
                                                            <?= number_format($prodi['nilai_minimal'], 2) ?>
                                                        </span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php
                                            $nomor++;
                                        endforeach;
                                        ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Tombol Lihat Semua Prodi (Bottom) -->
                            <?php if ($jumlah_tidak_memenuhi > 0): ?>
                            <div class="toggle-prodi-wrap" style="padding: 0.85rem 1.5rem; text-align: center; border-top: 1px solid #f3f4f6; background: #ffffff;">
                                <button type="button" id="btnToggleProdiBottom" onclick="toggleProdiLain()" style="
                                    display: inline-flex;
                                    align-items: center;
                                    justify-content: center;
                                    gap: 8px;
                                    width: 100%;
                                    max-width: 380px;
                                    background: #f0fdf4;
                                    border: 1.5px solid #86efac;
                                    color: #15803d;
                                    font-size: 0.82rem;
                                    font-weight: 700;
                                    padding: 0.55rem 1.25rem;
                                    border-radius: 12px;
                                    cursor: pointer;
                                    transition: all 0.2s;
                                    box-shadow: 0 2px 6px rgba(22, 163, 74, 0.08);
                                ">
                                    <i class="fa-solid fa-eye" id="iconToggleProdiBottom"></i>
                                    <span id="teksToggleProdi">Lihat semua prodi (<?= $jumlah_tidak_memenuhi ?> lainnya) →</span>
                                </button>
                            </div>
                            <?php endif; ?>

                        </div>
                    <?php endif; ?>

                </main>
            </div>

            <footer style="text-align: center; padding-bottom: 1rem;">
                <a href="index.php" class="footer-back-btn" style="
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: rgba(255,255,255,0.85);
                background: rgba(255,255,255,0.1);
                border: 1px solid rgba(255,255,255,0.2);
                padding: 0.6rem 1.5rem;
                border-radius: 10px;
                text-decoration: none;
                font-size: 0.85rem;
                font-weight: 500;
                transition: background 0.2s;
            " onmouseover="this.style.background='rgba(255,255,255,0.2)'"
                    onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                    <i class="fa-solid fa-arrow-left"></i>
                    Kembali Ke Awal
                </a>
            </footer>

        </div>

    </section>

    <div id="modalOverlay" onclick="tutupModal()"></div>

    <div id="modalBox">

        <div style="
        background: linear-gradient(135deg, #14532d 0%, #166534 50%, #15803d 100%);
        padding: 1.25rem 1.5rem;
        border-radius: 16px 16px 0 0;
        position: relative;
    ">
            <button onclick="tutupModal()" id="btnTutupModal" title="Tutup" style="
                position: absolute;
                top: 0.9rem;
                right: 1rem;
                background: rgba(255,255,255,0.15);
                border: 1px solid rgba(255,255,255,0.25);
                color: #ffffff;
                width: 30px;
                height: 30px;
                border-radius: 8px;
                cursor: pointer;
                font-size: 1rem;
                font-weight: 700;
                display: flex;
                align-items: center;
                justify-content: center;
                line-height: 1;
                font-family: 'Poppins', sans-serif;
                transition: background 0.2s;
            " onmouseover="this.style.background='rgba(255,255,255,0.3)'"
                onmouseout="this.style.background='rgba(255,255,255,0.15)'">&times;</button>

            <h2 id="modalNama" style="
            color: #ffffff;
            font-size: 1.1rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 2rem 0.25rem 0;
            line-height: 1.3;
        ">-</h2>

            <p id="modalSubtitle" style="
            color: rgba(134, 239, 172, 0.9);
            font-size: 0.78rem;
            margin: 0;
            font-weight: 500;
        ">-</p>
        </div>

        <div style="padding: 1.25rem 1.5rem;">

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem; margin-bottom: 1.25rem;">

                <div style="
                background: #f0fdf4;
                border: 1px solid #bbf7d0;
                border-radius: 10px;
                padding: 0.85rem;
                text-align: center;
            ">
                    <div
                        style="font-size: 0.65rem; font-weight: 700; color: #15803d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.3rem;">
                        Kode Prodi</div>
                    <div id="modalKode"
                        style="font-size: 0.88rem; font-weight: 800; color: #14532d; font-family: monospace;">-</div>
                </div>

                <div style="
                background: #f0fdf4;
                border: 1px solid #bbf7d0;
                border-radius: 10px;
                padding: 0.85rem;
                text-align: center;
            ">
                    <div
                        style="font-size: 0.65rem; font-weight: 700; color: #15803d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.3rem;">
                        Jenjang</div>
                    <div id="modalJenjang" style="font-size: 0.9rem; font-weight: 800; color: #14532d;">-</div>
                </div>

                <div style="
                background: #f0fdf4;
                border: 1px solid #bbf7d0;
                border-radius: 10px;
                padding: 0.85rem;
                text-align: center;
            ">
                    <div
                        style="font-size: 0.65rem; font-weight: 700; color: #15803d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.3rem;">
                        Daya Tampung</div>
                    <div id="modalDaya" style="font-size: 0.9rem; font-weight: 800; color: #14532d;">-</div>
                </div>

                <div style="
                background: #f0fdf4;
                border: 1px solid #bbf7d0;
                border-radius: 10px;
                padding: 0.85rem;
                text-align: center;
            ">
                    <div
                        style="font-size: 0.65rem; font-weight: 700; color: #15803d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.3rem;">
                        Portofolio</div>
                    <div id="modalPortofolio" style="font-size: 0.78rem; font-weight: 700; color: #14532d;">-</div>
                </div>
            </div>

            <div style="
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        ">
                <i class="fa-solid fa-chart-bar" style="color: #16a34a; font-size: 0.9rem;"></i>
                <span style="font-size: 0.82rem; font-weight: 700; color: #14532d;">Data Historis (5 Tahun
                    Terakhir)</span>
            </div>

            <div class="scroll-tabel-historis">
                <table style="width: 100%; border-collapse: collapse; min-width: 360px;">
                    <thead>
                        <tr style="background: linear-gradient(90deg, #14532d, #166534);">
                            <th
                                style="color: #fff; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 0.6rem 0.85rem; text-align: center;">
                                Tahun</th>
                            <th
                                style="color: #fff; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 0.6rem 0.85rem; text-align: center;">
                                Peminat</th>
                            <th
                                style="color: #fff; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 0.6rem 0.85rem; text-align: center;">
                                Daya Tampung</th>
                            <th
                                style="color: #fff; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 0.6rem 0.85rem; text-align: center;">
                                Keketatan</th>
                        </tr>
                    </thead>
                    <tbody id="modalBodyHistoris"></tbody>
                </table>
            </div>

        </div>
    </div>

    <script>
        var prodiSemuaDitampilkan = false;

        function toggleProdiLain() {
            prodiSemuaDitampilkan = !prodiSemuaDitampilkan;
            var barisTidakMemenuhi = document.querySelectorAll('.prodi-tidak-memenuhi');
            var totalBaris = document.querySelectorAll('#bodyProdi tr').length;
            var barisHiddenCount = barisTidakMemenuhi.length;

            barisTidakMemenuhi.forEach(function (row) {
                row.style.display = prodiSemuaDitampilkan ? '' : 'none';
            });

            var countTampilElem = document.getElementById('countProdiTampil');
            if (countTampilElem) {
                countTampilElem.textContent = prodiSemuaDitampilkan ? totalBaris : (totalBaris - barisHiddenCount);
            }

            // Top Header Button Elements
            var btnHeader = document.getElementById('btnToggleProdiHeader');
            var teksHeader = document.getElementById('teksToggleProdiHeader');
            var iconHeader = document.getElementById('iconToggleProdiHeader');

            // Bottom Button Elements
            var btnBottom = document.getElementById('btnToggleProdiBottom');
            var teksBtnBottom = document.getElementById('teksToggleProdi');
            var iconBtnBottom = document.getElementById('iconToggleProdiBottom');

            if (prodiSemuaDitampilkan) {
                if (teksHeader) teksHeader.textContent = 'Sembunyikan prodi lainnya';
                if (iconHeader) iconHeader.className = 'fa-solid fa-eye-slash';
                if (btnHeader) {
                    btnHeader.style.background = '#dcfce7';
                    btnHeader.style.borderColor = '#4ade80';
                    btnHeader.style.color = '#14532d';
                }
                if (teksBtnBottom) teksBtnBottom.textContent = 'Sembunyikan prodi lainnya ↑';
                if (iconBtnBottom) iconBtnBottom.className = 'fa-solid fa-eye-slash';
                if (btnBottom) {
                    btnBottom.style.background = '#dcfce7';
                    btnBottom.style.borderColor = '#4ade80';
                    btnBottom.style.color = '#14532d';
                }
            } else {
                if (teksHeader) teksHeader.textContent = 'Tampilkan Seluruh Prodi (' + barisHiddenCount + ' lainnya)';
                if (iconHeader) iconHeader.className = 'fa-solid fa-eye';
                if (btnHeader) {
                    btnHeader.style.background = '#f0fdf4';
                    btnHeader.style.borderColor = '#86efac';
                    btnHeader.style.color = '#15803d';
                }
                if (teksBtnBottom) teksBtnBottom.textContent = 'Lihat semua prodi (' + barisHiddenCount + ' lainnya) →';
                if (iconBtnBottom) iconBtnBottom.className = 'fa-solid fa-eye';
                if (btnBottom) {
                    btnBottom.style.background = '#f0fdf4';
                    btnBottom.style.borderColor = '#86efac';
                    btnBottom.style.color = '#15803d';
                }
            }
        }

        function filterProdi() {
            var keyword = document.getElementById('searchProdi').value.toLowerCase().trim();
            var baris = document.querySelectorAll('#bodyProdi tr');
            var barisTampilCount = 0;

            baris.forEach(function (b) {
                var namaProdi = b.querySelector('.nama-prodi');
                var kodeProdi = b.getAttribute('data-kode') || '';
                if (namaProdi) {
                    var teks = (namaProdi.textContent + ' ' + kodeProdi).toLowerCase();
                    if (keyword !== '') {
                        var isMatch = teks.includes(keyword);
                        b.style.display = isMatch ? '' : 'none';
                        if (isMatch) barisTampilCount++;
                    } else {
                        if (b.classList.contains('prodi-tidak-memenuhi')) {
                            var showThis = prodiSemuaDitampilkan;
                            b.style.display = showThis ? '' : 'none';
                            if (showThis) barisTampilCount++;
                        } else {
                            b.style.display = '';
                            barisTampilCount++;
                        }
                    }
                }
            });

            var countTampilElem = document.getElementById('countProdiTampil');
            if (countTampilElem) countTampilElem.textContent = barisTampilCount;
        }

        function filterPTN() {
            var keyword = document.getElementById('searchPTN') ? document.getElementById('searchPTN').value.toLowerCase().trim() : '';
            var provinsiDipilih = document.getElementById('filterProvinsi') ? document.getElementById('filterProvinsi').value : '';
            var semuaPTN = document.querySelectorAll('#daftarPTNNav .ptn-item');
            var jumlahTampil = 0;

            semuaPTN.forEach(function (item) {
                var provinsiPTN = item.getAttribute('data-provinsi') || '';
                var namaElem = item.querySelector('.ptn-name');
                var teksPTN = (namaElem ? namaElem.textContent : item.textContent).toLowerCase();

                var cocokProvinsi = (provinsiDipilih === '' || provinsiPTN === provinsiDipilih);
                var cocokKeyword = (keyword === '' || teksPTN.includes(keyword));

                if (cocokProvinsi && cocokKeyword) {
                    item.classList.remove('ptn-item-hidden');
                    jumlahTampil++;
                } else {
                    item.classList.add('ptn-item-hidden');
                }
            });

            var countBadge = document.getElementById('ptnCountBadge');
            if (countBadge) countBadge.textContent = jumlahTampil;

            var pesanKosong = document.getElementById('ptnKosong');
            if (pesanKosong) {
                pesanKosong.style.display = (jumlahTampil === 0) ? 'block' : 'none';
            }
        }

        function filterPTNMobile() {
            var keyword = document.getElementById('searchPTNMobile') ? document.getElementById('searchPTNMobile').value.toLowerCase().trim() : '';
            var selectElement = document.getElementById('mobilePTNSelect');
            if (!selectElement) return;
            var options = selectElement.querySelectorAll('option');

            options.forEach(function (opt) {
                if (opt.value === '') return;
                var teks = opt.textContent.toLowerCase();
                if (keyword === '' || teks.includes(keyword)) {
                    opt.hidden = false;
                    opt.disabled = false;
                    opt.style.display = '';
                } else {
                    opt.hidden = true;
                    opt.disabled = true;
                    opt.style.display = 'none';
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function () {

            var semuaBaris = document.querySelectorAll('.tabel-baris');

            semuaBaris.forEach(function (baris) {
                baris.addEventListener('click', function () {

                    var nama = this.getAttribute('data-nama');
                    var kode = this.getAttribute('data-kode');
                    var jenjang = this.getAttribute('data-jenjang');
                    var daya = this.getAttribute('data-daya');
                    var portofolio = this.getAttribute('data-portofolio');

                    var historisB64 = this.getAttribute('data-historis');
                    var historisJSON = atob(historisB64);
                    var historis = JSON.parse(historisJSON);

                    tampilkanModal(nama, kode, jenjang, daya, portofolio, historis);
                });
            });

        });

        function tampilkanModal(nama, kode, jenjang, daya, portofolio, historis) {

            document.getElementById('modalNama').textContent = nama;
            document.getElementById('modalSubtitle').textContent = jenjang + '  ·  Kode: ' + kode;

            document.getElementById('modalKode').textContent = kode;
            document.getElementById('modalJenjang').textContent = jenjang;
            document.getElementById('modalDaya').textContent = daya;
            document.getElementById('modalPortofolio').textContent = portofolio;

            var tbody = document.getElementById('modalBodyHistoris');
            tbody.innerHTML = '';

            for (var i = 0; i < historis.length; i++) {
                var h = historis[i];
                var warnaLatar = (i % 2 === 0) ? '#ffffff' : '#f9fffe';

                var baris = '<tr style="background:' + warnaLatar + '; border-bottom: 1px solid #f0fdf4;">';
                baris += '<td style="padding: 0.6rem 0.85rem; text-align: center; font-size: 0.82rem; font-weight: 600; color: #374151;">' + h.tahun + '</td>';
                baris += '<td style="padding: 0.6rem 0.85rem; text-align: center; font-size: 0.82rem; color: #374151;">' + h.peminat + '</td>';
                baris += '<td style="padding: 0.6rem 0.85rem; text-align: center; font-size: 0.82rem; color: #374151;">' + h.daya + '</td>';
                baris += '<td style="padding: 0.6rem 0.85rem; text-align: center; font-size: 0.82rem; font-weight: 700; color: #16a34a;">' + h.keketatan + '</td>';
                baris += '</tr>';

                tbody.innerHTML += baris;
            }

            document.getElementById('modalOverlay').style.display = 'block';
            document.getElementById('modalBox').style.display = 'block';
            document.getElementById('modalBox').classList.add('aktif');

            document.body.style.overflow = 'hidden';
        }

        function tutupModal() {
            document.getElementById('modalOverlay').style.display = 'none';
            document.getElementById('modalBox').style.display = 'none';
            document.getElementById('modalBox').classList.remove('aktif');
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                tutupModal();
            }
        });

    </script>

</body>

</html>








































































<!--Biar 1000 line-->