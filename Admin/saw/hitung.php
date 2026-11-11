<?php

session_start();
require_once __DIR__ . '/../../lib/auth.php';
wajibPetugas('../../auth/login.php');


if (!isset($_SESSION['login'])) {
    header("location:../../auth/login.php");
    exit;
}

include '../../connection/koneksi.php';
include '../../lib/saw_engine.php';

/*
|--------------------------------------------------------------------------
| 1. Kriteria aktif (dinamis, tidak lagi terkunci id 8-12)
|--------------------------------------------------------------------------
*/
// Dasar perhitungan: UMUM (kriteria non-personal) atau PERSONAL untuk satu siswa.
// Kriteria personal (C7 Minat Kategori, C8 Kemiripan Buku) bergantung pada riwayat
// baca siswa, jadi baru ikut dihitung setelah satu siswa dipilih.
$idSiswa = (int)($_GET['siswa'] ?? 0);
$profil = null; $namaSiswa = ''; $infoMode = '';
if ($idSiswa > 0) {
    $su = $koneksi->prepare("SELECT id, nama_lengkap FROM users WHERE id=? AND role='siswa'");
    $su->bind_param('i', $idSiswa);
    $su->execute();
    $u = $su->get_result()->fetch_assoc();
    if ($u) {
        $namaSiswa = $u['nama_lengkap'];
        $profil = muatProfil($koneksi, $idSiswa);
        if (empty($profil['ada'])) {
            $infoMode = 'Siswa ini belum punya riwayat baca (pinjam/cari/rating/preferensi), sehingga dihitung dengan kriteria umum.';
            $profil = null;
        }
    } else {
        $idSiswa = 0;
    }
}
$modePersonal = $profil && !empty($profil['ada']);
$listSiswa = [];
$qs = $koneksi->query("SELECT id, nama_lengkap FROM users WHERE role='siswa' ORDER BY nama_lengkap");
while ($qs && $r = $qs->fetch_assoc()) $listSiswa[] = $r;
$daftarKriteria = muatKriteria($koneksi, $modePersonal);

$kriteria = [];
foreach ($daftarKriteria as $k) {
    $kriteria[(int)$k['id']] = $k;
}

if (!$kriteria) {
    die("<div style='font-family:Arial;padding:30px'><h3>Belum ada kriteria aktif.</h3></div>");
}

/*
|--------------------------------------------------------------------------
| 2. Bobot AHP
|--------------------------------------------------------------------------
*/
$bobot = muatBobot($koneksi);

