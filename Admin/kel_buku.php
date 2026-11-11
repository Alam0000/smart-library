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
SELECT
    b.*,
    COALESCE(AVG(r.rating),0) as rating
FROM buku b
LEFT JOIN rating_buku r
ON b.id = r.id_buku
GROUP BY b.id
ORDER BY b.id DESC
");

?>

<!DOCTYPE html>
<html>
<head>

<title>Kelola Buku</title>

<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- BOOTSTRAP -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- ICON -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/style.css">

<style>

body{
    background:#0f172a;
    font-family:'Poppins',sans-serif;
    color:white;
}

/* AREA KONTEN UTAMA */
.main-content{
    margin-left:280px; /* sama dengan lebar sidebar */
    padding:30px;
    min-height:100vh;
}

/* HEADER */
.topbar{
    background:rgba(255,255,255,0.05);
    backdrop-filter:blur(20px);
    border:1px solid rgba(255,255,255,0.08);
    padding:20px 30px;
    border-radius:20px;
    margin-bottom:25px;
}

/* CARD */
.card-custom{
    background:rgba(255,255,255,0.05);
    backdrop-filter:blur(20px);
    border:1px solid rgba(255,255,255,0.08);
    border-radius:20px;
    color:white;
}

/* TABLE */
.table{
    margin-bottom:0;
}

.table thead{
    background:#0d6efd;
    color:white;
}

.table th{
    padding:15px;
    border:none;
    white-space:nowrap;
}

.table td{
    padding:15px;
    vertical-align:middle;
    white-space:nowrap;
}

/* IMAGE */
.book-img{
    width:70px;
    height:90px;
    object-fit:cover;
    border-radius:10px;
    box-shadow:0 3px 10px rgba(0,0,0,.15);
}

/* BUTTON */
.btn-custom{
    border-radius:10px;
    padding:8px 15px;
}

/* TITLE */
.page-title{
    font-weight:700;
    margin-bottom:5px;
}

/* BADGE */
.badge-tahun{
    background:#e3f2fd;
    color:#0d47a1;
    padding:8px 12px;
    border-radius:20px;
    font-size:13px;
}
.table{
    color:white;
}

.table tbody tr{
    border-color:rgba(255,255,255,0.08);
}

.table tbody tr:hover{
    background:rgba(255,255,255,0.05);
}

.table td{
    color:#e2e8f0;
}
/* RESPONSIVE */
.page-title{
    color:white;
    font-weight:700;
}
.table{
    color:#1e293b !important;
}

.table td{
    color:#334155 !important;
}

.table th{
    color:#1e293b !important;
    font-weight:700;
}

.table strong{
    color:#1e293b !important;
}

.card-custom{
    background:white;
}
.text-muted{
    color:#94a3b8 !important;
}
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

    .card-custom{
        overflow-x:auto;
    }

}
</style>
</head>
<body>

<!-- SIDEBAR -->
<?php include 'partials/sidebar.php'; ?>

<!-- MAIN CONTENT -->
<div class="main-content">

    <!-- HEADER -->
    <div class="topbar d-flex justify-content-between align-items-center">

        <div>

            <h2 class="page-title">
                📚 Kelola Buku
            </h2>

            <p class="text-muted mb-0">
                Manajemen data buku perpustakaan
            </p>

        </div>

        <div>

            <a href="tambah.php"
            class="btn btn-success btn-custom">

                <i class="bi bi-plus-circle"></i>
                Tambah Buku

            </a>

        </div>

    </div>

    <!-- CARD -->
    <div class="card-custom p-4">

    <div class="table-responsive">

        <table class="table table-hover align-middle">

            <thead>

                <tr>

                    <th>No</th>
                    <th>Kode</th>
                    <th>Cover</th>
                    <th>Nama Buku</th>
                    <th>Penulis</th>
                    <th>Penerbit</th>
                    <th>Kategori</th>
                    <th>Tahun</th>
                    <th>Stok</th>
                    <th>View</th>
                    <th>Rating</th>
                    <th>Dipinjam</th>
                    <th>Status</th>
                    <th width="180">Aksi</th>

                </tr>

            </thead>

            <tbody>

<?php
$no = 1;

while($d = $data->fetch_assoc()){
?>

<tr>

    <td><?= $no++ ?></td>

    <td>
        <span class="badge bg-dark">
            <?= $d['kode_buku'] ?>
        </span>
    </td>

    <td>
        <img src="../gambar/<?= $d['gambar'] ?>"
        class="book-img">
    </td>

    <td>
        <strong>
            <?= $d['nama_buku'] ?>
        </strong>
    </td>

    <td>
        <?= $d['penulis'] ?>
    </td>

    <td>
        <?= $d['penerbit'] ?>
    </td>

    <td>
        <span class="badge bg-info">
            <?= $d['kategori'] ?>
        </span>
    </td>

    <td>
        <?= $d['tahun_terbit'] ?>
    </td>

    <td>
        <span class="badge bg-primary">
            <?= $d['stok'] ?>
        </span>
    </td>

    <td>
        <?= $d['view_buku'] ?>
    </td>

    <td>

        <?php

        if($d['rating'] > 0){

            echo number_format($d['rating'],1);

        }else{

            echo "-";

        }

        ?>

        ⭐

        </td>

    <td>
        <?= $d['jumlah_dipinjam'] ?>
    </td>

    <td>

        <?php if($d['stok'] > 0){ ?>

            <span class="badge bg-success">
                Tersedia
            </span>

        <?php }else{ ?>

            <span class="badge bg-danger">
                Habis
            </span>

        <?php } ?>

    </td>

    <td>

        <a href="edit.php?id=<?= $d['id'] ?>"
        class="btn btn-warning btn-sm">

            <i class="bi bi-pencil-square"></i>

        </a>

        <a href="hapus.php?id=<?= $d['id'] ?>"
        class="btn btn-danger btn-sm"
        onclick="return confirm('Yakin ingin menghapus buku ini?')">

            <i class="bi bi-trash"></i>

        </a>

    </td>

</tr>

<?php } ?>

</tbody>

        </table>

    </div>

</div>

</body>
</html>