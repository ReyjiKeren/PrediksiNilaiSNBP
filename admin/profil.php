<?php
// admin/profil.php — Halaman Edit Profil Admin
require_once __DIR__ . '/../koneksi/config.php';
require_once __DIR__ . '/components/auth.php';

// Auto migration: Tambahkan kolom foto ke tabel admin jika belum ada
$checkCol = $conn->query("SHOW COLUMNS FROM `admin` LIKE 'foto'");
if ($checkCol && $checkCol->num_rows === 0) {
    $conn->query("ALTER TABLE `admin` ADD COLUMN `foto` VARCHAR(255) NULL DEFAULT NULL AFTER `password`");
}

$activePage = 'profil';
$pageTitle  = 'Edit Profil';
$adminName  = $_SESSION['admin_username'] ?? 'Admin';
$adminId    = $_SESSION['admin_id'] ?? 0;

$successFoto     = '';
$errorFoto       = '';
$successUsername = '';
$errorUsername   = '';
$successPassword = '';
$errorPassword   = '';

// Ambil data admin terbaru dari DB
$stmt = $conn->prepare("SELECT id, username, password, foto FROM admin WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $adminId);
$stmt->execute();
$currentAdmin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$currentAdmin) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Pastikan lokasi uploads/profile/ ada
$uploadDir = __DIR__ . '/../uploads/profile/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

// ============================================================
// PROSES UBAH / HAPUS FOTO PROFIL
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ACTION: UPLOAD / KAMERA FOTO
    if ($_POST['action'] === 'update_foto') {
        $fotoSavedPath = null;
        $maxSizeBytes  = 2 * 1024 * 1024; // 2 MB Limit

        // 1. Opsi Kamera (Base64)
        if (!empty($_POST['foto_base64'])) {
            $base64Data = $_POST['foto_base64'];
            if (preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,/', $base64Data, $type)) {
                $data = substr($base64Data, strpos($base64Data, ',') + 1);
                $data = base64_decode($data);

                if ($data !== false) {
                    if (strlen($data) > $maxSizeBytes) {
                        $errorFoto = 'Ukuran foto hasil jepretan kamera melebihi batas maksimal 2MB.';
                    } else {
                        $ext = strtolower($type[1]);
                        if ($ext === 'jpeg') $ext = 'jpg';
                        $filename = 'profile_' . $adminId . '_' . time() . '.' . $ext;
                        $targetPath = $uploadDir . $filename;

                        if (file_put_contents($targetPath, $data)) {
                            $fotoSavedPath = 'uploads/profile/' . $filename;
                        } else {
                            $errorFoto = 'Gagal menyimpan foto hasil jepretan kamera.';
                        }
                    }
                } else {
                    $errorFoto = 'Format data gambar dari kamera tidak valid.';
                }
            } else {
                $errorFoto = 'Format base64 foto kamera tidak dikenali.';
            }
        }
        // 2. Opsi File Upload
        elseif (isset($_FILES['foto_file']) && $_FILES['foto_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['foto_file'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errorFoto = 'Terjadi kesalahan saat mengunggah file foto.';
            } elseif ($file['size'] > $maxSizeBytes) {
                $errorFoto = 'Ukuran file foto melebihi batas maksimal 2MB (Ukuran file Anda: ' . round($file['size'] / (1024 * 1024), 2) . ' MB).';
            } else {
                $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mimeType, $allowedMimeTypes)) {
                    $errorFoto = 'Format file tidak diizinkan. Gunakan format JPG, JPEG, PNG, atau WEBP.';
                } else {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'profile_' . $adminId . '_' . time() . '.' . strtolower($ext);
                    $targetPath = $uploadDir . $filename;

                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $fotoSavedPath = 'uploads/profile/' . $filename;
                    } else {
                        $errorFoto = 'Gagal memindahkan file foto yang diunggah.';
                    }
                }
            }
        } else {
            $errorFoto = 'Silakan pilih file foto atau ambil foto dari kamera terlebih dahulu.';
        }

        // Simpan ke DB jika upload berhasil
        if ($fotoSavedPath) {
            // Hapus foto lama jika ada
            if (!empty($currentAdmin['foto'])) {
                $oldFile = __DIR__ . '/../' . $currentAdmin['foto'];
                if (file_exists($oldFile) && is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }

            $stmtFoto = $conn->prepare("UPDATE admin SET foto = ? WHERE id = ?");
            $stmtFoto->bind_param('si', $fotoSavedPath, $adminId);

            if ($stmtFoto->execute()) {
                $currentAdmin['foto']   = $fotoSavedPath;
                $_SESSION['admin_foto'] = $fotoSavedPath;
                $successFoto            = 'Foto profil berhasil diperbarui!';
            } else {
                $errorFoto = 'Gagal memperbarui foto profil di database.';
            }
            $stmtFoto->close();
        }
    }

    // ACTION: HAPUS FOTO
    if ($_POST['action'] === 'delete_foto') {
        if (!empty($currentAdmin['foto'])) {
            $oldFile = __DIR__ . '/../' . $currentAdmin['foto'];
            if (file_exists($oldFile) && is_file($oldFile)) {
                @unlink($oldFile);
            }

            $stmtDel = $conn->prepare("UPDATE admin SET foto = NULL WHERE id = ?");
            $stmtDel->bind_param('i', $adminId);
            if ($stmtDel->execute()) {
                $currentAdmin['foto']   = null;
                $_SESSION['admin_foto'] = null;
                $successFoto            = 'Foto profil berhasil dihapus.';
            } else {
                $errorFoto = 'Gagal menghapus foto profil di database.';
            }
            $stmtDel->close();
        }
    }
}

