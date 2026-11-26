<?php
// components/header.php
// Header bar atas setiap halaman
// $pageTitle   - string: judul halaman (misal: 'Dashboard')
// $adminName   - string: nama admin yang tampil di kanan atas
?>

<!-- Header / Topbar -->
<header
    class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 fixed top-0 right-0 left-0 lg:left-64 z-20">

    <!-- Kiri: tombol hamburger (mobile) + judul -->
    <div class="flex items-center gap-3">
        <!-- Tombol hamburger hanya muncul di mobile -->
        <button id="hamburgerBtn" onclick="openSidebar()"
            class="lg:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <!-- Judul Halaman -->
        <h2 class="text-base font-semibold text-gray-800">
            <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?>
        </h2>
    </div>

    <!-- Kanan: info admin -->
    <a href="profil.php" class="flex items-center gap-3 hover:opacity-80 transition-opacity cursor-pointer group"
        title="Edit Profil">
        <div class="text-right hidden sm:block">
            <div class="text-sm font-medium text-gray-800 group-hover:text-green-600 transition-colors">
                <?= htmlspecialchars($_SESSION['admin_username'] ?? $adminName ?? 'Admin') ?>
            </div>
            <div class="text-xs text-gray-500">Super Admin</div>
        </div>

        <!-- Avatar admin -->
        <?php if (!empty($_SESSION['admin_foto']) && file_exists(__DIR__ . '/../../' . $_SESSION['admin_foto'])): ?>
            <img src="../<?= htmlspecialchars($_SESSION['admin_foto']) ?>" alt="Foto Profil"
                style="width:36px; height:36px; min-width:36px; min-height:36px; object-fit:cover; border-radius:9999px; aspect-ratio: 1 / 1;"
                class="shrink-0 shadow-sm border border-green-500 group-hover:border-green-600 transition-colors" />
        <?php else: ?>
            <div style="width:36px; height:36px; min-width:36px; min-height:36px; border-radius:9999px; aspect-ratio: 1 / 1;"
                class="bg-green-600 flex items-center justify-center shrink-0 shadow-sm group-hover:bg-green-700 transition-colors">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>
        <?php endif; ?>
    </a>
</header>