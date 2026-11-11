<?php
session_start();
require_once __DIR__ . '/../lib/auth.php';
wajibPetugas('../auth/login.php');


if(!isset($_SESSION['login'])){
    header("location:../auth/login.php");
    exit;
}

include '../connection/koneksi.php';

if(!isset($_GET['id'])){
    header("location:kel_buku.php");
    exit;
}

$id = intval($_GET['id']);

try{

    $koneksi->begin_transaction();

    // hapus nilai buku
    $stmt = $koneksi->prepare("
    DELETE FROM nilai_buku
    WHERE id_buku=?
    ");
    $stmt->bind_param("i",$id);
    $stmt->execute();

    // hapus hasil saw
    $stmt = $koneksi->prepare("
    DELETE FROM hasil_saw
    WHERE id_buku=?
    ");
    $stmt->bind_param("i",$id);
    $stmt->execute();

    // hapus riwayat simpan buku
    $stmt = $koneksi->prepare("
    DELETE FROM simpan_buku
    WHERE id_buku=?
    ");
    $stmt->bind_param("i",$id);
    $stmt->execute();

    // hapus riwayat peminjaman
    $stmt = $koneksi->prepare("
    DELETE FROM peminjaman
    WHERE id_buku=?
    ");
    $stmt->bind_param("i",$id);
    $stmt->execute();

    // hapus buku
    $stmt = $koneksi->prepare("
    DELETE FROM buku
    WHERE id=?
    ");
    $stmt->bind_param("i",$id);
    $stmt->execute();

    $koneksi->commit();
catatAudit($koneksi,'hapus_buku','buku',$id,'Hapus buku ID '.$id);

    echo "
    <script>
    alert('Data buku berhasil dihapus');
    window.location='kel_buku.php';
    </script>
    ";

}catch(Exception $e){

    $koneksi->rollback();

    echo "
    <script>
    alert('Gagal menghapus data buku');
    window.location='kel_buku.php';
    </script>
    ";
}
?>