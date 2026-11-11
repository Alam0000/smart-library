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
| AMBIL DATA KRITERIA
|--------------------------------------------------------------------------
*/

$kriteria = [];

$query = $koneksi->query("
    SELECT *
    FROM kriteria
    WHERE aktif = 1
    ORDER BY id
");

while($row = $query->fetch_assoc()){
    $kriteria[] = $row;
}

/* nilai yang sudah tersimpan, supaya form terisi otomatis */
$tersimpan = [];
$qt = $koneksi->query("SELECT kriteria1, kriteria2, nilai FROM perbandingan_ahp");
while($r = $qt->fetch_assoc()){
    $tersimpan[$r['kriteria1'].'-'.$r['kriteria2']] = (float)$r['nilai'];
}

/* pilihan skala Saaty: 1/9 ... 1 ... 9 */
$pilihan = [];
for($x=9; $x>=2; $x--){ $pilihan['1/'.$x] = round(1/$x, 6); }
for($x=1; $x<=9; $x++){ $pilihan[(string)$x] = (float)$x; }

?>

<!DOCTYPE html>
<html>

<head>

<title>Input Perbandingan AHP</title>

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

    white-space:nowrap;

}


.table td{

    padding:15px;

    vertical-align:middle;

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

            ⚖️ Input Perbandingan AHP

        </h2>

        <p class="text-white-50 mb-0">

            Masukkan nilai perbandingan antar kriteria

        </p>

    </div>


    <!-- CARD -->

    <div class="card-custom p-4">


        <h3 class="mb-4">

            Input Perbandingan Kriteria AHP

        </h3>


        <form action="simpan.php" method="POST">
            <p class="text-muted mb-3">
                Nilai = seberapa penting <b>Kriteria 1</b> dibanding <b>Kriteria 2</b>.
                Pilih angka 2-9 jika Kriteria 1 lebih penting, 1 jika sama,
                dan pecahan (1/2 ... 1/9) jika <b>Kriteria 2</b> yang lebih penting.
                Jumlah pasangan menyesuaikan jumlah kriteria aktif.
            </p>


            <div class="table-responsive">

                <table class="table table-bordered">

                    <thead>

                        <tr>

                            <th>Kriteria 1</th>

                            <th>Kriteria 2</th>

                            <th>Nilai</th>

                        </tr>

                    </thead>


                    <tbody>

<?php

for($i=0; $i<count($kriteria); $i++){

    for($j=$i+1; $j<count($kriteria); $j++){

?>

                        <tr>

                            <td>

                                <?= htmlspecialchars(
                                    $kriteria[$i]['nama_kriteria']
                                ) ?>

                                <input
                                    type="hidden"
                                    name="k1[]"
                                    value="<?= $kriteria[$i]['id'] ?>"
                                >

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $kriteria[$j]['nama_kriteria']
                                ) ?>

                                <input
                                    type="hidden"
                                    name="k2[]"
                                    value="<?= $kriteria[$j]['id'] ?>"
                                >

                            </td>


                            <td>

                                <select
                                    name="nilai[]"
                                    class="form-control"
                                    required
                                >
<?php
    $kunci = $kriteria[$i]['id'].'-'.$kriteria[$j]['id'];
    $terpilih = $tersimpan[$kunci] ?? 1.0;
    foreach($pilihan as $label => $nilaiOpsi){
?>
                                    <option
                                        value="<?= $nilaiOpsi ?>"
                                        <?= abs($nilaiOpsi - $terpilih) < 0.0001 ? 'selected' : '' ?>
                                    ><?= $label ?></option>
<?php } ?>
                                </select>

                            </td>

                        </tr>

<?php

    }

}

?>

                    </tbody>

                </table>

            </div>


            <div class="d-flex gap-2 mt-4">

                <button
                    type="submit"
                    class="btn btn-primary btn-custom"
                >

                    <i class="bi bi-save"></i>

                    Simpan Data AHP

                </button>


                <a
                    href="../dashboard.php"
                    class="btn btn-secondary btn-custom"
                >

                    <i class="bi bi-arrow-left"></i>

                    Kembali

                </a>

            </div>


        </form>

    </div>


</div>


<!-- BOOTSTRAP JS -->

<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>