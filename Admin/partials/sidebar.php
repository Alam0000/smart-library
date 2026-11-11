<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<style>
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 260px;
        height: 100vh;
        background: linear-gradient(180deg, #111827 0%, #1f2937 100%);
        color: #fff;
        z-index: 1000;
        overflow-y: auto;
        padding: 22px 16px;
        box-sizing: border-box;
        box-shadow: 4px 0 20px rgba(0, 0, 0, 0.12);
    }

    /* Scrollbar */
    .sidebar::-webkit-scrollbar {
        width: 5px;
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.18);
        border-radius: 10px;
    }

    /* Logo */
    .sidebar .logo {
        padding: 8px 10px 24px;
        margin-bottom: 10px;
        border-bottom: 1px solid rgba(255,255,255,0.08);
    }

    .sidebar .logo h3 {
        margin: 0;
        font-size: 19px;
        font-weight: 700;
        color: #ffffff;
        line-height: 1.4;
    }

    .sidebar .logo p {
        margin: 5px 0 0;
        font-size: 12px;
        color: #9ca3af;
    }

    /* Menu title */
    .sidebar .menu-title {
        margin: 24px 10px 9px;
        font-size: 10px;
        font-weight: 700;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Menu */
    .sidebar a {
        display: flex;
        align-items: center;
        gap: 12px;
        width: 100%;
        min-height: 44px;
        padding: 10px 12px;
        margin-bottom: 4px;
        box-sizing: border-box;

        color: #d1d5db;
        text-decoration: none;
        border-radius: 9px;

        font-size: 13px;
        font-weight: 500;

        transition: all 0.2s ease;
    }

    .sidebar a i {
        width: 20px;
        min-width: 20px;
        text-align: center;
        font-size: 16px;
    }

    .sidebar a:hover {
        background: rgba(255,255,255,0.08);
        color: #ffffff;
        transform: translateX(2px);
    }

    .sidebar a.active {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
    }

    .sidebar a.active i {
        color: #ffffff;
    }

    /* Logout */
    .sidebar a.logout {
        color: #fca5a5;
    }

    .sidebar a.logout:hover {
        background: rgba(239, 68, 68, 0.12);
        color: #fecaca;
    }

    /* Mobile */
    @media (max-width: 768px) {
        .sidebar {
            width: 230px;
        }
    }
</style>

<div class="sidebar">

    <!-- LOGO -->
    <div class="logo">
        <h3>📚 Smart Library</h3>
        <p>SMP Negeri 4 Solear</p>
    </div>

    <!-- MAIN MENU -->
    <div class="menu-title">Main Menu</div>

    <a href="dashboard.php"
       class="<?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
        <i class="bi bi-grid-fill"></i>
        <span>Dashboard</span>
    </a>

    <a href="kel_buku.php"
       class="<?= $currentPage == 'kel_buku.php' ? 'active' : '' ?>">
        <i class="bi bi-book-fill"></i>
        <span>Kelola Buku</span>
    </a>

    <a href="kriteria.php"
       class="<?= $currentPage == 'kriteria.php' ? 'active' : '' ?>">
        <i class="bi bi-sliders"></i>
        <span>Kriteria</span>
    </a>

    <a href="saw/generate_nilai.php"
       class="<?= $currentPage == 'generate_nilai.php' ? 'active' : '' ?>">
        <i class="bi bi-cpu-fill"></i>
        <span>Generate Nilai</span>
    </a>


    <!-- METODE SPK -->
    <div class="menu-title">Metode SPK</div>

    <a href="ahp/input.php"
       class="<?= $currentPage == 'input.php' ? 'active' : '' ?>">
        <i class="bi bi-diagram-3-fill"></i>
        <span>Input AHP</span>
    </a>

    <a href="ahp/hitung_lengkap.php"
       class="<?= $currentPage == 'hitung_lengkap.php' ? 'active' : '' ?>">
        <i class="bi bi-bar-chart-fill"></i>
        <span>Hitung AHP</span>
    </a>

    <a href="saw/hitung.php"
       class="<?= $currentPage == 'hitung.php' ? 'active' : '' ?>">
        <i class="bi bi-calculator-fill"></i>
        <span>Hitung SAW</span>
    </a>

    <a href="hasil_ranking.php"
       class="<?= $currentPage == 'hasil_ranking.php' ? 'active' : '' ?>">
        <i class="bi bi-trophy-fill"></i>
        <span>Hasil Ranking</span>
    </a>


    <!-- TRANSAKSI -->
    <div class="menu-title">Transaksi</div>

    <a href="peminjaman/index.php"
       class="<?= $currentPage == 'index.php' ? 'active' : '' ?>">
        <i class="bi bi-journal-bookmark-fill"></i>
        <span>Peminjaman</span>
    </a>



    <a href="laporan_analitik.php"
       class="<?= $currentPage == 'laporan_analitik.php' ? 'active' : '' ?>">
        <i class="bi bi-graph-up"></i>
        <span>Laporan & Analitik</span>
    </a>

    <a href="pengguna.php"
       class="<?= $currentPage == 'pengguna.php' ? 'active' : '' ?>">
        <i class="bi bi-people"></i>
        <span>Kelola Pengguna</span>
    </a>

    <a href="impor_buku.php"
       class="<?= $currentPage == 'impor_buku.php' ? 'active' : '' ?>">
        <i class="bi bi-upload"></i>
        <span>Impor Buku CSV</span>
    </a>

    <!-- ACCOUNT -->
    <div class="menu-title">Account</div>

    <a href="logout.php" class="logout">
        <i class="bi bi-box-arrow-right"></i>
        <span>Logout</span>
    </a>

</div>