foreach ($kriteria as $id => $k) {
    if (!isset($bobot[$id])) {
        die("
            <div style='font-family:Arial;padding:30px'>
                <h3>Bobot AHP belum tersedia untuk kriteria {$k['kode_kriteria']}.</h3>
                <p>Silakan lengkapi perbandingan lalu jalankan perhitungan AHP terlebih dahulu.</p>
                <a href='../ahp/input.php'>Ke input AHP</a>
            </div>
        ");
    }
}

/*
|--------------------------------------------------------------------------
| 3. Nilai tiap buku (semua buku, termasuk yang belum pernah dipinjam)
|    lalu hitung SAW dengan mesin dinamis (benefit dan cost)
|--------------------------------------------------------------------------
*/
[$nilaiBuku, $infoBuku] = muatDataBuku($koneksi, $daftarKriteria, $profil);
$hasilSaw = hitungSAW($daftarKriteria, $bobot, $nilaiBuku, $infoBuku);

/*
|--------------------------------------------------------------------------
| 4. Nilai acuan normalisasi: Max untuk benefit, Min untuk cost
|--------------------------------------------------------------------------
*/
$bobotPakai = normalisasiBobot(array_intersect_key($bobot, $kriteria));
$max = [];
foreach ($kriteria as $id => $k) {
    $kolom = array_column($nilaiBuku, $id);
    if (!$kolom) { $max[$id] = 0; continue; }
    $max[$id] = ($k['tipe'] === 'cost') ? min($kolom) : max($kolom);
}

/*
|--------------------------------------------------------------------------
| 5. Susun array untuk tampilan (format sama seperti sebelumnya)
|--------------------------------------------------------------------------
*/
$dataSaw = [];

foreach ($hasilSaw as $h) {

    $d = [
        'id_buku'     => $h['id_buku'],
        'nama_buku'   => $h['nama_buku'],
        'nilai'       => [],
        'normalisasi' => [],
        'terbobot'    => [],
        'total'       => $h['skor']
    ];

    foreach ($kriteria as $id => $k) {
        $c = $h['kontribusi'][$id];
        $d['nilai'][$id]       = $c['nilai'];
        $d['normalisasi'][$id] = $c['normal'];
        $d['terbobot'][$id]    = $c['kontribusi'];
    }

    $dataSaw[$h['id_buku']] = $d;
}

/*
|--------------------------------------------------------------------------
| 6. Urutkan (skor tertinggi dulu, seri diurutkan nama)
|--------------------------------------------------------------------------
*/
usort($dataSaw, function ($a, $b) {
    if ($a['total'] == $b['total']) {
        return strcmp($a['nama_buku'], $b['nama_buku']);
    }
    return $b['total'] <=> $a['total'];
});

/*
|--------------------------------------------------------------------------
| 7. Arsipkan hasil ke hasil_saw (lengkap dengan ranking)
|--------------------------------------------------------------------------
*/
// arsip ranking hanya untuk perhitungan UMUM (hitungan personal tidak menimpa arsip)
if ($idSiswa === 0) {
$koneksi->query("TRUNCATE TABLE hasil_saw");
catatAudit($koneksi,'hitung_saw','hasil_saw',null,'Perhitungan SAW dijalankan ulang');

$stmt = $koneksi->prepare("
    INSERT INTO hasil_saw (id_buku, nilai, ranking) VALUES (?, ?, ?)
");

$urut = 0;
foreach ($dataSaw as $data) {
    $urut++;
    $id_buku = $data['id_buku'];
    $total = $data['total'];
    $stmt->bind_param("idi", $id_buku, $total, $urut);
    $stmt->execute();
}
$stmt->close();
}

?>

<!DOCTYPE html>

<html>

<head>

<title>Perhitungan SAW</title>

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

    color:white;

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


/* Semua link sidebar bisa diklik */

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

    position:relative;

    z-index:1;

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
   SECTION TITLE
========================================================= */

.section-title{

    color:#1e293b;

    font-weight:bold;

    margin-bottom:15px;

}


/* =========================================================
   TEXT
========================================================= */

.content-text{

    color:#334155;

}


/* =========================================================
   TABLE
========================================================= */

.table{

    margin-bottom:0;

    color:#334155;

}


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


.table tbody td{

    color:#334155 !important;

    padding:15px;

    vertical-align:middle;

    border-color:#dee2e6;

}


.table tbody tr:hover td{

    background:#f8fafc !important;

}


/* =========================================================
   NILAI
========================================================= */

.nilai{

    text-align:center;

    white-space:nowrap;

}


/* =========================================================
   ALERT RUMUS
========================================================= */

.alert-secondary{

    color:#334155;

}


/* =========================================================
   BUTTON
========================================================= */

.btn-custom{

    border-radius:10px;

    padding:10px 15px;

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


    .topbar{

        text-align:center;

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

            link.href = "generate_nilai.php";

        }


        /*
        |--------------------------------------------------------------------------
        | AHP
        |--------------------------------------------------------------------------
        */

        else if(href === "ahp/input.php"){

            link.href = "../ahp/input.php";

        }


        else if(href === "ahp/hitung_lengkap.php"){

            link.href = "../ahp/hitung_lengkap.php";

        }


        /*
        |--------------------------------------------------------------------------
        | SAW
        |--------------------------------------------------------------------------
        */

        else if(href === "saw/hitung.php"){

            link.href = "hitung.php";

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

            🏆 Perhitungan SAW Rekomendasi Buku

        </h2>


        <p class="text-muted mb-0">

            Proses perhitungan metode SAW berdasarkan bobot AHP

        </p>
        <form method="get" class="d-flex flex-wrap align-items-center gap-2 mt-3">
            <label class="mb-0 fw-semibold">Hitung untuk:</label>
            <select name="siswa" class="form-select" style="max-width:300px" onchange="this.form.submit()">
                <option value="0">Umum (tanpa kriteria personal)</option>
                <?php foreach ($listSiswa as $sw) { ?>
                    <option value="<?= (int)$sw['id'] ?>" <?= $idSiswa === (int)$sw['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sw['nama_lengkap']) ?></option>
                <?php } ?>
            </select>
        </form>
        <?php if ($modePersonal) { ?>
            <div class="alert alert-info mt-3 mb-0">Perhitungan personal untuk <b><?= htmlspecialchars($namaSiswa) ?></b>: kriteria Minat Kategori (C7) dan Kemiripan Buku (C8) ikut dihitung dari riwayat siswa. Hasil ini tidak mengganti arsip ranking umum.</div>
        <?php } elseif ($infoMode !== '') { ?>
            <div class="alert alert-warning mt-3 mb-0"><?= htmlspecialchars($infoMode) ?></div>
        <?php } else { ?>
            <p class="small text-muted mt-2 mb-0">Mode umum: kriteria personal (C7, C8) tidak ikut karena nilainya berbeda untuk tiap siswa. Pilih siswa untuk melihat perhitungan C1 sampai C8.</p>
        <?php } ?>


    </div>



    <!-- CARD UTAMA -->

    <div class="card-custom p-4">


        <!-- =================================================
             1. BOBOT AHP
        ================================================== -->

        <div class="section-title">

            1. Bobot Kriteria Hasil AHP

        </div>


        <div class="table-responsive">

            <table class="table table-bordered">

                <thead>

                    <tr>

                        <th>Kode</th>

                        <th>Kriteria</th>

                        <th>Bobot AHP</th>
<th>Bobot Terpakai</th>

                    </tr>

                </thead>


                <tbody>

<?php foreach ($kriteria as $id => $k) { ?>

                    <tr>

                        <td>

                            <?= htmlspecialchars($k['kode_kriteria']) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $k['nama_kriteria']
                            ) ?>

                        </td>


                        <td>

                            <?= number_format(
                                $bobot[$id],
                                6,
                                '.',
                                ''
                            ) ?>

                        </td>
<td><?= number_format($bobotPakai[$id] ?? 0, 6, '.', '') ?></td>

                    </tr>

<?php } ?>

                </tbody>

            </table>

        </div>



        <!-- =================================================
             2. MATRIKS KEPUTUSAN
        ================================================== -->

        <div class="section-title mt-4">

            2. Matriks Keputusan

        </div>


        <div class="table-responsive">

            <table class="table table-bordered">

                <thead>

                    <tr>

                        <th>No</th>

                        <th>Nama Buku</th>


<?php foreach ($kriteria as $id => $k) { ?>

                        <th>

                            <?= htmlspecialchars($k['kode_kriteria']) ?>

                        </th>

<?php } ?>

                    </tr>

                </thead>


                <tbody>

<?php

$no = 1;


foreach ($dataSaw as $data) {

?>

                    <tr>

                        <td>

                            <?= $no++ ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $data['nama_buku']
                            ) ?>

                        </td>


<?php foreach ($kriteria as $id => $k) { ?>

                        <td class="nilai">

                            <?= number_format(
                                $data['nilai'][$id],
                                2,
                                '.',
                                ''
                            ) ?>

                        </td>

<?php } ?>

                    </tr>

<?php } ?>

                </tbody>

            </table>

        </div>



        <!-- =================================================
             3. NILAI MAX
        ================================================== -->

        <div class="section-title mt-4">

            3. Nilai Acuan Setiap Kriteria (Max untuk Benefit, Min untuk Cost)

        </div>


        <div class="table-responsive">

            <table class="table table-bordered">

                <thead>

                    <tr>

<?php foreach ($kriteria as $id => $k) { ?>

                        <th>

                            <?= htmlspecialchars($k['kode_kriteria']) ?>

                        </th>

<?php } ?>

                    </tr>

                </thead>


                <tbody>

                    <tr>

<?php foreach ($kriteria as $id => $k) { ?>

                        <td class="nilai">

                            <?= number_format(
                                $max[$id],
                                2,
                                '.',
                                ''
                            ) ?>

                        </td>

<?php } ?>

                    </tr>

                </tbody>

            </table>

        </div>



        <!-- =================================================
             4. NORMALISASI
        ================================================== -->

        <div class="section-title mt-4">

            4. Matriks Normalisasi

        </div>


        <p class="content-text">
            Rumus normalisasi mengikuti jenis kriteria:
        </p>
        <div class="alert alert-secondary">
            <b>Benefit:</b> R<sub>ij</sub> = X<sub>ij</sub> / Max(X<sub>ij</sub>)
            <br>
            <b>Cost:</b> R<sub>ij</sub> = Min(X<sub>ij</sub>) / X<sub>ij</sub>
            <hr class="my-2">
            <?php foreach ($kriteria as $id => $k) { ?>
                <span class="badge bg-<?= $k['tipe'] === 'cost' ? 'danger' : 'success' ?> me-1">
                    <?= htmlspecialchars($k['kode_kriteria']) ?>: <?= htmlspecialchars($k['tipe']) ?>
                </span>
            <?php } ?>
        </div>


        <div class="table-responsive">

            <table class="table table-bordered">

                <thead>

                    <tr>

                        <th>No</th>

                        <th>Nama Buku</th>


<?php foreach ($kriteria as $id => $k) { ?>

                        <th>

                            <?= htmlspecialchars($k['kode_kriteria']) ?>

                        </th>

<?php } ?>

                    </tr>

                </thead>


                <tbody>

<?php

$no = 1;


foreach ($dataSaw as $data) {

?>

                    <tr>

                        <td>

                            <?= $no++ ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $data['nama_buku']
                            ) ?>

                        </td>


<?php foreach ($kriteria as $id => $k) { ?>

                        <td class="nilai">

                            <?= number_format(
                                $data['normalisasi'][$id],
                                6,
                                '.',
                                ''
                            ) ?>

                        </td>

<?php } ?>

                    </tr>

<?php } ?>

                </tbody>

            </table>

        </div>



        <!-- =================================================
             5. NILAI TERBOBOT
        ================================================== -->

        <div class="section-title mt-4">

            5. Perhitungan Nilai Preferensi

        </div>


        <p class="content-text">

            Setelah normalisasi, setiap nilai dikalikan dengan bobot AHP:

        </p>


        <div class="alert alert-secondary">

            <b>

                V<sub>i</sub> =
                Σ (W<sub>j</sub> × R<sub>ij</sub>)

            </b>

        </div>


        <div class="table-responsive">

            <table class="table table-bordered">

                <thead>

                    <tr>

                        <th>No</th>

                        <th>Nama Buku</th>


<?php foreach ($kriteria as $id => $k) { ?>

                        <th>

                            <?= htmlspecialchars($k['kode_kriteria']) ?>

                        </th>

<?php } ?>


                        <th>Total</th>

                    </tr>

                </thead>


                <tbody>

<?php

$no = 1;


foreach ($dataSaw as $data) {

?>

                    <tr>

                        <td>

                            <?= $no++ ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $data['nama_buku']
                            ) ?>

                        </td>


<?php foreach ($kriteria as $id => $k) { ?>

                        <td class="nilai">

                            <?= number_format(
                                $data['terbobot'][$id],
                                6,
                                '.',
                                ''
                            ) ?>

                        </td>

<?php } ?>


                        <td class="nilai">

                            <b>

                                <?= number_format(
                                    $data['total'],
                                    6,
                                    '.',
                                    ''
                                ) ?>

                            </b>

                        </td>

                    </tr>

<?php } ?>

                </tbody>

            </table>

        </div>



        <!-- =================================================
             6. HASIL RANKING
        ================================================== -->

        <div class="section-title mt-4">

            6. Hasil Ranking Rekomendasi Buku

        </div>


        <div class="table-responsive">

            <table class="table table-bordered">

                <thead>

                    <tr>

                        <th>Ranking</th>

                        <th>Nama Buku</th>

                        <th>Nilai SAW</th>

                    </tr>

                </thead>


                <tbody>

<?php

$ranking = 1;


foreach ($dataSaw as $data) {

?>

                    <tr>

                        <td>

                            <?= $ranking++ ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $data['nama_buku']
                            ) ?>

                        </td>


                        <td>

                            <b>

                                <?= number_format(
                                    $data['total'],
                                    6,
                                    '.',
                                    ''
                                ) ?>

                            </b>

                        </td>

                    </tr>

<?php } ?>

                </tbody>

            </table>

        </div>



        <!-- KEMBALI DASHBOARD -->

        <a
            href="../dashboard.php"
            class="btn btn-primary btn-custom w-100 mt-3"
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