<?php

session_start();
require_once __DIR__ . '/../lib/auth.php';
wajibPetugas('../auth/login.php');


if(!isset($_SESSION['login'])){
    header("location:../auth/login.php");
    exit;
}

include '../connection/koneksi.php';

$id = (int)$_GET['id'];

$stmt = $koneksi->prepare("
DELETE FROM kriteria
WHERE id=?
");

$stmt->bind_param("i",$id);
$stmt->execute();
catatAudit($koneksi,'hapus_kriteria','kriteria',$id,'Hapus kriteria ID '.$id);

header("location:kriteria.php");
exit;