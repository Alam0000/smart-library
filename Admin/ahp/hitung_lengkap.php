<?php

session_start();
require_once __DIR__ . '/../../lib/auth.php';
wajibPetugas('../../auth/login.php');


if(!isset($_SESSION['login'])){
    header("location:../../auth/login.php");
    exit;
}

include '../../connection/koneksi.php';


/*
|--------------------------------------------------------------------------
| AMBIL KRITERIA C1 - C5
|--------------------------------------------------------------------------
*/

$kriteria = [];

$q = $koneksi->query("
    SELECT *
    FROM kriteria
    WHERE aktif = 1
    ORDER BY id
");

while($d = $q->fetch_assoc()){
    $kriteria[] = $d;
}

$jumlah = count($kriteria);


/*
|--------------------------------------------------------------------------
| VALIDASI
|--------------------------------------------------------------------------
*/

if($jumlah < 2){

    die("
        <h3>Error</h3>
        <p>
        Sistem membutuhkan minimal 2 kriteria aktif untuk AHP.
        </p>
    ");
}


/*
|--------------------------------------------------------------------------
| BUAT MATRIKS IDENTITAS
|--------------------------------------------------------------------------
*/

$matriks = [];

for($i=0; $i<$jumlah; $i++){

    for($j=0; $j<$jumlah; $j++){

        if($i == $j){
            $matriks[$i][$j] = 1;
        }else{
            $matriks[$i][$j] = 0;
        }

    }
}


/*
|--------------------------------------------------------------------------
| AMBIL PERBANDINGAN AHP
|--------------------------------------------------------------------------
*/

$data = $koneksi->query("
    SELECT *
    FROM perbandingan_ahp
");

$pasanganTerbaca = 0;


while($d = $data->fetch_assoc()){

    $k1 = array_search(
        $d['kriteria1'],
        array_column($kriteria,'id')
    );

    $k2 = array_search(
        $d['kriteria2'],
        array_column($kriteria,'id')
    );


    if($k1 !== false && $k2 !== false){

        $matriks[$k1][$k2] =
            (float)$d['nilai'];

        $matriks[$k2][$k1] =
            1 / (float)$d['nilai'];

        $pasanganTerbaca++;
    }
}


/*
| CEK KELENGKAPAN: tiap pasangan kriteria aktif harus sudah dibandingkan.
| Jika admin menambah kriteria baru, perbandingannya harus diisi dulu.
*/
$pasanganSeharusnya = $jumlah * ($jumlah - 1) / 2;

if($pasanganTerbaca < $pasanganSeharusnya){

    die("
        <div style='font-family:Arial;padding:30px'>
            <h3>Perbandingan AHP belum lengkap</h3>
            <p>
            Ada $pasanganSeharusnya pasangan kriteria aktif, baru
            $pasanganTerbaca yang terisi. Kemungkinan Anda menambah atau
            mengaktifkan kriteria baru.
            </p>
            <a href='input.php'>Lengkapi perbandingan AHP</a>
        </div>
    ");
}


/*
|--------------------------------------------------------------------------
| JUMLAH KOLOM
|--------------------------------------------------------------------------
*/

$jumlahKolom = [];

for($j=0; $j<$jumlah; $j++){

    $jumlahKolom[$j] = 0;

    for($i=0; $i<$jumlah; $i++){

        $jumlahKolom[$j] +=
            $matriks[$i][$j];
    }
}


/*
|--------------------------------------------------------------------------
| NORMALISASI AHP
|--------------------------------------------------------------------------
*/

$normalisasi = [];

$bobot = [];

for($i=0; $i<$jumlah; $i++){

    $totalBaris = 0;

    for($j=0; $j<$jumlah; $j++){

        $normalisasi[$i][$j] =
            $matriks[$i][$j]
            /
            $jumlahKolom[$j];

        $totalBaris +=
            $normalisasi[$i][$j];
    }


    /*
    | Rata-rata baris
    */

    $bobot[$i] =
        $totalBaris / $jumlah;
}


/*
|--------------------------------------------------------------------------
| WEIGHTED SUM
|--------------------------------------------------------------------------
*/

$weightedSum = [];

$lambda = [];

$lambdaMax = 0;

for($i=0; $i<$jumlah; $i++){

    $weightedSum[$i] = 0;

    for($j=0; $j<$jumlah; $j++){

        $weightedSum[$i] +=
            $matriks[$i][$j]
            *
            $bobot[$j];
    }


    /*
    | Lambda setiap kriteria
    */

    $lambda[$i] =
        $weightedSum[$i]
        /
        $bobot[$i];


    $lambdaMax +=
        $lambda[$i];
}


/*
|--------------------------------------------------------------------------
| RATA-RATA LAMBDA MAX
|--------------------------------------------------------------------------
*/

$lambdaMax =
    $lambdaMax / $jumlah;


/*
|--------------------------------------------------------------------------
| CI
|--------------------------------------------------------------------------
*/

$CI =
    ($lambdaMax - $jumlah)
    /
    ($jumlah - 1);


/*
|--------------------------------------------------------------------------
| RI
|--------------------------------------------------------------------------
|
| RI untuk n = 5 = 1.12
|
*/

/* Tabel Random Index (Saaty) untuk n = 1..10 */
$tabelRI = [1=>0, 2=>0, 3=>0.58, 4=>0.90, 5=>1.12,
            6=>1.24, 7=>1.32, 8=>1.41, 9=>1.45, 10=>1.49];

$RI = $tabelRI[min($jumlah, 10)];


/*
|--------------------------------------------------------------------------
| CR
|--------------------------------------------------------------------------
*/

$CR = ($RI > 0) ? ($CI / $RI) : 0;


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$status =
    ($CR <= 0.10)
    ? "Konsisten"
    : "Tidak Konsisten";


/*
|--------------------------------------------------------------------------
| SIMPAN BOBOT
|--------------------------------------------------------------------------
*/

$koneksi->query("
    TRUNCATE TABLE bobot_ahp
");
catatAudit($koneksi,'hitung_bobot_ahp','bobot_ahp',null,'Bobot AHP dihitung ulang, CR='.round($CR,4).' ('.$status.')');


for($i=0; $i<$jumlah; $i++){

    $stmt = $koneksi->prepare("
        INSERT INTO bobot_ahp
        (
            id_kriteria,
            bobot
        )
        VALUES(?,?)
    ");

    $stmt->bind_param(
        "id",
        $kriteria[$i]['id'],
        $bobot[$i]
    );

    $stmt->execute();

    $stmt->close();
}

?>

<!DOCTYPE html>
<html>

<head>

<title>Perhitungan AHP</title>

<meta name="viewport" content="width=device-width, initial-scale=1">


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
href="../../assets/style.css"
>


<style>

/* =========================================================
   BODY
========================================================= */

body{

    margin:0;

    background:#0f172a;

    font-family:'Poppins','Segoe UI',sans-serif;

}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar{

    position:fixed !important;

    top:0 !important;

    left:0 !important;

    width:280px;

    height:100vh;

    z-index:99999 !important;

    pointer-events:auto !important;

}


.sidebar a{

    position:relative;

    z-index:100000 !important;

    pointer-events:auto !important;

    cursor:pointer;

}


/* =========================================================
   MAIN CONTENT
========================================================= */

.main-content{

    margin-left:280px;

    padding:30px;

    min-height:100vh;

}


/* =========================================================
   TOPBAR
========================================================= */

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


/* =========================================================
   CARD
========================================================= */

.card-custom{

    background:white;

    border:none;

    border-radius:20px;

    box-shadow:0 5px 15px rgba(0,0,0,0.15);

}


/* =========================================================
   TABLE
========================================================= */

.table{

    margin-bottom:0;

}


.table th{

    background:#0d6efd;

    color:white;

    font-weight:700;

    padding:15px;

}


.table td{

    padding:15px;

    vertical-align:middle;

    color:#334155;

}


/* =========================================================
   BUTTON
========================================================= */

.btn-custom{

    border-radius:10px;

    padding:10px 18px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:768px){

    .sidebar{

        width:100%;

        position:relative !important;

        height:auto;

    }


    .main-content{

        margin-left:0;

        padding:15px;

    }

}

</style>

</head>


<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<?php include '../partials/sidebar.php'; ?>


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

            link.href = "../dashboard.php";

        }


        else if(href === "kel_buku.php"){

            link.href = "../kel_buku.php";

        }


        else if(href === "kriteria.php"){

            link.href = "../kriteria.php";

        }


        /*
        |--------------------------------------------------------------------------
        | GENERATE NILAI
        |--------------------------------------------------------------------------
        */

        else if(href === "saw/generate_nilai.php"){

            link.href = "../saw/generate_nilai.php";

        }


        /*
        |--------------------------------------------------------------------------
        | AHP
        |--------------------------------------------------------------------------
        */

        else if(href === "ahp/input.php"){

            link.href = "input.php";

        }


        else if(href === "ahp/hitung_lengkap.php"){

            link.href = "hitung_lengkap.php";

        }


        /*
        |--------------------------------------------------------------------------
        | SAW
        |--------------------------------------------------------------------------
        */

        else if(href === "saw/hitung.php"){

            link.href = "../saw/hitung.php";

        }


        /*
        |--------------------------------------------------------------------------
        | HASIL RANKING
        |--------------------------------------------------------------------------
        */

        else if(href === "hasil_ranking.php"){

            link.href = "../hasil_ranking.php";

        }


        /*
        |--------------------------------------------------------------------------
        | PEMINJAMAN
        |--------------------------------------------------------------------------
        */

        else if(href === "peminjaman/index.php"){

            link.href = "../peminjaman/index.php";

        }


        /*
        |--------------------------------------------------------------------------
        | LOGOUT
        |--------------------------------------------------------------------------
        */

        else if(href === "logout.php"){

            link.href = "../logout.php";

        }

    });

});

</script>


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<div class="main-content">


    <!-- HEADER -->

    <div class="topbar">

        <h2 class="page-title">

            📊 Perhitungan AHP

        </h2>

        <p class="text-white-50 mb-0">

            Perhitungan bobot kriteria dan uji konsistensi AHP

        </p>

    </div>


    <!-- CARD UTAMA -->

    <div class="card-custom p-4">


        <!-- =================================================
             BOBOT
        ================================================== -->

        <h5 class="mb-3">

            Bobot Kriteria

        </h5>


        <div class="table-responsive">

            <table class="table table-bordered">


                <thead>

                    <tr>

                        <th>Kode</th>

                        <th>Kriteria</th>

                        <th>Bobot</th>

                    </tr>

                </thead>


                <tbody>

<?php

for($i=0; $i<$jumlah; $i++){

?>

                    <tr>

                        <td>

                            <?= htmlspecialchars($kriteria[$i]['kode_kriteria']) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $kriteria[$i]['nama_kriteria']
                            ) ?>

                        </td>


                        <td>

                            <?= number_format(
                                $bobot[$i],
                                6,
                                '.',
                                ''
                            ) ?>

                        </td>

                    </tr>

<?php

}

