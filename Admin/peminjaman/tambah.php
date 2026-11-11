<?php

session_start();
require_once __DIR__ . '/../../lib/auth.php';
wajibPetugas('../../auth/login.php');


/* =====================================================
   CEK LOGIN
===================================================== */

if (!isset($_SESSION['login'])) {

    header("Location: ../../auth/login.php");

    exit;
}


/* =====================================================
   KONEKSI DATABASE
===================================================== */

include '../../connection/koneksi.php';


/* =====================================================
   PROSES PEMINJAMAN
===================================================== */

if (isset($_POST['simpan'])) {

    $id_user = $_POST['id_user'] ?? '';
    $id_buku = $_POST['id_buku'] ?? '';


    /* =================================================
       VALIDASI
    ================================================= */

    if ($id_user == '' || $id_buku == '') {

        echo "
        <script>
            alert('Siswa dan buku harus dipilih!');
            window.location.href='tambah.php';
        </script>
        ";

        exit;
    }


    /* =================================================
       CEK STOK BUKU
    ================================================= */

    $idBukuInt = (int)$id_buku;
    $stCek = $koneksi->prepare("SELECT stok FROM buku WHERE id=?");
    $stCek->bind_param('i', $idBukuInt);
    $stCek->execute();
    $cek = $stCek->get_result();

    $dataBuku = mysqli_fetch_assoc($cek);


    if (!$dataBuku || $dataBuku['stok'] <= 0) {

        echo "
        <script>
            alert('Stok buku sudah habis!');
            window.location.href='tambah.php';
        </script>
        ";

        exit;
    }


    /* =================================================
       SIMPAN PEMINJAMAN
    ================================================= */

    $idUserInt = (int)$id_user;
    $idPetugas = (int)($_SESSION['id_user'] ?? 0);
    $simpan = false;
    try {
        $koneksi->begin_transaction();
        $ins = $koneksi->prepare("INSERT INTO peminjaman (id_user, id_buku, tgl_pengajuan, tanggal_pinjam, tanggal_kembali, status, id_petugas_acc, tgl_acc) VALUES (?, ?, CURDATE(), CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'dipinjam', ?, CURDATE())");
        $ins->bind_param('iii', $idUserInt, $idBukuInt, $idPetugas);
        $ins->execute();
        $idBaru = $ins->insert_id;
        $upd = $koneksi->prepare("UPDATE buku SET stok = stok - 1, jumlah_dipinjam = COALESCE(jumlah_dipinjam,0) + 1 WHERE id = ? AND stok > 0");
        $upd->bind_param('i', $idBukuInt);
        $upd->execute();
        if ($upd->affected_rows < 1) { throw new Exception('Stok habis'); }
        $koneksi->commit();
        $simpan = true;
        catatAudit($koneksi, 'tambah_peminjaman', 'peminjaman', $idBaru, "Peminjaman dicatat petugas: user $idUserInt, buku $idBukuInt");
    } catch (Throwable $e) {
        $koneksi->rollback();
    }


    /* =================================================
       UPDATE STOK BUKU
    ================================================= */

    if ($simpan) {

        /* stok dan jumlah_dipinjam sudah diperbarui di dalam transaksi di atas */


        echo "
        <script>
            alert('Peminjaman buku berhasil!');
            window.location.href='index.php';
        </script>
        ";

        exit;

    } else {

        echo "
        <script>
            alert('Peminjaman gagal disimpan!');
            window.location.href='tambah.php';
        </script>
        ";

        exit;
    }
}

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Peminjaman Buku - Smart Library</title>


    <!-- =================================================
         BOOTSTRAP
    ================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- =================================================
         BOOTSTRAP ICON
    ================================================== -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
    >


    <!-- =================================================
         STYLE UTAMA
         SAMA SEPERTI index.php
    ================================================== -->

    <link
        rel="stylesheet"
        href="../../assets/style.css"
    >


    <style>

        /* =================================================
           BODY
        ================================================= */

        body {

            margin: 0;

            background: #0f172a;

            font-family:
                'Poppins',
                'Segoe UI',
                sans-serif;

            color: white;

        }


        /* =================================================
           SIDEBAR
           SAMA SEPERTI index.php
        ================================================= */

        .sidebar {

            position: fixed !important;

            top: 0 !important;

            left: 0 !important;

            width: 280px !important;

            height: 100vh !important;

            z-index: 99999 !important;

            overflow-y: auto !important;

            pointer-events: auto !important;

        }


        .sidebar a {

            position: relative !important;

            z-index: 100000 !important;

            pointer-events: auto !important;

            cursor: pointer !important;

            text-decoration: none !important;

        }


        .sidebar a:hover {

            cursor: pointer !important;

        }


        /* =================================================
           MAIN CONTENT
        ================================================= */

        .main-content {

            margin-left: 280px;

            padding: 30px;

            min-height: 100vh;

            position: relative;

            z-index: 1;

        }


        /* =================================================
           PAGE TITLE
        ================================================= */

        .page-title {

            color: white;

            font-weight: 700;

            margin-bottom: 25px;

        }


        /* =================================================
           CARD
        ================================================= */

        .card-custom {

            background: white;

            border: none;

            border-radius: 20px;

            box-shadow:
                0 5px 15px
                rgba(0, 0, 0, 0.15);

            color: #334155;

        }


        /* =================================================
           FORM LABEL
        ================================================= */

        .form-label {

            font-weight: 600;

            color: #334155;

        }


        /* =================================================
           FORM CONTROL
        ================================================= */

        .form-control,
        .form-select {

            border-radius: 10px;

            padding: 11px;

        }


        .form-control:focus,
        .form-select:focus {

            box-shadow:
                0 0 0 3px
                rgba(13, 110, 253, 0.15);

        }


        /* =================================================
           BUTTON
        ================================================= */

        .btn-custom {

            border-radius: 10px;

            padding: 10px 20px;

        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 768px) {

            .sidebar {

                position: relative !important;

                width: 100% !important;

                height: auto !important;

            }


            .main-content {

                margin-left: 0;

                padding: 15px;

            }

        }

    </style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
     SAMA SEPERTI index.php
