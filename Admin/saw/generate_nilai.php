<?php

session_start();
require_once __DIR__ . '/../../lib/auth.php';
wajibPetugas('../../auth/login.php');


if(!isset($_SESSION['login'])){
    header("location:../../auth/login.php");
    exit;
}

include '../../connection/koneksi.php';
include '../../lib/saw_engine.php';

/*
| Mengisi ulang tabel nilai_buku (arsip nilai 1-5 tiap buku) memakai
| aturan di tabel skala_nilai. Tidak ada lagi aturan hardcode di file ini.
| Semua buku ikut, termasuk yang belum pernah dipinjam.
*/

$kriteria = muatKriteria($koneksi, false)  /* tanpa kriteria personal */;
[$nilaiBuku, $infoBuku] = muatDataBuku($koneksi, $kriteria);

$koneksi->query("TRUNCATE TABLE nilai_buku");
catatAudit($koneksi,'generate_nilai','nilai_buku',null,'Nilai buku digenerate ulang');

$stmt = $koneksi->prepare("
    INSERT INTO nilai_buku (id_buku, id_kriteria, nilai) VALUES (?,?,?)
");

foreach($nilaiBuku as $id_buku => $baris){
    foreach($baris as $id_kriteria => $nilai){
        $stmt->bind_param("iid", $id_buku, $id_kriteria, $nilai);
        $stmt->execute();
    }
}

$stmt->close();

echo "
<script>
alert('Nilai kriteria berhasil dibuat sesuai skala nilai');
window.location='hitung.php';
</script>
";
