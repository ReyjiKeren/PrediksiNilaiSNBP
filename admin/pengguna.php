<?php
// admin/pengguna.php — Halaman Daftar & Manajemen Admin
require_once __DIR__ . '/../koneksi/config.php';
require_once __DIR__ . '/components/auth.php';

// Auto migration: Tambahkan kolom foto ke tabel admin jika belum ada
$checkCol = $conn->query("SHOW COLUMNS FROM `admin` LIKE 'foto'");
if ($checkCol && $checkCol->num_rows === 0) {
    $conn->query("ALTER TABLE `admin` ADD COLUMN `foto` VARCHAR(255) NULL DEFAULT NULL AFTER `password`");
}

$activePage = 'pengguna';
$pageTitle  = 'Daftar Admin';
$adminName  = $_SESSION['admin_username'] ?? 'Admin';
$currentId  = $_SESSION['admin_id'] ?? 0;

$msgSuccess = '';
$msgError   = '';

// =========================================================================
// PROSES AKSI MANAJEMEN ADMIN (POST)
// =========================================================================
$aksi = $_GET['aksi'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. TAMBAH ADMIN BARU
    if ($aksi === 'tambah') {
        $username        = trim($_POST['username'] ?? '');
        $password        = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($username) || empty($password) || empty($confirmPassword)) {
            $msgError = 'Semua kolom form wajib diisi.';
        } elseif (strlen($username) < 3) {
            $msgError = 'Username minimal terdiri dari 3 karakter.';
        } elseif (strlen($password) < 6) {
            $msgError = 'Password minimal terdiri dari 6 karakter.';
        } elseif ($password !== $confirmPassword) {
            $msgError = 'Konfirmasi password tidak cocok.';
        } else {
            // Cek apakah username sudah dipakai
            $stmtCek = $conn->prepare("SELECT id FROM admin WHERE username = ? LIMIT 1");
            $stmtCek->bind_param('s', $username);
            $stmtCek->execute();
            $resCek = $stmtCek->get_result();

            if ($resCek && $resCek->num_rows > 0) {
                $msgError = "Username '{$username}' sudah terdaftar. Silakan gunakan username lain.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmtIns = $conn->prepare("INSERT INTO admin (username, password) VALUES (?, ?)");
                $stmtIns->bind_param('ss', $username, $hash);

                if ($stmtIns->execute()) {
                    $msgSuccess = "Akun admin baru '{$username}' berhasil ditambahkan!";
                } else {
                    $msgError = 'Gagal menambahkan admin baru ke database.';
                }
                $stmtIns->close();
            }
            $stmtCek->close();
        }
    }

    // 2. RESET PASSWORD ADMIN
    if ($aksi === 'reset_password') {
        $targetId        = intval($_POST['id_admin'] ?? 0);
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($targetId <= 0 || empty($newPassword) || empty($confirmPassword)) {
            $msgError = 'Semua kolom reset password wajib diisi.';
        } elseif (strlen($newPassword) < 6) {
            $msgError = 'Password baru minimal terdiri dari 6 karakter.';
        } elseif ($newPassword !== $confirmPassword) {
            $msgError = 'Konfirmasi password baru tidak cocok.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmtPass = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
            $stmtPass->bind_param('si', $hash, $targetId);

            if ($stmtPass->execute()) {
                $msgSuccess = 'Password akun admin berhasil di-reset!';
            } else {
                $msgError = 'Gagal mengupdate password admin.';
            }
            $stmtPass->close();
        }
    }

    // 3. HAPUS AKUN ADMIN
    if ($aksi === 'hapus') {
        $targetId = intval($_POST['id_admin'] ?? 0);

        if ($targetId <= 0) {
            $msgError = 'ID Admin tidak valid.';
        } elseif ($targetId === $currentId) {
            $msgError = 'Anda tidak dapat menghapus akun Anda sendiri yang sedang digunakan!';
        } else {
            // Ambil info foto admin sebelum dihapus
            $stmtCek = $conn->prepare("SELECT username, foto FROM admin WHERE id = ? LIMIT 1");
            $stmtCek->bind_param('i', $targetId);
            $stmtCek->execute();
            $adminTarget = $stmtCek->get_result()->fetch_assoc();
            $stmtCek->close();

            if ($adminTarget) {
                // Hapus foto jika ada
                if (!empty($adminTarget['foto'])) {
                    $fileFoto = __DIR__ . '/../' . $adminTarget['foto'];
                    if (file_exists($fileFoto) && is_file($fileFoto)) {
                        @unlink($fileFoto);
                    }
                }

                $stmtDel = $conn->prepare("DELETE FROM admin WHERE id = ?");
                $stmtDel->bind_param('i', $targetId);

                if ($stmtDel->execute()) {
                    $msgSuccess = "Akun admin '{$adminTarget['username']}' berhasil dihapus.";
                } else {
                    $msgError = 'Gagal menghapus akun admin dari database.';
                }
                $stmtDel->close();
            } else {
                $msgError = 'Akun admin tidak ditemukan.';
            }
        }
    }
}