// ============================================================
// PROSES UBAH USERNAME
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_username') {
    $newUsername = trim($_POST['username'] ?? '');

    if (empty($newUsername)) {
        $errorUsername = 'Username tidak boleh kosong.';
    } elseif ($newUsername === $currentAdmin['username']) {
        $errorUsername = 'Username baru tidak boleh sama dengan username saat ini.';
    } elseif (strlen($newUsername) < 3) {
        $errorUsername = 'Username minimal terdiri dari 3 karakter.';
    } else {
        $stmtCek = $conn->prepare("SELECT id FROM admin WHERE username = ? AND id != ? LIMIT 1");
        $stmtCek->bind_param('si', $newUsername, $adminId);
        $stmtCek->execute();
        $resCek = $stmtCek->get_result();

        if ($resCek && $resCek->num_rows > 0) {
            $errorUsername = 'Username tersebut sudah digunakan oleh akun lain.';
        } else {
            $stmtUpd = $conn->prepare("UPDATE admin SET username = ? WHERE id = ?");
            $stmtUpd->bind_param('si', $newUsername, $adminId);

            if ($stmtUpd->execute()) {
                $_SESSION['admin_username'] = $newUsername;
                $currentAdmin['username']   = $newUsername;
                $adminName                  = $newUsername;
                $successUsername            = 'Username berhasil diperbarui!';
            } else {
                $errorUsername = 'Gagal memperbarui username. Terjadi kesalahan pada database.';
            }
            $stmtUpd->close();
        }
        $stmtCek->close();
    }
}

