<?php
// components/sidebar.php
// Komponen sidebar yang digunakan di semua halaman admin
// $activePage - string: 'dashboard', 'ptn', 'prodi', 'historis', 'pengguna', 'pengaturan'

$menu = [
    ['key' => 'dashboard',   'label' => 'Dashboard',            'href' => 'index.php',    'icon' => 'dashboard'],
    ['key' => 'ptn',         'label' => 'Data PTN',             'href' => 'ptn.php',      'icon' => 'university'],
    ['key' => 'prodi',       'label' => 'Data Program Studi',   'href' => 'prodi.php',    'icon' => 'book'],
    ['key' => 'historis',    'label' => 'Data Historis',        'href' => 'historis.php', 'icon' => 'chart'],
    ['key' => 'viewer',      'label' => 'Data Viewer',          'href' => 'viewer.php',   'icon' => 'users'],
    ['key' => 'pengguna',    'label' => 'Daftar Admin',         'href' => 'pengguna.php', 'icon' => 'users'],
    ['key' => 'profil',      'label' => 'Edit Profil',          'href' => 'profil.php',   'icon' => 'user'],
];
?>

<!-- Sidebar Admin -->
<aside id="sidebar"
    class="fixed top-0 left-0 h-screen w-64 bg-white border-r border-gray-200 flex flex-col z-40
           -translate-x-full lg:translate-x-0 transition-transform duration-300">

    <!-- Logo / Brand -->
    <div class="flex items-center gap-3 px-5 py-5 border-b border-gray-100">
        <!-- Logo Image -->
        <img src="../img/logo.png" alt="SNBP Logo" class="w-10 h-10 object-contain shrink-0" />
        <div>
            <div class="text-sm font-bold text-gray-900 leading-tight">SNBP ADMIN</div>
            <div class="text-xs text-gray-500 leading-tight">Prediksi Nilai SNBP 2026</div>
        </div>
    </div>

    <!-- Menu Navigasi -->
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        <?php foreach ($menu as $item): ?>
            <a href="<?= $item['href'] ?>"
               class="sidebar-link <?= ($activePage === $item['key']) ? 'active' : '' ?>">

                <?php if ($item['icon'] === 'dashboard'): ?>
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2
                            2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0
                            011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>

                <?php elseif ($item['icon'] === 'university'): ?>
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9
                            0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0
                            011 1v5m-4 0h4"/>
                    </svg>

                <?php elseif ($item['icon'] === 'book'): ?>
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3
                            6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168
                            5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477
                            18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>

                <?php elseif ($item['icon'] === 'chart'): ?>
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0
                            002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0
                            002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>

                <?php elseif ($item['icon'] === 'users'): ?>
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7
                            20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002
                            5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>

                <?php elseif ($item['icon'] === 'user'): ?>
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>

                <?php elseif ($item['icon'] === 'setting'): ?>
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0
                            002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065
                            2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066
                            2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572
                            1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724
                            1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0
                            001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                <?php endif; ?>

                <span><?= htmlspecialchars($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Logout di bagian bawah -->
    <div class="px-3 py-4 border-t border-gray-100">
        <a href="logout.php" class="sidebar-link text-red-500 hover:bg-red-50 hover:text-red-600"
           onclick="return confirm('Yakin ingin keluar?')">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0
                    01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            <span>Logout</span>
        </a>
    </div>
    
</aside>

<!-- Overlay untuk mobile (menutup sidebar) -->
<div id="sidebarOverlay"
     class="fixed inset-0 bg-black/50 z-30 hidden lg:hidden"
     onclick="closeSidebar()">
</div>
