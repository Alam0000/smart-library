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
   AMBIL BUKU TERSIMPAN MILIK USER
===================================================== */

$sql = "
    SELECT
        buku.id,
        buku.nama_buku,
        buku.penulis,
        buku.gambar

    FROM simpan_buku

    JOIN buku
        ON buku.id = simpan_buku.id_buku

    WHERE simpan_buku.id_user = ?

    ORDER BY simpan_buku.id DESC
";


$stmt = $koneksi->prepare($sql);


/* =====================================================
   CEK QUERY
===================================================== */

if (!$stmt) {

    die("
        <div style='
            font-family: Arial;
            padding: 30px;
        '>

            <h3>Query gagal diproses.</h3>

            <p>"
            . htmlspecialchars($koneksi->error) .
            "</p>

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

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Buku Disimpan - Smart Library</title>


    <!-- =====================================================
         BOOTSTRAP
    ====================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- =====================================================
         BOOTSTRAP ICON
    ====================================================== -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
    >


    <!-- =====================================================
         GOOGLE FONT
    ====================================================== -->

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >


    <style>

        /* =====================================================
           RESET
        ====================================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        /* =====================================================
           BODY
        ====================================================== */

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
        ====================================================== */

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


        /* =====================================================
           LOGO
        ====================================================== */

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


        /* =====================================================
           NAV MENU
        ====================================================== */

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


        /* =====================================================
           USER BUTTON
        ====================================================== */

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


        /* =====================================================
           DROPDOWN
        ====================================================== */

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
           PAGE CONTAINER
        ====================================================== */

        .page-container {

            width: 94%;

            max-width: 1400px;

            margin: auto;
        }


        /* =====================================================
           HEADER
        ====================================================== */

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


        /* =====================================================
           BACK BUTTON
        ====================================================== */

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
           BOOKS CONTAINER
        ====================================================== */

        .books-card {

            background: rgba(255,255,255,.96);

            border-radius: 28px;

            padding: 30px;

            box-shadow:
                0 15px 40px rgba(15,23,42,.10);

            border: none;
        }


        /* =====================================================
           BOOK GRID
        ====================================================== */

        .books-grid {

            display: grid;

            grid-template-columns:
                repeat(4, minmax(220px, 280px));

            justify-content: center;

            gap: 25px;
        }


        /* =====================================================
           BOOK CARD
        ====================================================== */

        .book-card {

            background: #fff;

            border: 1px solid #f1f5f9;

            border-radius: 20px;

            overflow: hidden;

            width: 100%;

            height: 100%;

            display: flex;

            flex-direction: column;

            box-shadow:
                0 8px 25px rgba(15,23,42,.07);

            transition: .3s;
        }


        .book-card:hover {

            transform: translateY(-6px);

            box-shadow:
                0 15px 35px rgba(15,23,42,.13);
        }


        /* =====================================================
           BOOK IMAGE
        ====================================================== */

        .book-img-wrapper {

            width: 100%;

            height: 280px;

            background: #f8f5ff;

            overflow: hidden;

            display: flex;

            align-items: center;

            justify-content: center;
        }


        .book-img {

            width: 100%;

            height: 100%;

            object-fit: contain;

            transition: .3s;
        }


        .book-card:hover .book-img {

            transform: scale(1.03);
        }


        /* =====================================================
           NO COVER
        ====================================================== */

        .no-cover {

            width: 100%;

            height: 100%;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #af69ef;

            font-size: 50px;

            background: #f3e8ff;
        }


        /* =====================================================
           BOOK BODY
        ====================================================== */

        .book-body {

            padding: 20px;

            display: flex;

            flex-direction: column;

            flex: 1;
        }


        /* =====================================================
           BOOK NAME
        ====================================================== */

        .book-name {

            color: #0f172a;

            font-size: 17px;

            font-weight: 600;

            margin-bottom: 7px;

            line-height: 1.4;

            min-height: 48px;

            display: -webkit-box;

            -webkit-line-clamp: 2;

            -webkit-box-orient: vertical;

            overflow: hidden;
        }


        /* =====================================================
           AUTHOR
        ====================================================== */

        .book-author {

            color: #64748b;

            font-size: 13px;

            margin-bottom: 18px;

            min-height: 20px;

            display: -webkit-box;

            -webkit-line-clamp: 1;

            -webkit-box-orient: vertical;

            overflow: hidden;
        }


        /* =====================================================
           DETAIL BUTTON
        ====================================================== */

        .btn-detail {

            width: 100%;

            background:
                linear-gradient(
                    135deg,
                    #af69ef,
                    #8b5cf6
                );

            color: #fff;

            border: none;

            border-radius: 50px;

            padding: 10px 15px;

            font-size: 13px;

            font-weight: 600;

            text-decoration: none;

            display: inline-block;

            text-align: center;

            transition: .3s;

            margin-top: auto;
        }


        .btn-detail:hover {

            color: #fff;

            transform: translateY(-2px);

            box-shadow:
                0 8px 20px rgba(175,105,239,.30);
        }


        /* =====================================================
           EMPTY DATA
        ====================================================== */

        .empty-data {

            text-align: center;

            padding: 70px 20px;

            color: #64748b;
        }


        .empty-icon {

            width: 80px;

            height: 80px;

            margin: 0 auto 18px;

            border-radius: 22px;

            background: #f3e8ff;

            color: #af69ef;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 34px;
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
           RESPONSIVE - TABLET
        ====================================================== */

        @media (max-width: 1200px) {

            .books-grid {

                grid-template-columns:
                    repeat(3, minmax(220px, 280px));
            }
        }


        @media (max-width: 992px) {

            .navbar {

                width: 94%;
            }


            .nav-link {

                margin: 5px 0;
            }


            .navbar-collapse {

                padding-top: 15px;
            }


            .books-grid {

                grid-template-columns:
                    repeat(2, minmax(220px, 300px));
            }
        }


        /* =====================================================
   RESPONSIVE - MOBILE
====================================================== */

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

    .books-card {
        padding: 15px;
        border-radius: 20px;
    }

    .books-grid {
        grid-template-columns: 1fr;
        max-width: 320px;
        margin: auto;
    }

    .book-img-wrapper {
        height: 280px;
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


        <!-- MOBILE BUTTON -->

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


                <!-- BERANDA -->

                <li class="nav-item">

                    <a
                        href="index.php"
                        class="nav-link"
                    >
                        Beranda
                    </a>

                </li>


                <!-- LAYANAN -->

                <li class="nav-item">

                    <a
                        href="index.php#layanan"
                        class="nav-link"
                    >
                        Layanan
                    </a>

                </li>


                <!-- REKOMENDASI -->

                <li class="nav-item">

                    <a
                        href="index.php#rekomendasi"
                        class="nav-link"
                    >
                        Rekomendasi Buku
                    </a>

                </li>


                <!-- KOLEKSI -->

                <li class="nav-item">

                    <a
                        href="index.php#koleksi"
                        class="nav-link"
                    >
                        Koleksi Buku
                    </a>

                </li>


                <!-- BUKU SAYA -->

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


                        <!-- SEDANG DIPINJAM -->

                        <li>

                            <a
                                class="dropdown-item"
                                href="buku_saya.php"
                            >

                                <i class="bi bi-book-half me-2"></i>

                                Sedang Dipinjam

                            </a>

                        </li>


                        <!-- BUKU DISIMPAN -->

                        <li>

                            <a
                                class="dropdown-item"
                                href="buku_simpan.php"
                            >

                                <i class="bi bi-bookmark-heart me-2"></i>

                                Buku Disimpan

                            </a>

                        </li>


                        <!-- RIWAYAT -->

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


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="page-header">

        <div class="page-header-content">


            <div>

                <h1 class="page-title">

                    <i
                        class="bi bi-bookmark-heart-fill"
                        style="color:#af69ef;"
                    ></i>

                    Buku Disimpan

                </h1>


                <p class="page-subtitle">

                    Daftar buku yang kamu simpan untuk dibaca kembali.

                </p>

            </div>


            <a
                href="index.php#koleksi"
                class="btn-back"
            >

                <i class="bi bi-book me-1"></i>

                Lihat Koleksi Buku

            </a>

        </div>

    </div>


    <!-- =====================================================
         BOOK LIST
    ====================================================== -->

    <div class="books-card">


        <?php if ($data && $data->num_rows > 0) { ?>


            <div class="books-grid">


                <?php while ($d = $data->fetch_assoc()) { ?>


                    <div class="book-card">


                        <!-- =================================================
                             COVER
                        ================================================== -->

                        <div class="book-img-wrapper">


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


                        </div>


                        <!-- =================================================
                             BODY
                        ================================================== -->

                        <div class="book-body">


                            <!-- NAMA BUKU -->

                            <div class="book-name">

                                <?= htmlspecialchars(
                                    $d['nama_buku'] ?? 'Buku tidak ditemukan'
                                ) ?>

                            </div>


                            <!-- PENULIS -->

                            <div class="book-author">

                                <i class="bi bi-person me-1"></i>

                                <?= htmlspecialchars(
                                    $d['penulis'] ?? '-'
                                ) ?>

                            </div>


                            <!-- DETAIL -->

                            <a
                                href="detail_buku.php?id=<?= (int)$d['id'] ?>"
                                class="btn-detail"
                            >

                                <i class="bi bi-eye me-1"></i>

                                Detail Buku

                            </a>


                        </div>


                    </div>


                <?php } ?>


            </div>


        <?php } else { ?>


            <!-- =================================================
                 EMPTY DATA
            ================================================== -->

            <div class="empty-data">


                <div class="empty-icon">

                    <i class="bi bi-bookmark-heart"></i>

                </div>


                <h5>

                    Belum Ada Buku Disimpan

                </h5>


                <p>

                    Kamu belum menyimpan buku apa pun.
                    Silakan jelajahi koleksi buku dan simpan buku
                    yang ingin kamu baca nanti.

                </p>


                <a
                    href="index.php#koleksi"
                    class="btn-explore"
                >

                    <i class="bi bi-search me-1"></i>

                    Jelajahi Koleksi

                </a>


            </div>


        <?php } ?>


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