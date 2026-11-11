<?php
$koneksi = new mysqli("localhost", "root", "", "perpustakaan_db",3311);

if ($koneksi->connect_error) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}
?>