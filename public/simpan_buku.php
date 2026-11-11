<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

include '../connection/koneksi.php';


/* =====================================================
   CEK LOGIN
===================================================== */

if(!isset($_SESSION['login'])){

    echo "
    <script>
        alert('Silakan login terlebih dahulu untuk menyimpan buku.');
        window.location='../auth/login.php';
    </script>
    ";

    exit;
}


/* =====================================================
   CEK SESSION ID USER
===================================================== */

if(!isset($_SESSION['id_user'])){

    echo "
    <script>
        alert('Data pengguna tidak ditemukan.');
        window.location='index.php';
    </script>
    ";

    exit;
}


$id_user = (int)$_SESSION['id_user'];


/* =====================================================
   CEK ID BUKU
===================================================== */

if(!isset($_GET['id'])){

    echo "
    <script>
        alert('ID buku tidak ditemukan.');
        window.location='index.php';
    </script>
    ";

    exit;
}


$id_buku = (int)$_GET['id'];


/* =====================================================
   CEK APAKAH BUKU ADA
===================================================== */

$cekBuku = $koneksi->prepare("
    SELECT id
    FROM buku
    WHERE id = ?
");

$cekBuku->bind_param(
    "i",
    $id_buku
);

$cekBuku->execute();

$resultBuku = $cekBuku->get_result();


if($resultBuku->num_rows == 0){

    echo "
    <script>
        alert('Buku tidak ditemukan.');
        window.location='index.php';
    </script>
    ";

    exit;
}


/* =====================================================
   CEK APAKAH BUKU SUDAH DISIMPAN
===================================================== */

$cekSimpan = $koneksi->prepare("
    SELECT id
    FROM simpan_buku
    WHERE id_user = ?
    AND id_buku = ?
");

$cekSimpan->bind_param(
    "ii",
    $id_user,
    $id_buku
);

$cekSimpan->execute();

$resultSimpan = $cekSimpan->get_result();


/* =====================================================
   JIKA SUDAH DISIMPAN
===================================================== */

if($resultSimpan->num_rows > 0){

    echo "
    <script>
        alert('Buku ini sudah ada di Buku Tersimpan.');
        window.location='buku_saya.php';
    </script>
    ";

    exit;
}


/* =====================================================
   SIMPAN BUKU KE DATABASE
===================================================== */

$stmt = $koneksi->prepare("
    INSERT INTO simpan_buku
    (
        id_user,
        id_buku
    )
    VALUES (?, ?)
");


$stmt->bind_param(
    "ii",
    $id_user,
    $id_buku
);


/* =====================================================
   CEK HASIL INSERT
===================================================== */

if($stmt->execute()){

    echo "
    <script>
        alert('❤️ Buku berhasil disimpan!');
        window.location='buku_saya.php';
    </script>
    ";

    exit;

}else{

    echo "
    <script>
        alert('Gagal menyimpan buku. Silakan coba lagi.');
        window.location='detail_buku.php?id=".$id_buku."';
    </script>
    ";

    exit;
}

?>