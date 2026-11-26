<?php
// admin/login.php — Halaman Login Admin SNBP 2026

require_once __DIR__ . '/../koneksi/config.php';
// Catatan: session_start() sudah dipanggil di dalam config.php

// Jika sudah login, langsung redirect ke dashboard
if (isset($_SESSION['admin_login']) && $_SESSION['admin_login'] === true) {
    header("Location: index.php");
    exit;
}

$errorMsg = '';

// ============================================================
// Proses Form Login (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Ambil & bersihkan input
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // 2. Validasi input tidak kosong
    if ($username === '' || $password === '') {
        $errorMsg = 'Username dan password tidak boleh kosong.';
    } else {
        // 3. Cari admin di database menggunakan Prepared Statement
        $stmt = $conn->prepare("SELECT id, username, password FROM admin WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();
        $stmt->close();

        // 4. Verifikasi password dengan password_verify()
        if ($admin && password_verify($password, $admin['password'])) {
            // 5. Login berhasil — buat session yang aman
            session_regenerate_id(true); // Cegah session fixation

            $_SESSION['admin_login'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];

            // 6. Redirect ke dashboard
            header("Location: index.php");
            exit;
        } else {
            // 7. Login gagal — pesan generik
            $errorMsg = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Login Admin — Sistem Administrasi Prediksi Nilai SNBP 2026" />
    <title>Login Admin — SNBP 2026</title>
    <link rel="shortcut icon" href="../img/logo.png" type="image/png" />

    <!-- Google Fonts: Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="../src/output.css" />

    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        .login-bg {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 40%, #f8fafc 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .login-card {
            background: #ffffff;
            border-radius: 1.25rem;
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.08), 0 2px 8px rgba(0, 0, 0, 0.04);
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 400px;
        }

        .login-input {
            width: 100%;
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
            font-family: 'Poppins', sans-serif;
            color: #1f2937;
            background: #f9fafb;
            border: 1.5px solid #e5e7eb;
            border-radius: 0.625rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }

        .login-input:focus {
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.12);
            background: #fff;
        }

        .login-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.375rem;
        }

        .login-btn {
            width: 100%;
            padding: 0.7rem;
            font-size: 0.9rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            color: #ffffff;
            background: linear-gradient(135deg, #16a34a, #15803d);
            border: none;
            border-radius: 0.625rem;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.1s;
            letter-spacing: 0.02em;
        }

        .login-btn:hover {
            opacity: 0.92;
        }

        .login-btn:active {
            transform: scale(0.98);
        }

        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 0.625rem;
            padding: 0.625rem 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: #dc2626;
            font-weight: 500;
        }

        .dot-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: radial-gradient(#d1fae5 1px, transparent 1px);
            background-size: 28px 28px;
            opacity: 0.5;
            pointer-events: none;
            z-index: 0;
        }

        .login-bg>* {
            position: relative;
            z-index: 1;
        }
    </style>
</head>

<body>
    <div class="dot-pattern"></div>

    <div class="login-bg">
        <div class="login-card">

            <!-- Logo + Judul -->
            <div class="text-center mb-8">
                <div class="flex justify-center mb-3">
                    <img src="../img/logo.png" alt="Logo SNBP" style="width:56px;height:56px;object-fit:contain;" />
                </div>
                <h1 class="text-xl font-bold text-gray-900 leading-tight">SNBP Admin 2026</h1>
                <p class="text-sm text-gray-500 mt-1">Masuk ke panel administrasi</p>
            </div>

            <!-- Pesan Error -->
            <?php if ($errorMsg !== ''): ?>
                <div class="error-box mb-5" role="alert">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        style="flex-shrink:0;">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    <?= htmlspecialchars($errorMsg) ?>
                </div>
            <?php endif; ?>

            <!-- Form Login -->
            <form method="POST" action="login.php" id="loginForm" novalidate>

                <!-- Username -->
                <div class="mb-4">
                    <label class="login-label" for="username">Username</label>
                    <input type="text" id="username" name="username" class="login-input" placeholder="Masukkan username"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autocomplete="username" />
                </div>

                <!-- Password -->
                <div class="mb-6">
                    <label class="login-label" for="password">Password</label>
                    <div style="position:relative;">
                        <input type="password" id="password" name="password" class="login-input" placeholder="••••••••"
                            required autocomplete="current-password" style="padding-right:2.75rem;" />
                        <!-- Toggle show/hide password -->
                        <button type="button" onclick="togglePassword()" style="position:absolute;right:0.75rem;top:50%;transform:translateY(-50%);
                                       background:none;border:none;cursor:pointer;color:#9ca3af;padding:0;"
                            title="Tampilkan/sembunyikan password">
                            <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Tombol Login -->
                <button type="submit" class="login-btn" id="loginBtn">Masuk</button>

            </form>

            <!-- Footer -->
            <p class="text-center text-xs text-gray-400 mt-6">
                Prediksi Nilai SNBP 2026 &copy;
                <?= date('Y') ?>
            </p>

        </div>
    </div>

    <script>
        function togglePassword() {
            var input = document.getElementById('password');
            var icon = document.getElementById('eyeIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/>' +
                    '<path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/>' +
                    '<line x1="1" y1="1" x2="23" y2="23"/>';
            } else {
                input.type = 'password';
                icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>' +
                    '<circle cx="12" cy="12" r="3"/>';
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function () {
            var btn = document.getElementById('loginBtn');
            btn.textContent = 'Memproses...';
            btn.disabled = true;
        });
    </script>
</body>

</html>