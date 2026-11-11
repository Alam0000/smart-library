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
   CEK STATUS AKUN TERKINI
===================================================== */

$status_akun = 'aktif';

$stmtUser = $koneksi->prepare("
    SELECT status_akun
    FROM users
    WHERE id = ?
    LIMIT 1
");

if ($stmtUser) {

    $stmtUser->bind_param("i", $id_user);
    $stmtUser->execute();

    $resultUser = $stmtUser->get_result();
    $userData = $resultUser->fetch_assoc();

    if ($userData) {
        $status_akun = strtolower(
            trim($userData['status_akun'] ?? 'aktif')
        );
    }

    $stmtUser->close();
}


/* =====================================================
   OTOMATIS UBAH PEMINJAMAN MENJADI TERLAMBAT

   Syarat:
   - status masih dipinjam
   - tanggal kembali sudah lewat
   - buku belum dikembalikan

   Denda:
   Rp1.000 per hari keterlambatan
===================================================== */

$updateTerlambat = $koneksi->query("
    UPDATE peminjaman
    SET
        status = 'terlambat',
        denda = DATEDIFF(CURDATE(), tanggal_kembali) * 1000,
        status_bayar = 'belum_bayar'
    WHERE status = 'dipinjam'
      AND tanggal_kembali IS NOT NULL
      AND tanggal_kembali < CURDATE()
      AND tgl_kembali_aktual IS NULL
");


/* =====================================================
   AMBIL DATA PEMINJAMAN USER

   Status yang ditampilkan:
   - menunggu
   - dipinjam
   - terlambat
   - ditolak
===================================================== */

$sql = "

    SELECT

        p.id,
        p.id_user,
        p.id_buku,
        p.tgl_pengajuan,
        p.tanggal_pinjam,
        p.tanggal_kembali,
        p.status,
        p.denda,
        p.status_bayar,
        p.catatan_tolak,

        b.nama_buku,
        b.gambar,
        b.penulis,

        u.nama_lengkap,
        u.status_akun

    FROM peminjaman p

    LEFT JOIN buku b
        ON b.id = p.id_buku

    LEFT JOIN users u
        ON u.id = p.id_user

    WHERE p.id_user = ?

      AND p.status IN (
          'menunggu',
          'dipinjam',
          'terlambat',
          'ditolak'
      )

    ORDER BY p.id DESC
";


/* =====================================================
   PREPARE QUERY
===================================================== */

$stmt = $koneksi->prepare($sql);

if (!$stmt) {

    die("
        <div style='
            font-family:Arial;
            padding:30px;
        '>

            <h3>Query gagal diproses.</h3>

            <p>"
            . htmlspecialchars($koneksi->error)
            . "</p>

        </div>
    ");
}


/* =====================================================
   EXECUTE QUERY
===================================================== */

$stmt->bind_param("i", $id_user);

$stmt->execute();

$data = $stmt->get_result();

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <title>Buku Saya - Smart Library</title>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

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
           MENU
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
           CONTENT
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
           BUTTON KEMBALI
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
           TABLE CARD
        ====================================================== */

        .table-card {

            background: rgba(255,255,255,.96);

            border: none;

            border-radius: 28px;

            padding: 25px;

            box-shadow:
                0 15px 40px rgba(15,23,42,.10);

            overflow: hidden;
        }


        /* =====================================================
           TABLE
        ====================================================== */

        .table {

            margin-bottom: 0;

            min-width: 1250px;
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
        ====================================================== */

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


        /* =====================================================
           NAMA BUKU
        ====================================================== */

        .book-name {

            font-weight: 600;

            color: #0f172a;
        }

        .book-author {

            color: #64748b;

            font-size: 12px;
        }


        /* =====================================================
           PEMINJAM
        ====================================================== */

        .borrower {

            font-weight: 500;

            color: #475569;
        }


        /* =====================================================
           STATUS BADGE
        ====================================================== */

        .status-badge {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            padding: 7px 13px;

            border-radius: 50px;

            font-size: 11px;

            font-weight: 600;

            white-space: nowrap;
        }


        /* =====================================================
           STATUS DIPINJAM
        ====================================================== */

        .status-dipinjam {

            background: #fef3c7;

            color: #b45309;
        }


        /* =====================================================
           STATUS TERLAMBAT
        ====================================================== */

        .status-terlambat {

            background: #fee2e2;

            color: #dc2626;
        }


        /* =====================================================
           STATUS MENUNGGU
        ====================================================== */

        .status-menunggu {

            background: #dbeafe;

            color: #2563eb;
        }


        /* =====================================================
           STATUS DITOLAK
        ====================================================== */

        .status-ditolak {

            background: #fee2e2;

            color: #dc2626;
        }


        /* =====================================================
           STATUS DEFAULT
        ====================================================== */

        .status-default {

            background: #f1f5f9;

            color: #475569;
        }


        /* =====================================================
           BUTTON KEMBALIKAN
        ====================================================== */

        .btn-return {

            background: #22c55e;

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

        .btn-return:hover {

            background: #16a34a;

            color: #fff;

            transform: translateY(-2px);
        }


        /* =====================================================
           AKSI TERLAMBAT
        ====================================================== */

        .action-late {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            background: #fee2e2;

            color: #dc2626;

            padding: 8px 13px;

            border-radius: 50px;

            font-size: 11px;

            font-weight: 600;

            white-space: nowrap;
        }


        /* =====================================================
           AKUN SUSPEND
        ====================================================== */

        .action-suspend {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            background: #fef2f2;

            color: #dc2626;

            border: 1px solid #fecaca;

            padding: 8px 13px;

            border-radius: 50px;

            font-size: 11px;

            font-weight: 600;

            white-space: nowrap;
        }


        /* =====================================================
           BUTTON STATUS MENUNGGU
        ====================================================== */

        .action-waiting {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            background: #dbeafe;

            color: #2563eb;

            padding: 8px 13px;

            border-radius: 50px;

            font-size: 11px;

            font-weight: 600;

            white-space: nowrap;
        }


        /* =====================================================
           BUTTON STATUS DITOLAK
        ====================================================== */

        .action-rejected {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            background: #fee2e2;

            color: #dc2626;

            padding: 8px 13px;

            border-radius: 50px;

            font-size: 11px;

            font-weight: 600;

            white-space: nowrap;
        }


        /* =====================================================
           ALASAN DITOLAK
        ====================================================== */

        .rejection-note {

            margin-top: 8px;

            font-size: 11px;

            color: #dc2626;

            max-width: 180px;

            line-height: 1.5;
        }


        /* =====================================================
           EMPTY DATA
        ====================================================== */

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


        /* =====================================================
           BUTTON EXPLORE
        ====================================================== */

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
        ====================================================== */

        @media(max-width: 992px) {

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


        @media(max-width: 768px) {

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
====================================================== -->

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

                        <li>

                            <a
                                class="dropdown-item"
                                href="buku_saya.php"
                            >

                                <i class="bi bi-book-half me-2"></i>

                                Buku Saya

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
====================================================== -->

<div class="page-container">


    <!-- =================================================
         HEADER
    ================================================== -->

    <div class="page-header">

        <div class="page-header-content">

            <div>

                <h1 class="page-title">

                    <i
                        class="bi bi-book-heart-fill"
                        style="color:#af69ef;"
                    ></i>

                    Buku Saya

                </h1>


                <p class="page-subtitle">

                    Daftar pengajuan dan buku yang sedang kamu pinjam.

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


    <!-- =================================================
         NOTIFIKASI AKUN SUSPEND
    ================================================== -->

    <?php if ($status_akun === 'suspend') { ?>

        <div
            class="alert alert-danger shadow-sm"
            style="
                border-radius:18px;
                border:none;
                margin-bottom:20px;
            "
        >

            <div class="d-flex align-items-start gap-3">

                <i
                    class="bi bi-shield-lock-fill"
                    style="font-size:24px;"
                ></i>

                <div>

                    <strong>Akun Anda Ditangguhkan</strong>

                    <div class="mt-1">

                        Akun Anda sedang ditangguhkan karena masih
                        memiliki denda perpustakaan yang belum lunas.

                    </div>

                    <div class="mt-1">

                        Silakan lunasi denda terlebih dahulu di
                        perpustakaan.

                    </div>

                </div>

            </div>

        </div>

    <?php } ?>


    <!-- =================================================
         TABLE
    ================================================== -->

    <div class="table-card">

        <div class="table-responsive">

            <table class="table align-middle">

                <thead>

                    <tr>

                        <th>No</th>

                        <th>Cover</th>

                        <th>Buku</th>

                        <th>Penulis</th>

                        <th>Peminjam</th>

                        <th>Tgl Pengajuan</th>

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


                        $status = strtolower(
                            trim($d['status'] ?? '')
                        );


                        /* =====================================
                           STATUS MENUNGGU
                        ====================================== */

                        if ($status === 'menunggu') {

                            $statusText  = 'Menunggu Persetujuan';

                            $statusClass = 'status-menunggu';

                            $statusIcon  = 'bi-hourglass-split';

                        }


                        /* =====================================
                           STATUS DIPINJAM
                        ====================================== */

                        elseif ($status === 'dipinjam') {

                            $statusText  = 'Dipinjam';

                            $statusClass = 'status-dipinjam';

                            $statusIcon  = 'bi-book-fill';

                        }


                        /* =====================================
                           STATUS TERLAMBAT
                        ====================================== */

                        elseif ($status === 'terlambat') {

                            $statusText  = 'Terlambat';

                            $statusClass = 'status-terlambat';

                            $statusIcon  = 'bi-exclamation-triangle-fill';

                        }


                        /* =====================================
                           STATUS DITOLAK
                        ====================================== */

                        elseif ($status === 'ditolak') {

                            $statusText  = 'Ditolak';

                            $statusClass = 'status-ditolak';

                            $statusIcon  = 'bi-x-circle-fill';

                        }


                        /* =====================================
                           STATUS DEFAULT
                        ====================================== */

                        else {

                            $statusText  = ucfirst($status);

                            $statusClass = 'status-default';

                            $statusIcon  = 'bi-info-circle-fill';

                        }

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
                                    onerror="
                                        this.style.display='none';
                                        this.nextElementSibling.style.display='flex';
                                    "
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


                        <!-- PEMINJAM -->

                        <td>

                            <div class="borrower">

                                <?= htmlspecialchars(
                                    $d['nama_lengkap'] ?? 'Tidak diketahui'
                                ) ?>

                            </div>

                        </td>


                        <!-- TANGGAL PENGAJUAN -->

                        <td>

                            <?= htmlspecialchars(
                                $d['tgl_pengajuan'] ?? '-'
                            ) ?>

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

                            <span
                                class="status-badge <?= $statusClass ?>"
                            >

                                <i
                                    class="bi <?= $statusIcon ?>"
                                ></i>

                                <?= htmlspecialchars($statusText) ?>

                            </span>


                            <?php if ($status === 'terlambat') { ?>

                                <div
                                    style="
                                        margin-top:8px;
                                        font-size:11px;
                                        color:#dc2626;
                                        line-height:1.5;
                                    "
                                >

                                    <strong>
                                        Denda:
                                    </strong>

                                    Rp<?= number_format(
                                        (int) ($d['denda'] ?? 0),
                                        0,
                                        ',',
                                        '.'
                                    ) ?>

                                    <br>

                                    <strong>
                                        Status:
                                    </strong>

                                    <?= (
                                        ($d['status_bayar'] ?? '') === 'lunas'
                                        ? 'Lunas'
                                        : 'Belum Bayar'
                                    ) ?>

                                </div>

                            <?php } ?>


                            <?php if (
                                $status === 'ditolak'
                                && !empty($d['catatan_tolak'])
                            ) { ?>

                                <div class="rejection-note">

                                    <strong>Alasan:</strong><br>

                                    <?= htmlspecialchars(
                                        $d['catatan_tolak']
                                    ) ?>

                                </div>

                            <?php } ?>

                        </td>


                        <!-- AKSI -->

                        <td>


                            <?php if (
                                ($status === 'dipinjam'
                                || $status === 'terlambat')
                                && $status_akun !== 'suspend'
                                && $status_akun !== 'diblokir'
                            ) { ?>


                                <!-- KEMBALIKAN -->

                                <a
                                    href="kembalikan.php?id=<?= (int) $d['id'] ?>"
                                    class="btn-return"
                                    onclick="
                                        return confirm(
                                            'Yakin ingin mengembalikan buku ini?'
                                        )
                                    "
                                >

                                    <i
                                        class="bi bi-arrow-return-left"
                                    ></i>

                                    Kembalikan

                                </a>


                            <?php } elseif (
                                ($status === 'dipinjam'
                                || $status === 'terlambat')
                                && (
                                    $status_akun === 'suspend'
                                    || $status_akun === 'diblokir'
                                )
                            ) { ?>


                                <!-- AKUN SUSPEND / BLOKIR -->

                                <span
                                    class="action-suspend"
                                    title="Akun tidak dapat melakukan pengembalian"
                                >

                                    <i class="bi bi-lock-fill"></i>

                                    Akun Ditangguhkan

                                </span>


                            <?php } elseif ($status === 'menunggu') { ?>


                                <!-- MENUNGGU -->

                                <span class="action-waiting">

                                    <i class="bi bi-hourglass-split"></i>

                                    Menunggu

                                </span>


                            <?php } elseif ($status === 'ditolak') { ?>


                                <!-- DITOLAK -->

                                <span class="action-rejected">

                                    <i class="bi bi-x-circle"></i>

                                    Pengajuan Ditolak

                                </span>


                            <?php } ?>

                        </td>

                    </tr>


                <?php

                    }

                } else {

                ?>


                    <!-- =================================================
                         EMPTY DATA
                    ================================================== -->

                    <tr>

                        <td colspan="10">

                            <div class="empty-data">


                                <div class="empty-icon">

                                    <i class="bi bi-book"></i>

                                </div>


                                <h5>

                                    Belum Ada Pengajuan Peminjaman

                                </h5>


                                <p>

                                    Saat ini kamu belum memiliki pengajuan
                                    peminjaman atau buku yang sedang dipinjam.

                                    Buku yang sudah dikembalikan dapat dilihat
                                    di Riwayat Peminjaman.

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
====================================================== -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>