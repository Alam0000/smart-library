<?php

session_start();
require_once __DIR__ . '/../../lib/auth.php';
wajibPetugas('../../auth/login.php');


if(!isset($_SESSION['login'])){
    header("location:../../auth/login.php");
    exit;
}

include '../../connection/koneksi.php';

$k1 = $_POST['k1'] ?? [];
$k2 = $_POST['k2'] ?? [];
$nilai = $_POST['nilai'] ?? [];

if(count($nilai) == 0 || count($k1) != count($nilai) || count($k2) != count($nilai)){
    die("Data perbandingan tidak lengkap. <a href='input.php'>Kembali</a>");
}

$koneksi->query("TRUNCATE perbandingan_ahp");
catatAudit($koneksi,'simpan_perbandingan_ahp','perbandingan_ahp',null,'Matriks perbandingan AHP disimpan');

$stmt = $koneksi->prepare("
    INSERT INTO perbandingan_ahp (kriteria1, kriteria2, nilai)
    VALUES (?,?,?)
");

for($i=0; $i<count($nilai); $i++){

    $a = (int)$k1[$i];
    $b = (int)$k2[$i];
    $v = (float)$nilai[$i];

    if($v < (1/9) - 0.0001 || $v > 9){
        continue; // di luar skala Saaty
    }

    $stmt->bind_param("iid", $a, $b, $v);
    $stmt->execute();
}

echo "
<script>
alert('Data berhasil disimpan');
window.location='hitung_lengkap.php';
</script>
";
