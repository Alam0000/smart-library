<?php
session_start();
require_once __DIR__ . '/../lib/auth.php';
wajibPetugas('../auth/login.php');


if(!isset($_SESSION['login'])){
    header("location:../auth/login.php");
    exit;
}

include '../connection/koneksi.php';
include '../lib/saw_engine.php';

$id = (int)$_GET['id'];

$stmt = $koneksi->prepare("
SELECT *
FROM kriteria
WHERE id=?
");

$stmt->bind_param("i",$id);
$stmt->execute();

$d = $stmt->get_result()->fetch_assoc();

if(isset($_POST['update'])){

    $kode = $_POST['kode'];
    $nama = $_POST['nama'];
    $tipe = $_POST['tipe'];
    $sumber = $_POST['sumber'];
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    if(!isset(daftarSumberData()[$sumber])){ die('Sumber data tidak valid'); }

    $update = $koneksi->prepare("
    UPDATE kriteria
    SET
        kode_kriteria=?,
        nama_kriteria=?,
        tipe=?,
        sumber_data=?,
        aktif=?
    WHERE id=?
    ");

    $update->bind_param(
        "ssssii",
        $kode,
        $nama,
        $tipe,
        $sumber,
        $aktif,
        $id
    );

    $update->execute();
catatAudit($koneksi,'ubah_kriteria','kriteria',null,'Ubah kriteria '.$kode.' ('.$nama.')');

    echo "
    <script>
    alert('Data berhasil diupdate');
    window.location='kriteria.php';
    </script>
    ";
}
?>

<!DOCTYPE html>
<html>
<head>

<title>Edit Kriteria</title>

<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- BOOTSTRAP -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- BOOTSTRAP ICON -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<!-- STYLE UTAMA -->
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
   FORM
========================= */

.form-label{
    color:#1e293b;
    font-weight:600;
}

.form-control,
.form-select{
    border-radius:10px;
    padding:10px 12px;
    border:1px solid #d1d5db;
}

.form-control:focus,
.form-select:focus{
    border-color:#0d6efd;
    box-shadow:0 0 0 0.2rem rgba(13,110,253,.15);
}


/* =========================
   BUTTON
========================= */

.btn-custom{
    border-radius:10px;
    padding:10px 15px;
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

    <div class="topbar">

        <h2 class="page-title">
            ✏️ Edit Kriteria
        </h2>

        <p class="text-muted mb-0">
            Ubah data kriteria perpustakaan
        </p>

    </div>


    <!-- =========================
         FORM CARD
    ========================= -->

    <div class="card-custom p-4 col-lg-6 col-md-8 mx-auto">

        <h5 class="text-dark fw-bold mb-4">

            <i class="bi bi-pencil-square"></i>

            Form Edit Kriteria

        </h5>


        <form method="POST">


            <!-- KODE -->

            <div class="mb-3">

                <label class="form-label">
                    Kode Kriteria
                </label>

                <input
                    type="text"
                    name="kode"
                    class="form-control"
                    value="<?= htmlspecialchars($d['kode_kriteria']) ?>"
                    required
                >

            </div>


            <!-- NAMA -->

            <div class="mb-3">

                <label class="form-label">
                    Nama Kriteria
                </label>

                <input
                    type="text"
                    name="nama"
                    class="form-control"
                    value="<?= htmlspecialchars($d['nama_kriteria']) ?>"
                    required
                >

            </div>


            <!-- TIPE -->

            <div class="mb-4">

                <label class="form-label">
                    Tipe
                </label>

                <select
                    name="tipe"
                    class="form-select"
                    required
                >

                    <option
                        value="benefit"
                        <?= $d['tipe']=='benefit' ? 'selected' : '' ?>
                    >
                        Benefit
                    </option>

                    <option
                        value="cost"
                        <?= $d['tipe']=='cost' ? 'selected' : '' ?>
                    >
                        Cost
                    </option>

                </select>

            </div>


            <!-- UPDATE -->
            <!-- SUMBER DATA & STATUS -->

            <div class="mb-3">

                <label class="form-label">
                    Sumber Data
                </label>

                <select name="sumber" class="form-select" required>
                    <?php foreach(daftarSumberData() as $kunci => $label){ ?>
                        <option
                            value="<?= $kunci ?>"
                            <?= (($d['sumber_data'] ?? '') === $kunci) ? 'selected' : '' ?>
                        ><?= htmlspecialchars($label) ?></option>
                    <?php } ?>
                </select>

                <small class="text-muted">
                    Dari kolom mana nilai kriteria diambil. Aturan konversi
                    ke skor 1-5 diatur di menu Skala Nilai.
                </small>

            </div>

            <div class="mb-4 form-check">

                <input
                    type="checkbox"
                    name="aktif"
                    value="1"
                    id="aktif"
                    class="form-check-input"
                    <?= (!isset($d) || ($d['aktif'] ?? 1)) ? 'checked' : '' ?>
                >

                <label class="form-check-label" for="aktif">
                    Kriteria aktif (ikut perhitungan AHP dan SAW)
                </label>

            </div>

            

            <button
                name="update"
                class="btn btn-primary btn-custom w-100"
            >

                <i class="bi bi-check-circle"></i>

                Update

            </button>


            <!-- KEMBALI -->

            <a
                href="kriteria.php"
                class="btn btn-secondary btn-custom w-100 mt-2"
            >

                <i class="bi bi-arrow-left"></i>

                Kembali

            </a>


        </form>

    </div>

</div>


<!-- BOOTSTRAP JS -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>