// ============================================================
// PROSES UBAH PASSWORD
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_password') {
    $oldPassword     = $_POST['old_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
        $errorPassword = 'Semua kolom password wajib diisi.';
    } elseif (!password_verify($oldPassword, $currentAdmin['password'])) {
        $errorPassword = 'Password saat ini salah.';
    } elseif (strlen($newPassword) < 6) {
        $errorPassword = 'Password baru minimal 6 karakter.';
    } elseif ($newPassword !== $confirmPassword) {
        $errorPassword = 'Konfirmasi password baru tidak cocok.';
    } else {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmtPass = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
        $stmtPass->bind_param('si', $newHash, $adminId);

        if ($stmtPass->execute()) {
            $currentAdmin['password'] = $newHash;
            $successPassword          = 'Password berhasil diubah!';
        } else {
            $errorPassword = 'Gagal memperbarui password. Terjadi kesalahan pada database.';
        }
        $stmtPass->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Edit Profil – SNBP Admin 2026</title>
    <link rel="shortcut icon" href="../img/logo.png" type="image/png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../src/output.css" />
</head>

<body class="bg-gray-50 min-h-screen">

    <?php include 'components/sidebar.php'; ?>
    <?php include 'components/header.php'; ?>

    <!-- FLOATING TOAST NOTIFICATION CONTAINER -->
    <div id="toastContainer" class="fixed top-20 right-5 z-50 flex flex-col gap-3 max-w-sm w-full pointer-events-none"></div>

    <main class="lg:ml-64 pt-16 min-h-screen">
        <div class="p-6 max-w-5xl mx-auto">

            <!-- Page Header -->
            <div class="page-header mb-6">
                <h1>Edit Profil</h1>
                <p>Kelola foto profil, nama pengguna, dan kata sandi akun administrator Anda</p>
            </div>

            <!-- CARD FOTO PROFIL & USER SUMMARY -->
            <div class="card mb-6">
                <div class="card-header">
                    <h2 class="text-base font-semibold text-gray-800">Foto & Informasi Profil</h2>
                    <span class="badge badge-green">Super Admin</span>
                </div>

                <div class="card-body">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-6">
                        <!-- Foto & Detail User -->
                        <div class="flex flex-col sm:flex-row items-center gap-5 text-center sm:text-left">
                            <div class="shrink-0 flex items-center justify-center">
                                <?php if (!empty($currentAdmin['foto']) && file_exists(__DIR__ . '/../' . $currentAdmin['foto'])): ?>
                                    <img src="../<?= htmlspecialchars($currentAdmin['foto']) ?>" alt="Foto Profil"
                                         style="width: 96px; height: 96px; min-width: 96px; min-height: 96px; max-width: 96px; max-height: 96px; object-fit: cover; border-radius: 9999px; aspect-ratio: 1 / 1;"
                                         class="shadow border-2 border-green-600 shrink-0" />
                                <?php else: ?>
                                    <div style="width: 96px; height: 96px; min-width: 96px; min-height: 96px; max-width: 96px; max-height: 96px; border-radius: 9999px; aspect-ratio: 1 / 1;"
                                         class="bg-green-600 flex items-center justify-center text-white text-3xl font-semibold shadow border-2 border-green-600 shrink-0">
                                        <?= strtoupper(substr($currentAdmin['username'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div>
                                <h2 class="text-lg font-bold text-gray-900">
                                    <?= htmlspecialchars($currentAdmin['username']) ?>
                                </h2>
                                <p class="text-xs text-gray-500 font-medium mt-0.5">
                                    Administrator Utama &bull; Super Admin
                                </p>
                                <div class="mt-2 flex flex-wrap items-center justify-center sm:justify-start gap-2">
                                    <span class="badge badge-green">
                                        Akun Aktif
                                    </span>
                                    <span class="text-xs text-gray-400">&bull; Maksimal: <strong>2MB</strong> (JPG, PNG, WEBP)</span>
                                </div>
                            </div>
                        </div>

                        <!-- Tombol Aksi Foto Profil -->
                        <div class="flex flex-wrap items-center justify-center gap-2">
                            <!-- Form Upload File -->
                            <form id="fileUploadForm" method="POST" action="profil.php" enctype="multipart/form-data" class="m-0 inline-block">
                                <input type="hidden" name="action" value="update_foto" />
                                <input type="file" id="fotoFileInput" name="foto_file"
                                       accept="image/jpeg,image/png,image/webp" class="hidden"
                                       onchange="submitFileUpload(this)" />
                                <button type="button" onclick="document.getElementById('fotoFileInput').click()" class="btn btn-gray">
                                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                    Pilih File
                                </button>
                            </form>

                            <!-- Tombol Kamera -->
                            <button type="button" onclick="openCameraModal()" class="btn btn-green">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Kamera
                            </button>

                            <!-- Tombol Hapus Foto -->
                            <?php if (!empty($currentAdmin['foto'])): ?>
                                <form method="POST" action="profil.php" class="m-0 inline-block" onsubmit="return confirm('Yakin ingin menghapus foto profil?')">
                                    <input type="hidden" name="action" value="delete_foto" />
                                    <button type="submit" class="btn btn-red">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Hapus
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grid 2 Kolom untuk Form Edit Username & Password -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- CARD 1: UBAH USERNAME -->
                <div class="card flex flex-col h-full">
                    <div class="card-header">
                        <h2 class="text-base font-semibold text-gray-800">Ubah Username</h2>
                    </div>

                    <div class="card-body flex-1 flex flex-col justify-between space-y-4">
                        <form method="POST" action="profil.php" class="space-y-4">
                            <input type="hidden" name="action" value="update_username" />

                            <div>
                                <label class="form-label">Username Saat Ini</label>
                                <input type="text" class="form-input bg-gray-100 text-gray-500 cursor-not-allowed"
                                       value="<?= htmlspecialchars($currentAdmin['username']) ?>" disabled />
                            </div>

                            <div>
                                <label for="username" class="form-label">Username Baru</label>
                                <input type="text" id="username" name="username" required class="form-input"
                                       placeholder="Masukkan username baru" />
                            </div>

                            <div class="pt-2 flex justify-end">
                                <button type="submit" class="btn btn-green">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Simpan Username
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- CARD 2: GANTI PASSWORD -->
                <div class="card flex flex-col h-full">
                    <div class="card-header">
                        <h2 class="text-base font-semibold text-gray-800">Ubah Password</h2>
                    </div>

                    <div class="card-body flex-1 flex flex-col justify-between space-y-4">
                        <form method="POST" action="profil.php" class="space-y-4">
                            <input type="hidden" name="action" value="update_password" />

                            <div>
                                <label for="old_password" class="form-label">Password Saat Ini</label>
                                <input type="password" id="old_password" name="old_password" required class="form-input" placeholder="••••••••" />
                            </div>

                            <div>
                                <label for="new_password" class="form-label">Password Baru</label>
                                <input type="password" id="new_password" name="new_password" required class="form-input" placeholder="Minimal 6 karakter" />
                            </div>

                            <div>
                                <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                                <input type="password" id="confirm_password" name="confirm_password" required class="form-input" placeholder="Ulangi password baru" />
                            </div>

                            <div class="pt-2 flex justify-end">
                                <button type="submit" class="btn btn-green">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    Ubah Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>

        </div>
    </main>

    <!-- MODAL KAMERA WEBCAM -->
    <div id="cameraModal" class="modal-overlay hidden">
        <div class="modal-box max-w-md">

            <div class="modal-header-green">
                <h3 class="text-base font-semibold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Ambil Foto dari Kamera
                </h3>
                <button type="button" onclick="closeCameraModal()" class="text-white hover:text-gray-200">✕</button>
            </div>

            <div class="modal-body space-y-4">
                <!-- Video Stream / Canvas Preview (Selalu 1:1 Square Box) -->
                <div class="flex justify-center my-2">
                    <div style="width: 240px; height: 240px; min-width: 240px; min-height: 240px; aspect-ratio: 1 / 1;"
                         class="relative bg-gray-900 rounded-full overflow-hidden border-4 border-green-600 shadow-inner flex items-center justify-center">
                        <video id="webcamVideo" autoplay playsinline style="width: 100%; height: 100%; object-fit: cover; aspect-ratio: 1 / 1;"></video>
                        <canvas id="webcamCanvas" class="hidden" style="width: 100%; height: 100%; object-fit: cover; aspect-ratio: 1 / 1;"></canvas>
                    </div>
                </div>

                <!-- Form tersembunyi submit hasil kamera -->
                <form id="cameraForm" method="POST" action="profil.php">
                    <input type="hidden" name="action" value="update_foto" />
                    <input type="hidden" id="fotoBase64Input" name="foto_base64" value="" />
                </form>

                <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                    <button type="button" onclick="closeCameraModal()" class="btn btn-gray">
                        Batal
                    </button>

                    <div class="flex items-center gap-2">
                        <button type="button" id="btnRetake" onclick="resetWebcam()" class="btn btn-gray hidden">
                            Ulangi
                        </button>
                        <button type="button" id="btnCapture" onclick="capturePhoto()" class="btn btn-green">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke-width="2" />
                                <circle cx="12" cy="12" r="4" fill="currentColor" />
                            </svg>
                            Jepret Foto
                        </button>
                        <button type="button" id="btnSaveCamera" onclick="saveCapturedPhoto()" class="btn btn-green hidden">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Gunakan Foto Ini
                        </button>
                    </div>
                </div>
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

        // Client-side file size & extension validation
        function submitFileUpload(input) {
            if (!input.files || !input.files[0]) return;
            var file = input.files[0];
            var maxSizeBytes = 2 * 1024 * 1024; // 2MB

            if (file.size > maxSizeBytes) {
                showToast('error', 'Ukuran file melebihi batas maksimal 2MB!\nUkuran file Anda: ' + (file.size / (1024 * 1024)).toFixed(2) + ' MB.');
                input.value = '';
                return;
            }

            var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                showToast('error', 'Format file tidak diizinkan! Silakan pilih gambar berformat JPG, PNG, atau WEBP.');
                input.value = '';
                return;
            }

            document.getElementById('fileUploadForm').submit();
        }

        // Camera Webcam Logic
        var mediaStream = null;

        function openCameraModal() {
            var modal = document.getElementById('cameraModal');
            modal.classList.remove('hidden');

            resetWebcam();

            navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480, facingMode: 'user' } })
                .then(function (stream) {
                    mediaStream = stream;
                    var video = document.getElementById('webcamVideo');
                    video.srcObject = stream;
                })
                .catch(function (err) {
                    console.error("Camera access error: ", err);
                    showToast('error', 'Gagal mengakses kamera. Pastikan Anda memberikan izin akses kamera di browser.');
                });
        }

        function closeCameraModal() {
            if (mediaStream) {
                mediaStream.getTracks().forEach(function (track) { track.stop(); });
                mediaStream = null;
            }
            document.getElementById('cameraModal').classList.add('hidden');
        }

        function capturePhoto() {
            var video = document.getElementById('webcamVideo');
            var canvas = document.getElementById('webcamCanvas');
            var context = canvas.getContext('2d');

            var vW = video.videoWidth || 640;
            var vH = video.videoHeight || 480;

            // Square Crop 1:1
            var cropSize = Math.min(vW, vH);
            var startX = (vW - cropSize) / 2;
            var startY = (vH - cropSize) / 2;

            canvas.width = cropSize;
            canvas.height = cropSize;
            context.drawImage(video, startX, startY, cropSize, cropSize, 0, 0, cropSize, cropSize);

            video.classList.add('hidden');
            canvas.classList.remove('hidden');

            document.getElementById('btnCapture').classList.add('hidden');
            document.getElementById('btnRetake').classList.remove('hidden');
            document.getElementById('btnSaveCamera').classList.remove('hidden');
        }

        function resetWebcam() {
            var video = document.getElementById('webcamVideo');
            var canvas = document.getElementById('webcamCanvas');

            canvas.classList.add('hidden');
            video.classList.remove('hidden');

            document.getElementById('btnCapture').classList.remove('hidden');
            document.getElementById('btnRetake').classList.add('hidden');
            document.getElementById('btnSaveCamera').classList.add('hidden');
        }

        function saveCapturedPhoto() {
            var canvas = document.getElementById('webcamCanvas');
            var dataURL = canvas.toDataURL('image/png');

            document.getElementById('fotoBase64Input').value = dataURL;
            document.getElementById('cameraForm').submit();
        }
    </script>

    <!-- TRIGER TOAST DARI PHP SESUAI STATUS PROSES -->
    <script>
    <?php if ($successFoto): ?>
        showToast('success', <?= json_encode($successFoto) ?>);
    <?php endif; ?>
    <?php if ($errorFoto): ?>
        showToast('error', <?= json_encode($errorFoto) ?>);
    <?php endif; ?>
    <?php if ($successUsername): ?>
        showToast('success', <?= json_encode($successUsername) ?>);
    <?php endif; ?>
    <?php if ($errorUsername): ?>
        showToast('error', <?= json_encode($errorUsername) ?>);
    <?php endif; ?>
    <?php if ($successPassword): ?>
        showToast('success', <?= json_encode($successPassword) ?>);
    <?php endif; ?>
    <?php if ($errorPassword): ?>
        showToast('error', <?= json_encode($errorPassword) ?>);
    <?php endif; ?>
    </script>
</body>

</html>