<?php

session_start();
require_once __DIR__ . '/../lib/auth.php';
wajibPetugas('../auth/login.php');


if (!isset($_SESSION['login'])) {

    header("location:../../auth/login.php");

    exit;
}

include '../connection/koneksi.php';



// ==========================================
// 1. HITUNG ULANG RANKING UMUM (MESIN DINAMIS AHP + SAW)
//    Kriteria umum yang aktif, semua buku, benefit dan cost.
//    Hasil diarsipkan ke hasil_saw lengkap dengan ranking,
//    dengan rumus yang sama seperti halaman Perhitungan SAW.
// ==========================================

require_once __DIR__ . '/../lib/saw_engine.php';
arsipkanRankingUmum($koneksi);



// ==========================================
// 7. AMBIL HASIL RANKING
// ==========================================

$ranking = $koneksi->query("
    SELECT
        hasil_saw.id_buku,
        buku.nama_buku,
        hasil_saw.nilai
    FROM hasil_saw
    JOIN buku
        ON buku.id = hasil_saw.id_buku
    ORDER BY
        hasil_saw.nilai DESC,
        buku.nama_buku ASC
");

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Hasil Ranking</title>


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


    <!-- STYLE UTAMA -->

    <link
        rel="stylesheet"
        href="../assets/style.css"
    >


    <style>

        /* =========================================================
           BODY
        ========================================================= */

        body {

            margin: 0;

            background: #0f172a;

            font-family: 'Poppins', 'Segoe UI', sans-serif;

            color: white;

        }



        /* =========================================================
           SIDEBAR
        ========================================================= */

        .sidebar {

            position: fixed !important;

            top: 0 !important;

            left: 0 !important;

            width: 280px;

            height: 100vh;

            z-index: 99999 !important;

            pointer-events: auto !important;

        }


        /* Semua link sidebar bisa diklik */

        .sidebar a {

            position: relative;

            z-index: 100000 !important;

            pointer-events: auto !important;

            cursor: pointer;

        }



        /* =========================================================
           MAIN CONTENT
        ========================================================= */

        .main-content {

            margin-left: 280px;

            padding: 30px;

            min-height: 100vh;

            position: relative;

            z-index: 1;

        }



        /* =========================================================
           TOPBAR
        ========================================================= */

        .topbar {

            background: rgba(255,255,255,0.05);

            backdrop-filter: blur(20px);

            border: 1px solid rgba(255,255,255,0.08);

            padding: 20px 30px;

            border-radius: 20px;

            margin-bottom: 25px;

        }


        .page-title {

            color: white;

            font-weight: 700;

            margin-bottom: 5px;

        }



        /* =========================================================
           CARD
        ========================================================= */

        .card-custom {

            background: white;

            border: none;

            border-radius: 20px;

            box-shadow: 0 5px 15px rgba(0,0,0,0.15);

        }



        /* =========================================================
           JUDUL DALAM CARD
        ========================================================= */

        .section-title {

            color: #1e293b;

            font-weight: 700;

        }



        /* =========================================================
           TABLE
        ========================================================= */

        .table {

            margin-bottom: 0;

            color: #334155;

        }


        .table thead tr {

            background: #0d6efd !important;

        }


        .table thead th {

            background: #0d6efd !important;

            color: white !important;

            font-weight: 700;

            padding: 15px;

            border: 1px solid #0b5ed7 !important;

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



        /* =========================================================
           RANKING
        ========================================================= */

        .ranking-number {

            font-weight: 700;

        }


        .nilai-saw {

            font-weight: 700;

        }



        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media(max-width:768px) {

            .sidebar {

                width: 100%;

                position: relative !important;

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

<?php include 'partials/sidebar.php'; ?>



<!-- =========================================================
     PERBAIKAN LINK SIDEBAR
========================================================= -->

<script>

document.addEventListener("DOMContentLoaded", function(){

    const sidebarLinks =
        document.querySelectorAll(".sidebar a");


    sidebarLinks.forEach(function(link){

        let href =
            link.getAttribute("href");


        if(
            !href ||
            href.startsWith("#") ||
            href.startsWith("http")
        ){

            return;

        }


        /*
        |--------------------------------------------------------------------------
        | MENU UTAMA
        |--------------------------------------------------------------------------
        */

        if(href === "dashboard.php"){

            link.href = "dashboard.php";

        }


        else if(href === "kel_buku.php"){

            link.href = "kel_buku.php";

        }


        else if(href === "kriteria.php"){

            link.href = "kriteria.php";

        }


        /*
        |--------------------------------------------------------------------------
        | GENERATE NILAI
        |--------------------------------------------------------------------------
        */

        else if(href === "saw/generate_nilai.php"){

            link.href = "saw/generate_nilai.php";

        }


        /*
        |--------------------------------------------------------------------------
        | AHP
        |--------------------------------------------------------------------------
        */

        else if(href === "ahp/input.php"){

            link.href = "ahp/input.php";

        }


        else if(href === "ahp/hitung_lengkap.php"){

            link.href = "ahp/hitung_lengkap.php";

        }


        /*
        |--------------------------------------------------------------------------
        | SAW
        |--------------------------------------------------------------------------
        */

        else if(href === "saw/hitung.php"){

            link.href = "saw/hitung.php";

        }


        /*
        |--------------------------------------------------------------------------
        | HASIL RANKING
        |--------------------------------------------------------------------------
        */

        else if(href === "hasil_ranking.php"){

            link.href = "hasil_ranking.php";

        }


        /*
        |--------------------------------------------------------------------------
        | PEMINJAMAN
        |--------------------------------------------------------------------------
        */

        else if(href === "peminjaman/index.php"){

            link.href = "peminjaman/index.php";

        }


        /*
        |--------------------------------------------------------------------------
        | LOGOUT
        |--------------------------------------------------------------------------
        */

        else if(href === "logout.php"){

            link.href = "logout.php";

        }

    });

});

</script>



<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<div class="main-content">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="topbar">

        <h2 class="page-title">

            🏆 Hasil Ranking Rekomendasi Buku

        </h2>

        <p class="text-white-50 mb-0">

            Hasil akhir perhitungan metode SAW berdasarkan bobot AHP

        </p>

    </div>



    <!-- =====================================================
         CARD UTAMA
    ====================================================== -->

    <div class="card-custom p-4">


        <h3 class="section-title text-center mb-4">

            Hasil Ranking Rekomendasi Buku

        </h3>


        <div class="table-responsive">

            <table class="table table-bordered table-hover text-center">

                <thead>

                    <tr>

                        <th width="100">
                            Ranking
                        </th>

                        <th>
                            Nama Buku
                        </th>

                        <th width="180">
                            Nilai SAW
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php

                $no = 1;

                while ($r = $ranking->fetch_assoc()) {

                ?>

                    <tr>

                        <td>

                            <strong class="ranking-number">

                                <?= $no++ ?>

                            </strong>

                        </td>


                        <td class="text-start">

                            <?= htmlspecialchars(
                                $r['nama_buku']
                            ) ?>

                        </td>


                        <td>

                            <strong class="nilai-saw">

                                <?= number_format(
                                    $r['nilai'],
                                    6,
                                    '.',
                                    ''
                                ) ?>

                            </strong>

                        </td>

                    </tr>

                <?php } ?>

                </tbody>

            </table>

        </div>


    </div>

</div>



<!-- =========================================================
     BOOTSTRAP JS
========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>