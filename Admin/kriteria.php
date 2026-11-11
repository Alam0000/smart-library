<?php
session_start();
require_once __DIR__ . '/../lib/auth.php';
wajibPetugas('../auth/login.php');


if(!isset($_SESSION['login'])){
    header("location:../auth/login.php");
    exit;
}

include '../connection/koneksi.php';

$data = $koneksi->query("
SELECT *
FROM kriteria
ORDER BY id ASC
");
?>

<!DOCTYPE html>
<html>
<head>

<title>Data Kriteria</title>

<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- BOOTSTRAP -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- ICON -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<!-- STYLE -->
<link rel="stylesheet" href="../assets/style.css">

<style>

/* =========================
   BODY
========================= */

body{
    background:#0f172a;
    font-family:'Poppins','Segoe UI',sans-serif;
    color:white;
}


/* =========================
   MAIN CONTENT
========================= */

.main-content{
    margin-left:280px;
    padding:30px;
    min-height:100vh;
}


/* =========================
   TOPBAR
========================= */

.topbar{
    background:rgba(255,255,255,0.05);
    backdrop-filter:blur(20px);
    border:1px solid rgba(255,255,255,0.08);
    padding:20px 30px;
    border-radius:20px;
    margin-bottom:25px;
}


.page-title{
    color:white;
    font-weight:700;
    margin-bottom:5px;
}


/* =========================
   CARD
========================= */

.card-custom{
    background:white;
    border:none;
    border-radius:20px;
    box-shadow:0 5px 15px rgba(0,0,0,0.15);
}


/* =========================
   TABLE
========================= */

.table{
    margin-bottom:0;
    color:#1e293b;
}


/* HEADER TABEL */
.table thead tr{
    background:#0d6efd !important;
}

.table thead th{
    background:#0d6efd !important;
    color:white !important;
    font-weight:700;
    padding:15px;
    border:1px solid #0b5ed7 !important;
    white-space:nowrap;
}


/* ISI TABEL */
.table tbody td{
    color:#334155 !important;
    padding:15px;
    vertical-align:middle;
    border-color:#dee2e6;
}


/* HOVER */
.table tbody tr:hover td{
    background:#f8fafc !important;
}


/* =========================
   BUTTON
========================= */

.btn-custom{
    border-radius:10px;
    padding:8px 15px;
}


/* =========================
   RESPONSIVE
========================= */

@media(max-width:768px){

    .main-content{
        margin-left:0;
        padding:15px;
    }

    .topbar{
        flex-direction:column;
        gap:15px;
        text-align:center;
    }

}

</style>

</head>

<body>


<!-- =========================
     SIDEBAR
========================= -->

<?php include 'partials/sidebar.php'; ?>


<!-- =========================
     MAIN CONTENT
========================= -->

<div class="main-content">


    <!-- =========================
         HEADER
    ========================= -->

    <div class="topbar d-flex justify-content-between align-items-center">

        <div>

            <h2 class="page-title">
                ⚙️ Data Kriteria
            </h2>

            <p class="text-muted mb-0">
                Manajemen data kriteria sistem
            </p>

        </div>


        <div>

            <a href="tambah_kriteria.php"
               class="btn btn-primary btn-custom">

                <i class="bi bi-plus-circle"></i>

                Tambah Kriteria

            </a>

            <a href="skala_nilai.php" class="btn btn-warning btn-custom ms-2">
                <i class="bi bi-sliders"></i>
                Skala Nilai
            </a>

        </div>

    </div>



    <!-- =========================
         CARD DATA
    ========================= -->

    <div class="card-custom p-4">


        <!-- JUDUL CARD -->

        <div class="d-flex justify-content-between align-items-center mb-4">

            <h5 class="mb-0 text-dark fw-bold">

                <i class="bi bi-list-ul"></i>

                Daftar Kriteria

            </h5>


            <a href="dashboard.php"
               class="btn btn-secondary btn-custom">

                <i class="bi bi-arrow-left"></i>

                Kembali

            </a>

        </div>



        <!-- =========================
             TABLE
        ========================= -->

        <div class="table-responsive">

            <table class="table table-bordered table-hover align-middle">


                <!-- HEADER TABEL -->

                <thead>

                    <tr>

                        <th style="width:70px;">
                            No
                        </th>

                        <th style="width:120px;">
                            Kode
                        </th>

                        <th>
                            Nama Kriteria
                        </th>

                        <th style="width:150px;">
                            Tipe
                        </th>

                        <th style="width:220px;">
                            Aksi
                        </th>

                    </tr>

                </thead>



                <!-- BODY TABEL -->

                <tbody>

<?php

$no = 1;

while($d = $data->fetch_assoc()){

?>

                    <tr>


                        <!-- NO -->

                        <td>

                            <?= $no++ ?>

                        </td>



                        <!-- KODE -->

                        <td>

                            <span class="badge bg-dark">

                                <?= $d['kode_kriteria'] ?>

                            </span>

                        </td>



                        <!-- NAMA KRITERIA -->

                        <td>

                            <strong>

                                <?= $d['nama_kriteria'] ?>

                            </strong>

                        </td>



                        <!-- TIPE -->

                        <td>

<?php if($d['tipe'] == 'benefit'){ ?>

                            <span class="badge bg-success">

                                Benefit

                            </span>

<?php }else{ ?>

                            <span class="badge bg-warning text-dark">

                                Cost

                            </span>

<?php } ?>

                        </td>



                        <!-- AKSI -->

                        <td>

                            <a href="edit_kriteria.php?id=<?= $d['id'] ?>"
                               class="btn btn-warning btn-sm">

                                <i class="bi bi-pencil-square"></i>

                                Edit

                            </a>


                            <a href="hapus_kriteria.php?id=<?= $d['id'] ?>"
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Hapus data?')">

                                <i class="bi bi-trash"></i>

                                Hapus

                            </a>

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



<!-- BOOTSTRAP JS -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>