===================================================== -->

<div class="sidebar">


    <!-- =================================================
         LOGO
    ================================================== -->

    <div class="logo">

        <h3>📚 Smart Library</h3>

        <p>SMP Negeri 4 Solear</p>

    </div>


    <!-- =================================================
         MAIN MENU
    ================================================== -->

    <div class="menu-title">

        Main Menu

    </div>


    <!-- =================================================
         DASHBOARD
    ================================================== -->

    <a href="../dashboard.php">

        <i class="bi bi-grid-fill"></i>

        Dashboard

    </a>


    <!-- =================================================
         KELOLA BUKU
    ================================================== -->

    <a href="../kel_buku.php">

        <i class="bi bi-book-fill"></i>

        Kelola Buku

    </a>


    <!-- =================================================
         KRITERIA
    ================================================== -->

    <a href="../kriteria.php">

        <i class="bi bi-sliders"></i>

        Kriteria

    </a>


    <!-- =================================================
         GENERATE NILAI
    ================================================== -->

    <a href="../saw/generate_nilai.php">

        <i class="bi bi-cpu-fill"></i>

        Generate Nilai

    </a>


    <!-- =================================================
         METODE SPK
    ================================================== -->

    <div class="menu-title">

        Metode SPK

    </div>


    <!-- =================================================
         INPUT AHP
    ================================================== -->

    <a href="../ahp/input.php">

        <i class="bi bi-diagram-3-fill"></i>

        Input AHP

    </a>


    <!-- =================================================
         HITUNG AHP
    ================================================== -->

    <a href="../ahp/hitung_lengkap.php">

        <i class="bi bi-bar-chart-fill"></i>

        Hitung AHP

    </a>


    <!-- =================================================
         HITUNG SAW
    ================================================== -->

    <a href="../saw/hitung.php">

        <i class="bi bi-calculator-fill"></i>

        Hitung SAW

    </a>


    <!-- =================================================
         HASIL RANKING
    ================================================== -->

    <a href="../hasil_ranking.php">

        <i class="bi bi-trophy-fill"></i>

        Hasil Ranking

    </a>


    <!-- =================================================
         TRANSAKSI
    ================================================== -->

    <div class="menu-title">

        Transaksi

    </div>


    <!-- =================================================
         PEMINJAMAN
         ACTIVE
    ================================================== -->

    <a
        href="index.php"
        class="active"
    >

        <i class="bi bi-journal-bookmark-fill"></i>

        Peminjaman

    </a>


    <!-- =================================================
         ACCOUNT
    ================================================== -->

    <div class="menu-title">

        Account

    </div>


    <!-- =================================================
         LOGOUT
    ================================================== -->

    <a href="../../auth/logout.php">

        <i class="bi bi-box-arrow-right"></i>

        Logout

    </a>