// =========================================================================
// AMBIL DAFTAR SELURUH ADMIN
// =========================================================================
$resAdmin = $conn->query("SELECT id, username, foto FROM admin ORDER BY id ASC");
$adminList = [];
if ($resAdmin) {
    while ($row = $resAdmin->fetch_assoc()) {
        $adminList[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Daftar Admin – SNBP Admin 2026</title>
    <link rel="shortcut icon" href="../img/logo.png" type="image/png"/>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="../src/output.css"/>
    <style>
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

    <?php include 'components/sidebar.php'; ?>
    <?php include 'components/header.php'; ?>

    <!-- FLOATING TOAST NOTIFICATION CONTAINER -->
    <div id="toastContainer" class="fixed top-20 right-5 z-50 flex flex-col gap-3 max-w-sm w-full pointer-events-none"></div>

    <main class="lg:ml-64 pt-16 min-h-screen">
        <div class="p-6 max-w-6xl mx-auto">

            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Daftar Admin</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Kelola daftar seluruh akun administrator sistem SNBP 2026</p>
                </div>
                <button type="button" onclick="openModalTambahAdmin()"
                        class="btn btn-green self-start sm:self-auto">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Tambah Admin Baru
                </button>
            </div>

            <!-- Tabel Daftar Admin -->
            <div class="card overflow-hidden">
                <div class="card-header">
                    <h2 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Total Administrator (<?= count($adminList) ?>)
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="w-12 text-center">No</th>
                                <th class="w-16 text-center">Foto</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($adminList)): ?>
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-gray-400 text-xs">
                                        Belum ada data admin terdaftar.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($adminList as $i => $adm): ?>
                                    <tr>
                                        <td class="text-center text-xs text-gray-400 font-medium">
                                            <?= $i + 1 ?>
                                        </td>

                                        <td>
                                            <div class="flex items-center justify-center">
                                                <?php if (!empty($adm['foto']) && file_exists(__DIR__ . '/../' . $adm['foto'])): ?>
                                                    <img src="../<?= htmlspecialchars($adm['foto']) ?>"
                                                         alt="Foto <?= htmlspecialchars($adm['username']) ?>"
                                                         style="width:38px; height:38px; min-width:38px; min-height:38px; max-width:38px; max-height:38px; object-fit:cover; border-radius:9999px; aspect-ratio:1/1;"
                                                         class="shadow-sm border border-green-500 shrink-0"/>
                                                <?php else: ?>
                                                    <div style="width:38px; height:38px; min-width:38px; min-height:38px; max-width:38px; max-height:38px; border-radius:9999px; aspect-ratio:1/1;"
                                                         class="bg-green-600 flex items-center justify-center text-white text-sm font-semibold shadow-sm border border-green-500 shrink-0">
                                                        <?= strtoupper(substr($adm['username'], 0, 1)) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="font-semibold text-gray-900 flex items-center gap-2">
                                                <?= htmlspecialchars($adm['username']) ?>
                                                <?php if ($adm['id'] == $currentId): ?>
                                                    <span class="badge badge-green">
                                                        (Anda)
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <td>
                                            <span class="badge badge-green">
                                                Super Admin
                                            </span>
                                        </td>

                                        <td class="text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <!-- Tombol Reset Password -->
                                                <button type="button"
                                                        onclick="openModalResetPassword(<?= $adm['id'] ?>, '<?= htmlspecialchars(addslashes($adm['username'])) ?>')"
                                                        class="btn btn-sm btn-yellow">
                                                    Reset Password
                                                </button>

                                                <!-- Tombol Hapus Admin -->
                                                <?php if ($adm['id'] == $currentId): ?>
                                                    <button type="button" disabled
                                                            class="btn btn-sm btn-gray opacity-50 cursor-not-allowed"
                                                            title="Tidak dapat menghapus akun sendiri">
                                                        Hapus
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button"
                                                            onclick="openModalHapusAdmin(<?= $adm['id'] ?>, '<?= htmlspecialchars(addslashes($adm['username'])) ?>')"
                                                            class="btn btn-sm btn-red">
                                                        Hapus
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- MODAL TAMBAH ADMIN -->
    <div id="modalTambahAdmin" class="modal-overlay hidden">
        <div class="modal-box max-w-md">
            <div class="modal-header-green">
                <h3 class="text-base font-semibold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                    Tambah Akun Admin Baru
                </h3>
                <button type="button" onclick="closeModal('modalTambahAdmin')" class="text-white hover:text-gray-200">✕</button>
            </div>

            <div class="modal-body">
                <form method="POST" action="pengguna.php?aksi=tambah" class="space-y-4">
                    <div>
                        <label for="add_username" class="form-label">Username Admin</label>
                        <input type="text" id="add_username" name="username" required class="form-input" placeholder="Contoh: admin2"/>
                    </div>

                    <div>
                        <label for="add_password" class="form-label">Password</label>
                        <input type="password" id="add_password" name="password" required class="form-input" placeholder="Minimal 6 karakter"/>
                    </div>

                    <div>
                        <label for="add_confirm_password" class="form-label">Konfirmasi Password</label>
                        <input type="password" id="add_confirm_password" name="confirm_password" required class="form-input" placeholder="Ulangi password"/>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-gray-100 mt-6">
                        <button type="button" onclick="closeModal('modalTambahAdmin')" class="btn btn-gray">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-green">
                            Simpan Admin
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL RESET PASSWORD ADMIN -->
    <div id="modalResetPassword" class="modal-overlay hidden">
        <div class="modal-box max-w-md">
            <div class="modal-header-green">
                <h3 class="text-base font-semibold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                    Reset Password Admin: <span id="resetTargetUsername" class="text-white underline"></span>
                </h3>
                <button type="button" onclick="closeModal('modalResetPassword')" class="text-white hover:text-gray-200">✕</button>
            </div>

            <div class="modal-body">
                <form method="POST" action="pengguna.php?aksi=reset_password" class="space-y-4">
                    <input type="hidden" id="resetTargetId" name="id_admin" value=""/>

                    <div>
                        <label for="reset_new_password" class="form-label">Password Baru</label>
                        <input type="password" id="reset_new_password" name="new_password" required class="form-input" placeholder="Minimal 6 karakter"/>
                    </div>

                    <div>
                        <label for="reset_confirm_password" class="form-label">Konfirmasi Password Baru</label>
                        <input type="password" id="reset_confirm_password" name="confirm_password" required class="form-input" placeholder="Ulangi password baru"/>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-gray-100 mt-6">
                        <button type="button" onclick="closeModal('modalResetPassword')" class="btn btn-gray">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-yellow">
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL HAPUS ADMIN -->
    <div id="modalHapusAdmin" class="modal-overlay hidden">
        <div class="modal-box max-w-sm">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Hapus Akun Admin
                </h3>
                <button type="button" onclick="closeModal('modalHapusAdmin')" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>

            <div class="modal-body text-center space-y-4">
                <form method="POST" action="pengguna.php?aksi=hapus" class="space-y-4">
                    <input type="hidden" id="hapusTargetId" name="id_admin" value=""/>
                    <p class="text-xs text-gray-600">
                        Apakah Anda yakin ingin menghapus akun admin <strong id="hapusTargetUsername" class="text-gray-900 font-bold"></strong>? Akses akun ini ke panel admin akan hilang secara permanen.
                    </p>

                    <div class="flex items-center justify-center gap-2 pt-2">
                        <button type="button" onclick="closeModal('modalHapusAdmin')" class="btn btn-gray flex-1 justify-center">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-red flex-1 justify-center">
                            Ya, Hapus
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function openSidebar() {
        document.getElementById('sidebar').classList.remove('-translate-x-full');
        document.getElementById('sidebarOverlay').classList.remove('hidden');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.add('-translate-x-full');
        document.getElementById('sidebarOverlay').classList.add('hidden');
    }
    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
    }
    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
    }
    function openModalTambahAdmin() {
        openModal('modalTambahAdmin');
    }
    function openModalResetPassword(id, username) {
        document.getElementById('resetTargetId').value = id;
        document.getElementById('resetTargetUsername').textContent = username;
        openModal('modalResetPassword');
    }
    function openModalHapusAdmin(id, username) {
        document.getElementById('hapusTargetId').value = id;
        document.getElementById('hapusTargetUsername').textContent = username;
        openModal('modalHapusAdmin');
    }

    // ============================================================
    // TOAST NOTIFICATION SYSTEM
    // ============================================================
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
    </script>

    <!-- TRIGER TOAST DARI PHP SESUAI STATUS PROSES -->
    <script>
    <?php if ($msgSuccess): ?>
        showToast('success', <?= json_encode($msgSuccess) ?>);
    <?php endif; ?>
    <?php if ($msgError): ?>
        showToast('error', <?= json_encode($msgError) ?>);
    <?php endif; ?>
    </script>
</body>
</html>