<?php

session_start();

include '../connection/koneksi.php';

/* =====================================================
   CEK LOGIN
===================================================== */

if (!isset($_SESSION['login'])) {
    echo "
    <script>
        alert('Silakan login terlebih dahulu.');
        window.location='../auth/login.php';
    </script>
    ";
    exit;
}

/* =====================================================
   DATA USER
===================================================== */

$id_user = isset($_SESSION['id_user'])
    ? (int) $_SESSION['id_user']
    : 0;

$username = $_SESSION['username'] ?? '';
$role     = $_SESSION['role'] ?? '';

/* =====================================================
   AMBIL RIWAYAT PEMINJAMAN MILIK USER YANG LOGIN
   HANYA TRANSAKSI YANG SUDAH DIKEMBALIKAN
===================================================== */

$sql = "
    SELECT
        p.id,
        p.id_user,
        p.id_buku,
        p.tanggal_pinjam,
        p.tanggal_kembali,
        p.status,

        b.nama_buku,
        b.gambar,
        b.penulis

    FROM peminjaman p

    LEFT JOIN buku b
        ON b.id = p.id_buku

    WHERE p.id_user = ?
      AND p.status = 'dikembalikan'

    ORDER BY p.id DESC
";

$stmt = $koneksi->prepare($sql);