</div>


<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<div class="main-content">


    <!-- =================================================
         TITLE
    ================================================== -->

    <h2 class="page-title">

        📚 Peminjaman Buku

    </h2>


    <!-- =================================================
         CARD
    ================================================== -->

    <div class="card-custom p-4">


        <h4 class="mb-4">

            <i class="bi bi-journal-plus"></i>

            Peminjaman Buku

        </h4>


        <!-- =================================================
             FORM PEMINJAMAN
        ================================================== -->

        <form method="POST">


            <!-- =================================================
                 SISWA
            ================================================== -->

            <div class="mb-3">

                <label class="form-label">

                    Siswa

                </label>


                <select
                    name="id_user"
                    class="form-select"
                    required
                >

                    <option value="">

                        -- Pilih Siswa --

                    </option>


                    <?php

                    $siswa = mysqli_query(
                        $koneksi,
                        "
                        SELECT *
                        FROM users
                        WHERE role='siswa'
                        ORDER BY nama_lengkap ASC
                        "
                    );


                    while ($s = mysqli_fetch_assoc($siswa)) {

                    ?>

                        <option
                            value="<?= htmlspecialchars($s['id']) ?>"
                        >

                            <?= htmlspecialchars(
                                $s['nama_lengkap']
                            ) ?>

                        </option>

                    <?php

                    }

                    ?>

                </select>

            </div>


            <!-- =================================================
                 BUKU
            ================================================== -->

            <div class="mb-4">

                <label class="form-label">

                    Buku

                </label>


                <select
                    name="id_buku"
                    class="form-select"
                    required
                >

                    <option value="">

                        -- Pilih Buku --

                    </option>


                    <?php

                    $buku = mysqli_query(
                        $koneksi,
                        "
                        SELECT *
                        FROM buku
                        WHERE stok > 0
                        ORDER BY nama_buku ASC
                        "
                    );


                    while ($b = mysqli_fetch_assoc($buku)) {

                    ?>

                        <option
                            value="<?= htmlspecialchars($b['id']) ?>"
                        >

                            <?= htmlspecialchars(
                                $b['nama_buku']
                            ) ?>

                            — Stok:

                            <?= htmlspecialchars(
                                $b['stok']
                            ) ?>

                        </option>

                    <?php

                    }

                    ?>

                </select>

            </div>


            <!-- =================================================
                 BUTTON PINJAM
            ================================================== -->

            <button
                type="submit"
                name="simpan"
                class="btn btn-success btn-custom"
            >

                <i class="bi bi-bookmark-plus-fill"></i>

                Pinjam Buku

            </button>


            <!-- =================================================
                 BUTTON KEMBALI
            ================================================== -->

            <a
                href="index.php"
                class="btn btn-secondary btn-custom"
            >

                <i class="bi bi-arrow-left"></i>

                Kembali

            </a>


        </form>


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