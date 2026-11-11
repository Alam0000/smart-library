<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include '../connection/koneksi.php';

// SEARCH
$cari = $_GET['cari'] ?? '';
include '../lib/catat_pencarian.php';
    catatPencarian($koneksi, $cari);
// QUERY BUKU
$stmt = $koneksi->prepare("
SELECT *
FROM buku
WHERE nama_buku LIKE ?
OR penulis LIKE ?
ORDER BY id DESC
LIMIT 8
");

$search = "%".$cari."%";

$stmt->bind_param(
    "ss",
    $search,
    $search
);

$stmt->execute();

$buku = $stmt->get_result();

/* =========================
BUKU TERPOPULER
========================= */

$buku_populer = mysqli_query($koneksi,"
SELECT *
FROM buku
ORDER BY jumlah_dipinjam DESC, view_buku DESC
LIMIT 4
");


/* =========================
BUKU TREN (30 hari terakhir) + STATISTIK KOLEKSI
========================= */
$buku_tren = mysqli_query($koneksi,"
SELECT b.*, COUNT(p.id) AS pinjam_30
FROM buku b
JOIN peminjaman p ON p.id_buku = b.id
WHERE COALESCE(p.tanggal_pinjam, p.tgl_pengajuan) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
AND p.status <> 'ditolak'
GROUP BY b.id
ORDER BY pinjam_30 DESC, b.view_buku DESC
LIMIT 4
");
$stat_koleksi = mysqli_fetch_assoc(mysqli_query($koneksi,"
SELECT COUNT(*) AS judul, COUNT(DISTINCT kategori) AS kategori, COALESCE(SUM(stok),0) AS eksemplar
FROM buku
"));

// cek login
$isLogin = isset($_SESSION['login']);
$role = $_SESSION['role'] ?? '';

// notifikasi persisten (siswa) + rekomendasi AHP-SAW untuk beranda
require_once __DIR__ . '/../lib/notifikasi.php';
$notifBaru = 0;
if ($isLogin && $role === 'siswa') {
    sinkronNotifikasi($koneksi, (int)($_SESSION['id_user'] ?? 0));
    $notifBaru = jumlahNotifBelumDibaca($koneksi, (int)($_SESSION['id_user'] ?? 0));
}
$rek_home = [];
try {
    $rek_home = array_slice(rekomendasi($koneksi, null, $isLogin ? (int)($_SESSION['id_user'] ?? 0) : null), 0, 4);
} catch (Throwable $e) {}
?>

<!DOCTYPE html>
<html>
<head>

<title>Smart Library</title>

<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- BOOTSTRAP -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- ICON -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<style>
/* =========================
TENTANG SEKOLAH
========================= */

.school-card{
    max-width:1000px; /* lebar card */

    margin:auto; /* membuat card berada di tengah */

    background:#fff;

    border-radius:30px;

    padding:40px;

    border:1px solid rgba(175,105,239,.08);

    box-shadow:
        0 15px 40px rgba(0,0,0,.08);

    transition:.3s;
    object-position:center 100%;
}

.school-card:hover{
    transform:translateY(-5px);

    box-shadow:
        0 20px 50px rgba(175,105,239,.15);
}

/* Gambar */
.school-img{
    width:100%;
    height:320px;
    object-fit:cover;
    object-position:center bottom;

    border-radius:25px;
    box-shadow:0 10px 30px rgba(0,0,0,.1);
}

/* Badge */
.school-badge{
    display:inline-block;

    background:#f3e8ff;

    color:#af69ef;

    padding:8px 18px;

    border-radius:50px;

    font-size:14px;

    font-weight:600;

    margin-bottom:20px;
}

/* Judul */
.school-title{
    font-size:40px;

    font-weight:700;

    color:#0f172a;

    margin-bottom:20px;
}

/* Deskripsi */
.school-text{
    color:#64748b;

    font-size:16px;

    line-height:1.9;

    margin-bottom:15px;
}
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

:root{
    --primary:#af69ef;
    --secondary:#9c4fe8;
    --dark:#0f172a;
    --light:#f8fafc;
    --text:#334155;
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:'Poppins',sans-serif;

    background: linear-gradient(
        135deg,
        #f8f2ff 0%,
        #d8b4fe 25%,
        #af69ef 55%,
        #9333ea 100%
    );

    color: var(--text);
}



/* =========================
MODERN NAVBAR
========================= */

.navbar{
    position:fixed;
    top:20px;
    left:50%;
    transform:translateX(-50%);

    width:90%;
    max-width:950px;

    background:rgba(255,255,255,.92);
    backdrop-filter:blur(20px);

    border:1px solid rgba(175,105,239,.12);

    border-radius:60px;

    padding:12px 25px;

    box-shadow:
        0 10px 30px rgba(0,0,0,.06);

    z-index:9999;

    animation:navFade .8s ease;
}

@keyframes navFade{

    from{
        opacity:0;
        transform:
        translateX(-50%)
        translateY(-30px);
    }

    to{
        opacity:1;
        transform:
        translateX(-50%)
        translateY(0);
    }

}

.navbar-brand{
    font-size:20px;
    font-weight:600;
    color:#af69ef !important;
    letter-spacing:.5px;
}

.nav-link{
    color:#1e293b !important;
    font-weight:500;
    position:relative;
    margin:0 10px;
    transition:.3s;
}

.nav-link:hover{
    color:#af69ef !important;
    transform:translateY(-2px);
}

.nav-link::after{
    content:'';
    position:absolute;
    width:0;
    height:2px;
    left:50%;
    bottom:-5px;

    background:#af69ef;

    transition:.3s;
}

.nav-link:hover::after{
    width:100%;
    left:0;
}

.btn-login{
    background:#af69ef;
    color:#fff;
    border:none;
    border-radius:50px;
    padding:10px 25px;
    font-weight:600;
    transition:.3s;
}

.btn-login:hover{
    background:#9c4fe8;
    color:#fff;
    transform:translateY(-2px);
    box-shadow:0 10px 25px rgba(175,105,239,.35);
}

.user-btn{
    background:#f3e8ff;
    color:#af69ef;
    border:none;
    border-radius:50px;
    padding:10px 20px;
    font-weight:600;
    transition:.3s;
}

.user-btn:hover{
    background:#e9d5ff;
}

/* =========================
RESPONSIVE NAVBAR
========================= */

@media(max-width:768px){

    .navbar{
        width:95%;
        padding:10px 12px;
    }

    .navbar-brand{
        font-size:18px;
        white-space:nowrap;
    }

    .navbar-toggler{
        flex-shrink:0;
        margin-left:auto;
    }

}


/* =========================
HERO SLIDER
========================= */

.hero{
    min-height:590px;

    display:flex;
    align-items:center;
    justify-content:center;

     padding:110px 30px 60px;

    position:relative;
    overflow:hidden;
}

.hero::before{
    content: '';
    position: absolute;
    inset: 0;

    background-image:
        linear-gradient(
            rgba(15,23,42,.15),
            rgba(15,23,42,.15)
        ),
        url('../assets/img/sekolah2.jpeg');

    background-size: cover;
    background-position: center 80%; /* geser gambar ke bawah */
    background-repeat: no-repeat;
}



/* Animasi Slider */

@keyframes slider1{
    0%,45%{
        opacity:1;
    }

    50%,95%{
        opacity:0;
    }

    100%{
        opacity:1;
    }
}

@keyframes slider2{
    0%,45%{
        opacity:0;
    }

    50%,95%{
        opacity:1;
    }

    100%{
        opacity:0;
    }
}

/* Konten */

.hero-content{
    text-align:center;
    max-width:600px;
    margin:auto;
    position:relative;
    z-index:10;
}

.hero h1{
    font-size: 48px;
    font-weight: 800;
    color: #ffffff;
    line-height: 1.2;
    margin-bottom: 15px;

    text-shadow:
        0 4px 15px rgba(0,0,0,.8),
        0 2px 8px rgba(0,0,0,.6);
}

.hero p{
    max-width: 650px;
    margin: 0 auto 30px;
    font-size: 18px;
    color: #f8fafc;
    line-height: 1.8;

    text-shadow:
        0 3px 10px rgba(0,0,0,.7);
}

.hero-buttons{
    display:flex;
    justify-content:center;
    gap:15px;
    flex-wrap:wrap;
    margin-top:25px;
}

.hero-buttons .btn{
    padding:12px 30px;
    border-radius:50px;
    font-weight:600;
}
.btn-custom{
    background:linear-gradient(135deg,#af69ef,#8b5cf6);
    color:#fff !important;
    border:none;
    padding:12px 30px;
    border-radius:50px;
    font-weight:600;
    transition:.3s;
}

.btn-custom:hover{
    transform:translateY(-2px);
    box-shadow:0 12px 30px rgba(175,105,239,.4);
    color:#fff !important;
}
/* =========================
RESPONSIVE
========================= */

@media(max-width:768px){

    .hero{
        min-height:450px;
        padding:100px 20px 60px;
    }

    .hero h1{
        font-size:40px;
    }

    .hero p{
        font-size:16px;
        line-height:1.7;
    }

    .hero-buttons{
        gap:10px;
    }
}
/* =========================
SECTION
========================= */

.section{
    padding:90px 0;
}

.section-title{
    text-align:center;
    margin-bottom:45px;
}

.section-title h2{
    font-size:35px;
    font-weight:700;
    color:var(--dark);
}

.section-title p{
    color:#64748b;
}

/* =========================
SEARCH
========================= */

.search-box{
    max-width:700px;
    margin:auto;
}

.search-box .input-group{
    overflow:hidden;
    border-radius:50px;
    background:#fff;
    box-shadow:0 10px 25px rgba(0,0,0,.06);
}

.search-box .form-control{
    border:none;
    background:#fff;
    height:55px;
    padding-left:25px;
    font-size:16px;
    box-shadow:none;
}

.search-box .form-control:focus{
    box-shadow:none;
}

.search-box .btn{
    border:none;
    width:140px;
    background:linear-gradient(
        135deg,
        #af69ef,
        #8b5cf6
    );
    font-size:17px;
    font-weight:600;
}

/* =========================
LAYANAN
========================= */


.service-card{
    background:white;
    border-radius:20px;
    padding:28px;
    text-align:center;
    height:100%;
    transition:.3s;
    box-shadow:0 8px 25px rgba(0,0,0,.05);
}

.service-card:hover{
    transform:translateY(-8px);
}

.service-icon{
    width:60px;
    height:60px;
    border-radius:18px;
    background:#f3e8ff;
    color:#af69ef;

    display:flex;
    align-items:center;
    justify-content:center;

    margin:auto;
    margin-bottom:18px;

    font-size:28px;
}

.service-card h4{
    font-size:20px;
    font-weight:600;
    margin-bottom:12px;
}

.service-card p{
    font-size:14px;
    line-height:1.7;
    margin-bottom:0;
}
/* =========================
KOLEKSI BUKU
========================= */

.book-card{
    background:#fff;
    border-radius:24px;
    overflow:hidden;
    height:100%;
    position:relative;

    border:1px solid rgba(175,105,239,.08);

    box-shadow:
        0 8px 25px rgba(15,23,42,.06);

    transition:.35s ease;
}

.book-card:hover{
    transform:translateY(-8px);

    box-shadow:
        0 20px 45px rgba(175,105,239,.16);
}


/* =========================
GAMBAR
========================= */

.book-img{
    width:100%;
    height:230px;
    object-fit:cover;

    border-radius:24px 24px 0 0;

    transition:.4s;
}

.book-card:hover .book-img{
    transform:scale(1.03);
}


/* =========================
ISI
========================= */

.book-content{
    padding:20px;
}


/* =========================
JUDUL
========================= */

.book-title{
    font-size:18px;
    font-weight:700;
    color:#0f172a;

    line-height:1.5;

    min-height:56px;

    margin-bottom:8px;
}


/* =========================
PENULIS
========================= */

.book-author{
    color:#64748b;
    font-size:14px;

    margin-bottom:14px;
}


/* =========================
META
========================= */

.book-meta{
    color:#64748b;
    font-size:13px;

    margin-top:8px;
}


/* =========================
BADGE
========================= */

.badge{
    padding:7px 14px;
    border-radius:50px;
    font-size:11px;
    font-weight:600;
}

/* Tahun */

.bg-primary{
    background:#ede9fe !important;
    color:#7c3aed !important;
}

/* Tersedia */

.bg-success{
    background:#dcfce7 !important;
    color:#15803d !important;
}


/* =========================
BUTTON
========================= */

.book-card .btn{
    border:none;
    font-size:14px;
    font-weight:600;

    padding:11px;

    border-radius:16px !important;

    transition:.3s;
}


/* Detail Buku */

.book-card .btn-primary{
    background:linear-gradient(
        135deg,
        #af69ef,
        #8b5cf6
    );
}

.book-card .btn-primary:hover{
    transform:translateY(-2px);

    box-shadow:
        0 10px 25px rgba(175,105,239,.35);
}


/* Pinjam */

.book-card .btn-success{
    background:#22c55e;
}

.book-card .btn-success:hover{
    background:#16a34a;

    transform:translateY(-2px);
}


/* Simpan */

.book-card .btn-outline-warning{
    border:2px solid #fcd34d;

    color:#ca8a04;

    background:#fff;
}

.book-card .btn-outline-warning:hover{
    background:#fbbf24;

    color:#fff;
}


/* HILANGKAN ICON 📚 */

.book-card::before{
    display:none;
}

/* =========================
STATISTIK
========================= */

.stat-box{
    background:#fff;

    border-radius:24px;

    padding:28px 20px;

    text-align:center;

    border:1px solid rgba(175,105,239,.08);

    box-shadow:
        0 8px 20px rgba(15,23,42,.05);

    transition:.3s;
}

.stat-box:hover{
    transform:translateY(-6px);

    box-shadow:
        0 15px 35px rgba(175,105,239,.15);
}

/* =========================
ICON
========================= */

.stat-icon{
    width:60px;
    height:60px;

    margin:auto;
    margin-bottom:15px;

    border-radius:18px;

    background:linear-gradient(
        135deg,
        #af69ef,
        #8b5cf6
    );

    color:#fff;

    display:flex;
    align-items:center;
    justify-content:center;

    font-size:25px;

    box-shadow:
        0 8px 20px rgba(175,105,239,.25);
}

/* =========================
ANGKA
========================= */

.stat-box h2{
    font-size:36px;

    font-weight:700;

    color:#0f172a;

    margin-bottom:5px;
}

/* =========================
KETERANGAN
========================= */

.stat-box p{
    color:#64748b;

    font-size:14px;

    margin:0;
}
/* =========================
FOOTER CLEAN MODERN
========================= */

footer{
    background:#fff;

    margin-top:80px;

    padding:45px 0 15px;

    border-top:1px solid rgba(175,105,239,.08);
}

/* Logo */

.footer-logo{
    color:#af69ef;

    font-size:22px;

    font-weight:700;

    margin-bottom:10px;
}

/* Deskripsi */

.footer-desc{
    color:#64748b;

    font-size:14px;

    line-height:1.7;

    max-width:280px;
}

/* Judul */

footer h5{
    color:#0f172a;

    font-size:16px;

    font-weight:600;

    margin-bottom:15px;
}

/* Link */

.footer-links{
    list-style:none;

    padding:0;
}

.footer-links li{
    margin-bottom:8px;
}

.footer-links a{
    color:#64748b;

    font-size:14px;

    text-decoration:none;

    transition:.3s;
}

.footer-links a:hover{
    color:#af69ef;
}

/* Kontak */

footer p{
    color:#64748b;

    font-size:14px;

    margin-bottom:10px;
}

footer i{
    color:#af69ef;

    margin-right:8px;
}

/* Garis */

footer hr{
    margin:25px 0 15px;

    border-color:#e5e7eb;
}

/* Copyright */

.footer-copy{
    text-align:center;

    color:#94a3b8;

    font-size:13px;
}

/* =========================
BUTTONS
========================= */

.btn-primary{
    background:var(--primary);
    border:none;
}

.btn-primary:hover{
    background:var(--secondary);
}

.btn-success{
    border-radius:50px;
}

.btn-outline-warning{
    border-radius:50px;
}

.rounded-pill{
    border-radius:50px!important;
}

/* =========================
RESPONSIVE
========================= */

@media(max-width:768px){

.hero h1{
    font-size:42px;
}

.hero-box{
    padding:30px;
}

.section-title h2{
    font-size:30px;
}

.book-img{
    height:250px;
}

}

/* =========================
REKOMENDASI BUKU
========================= */

.rekom-card{
    background:#fff;

    border-radius:22px;
    overflow:hidden;

    color:#0f172a;

    height:100%;

    display:flex;
    flex-direction:column;

    transition:.3s;

    border:1px solid #ede9fe;

    box-shadow:
        0 10px 30px rgba(15,23,42,.06);
}

.rekom-card:hover{
    transform:translateY(-8px);

    box-shadow:
        0 20px 45px rgba(175,105,239,.18);
}

.rekom-img{
    width:100%;
    height:180px;
    object-fit:cover;
}

.rekom-content{
    padding:16px;

    display:flex;
    flex-direction:column;

    flex:1;
}

.rekom-badge{
    background:#f3e8ff;

    color:#af69ef;

    padding:6px 12px;

    border-radius:30px;

    display:inline-block;

    margin-bottom:12px;

    font-size:12px;
    font-weight:600;
}

.rekom-title{
    font-size:16px;
    font-weight:700;

    color:#0f172a;

    margin-bottom:10px;

    line-height:1.5;

    min-height:52px;
}

.rekom-info{
    font-size:13px;

    color:#64748b;

    margin-bottom:6px;
}

.rekom-btn{
    margin-top:auto;

    width:100%;

    border:none;

    background:linear-gradient(
        135deg,
        #af69ef,
        #8b5cf6
    );

    color:#fff;

    font-weight:600;

    padding:11px;

    border-radius:50px;

    transition:.3s;
}

.rekom-btn:hover{
    color:#fff;

    transform:translateY(-2px);

    box-shadow:
        0 10px 25px rgba(175,105,239,.35);
}
/* =========================
FIX MOBILE SPACE KANAN
========================= */

/* =========================
FIX SPACE KANAN MOBILE
========================= */

@media(max-width:768px){

    html,
    body{
        width:100%;
        max-width:100%;
        overflow-x:hidden !important;
    }

}


</style>

</head>

<body>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg">

<div class="container-fluid">

<a class="navbar-brand" href="#">
  <img src="../assets/img/bg1.png" alt="Logo Sekolah" width="30" height="30" class="d-inline-block align-top me-2">
  SMART LIBRARY
</a>

<button class="navbar-toggler"
type="button"
data-bs-toggle="collapse"
data-bs-target="#menu">

<span class="navbar-toggler-icon"></span>

</button>

<div class="collapse navbar-collapse" id="menu">

<ul class="navbar-nav mx-auto">

<li class="nav-item">
<a href="#" class="nav-link">
Beranda
</a>
</li>

<li class="nav-item">
<a href="#layanan" class="nav-link">
Layanan
</a>
</li>

<li class="nav-item">
<a href="#rekomendasi" class="nav-link">
Rekomendasi Buku
</a>
</li>

<li class="nav-item">
<a href="#koleksi" class="nav-link">
Koleksi Buku
</a>
</li>

<?php if($isLogin){ ?>

 <li class="nav-item"><a class="nav-link position-relative" href="notifikasi.php">🔔 Notifikasi<?php if($notifBaru > 0){ ?> <span class="badge rounded-pill bg-danger"><?= (int)$notifBaru ?></span><?php } ?></a></li>
<li class="nav-item dropdown">
        <a href="#" 
           class="nav-link dropdown-toggle" 
           id="bukuSayaDropdown"
           role="button"
           data-bs-toggle="dropdown"
           aria-expanded="false">
            Buku Saya
        </a>

        <ul class="dropdown-menu" aria-labelledby="bukuSayaDropdown">

            <li>
                <a class="dropdown-item" href="buku_saya.php">
                    <i class="bi bi-book-half me-2"></i>
                    Sedang Dipinjam
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
                <a class="dropdown-item" href="riwayat_peminjaman.php">
                    <i class="bi bi-clock-history me-2"></i>
                    Riwayat Peminjaman
                </a>
            </li>

        </ul>
    </li>

<?php } ?>

</ul>

<?php if(!$isLogin){ ?>

<a href="../auth/login.php"
class="btn btn-login">
Login
</a>

<?php } else { ?>

<div class="dropdown">

<button
class="btn user-btn dropdown-toggle"
data-bs-toggle="dropdown">

👤 <?= $_SESSION['username']; ?>

</button>

<ul class="dropdown-menu dropdown-menu-end">

<?php if($role == 'petugas'){ ?>

<li>

<a class="dropdown-item"
href="../Admin/dashboard.php">

Dashboard Petugas

</a>

</li>

<?php } ?>

<li>

<a class="dropdown-item"
href="../auth/logout.php">

Logout

</a>

</li>

</ul>

</div>

<?php } ?>

</div>

</div>

</nav>

<!-- HERO -->
<section class="hero">

<div class="container">

<div class="hero-content">

<h1>
Selamat Datang di<br>
Perpustakaan Digital
</h1>

<p>
SMP Negeri 4 Solear menyediakan akses mudah ke berbagai koleksi buku
dan sumber pembelajaran untuk mendukung budaya literasi dan prestasi siswa.
</p>

<div class="hero-buttons">

<a href="#koleksi" class="btn btn-custom d-inline-flex align-items-center">
    <i class="fas fa-search me-2"></i>
    Jelajahi Buku
</a>


<?php if($isLogin){ ?>

<a href="rekomendasi.php"
class="btn btn-dark rounded-pill px-4">

🏆 Rekomendasi

</a>

<?php } ?>

</div>

</div>



</div>

</section>

<!-- SEARCH -->
<section class="section py-5">

<div class="search-wrappper ">

<div class="section-title mb-4">

<h2>Cari Rekomendasi #<?= $__i + 1 ?>mu</h2>

<p>
Temukan buku berdasarkan judul atau nama penulis
</p>

</div>

<div class="search-box">

<form method="GET">

<div class="input-group">

<input
type="text"
name="cari"
class="form-control"
placeholder="Cari buku atau penulis..."
value="<?= $cari ?>">

<button class="btn btn-primary">

<i class="bi bi-search"></i>
Cari

</button>

</div>

</form>

</div>

</div>

</section>

<section class="section">

<div class="container">

<div class="school-card">

<div class="row align-items-center g-5">

    <div class="col-lg-6">
        <img src="../assets/img/sekolah.jpeg"
             class="school-img">
    </div>

    <div class="col-lg-6">

        <span class="school-badge">
            🏫 Tentang Sekolah
        </span>

        <h2 class="school-title">
            SMP Negeri 4 Solear
        </h2>

        <p class="school-text">
            SMP Negeri 4 Solear merupakan sekolah yang
            berkomitmen meningkatkan kualitas pendidikan,
            budaya literasi, serta pemanfaatan teknologi
            digital dalam proses pembelajaran.
        </p>

        <p class="school-text">
            Melalui Smart Library, siswa dapat mengakses
            koleksi buku dan rekomendasi bacaan secara
            lebih mudah, cepat, dan modern.
        </p>

    </div>

</div>

</div>

</div>

</section>

<!-- LAYANAN -->
<section class="section" id="layanan">

<div class="container">

<div class="section-title">

<h2>
Layanan Smart Library
</h2>

<p>
Fitur utama sistem perpustakaan digital
</p>

</div>

<div class="row gy-5 gx-4">

<div class="col-md-4">

<div class="service-card">

<div class="service-icon">
<i class="bi bi-book-fill"></i>
</div>

<h4>
Koleksi Buku
</h4>

<p>
Menampilkan berbagai koleksi
buku pelajaran SMP secara digital.
</p>

</div>

</div>

<div class="col-md-4">

<div class="service-card">

<div class="service-icon">
<i class="bi bi-bar-chart-fill"></i>
</div>

<h4>
Sistem Rekomendasi
</h4>

<p>
Menggunakan metode AHP dan SAW
untuk rekomendasi buku terbaik.
</p>

</div>

</div>

<div class="col-md-4">

<div class="service-card">

<div class="service-icon">
<i class="bi bi-people-fill"></i>
</div>

<h4>
Smart Library
</h4>

<p>
Membantu siswa menemukan
buku sesuai kebutuhan belajar.
</p>

</div>

</div>

</div>

</div>

</section>
<!-- REKOMENDASI BUKU -->
<section class="section rekom-section" id="rekomendasi">

<div class="container">

<div class="section-title">
<h2 class="d-flex align-items-center justify-content-center">
    <i class="fas fa-star me-2"></i>
    Buku Yang Paling di Rekomendasikan
</h2>

<p class="text-white">Dihitung dengan metode AHP + SAW. <a class="text-white fw-bold" href="rekomendasi.php">Lihat selengkapnya &amp; What-If</a></p>
</div>

<div class="row g-4">

<?php foreach($rek_home as $__i => $__it){ $r = $__it['buku']; ?>

<div class="col-lg-3 col-md-6">

<div class="rekom-card">

<img src="../gambar/<?= $r['gambar']; ?>"
class="rekom-img">

<div class="rekom-content">

<span class="rekom-badge">
Buku Favorit
</span>

<div class="rekom-title">
<?= $r['nama_buku']; ?>
</div>

<div class="rekom-info">
✍️ <?= $r['penulis']; ?>
</div>

<div class="rekom-info">
📖 <?= $r['jumlah_dipinjam']; ?> kali dipinjam
</div>

<div class="rekom-info">💡 <?= htmlspecialchars($__it['alasan']['kalimat']) ?></div>

<div class="rekom-info">
👁️ <?= $r['view_buku']; ?> kali dilihat
</div>

<a href="detail_buku.php?id=<?= $r['id']; ?>"
class="btn rekom-btn">

<i class="bi bi-eye-fill"></i>
Lihat Detail

</a>

</div>

</div>

</div>

<?php } ?>

</div>

</div>

</section>
<!-- KOLEKSI -->
<!-- TREN & STATISTIK KOLEKSI -->
<section class="section" id="tren">
<div class="container">
<div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
  <div>
    <h2 class="fw-bold mb-1">📈 Sedang Tren</h2>
    <p class="text-secondary mb-0">Buku yang paling banyak dipinjam dalam 30 hari terakhir.</p>
  </div>
  <div class="d-flex gap-3 text-secondary small">
    <span><b><?= (int)$stat_koleksi['judul'] ?></b> judul</span>
    <span><b><?= (int)$stat_koleksi['kategori'] ?></b> kategori</span>
    <span><b><?= (int)$stat_koleksi['eksemplar'] ?></b> eksemplar tersedia</span>
  </div>
</div>
<?php if ($buku_tren && mysqli_num_rows($buku_tren) > 0): ?>
<div class="row g-3">
<?php while ($t = mysqli_fetch_assoc($buku_tren)): ?>
  <div class="col-6 col-lg-3">
    <a class="text-decoration-none" href="detail_buku.php?id=<?= (int)$t['id'] ?>">
      <div class="card h-100 border-0 shadow-sm"><div class="card-body">
        <div class="small text-secondary"><?= htmlspecialchars((string)$t['kategori']) ?></div>
        <h6 class="fw-bold text-dark mt-1"><?= htmlspecialchars((string)$t['nama_buku']) ?></h6>
        <div class="small text-secondary"><?= htmlspecialchars((string)$t['penulis']) ?></div>
        <span class="badge bg-success mt-2">↑ <?= (int)$t['pinjam_30'] ?>x dipinjam</span>
      </div></div>
    </a>
  </div>
<?php endwhile; ?>
</div>
<?php else: ?>
<p class="text-secondary">Belum ada data peminjaman dalam 30 hari terakhir.</p>
<?php endif; ?>
</div>
</section>

<section class="section" id="koleksi">

<div class="container">

<div class="section-title">

<h2>
Koleksi Buku
</h2>

<p class="text-white">Buku Terbaru Perpustakaan</p>


</div>

<div class="row g-4">

<?php while($d = $buku->fetch_assoc()){ ?>

<div class="col-md-3">

<div class="book-card">

<img src="../gambar/<?= $d['gambar'] ?>"
class="book-img">

<div class="book-content">

<div class="book-title">
<?= $d['nama_buku'] ?>
</div>

<div class="book-author">
<?= $d['penulis'] ?>
</div>

<div class="mt-3">
<span class="badge bg-success">
<?= $d['kategori'] ?>
</span>
</div>

<div class="book-meta">
👁️ <?= $d['view_buku'] ?> dilihat
</div>

<div class="book-meta">
📖 Dipinjam <b><?= $d['jumlah_dipinjam'] ?></b> kali
</div>

<div class="mt-3 d-flex gap-2 flex-wrap">

<span class="badge bg-primary">
<?= $d['tahun_terbit'] ?? '-' ?>
</span>

<?php if($d['stok'] > 0){ ?>
<span class="badge bg-success">Tersedia</span>
<?php } else { ?>
<span class="badge bg-danger">Habis</span>
<?php } ?>

</div>

<div class="mt-4 d-grid gap-2">

<a href="detail_buku.php?id=<?= $d['id'] ?>"
class="btn btn-primary rounded-pill">
<i class="bi bi-eye-fill"></i>
 Detail Buku
</a>

<a href="pinjam.php?id=<?= $d['id'] ?>"
class="btn btn-success rounded-pill">
<i class="bi bi-book"></i>
 Pinjam Buku
</a>

<?php if($isLogin){ ?>

<a href="simpan_buku.php?id=<?= $d['id'] ?>"
class="btn btn-outline-warning rounded-pill">
<i class="bi bi-bookmark-heart-fill"></i>
 Simpan Buku
</a>

<?php } ?>

</div>

</div>

</div>

</div>

<?php } ?>

</div>

</div>

</section>

<div class="row justify-content-center g-4">

    <div class="col-lg-3 col-md-4 col-6">

        <div class="stat-box">

            <div class="stat-icon">
                <i class="bi bi-book-half"></i>
            </div>

            <h2>500+</h2>

            <p>Koleksi Buku</p>

        </div>

    </div>

    <div class="col-lg-3 col-md-4 col-6">

        <div class="stat-box">

            <div class="stat-icon">
                <i class="bi bi-people-fill"></i>
            </div>

            <h2>1000+</h2>

            <p>Pengguna Aktif</p>

        </div>

    </div>

    <div class="col-lg-3 col-md-4 col-6">

        <div class="stat-box">

            <div class="stat-icon">
                <i class="bi bi-star-fill"></i>
            </div>

            <h2>95%</h2>

            <p>Tingkat Kepuasan</p>

        </div>

    </div>

</div><!-- FOOTER -->
<footer>

<div class="container">

<div class="row gy-4">

<!-- Logo -->
<div class="col-lg-4">

<h4 class="footer-logo">
<img src="../assets/img/bg1.png" alt="Logo Sekolah" width="30" height="30" class="d-inline-block align-top me-2">
Smart Library
</h4>

<p class="footer-desc">
Sistem rekomendasi buku berbasis metode AHP dan SAW untuk membantu siswa menemukan buku terbaik.
</p>

</div>

<!-- Menu -->
<div class="col-lg-4">

<h5>Menu</h5>

<ul class="footer-links">

<li><a href="#rekomendasi">Rekomendasi Buku</a></li>

<li><a href="#koleksi">Koleksi Buku</a></li>

<li><a href="#layanan">Layanan</a></li>

</ul>

</div>

<!-- Kontak -->
<div class="col-lg-4">

<h5>Kontak</h5>

<p>
<i class="bi bi-geo-alt-fill"></i>
SMP Negeri 4 Solear
</p>

<p>
<i class="bi bi-envelope-fill"></i>
smartlibrary@gmail.com
</p>

</div>

</div>

<hr>

<div class="footer-copy">
    © 2026 Smart Library · All rights reserved
</div>

</div>

</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>