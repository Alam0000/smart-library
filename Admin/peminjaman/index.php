<?php

session_start();
require_once __DIR__ . '/../../lib/auth.php';
wajibPetugas('../../auth/login.php');



/* =========================================================
   CEK LOGIN
========================================================= */

if (!isset($_SESSION['login'])) {

    header("Location: ../../auth/login.php");

    exit;
}


/* =========================================================
   KONEKSI DATABASE
========================================================= */

include '../../connection/koneksi.php';


/* =========================================================
   AMBIL DATA PEMINJAMAN
========================================================= */

$data = mysqli_query(
    $koneksi,
    "
    SELECT
        p.*,
        u.nama_lengkap,
        b.nama_buku

    FROM peminjaman p

    JOIN users u
        ON p.id_user = u.id

    JOIN buku b
        ON p.id_buku = b.id

    ORDER BY p.id DESC
    "
);

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Data Peminjaman</title>


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
         STYLE UTAMA
    ====================================================== -->

    <link
        rel="stylesheet"
        href="../../assets/style.css"
    >


    <style>

        /* =====================================================
           BODY
        ===================================================== */

        body {

            margin: 0;

            background: #0f172a;

            font-family:
                'Poppins',
                'Segoe UI',
                sans-serif;

            color: white;

        }


        /* =====================================================
           SIDEBAR
        ===================================================== */

        .sidebar {

            position: fixed !important;

            top: 0 !important;

            left: 0 !important;

            width: 280px;

            height: 100vh;

            z-index: 99999 !important;

            overflow-y: auto;

            pointer-events: auto !important;

        }


        .sidebar a {

            position: relative;

            z-index: 100000 !important;

            pointer-events: auto !important;

            cursor: pointer;

            text-decoration: none;

        }


        .sidebar a:hover {

            cursor: pointer;

        }


        /* =====================================================
           MAIN CONTENT
        ===================================================== */

        .main-content {

            margin-left: 280px;

            padding: 30px;

            min-height: 100vh;

            position: relative;

            z-index: 1;

        }


        /* =====================================================
           TOPBAR
        ===================================================== */

        .topbar {

            background:
                rgba(255,255,255,0.05);

            backdrop-filter: blur(20px);

            border:
                1px solid
                rgba(255,255,255,0.08);

            padding: 20px 30px;

            border-radius: 20px;

            margin-bottom: 25px;

        }


        /* =====================================================
           PAGE TITLE
        ===================================================== */

        .page-title {

            color: white;

            font-weight: 700;

            margin-bottom: 5px;

        }


        /* =====================================================
           CARD
        ===================================================== */

        .card-custom {

            background: white;

            border: none;

            border-radius: 20px;

            box-shadow:
                0 5px 15px
                rgba(0,0,0,0.15);

        }


        /* =====================================================
           TABLE
        ===================================================== */

        .table {

            margin-bottom: 0;

            color: #334155;

        }


        .table thead th {

            background: #0d6efd !important;

            color: white !important;

            font-weight: 700;

            padding: 15px;

            border:
                1px solid
                #0b5ed7 !important;

            white-space: nowrap;

        }


        .table tbody td {

            color: #334155 !important;

            padding: 15px;

            vertical-align: middle;

            border-color: #dee2e6;

        }


        .table tbody tr:hover td {

            background: #f8fafc !important;

        }


        /* =====================================================
           BUTTON
        ===================================================== */

        .btn-custom {

            border-radius: 10px;

            padding: 8px 15px;

        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 768px) {

            .sidebar {

                position: relative !important;

                width: 100%;

                height: auto;

            }


            .main-content {

                margin-left: 0;

                padding: 15px;

            }


            .topbar {

                text-align: center;

            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<div class="sidebar">


    <!-- =====================================================
         LOGO
    ====================================================== -->

    <div class="logo">

        <h3>📚 Smart Library</h3>

        <p>SMP Negeri 4 Solear</p>

    </div>


    <!-- =====================================================
         MAIN MENU
    ====================================================== -->

    <div class="menu-title">

        Main Menu

    </div>


    <!-- =====================================================
         DASHBOARD
         FILE INI = views/peminjaman/index.php
         DASHBOARD = views/dashboard.php
    ====================================================== -->

    <a href="../dashboard.php">

        <i class="bi bi-grid-fill"></i>

        Dashboard

    </a>


    <!-- =====================================================
         KELOLA BUKU
         = views/kel_buku.php
    ====================================================== -->

    <a href="../kel_buku.php">

        <i class="bi bi-book-fill"></i>

        Kelola Buku

    </a>


    <!-- =====================================================
         KRITERIA
         = views/kriteria.php
    ====================================================== -->

    <a href="../kriteria.php">

        <i class="bi bi-sliders"></i>

        Kriteria

    </a>


    <!-- =====================================================
         GENERATE NILAI
         = views/saw/generate_nilai.php
    ====================================================== -->

    <a href="../saw/generate_nilai.php">

        <i class="bi bi-cpu-fill"></i>

        Generate Nilai

    </a>


    <!-- =====================================================
         METODE SPK
    ====================================================== -->

    <div class="menu-title">

        Metode SPK

    </div>


    <!-- =====================================================
         INPUT AHP
         = views/ahp/input.php
    ====================================================== -->

    <a href="../ahp/input.php">

        <i class="bi bi-diagram-3-fill"></i>

        Input AHP

    </a>


    <!-- =====================================================
         HITUNG AHP
         = views/ahp/hitung_lengkap.php
    ====================================================== -->

    <a href="../ahp/hitung_lengkap.php">

        <i class="bi bi-bar-chart-fill"></i>

        Hitung AHP

    </a>


    <!-- =====================================================
         HITUNG SAW
         = views/saw/hitung.php
    ====================================================== -->

    <a href="../saw/hitung.php">

        <i class="bi bi-calculator-fill"></i>

        Hitung SAW

    </a>


    <!-- =====================================================
         HASIL RANKING
         = views/hasil_ranking.php
    ====================================================== -->

    <a href="../hasil_ranking.php">

        <i class="bi bi-trophy-fill"></i>

        Hasil Ranking

    </a>


    <!-- =====================================================
         TRANSAKSI
    ====================================================== -->

    <div class="menu-title">

        Transaksi

    </div>


    <!-- =====================================================
         PEMINJAMAN
         FILE SEKARANG = views/peminjaman/index.php
    ====================================================== -->

    <a
        href="index.php"
        class="active"
    >

        <i class="bi bi-journal-bookmark-fill"></i>

        Peminjaman

    </a>


    <!-- =====================================================
         ACCOUNT
    ====================================================== -->

    <div class="menu-title">

        Account

    </div>


    <!-- =====================================================
         LOGOUT
         = auth/logout.php
    ====================================================== -->

    <a href="../../auth/logout.php">

        <i class="bi bi-box-arrow-right"></i>

        Logout

    </a>


</div>


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<div class="main-content">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="topbar d-flex justify-content-between align-items-center">


        <div>

            <h2 class="page-title">

                📚 Data Peminjaman

            </h2>


            <p class="text-muted mb-0">

                Kelola data peminjaman buku perpustakaan

            </p>

        </div>


        <div>

            <a
                href="tambah.php"
                class="btn btn-success btn-custom"
            >

                <i class="bi bi-plus-circle"></i>

                Peminjaman Baru

            </a>

        </div>


    </div>


    <!-- =====================================================
         CARD
    ====================================================== -->

    <div class="card-custom p-4">


        <div class="table-responsive">


            <table
                class="table table-bordered table-hover align-middle"
            >


                <thead>

                    <tr>

                        <th width="70">

                            No

                        </th>


                        <th>

                            Siswa

                        </th>


                        <th>

                            Buku

                        </th>


                        <th>

                            Tgl Pinjam

                        </th>


                        <th>

                            Status

                        </th>


                        <th width="150">

                            Aksi

                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php

                $no = 1;

                while ($d = mysqli_fetch_assoc($data)) {

                ?>


                    <tr>


                        <!-- NOMOR -->

                        <td>

                            <?= $no++ ?>

                        </td>


                        <!-- SISWA -->

                        <td>

                            <?= htmlspecialchars(
                                $d['nama_lengkap']
                            ) ?>

                        </td>


                        <!-- BUKU -->

                        <td>

                            <?= htmlspecialchars(
                                $d['nama_buku']
                            ) ?>

                        </td>


                        <!-- TANGGAL -->

                        <td>

                            <?= htmlspecialchars(
                                $d['tanggal_pinjam']
                            ) ?>

                        </td>


                        <!-- STATUS -->

                        <td>


                            <?php

                            if ($d['status'] == 'Dipinjam') {

                            ?>

                                <span
                                    class="badge bg-warning text-dark"
                                >

                                    <i class="bi bi-book"></i>

                                    Dipinjam

                                </span>


                            <?php

                            } else {

                            ?>


                                <span
                                    class="badge bg-success"
                                >

                                    <i class="bi bi-check-circle"></i>

                                    <?= htmlspecialchars(
                                        $d['status']
                                    ) ?>

                                </span>


                            <?php

                            }

                            ?>


                        </td>


                        <!-- AKSI -->

                        <td>


                            <?php

                            if ($d['status'] == 'Dipinjam') {

                            ?>


                                <a
                                    href="kembali.php?id=<?= $d['id'] ?>"
                                    class="btn btn-primary btn-sm"
                                    onclick="return confirm('Yakin buku ini dikembalikan?')"
                                >

                                    <i
                                        class="bi bi-arrow-return-left"
                                    ></i>

                                    Kembalikan

                                </a>


                            <?php

                            } else {

                            ?>


                                <span class="text-muted">

                                    <i
                                        class="bi bi-check-circle"
                                    ></i>

                                    Selesai

                                </span>


                            <?php

                            }

                            ?>


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


<!-- =========================================================
     BOOTSTRAP JS
========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>