if (!$stmt) {
    die("
        <div style='font-family:Arial;padding:30px;'>
            <h3>Query gagal diproses.</h3>
            <p>" . htmlspecialchars($koneksi->error) . "</p>
        </div>
    ");
}

$stmt->bind_param("i", $id_user);
$stmt->execute();

$data = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <title>Riwayat Peminjaman - Smart Library</title>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <!-- BOOTSTRAP -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- BOOTSTRAP ICON -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
    >

    <!-- GOOGLE FONT -->
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            color: #334155;

            background:
                linear-gradient(
                    135deg,
                    #f8f2ff 0%,
                    #d8b4fe 25%,
                    #af69ef 55%,
                    #9333ea 100%
                );

            padding-top: 120px;
        }

        /* =====================================================
           NAVBAR
        ===================================================== */

        .navbar {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);

            width: 90%;
            max-width: 1100px;

            background: rgba(255,255,255,.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);

            border: 1px solid rgba(175,105,239,.12);
            border-radius: 60px;

            padding: 12px 25px;

            box-shadow:
                0 10px 30px rgba(0,0,0,.06);

            z-index: 9999;
        }

        .navbar-brand {
            font-size: 20px;
            font-weight: 600;
            color: #af69ef !important;
            letter-spacing: .5px;

            display: flex;
            align-items: center;

            white-space: nowrap;
            text-decoration: none;
        }

        .navbar-brand img {
            width: 30px;
            height: 30px;
            object-fit: contain;
            display: inline-block;
        }

        .navbar-nav {
            align-items: center;
        }

        .nav-link {
            color: #1e293b !important;
            font-weight: 500;
            position: relative;

            margin: 0 10px;
            padding: 8px 5px !important;

            transition: .3s;
            text-decoration: none;
            white-space: nowrap;
        }

        .nav-link:hover {
            color: #af69ef !important;
            transform: translateY(-2px);
        }

        .nav-link::after {
            content: '';
            position: absolute;

            width: 0;
            height: 2px;

            left: 50%;
            bottom: -5px;

            background: #af69ef;
            transition: .3s;
        }

        .nav-link:hover::after {
            width: 100%;
            left: 0;
        }

        .user-btn {
            background: #f3e8ff !important;
            color: #af69ef !important;

            border: none;
            border-radius: 50px;

            padding: 10px 20px;

            font-weight: 600;
            transition: .3s;

            white-space: nowrap;
        }

        .user-btn:hover {
            background: #e9d5ff !important;
            color: #9c4fe8 !important;
        }

        .dropdown-menu {
            border: none;
            border-radius: 16px;

            padding: 8px;
            margin-top: 10px !important;

            box-shadow:
                0 10px 30px rgba(0,0,0,.12);
        }

        .dropdown-item {
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
            transition: .2s;
        }

        .dropdown-item:hover {
            background: #f3e8ff;
            color: #af69ef;
        }

        /* =====================================================
           CONTENT
        ===================================================== */

        .page-container {
            width: 94%;
            max-width: 1400px;
            margin: auto;
        }

        .page-header {
            background: rgba(255,255,255,.94);

            border-radius: 28px;
            padding: 30px 35px;
            margin-bottom: 25px;

            box-shadow:
                0 15px 40px rgba(15,23,42,.10);

            border: 1px solid rgba(175,105,239,.10);
        }

        .page-header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .page-title {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
        }

        .page-subtitle {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 14px;
        }

        .btn-back {
            background: #f3e8ff;
            color: #af69ef;

            border: none;
            border-radius: 50px;

            padding: 10px 20px;

            font-weight: 600;
            text-decoration: none;
            transition: .3s;

            white-space: nowrap;
        }

        .btn-back:hover {
            background: #af69ef;
            color: #fff;

            transform: translateY(-2px);

            box-shadow:
                0 8px 20px rgba(175,105,239,.25);
        }

        /* =====================================================
           TABLE CARD
        ===================================================== */

        .table-card {
            background: rgba(255,255,255,.96);

            border: none;
            border-radius: 28px;

            padding: 25px;

            box-shadow:
                0 15px 40px rgba(15,23,42,.10);

            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
            min-width: 1000px;
        }

        .table thead th {
            background:
                linear-gradient(
                    135deg,
                    #af69ef,
                    #8b5cf6
                );

            color: #fff;
            border: none;

            font-size: 13px;
            font-weight: 600;

            padding: 16px 14px;
            white-space: nowrap;
        }

        .table thead th:first-child {
            border-radius: 12px 0 0 0;
        }

        .table thead th:last-child {
            border-radius: 0 12px 0 0;
        }

        .table tbody td {
            padding: 16px 14px;

            color: #334155;
            font-size: 13px;

            vertical-align: middle;
            border-color: #f1f5f9;
        }

        .table tbody tr {
            transition: .25s;
        }

        .table tbody tr:hover {
            background: #faf5ff;
        }

        /* =====================================================
           COVER
        ===================================================== */

        .book-img {
            width: 65px;
            height: 85px;

            object-fit: cover;
            border-radius: 10px;

            box-shadow:
                0 5px 15px rgba(15,23,42,.12);
        }

        .no-cover {
            width: 65px;
            height: 85px;

            border-radius: 10px;

            background: #f3e8ff;
            color: #af69ef;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 25px;
        }

        .book-name {
            font-weight: 600;
            color: #0f172a;
        }

        .book-author {
            color: #64748b;
            font-size: 12px;
        }

        /* =====================================================
           STATUS
        ===================================================== */

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;

            padding: 7px 13px;
            border-radius: 50px;

            font-size: 11px;
            font-weight: 600;
        }

        .status-dikembalikan {
            background: #dcfce7;
            color: #15803d;
        }

        /* =====================================================
           BUTTON RATING
        ===================================================== */

        .btn-rating {
            background: #fbbf24;
            color: #fff;

            border: none;
            border-radius: 50px;

            padding: 8px 14px;

            font-size: 12px;
            font-weight: 600;

            transition: .3s;
            text-decoration: none;

            display: inline-block;
            white-space: nowrap;
        }

        .btn-rating:hover {
            background: #f59e0b;
            color: #fff;

            transform: translateY(-2px);
        }

        /* =====================================================
           EMPTY DATA
        ===================================================== */

        .empty-data {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-icon {
            width: 75px;
            height: 75px;

            margin: 0 auto 18px;

            border-radius: 22px;

            background: #f3e8ff;
            color: #af69ef;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 32px;
        }

        .empty-data h5 {
            color: #0f172a;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .empty-data p {
            font-size: 14px;
            margin-bottom: 20px;
        }

        .btn-explore {
            display: inline-block;

            background:
                linear-gradient(
                    135deg,
                    #af69ef,
                    #8b5cf6
                );

            color: #fff;

            padding: 11px 24px;
            border-radius: 50px;

            text-decoration: none;

            font-size: 13px;
            font-weight: 600;

            transition: .3s;
        }

        .btn-explore:hover {
            color: #fff;

            transform: translateY(-2px);

            box-shadow:
                0 10px 25px rgba(175,105,239,.35);
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media(max-width:992px) {

            .navbar {
                width: 94%;
            }

            .nav-link {
                margin: 5px 0;
            }

            .navbar-collapse {
                padding-top: 15px;
            }
        }

        @media (max-width: 768px) {

    body {
        padding-top: 105px;
    }

    .navbar {
        width: 95%;
        padding: 10px 18px;
        border-radius: 30px;
    }

    .navbar-brand {
        font-size: 20px;
    }

    /* =========================================
       MENU NAVBAR MOBILE RATA KIRI
    ========================================= */

    .navbar-collapse {
        text-align: left;
    }

    .navbar-nav {
        width: 100%;
        align-items: flex-start;
    }

    .navbar-nav .nav-item {
        width: 100%;
        text-align: left;
    }

    .navbar-nav .nav-link {
        width: 100%;
        margin-left: 0;
        margin-right: 0;
        text-align: left;
    }

    .navbar-nav .dropdown-menu {
        text-align: left;
    }

    .navbar-nav .dropdown-item {
        text-align: left;
    }


    .page-container {
        width: 94%;
    }

    .page-header {
        padding: 22px;
        border-radius: 22px;
    }

    .page-header-content {
        align-items: flex-start;
        flex-direction: column;
    }

    .page-title {
        font-size: 23px;
    }
            .table-card {
                padding: 12px;
                border-radius: 20px;
            }

            .table tbody td {
                padding: 12px 10px;
            }
        }

    </style>

</head>

<body>

<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="navbar navbar-expand-lg">

    <div class="container-fluid">

        <!-- LOGO -->

        <a
            class="navbar-brand"
            href="index.php"
        >

            <img
                src="../assets/img/bg1.png"
                alt="Logo Sekolah"
                width="30"
                height="30"
                class="me-2"
            >

            SMART LIBRARY

        </a>

        <!-- MOBILE -->

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#menuSmartLibrary"
            aria-controls="menuSmartLibrary"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >

            <span class="navbar-toggler-icon"></span>

        </button>

        <!-- MENU -->

        <div
            class="collapse navbar-collapse"
            id="menuSmartLibrary"
        >

            <ul class="navbar-nav mx-auto">

                <li class="nav-item">
                    <a
                        href="index.php"
                        class="nav-link"
                    >
                        Beranda
                    </a>
                </li>

                <li class="nav-item">
                    <a
                        href="index.php#layanan"
                        class="nav-link"
                    >
                        Layanan
                    </a>
                </li>

                <li class="nav-item">
                    <a
                        href="index.php#rekomendasi"
                        class="nav-link"
                    >
                        Rekomendasi Buku
                    </a>
                </li>

                <li class="nav-item">
                    <a
                        href="index.php#koleksi"
                        class="nav-link"
                    >
                        Koleksi Buku
                    </a>
                </li>

                <!-- DROPDOWN BUKU SAYA -->

                <li class="nav-item dropdown">

                    <a
                        href="#"
                        class="nav-link dropdown-toggle"
                        id="bukuSayaDropdown"
                        role="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                    >
                        Buku Saya
                    </a>

                    <ul
                        class="dropdown-menu"
                        aria-labelledby="bukuSayaDropdown"
                    >

                        <li>
                            <a
                                class="dropdown-item"
                                href="buku_saya.php"
                            >
                                <i class="bi bi-book-half me-2"></i>
                                Sedang Dipinjam
                            </a>
                        </li>
                        <li>

                            <a
                                class="dropdown-item"
                                href="buku_simpan.php"
                            >

                                <i class="bi bi-bookmark-heart me-2"></i>

                                Buku Disimpan

                            </a>

                        </li>

                        <li>
                            <a
                                class="dropdown-item"
                                href="riwayat_peminjaman.php"
                            >
                                <i class="bi bi-clock-history me-2"></i>
                                Riwayat Peminjaman
                            </a>
                        </li>

                    </ul>

                </li>

            </ul>

            <!-- USER -->

            <div class="dropdown">

                <button
                    class="user-btn dropdown-toggle"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >

                    👤 <?= htmlspecialchars($username) ?>

                </button>

                <ul class="dropdown-menu dropdown-menu-end">

                    <?php if ($role == 'petugas') { ?>

                        <li>

                            <a
                                class="dropdown-item"
                                href="../Admin/dashboard.php"
                            >

                                <i class="bi bi-speedometer2 me-2"></i>

                                Dashboard Petugas

                            </a>

                        </li>

                        <li>
                            <hr class="dropdown-divider">
                        </li>

                    <?php } ?>

                    <li>

                        <a
                            class="dropdown-item"
                            href="../auth/logout.php"
                        >

                            <i class="bi bi-box-arrow-right me-2"></i>

                            Logout

                        </a>

                    </li>

                </ul>

            </div>

        </div>

    </div>

</nav>


<!-- =====================================================
     CONTENT
===================================================== -->

<div class="page-container">

    <!-- HEADER -->

    <div class="page-header">

        <div class="page-header-content">

            <div>

                <h1 class="page-title">

                    <i
                        class="bi bi-clock-history"
                        style="color:#af69ef;"
                    ></i>

                    Riwayat Peminjaman

                </h1>

                <p class="page-subtitle">

                    Daftar buku yang pernah kamu pinjam dan sudah dikembalikan.

                </p>

            </div>

            <a
                href="buku_saya.php"
                class="btn-back"
            >

                <i class="bi bi-book-half me-1"></i>

                Sedang Dipinjam

            </a>

        </div>

    </div>


    <!-- TABLE -->

    <div class="table-card">

        <div class="table-responsive">

            <table class="table align-middle">

                <thead>

                    <tr>

                        <th>No</th>
                        <th>Cover</th>
                        <th>Buku</th>
                        <th>Penulis</th>
                        <th>Tgl Pinjam</th>
                        <th>Tgl Kembali</th>
                        <th>Status</th>
                        <th>Aksi</th>

                    </tr>

                </thead>

                <tbody>

                <?php

                $no = 1;

                if ($data && $data->num_rows > 0) {

                    while ($d = $data->fetch_assoc()) {

                ?>

                    <tr>

                        <!-- NO -->

                        <td>
                            <?= $no++ ?>
                        </td>


                        <!-- COVER -->

                        <td>

                            <?php if (!empty($d['gambar'])) { ?>

                                <img
                                    src="../gambar/<?= htmlspecialchars($d['gambar']) ?>"
                                    class="book-img"
                                    alt="<?= htmlspecialchars($d['nama_buku'] ?? 'Buku') ?>"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                >

                                <div
                                    class="no-cover"
                                    style="display:none;"
                                >
                                    <i class="bi bi-book"></i>
                                </div>

                            <?php } else { ?>

                                <div class="no-cover">
                                    <i class="bi bi-book"></i>
                                </div>

                            <?php } ?>

                        </td>


                        <!-- NAMA BUKU -->

                        <td>

                            <div class="book-name">

                                <?= htmlspecialchars(
                                    $d['nama_buku'] ?? 'Buku tidak ditemukan'
                                ) ?>

                            </div>

                        </td>


                        <!-- PENULIS -->

                        <td>

                            <div class="book-author">

                                <?= htmlspecialchars(
                                    $d['penulis'] ?? '-'
                                ) ?>

                            </div>

                        </td>


                        <!-- TANGGAL PINJAM -->

                        <td>

                            <?= htmlspecialchars(
                                $d['tanggal_pinjam'] ?? '-'
                            ) ?>

                        </td>


                        <!-- TANGGAL KEMBALI -->

                        <td>

                            <?= htmlspecialchars(
                                $d['tanggal_kembali'] ?? '-'
                            ) ?>

                        </td>


                        <!-- STATUS -->

                        <td>

                            <span class="status-badge status-dikembalikan">

                                <i class="bi bi-check-circle-fill"></i>

                                Dikembalikan

                            </span>

                        </td>


                        <!-- AKSI -->

                        <td>

                            <a
                                href="rating.php?id=<?= (int)$d['id_buku'] ?>"
                                class="btn-rating"
                            >

                                <i class="bi bi-star-fill"></i>

                                Beri Rating

                            </a>

                        </td>

                    </tr>

                <?php

                    }

                } else {

                ?>

                    <tr>

                        <td colspan="8">

                            <div class="empty-data">

                                <div class="empty-icon">

                                    <i class="bi bi-clock-history"></i>

                                </div>

                                <h5>
                                    Belum Ada Riwayat Peminjaman
                                </h5>

                                <p>
                                    Belum terdapat buku yang pernah kamu pinjam dan kembalikan.
                                </p>

                                <a
                                    href="index.php#koleksi"
                                    class="btn-explore"
                                >

                                    <i class="bi bi-search me-1"></i>

                                    Jelajahi Koleksi

                                </a>

                            </div>

                        </td>

                    </tr>

                <?php

                }

                ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<!-- =====================================================
     BOOTSTRAP JS
===================================================== -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>
