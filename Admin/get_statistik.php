<?php
session_start();
require_once __DIR__ . '/../lib/auth.php';
wajibPetugas('../auth/login.php');

include '../connection/koneksi.php';

$buku = mysqli_num_rows(mysqli_query($koneksi,"SELECT * FROM buku"));

$siswa = mysqli_num_rows(mysqli_query($koneksi,"SELECT * FROM users WHERE role='siswa'"));

$kriteria = mysqli_num_rows(mysqli_query($koneksi,"SELECT * FROM kriteria"));

$ranking = mysqli_num_rows(mysqli_query($koneksi,"SELECT * FROM hasil_saw"));

$data = [
    'buku' => $buku,
    'siswa' => $siswa,
    'kriteria' => $kriteria,
    'ranking' => $ranking
];

header('Content-Type: application/json');

echo json_encode($data);
?>