?>


                    <tr>

                        <th colspan="2">

                            Total

                        </th>


                        <th>

                            <?= number_format(
                                array_sum($bobot),
                                6,
                                '.',
                                ''
                            ) ?>

                        </th>

                    </tr>

                </tbody>

            </table>

        </div>


        <!-- =================================================
             KONSISTENSI
        ================================================== -->

        <h5 class="mt-4 mb-3">

            Uji Konsistensi AHP

        </h5>


        <div class="table-responsive">

            <table class="table table-bordered">


                <thead>

                    <tr>

                        <th>Parameter</th>

                        <th>Nilai</th>

                    </tr>

                </thead>


                <tbody>


                    <tr>

                        <td>

                            λ Max

                        </td>


                        <td>

                            <?= number_format(
                                $lambdaMax,
                                6,
                                '.',
                                ''
                            ) ?>

                        </td>

                    </tr>


                    <tr>

                        <td>

                            CI

                        </td>


                        <td>

                            <?= number_format(
                                $CI,
                                6,
                                '.',
                                ''
                            ) ?>

                        </td>

                    </tr>


                    <tr>

                        <td>

                            RI

                        </td>


                        <td>

                            <?= number_format(
                                $RI,
                                2,
                                '.',
                                ''
                            ) ?>

                        </td>

                    </tr>


                    <tr>

                        <td>

                            CR

                        </td>


                        <td>

                            <?= number_format(
                                $CR,
                                6,
                                '.',
                                ''
                            ) ?>

                        </td>

                    </tr>


                    <tr>

                        <td>

                            Status

                        </td>


                        <td>

<?php if($CR <= 0.10){ ?>

                            <span class="badge bg-success">

                                Konsisten

                            </span>

<?php }else{ ?>

                            <span class="badge bg-danger">

                                Tidak Konsisten

                            </span>

<?php } ?>

                        </td>

                    </tr>


                </tbody>

            </table>

        </div>


        <!-- =================================================
             TOMBOL
        ================================================== -->

        <a
            href="../dashboard.php"
            class="btn btn-primary btn-custom mt-3"
        >

            <i class="bi bi-arrow-left"></i>

            Kembali Dashboard

        </a>


    </div>


</div>


<!-- BOOTSTRAP JS -->

<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>