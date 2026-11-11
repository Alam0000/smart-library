<?php
/**
 * Menyimpan kategori favorit siswa (Personalized Recommendation).
 * POST: kategori[] = daftar kategori. Mengganti preferensi lama.
 * Balasan: JSON {"ok":true,"jumlah":N}
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['login']) || empty($_SESSION['id_user'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'pesan' => 'Silakan login terlebih dahulu.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'pesan' => 'Metode tidak diizinkan.']);
    exit;
}

include __DIR__ . '/../connection/koneksi.php';

$idUser = (int)$_SESSION['id_user'];
$pilihan = $_POST['kategori'] ?? [];
if (!is_array($pilihan)) $pilihan = [];

/* hanya kategori yang benar-benar ada di tabel buku yang diterima */
$sah = [];
$q = $koneksi->query("SELECT DISTINCT kategori FROM buku WHERE kategori IS NOT NULL AND kategori <> ''");
while ($r = $q->fetch_assoc()) $sah[strtolower($r['kategori'])] = $r['kategori'];

$bersih = [];
foreach ($pilihan as $p) {
    $k = strtolower(trim((string)$p));
    if (isset($sah[$k])) $bersih[$sah[$k]] = true;
}
$bersih = array_keys($bersih);

try {
    $koneksi->begin_transaction();

    $del = $koneksi->prepare("DELETE FROM preferensi_kategori WHERE id_user = ?");
    $del->bind_param('i', $idUser);
    $del->execute();

    $ins = $koneksi->prepare("INSERT INTO preferensi_kategori (id_user, kategori) VALUES (?, ?)");
    foreach ($bersih as $kat) {
        $ins->bind_param('is', $idUser, $kat);
        $ins->execute();
    }

    $koneksi->commit();
    echo json_encode(['ok' => true, 'jumlah' => count($bersih)]);
} catch (Throwable $e) {
    $koneksi->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'pesan' => 'Gagal menyimpan. Pastikan migrasi 4_personalisasi.sql sudah dijalankan.']);
}
