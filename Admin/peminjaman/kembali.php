<?php
session_start();
require_once __DIR__ . '/../../connection/koneksi.php';
require_once __DIR__ . '/../../lib/auth.php';
wajibPetugas('../../auth/login.php');

$id = (int)($_GET['id'] ?? 0);
$st = $koneksi->prepare("SELECT id, id_buku, status FROM peminjaman WHERE id=?");
$st->bind_param('i', $id);
$st->execute();
$data = $st->get_result()->fetch_assoc();

// hanya pinjaman yang masih berjalan yang boleh dikembalikan (mencegah stok bertambah dua kali)
if ($data && in_array(strtolower($data['status']), ['dipinjam', 'terlambat'], true)) {
    $koneksi->begin_transaction();
    try {
        $u = $koneksi->prepare("UPDATE peminjaman SET status='dikembalikan', tgl_kembali_aktual=CURDATE() WHERE id=?");
        $u->bind_param('i', $id);
        $u->execute();
        $b = $koneksi->prepare("UPDATE buku SET stok = stok + 1 WHERE id=?");
        $idBuku = (int)$data['id_buku'];
        $b->bind_param('i', $idBuku);
        $b->execute();
        $koneksi->commit();
        catatAudit($koneksi, 'kembalikan_buku', 'peminjaman', $id, 'Buku dikembalikan, peminjaman ID ' . $id);
    } catch (Throwable $e) {
        $koneksi->rollback();
    }
}
header("Location: index.php");
exit;
