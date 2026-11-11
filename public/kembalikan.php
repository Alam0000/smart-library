<?php
session_start();
require_once __DIR__ . '/../connection/koneksi.php';
require_once __DIR__ . '/../lib/auth.php';
wajibLogin('../auth/login.php');

$idPinjam = (int)($_GET['id'] ?? 0);
$idUser   = (int)($_SESSION['id_user'] ?? 0);

// hanya pemilik pinjaman, dan hanya yang masih berjalan
$st = $koneksi->prepare("SELECT id, id_buku, status FROM peminjaman WHERE id=? AND id_user=?");
$st->bind_param('ii', $idPinjam, $idUser);
$st->execute();
$data = $st->get_result()->fetch_assoc();

if (!$data) {
    die("Data tidak ditemukan");
}

if (in_array(strtolower($data['status']), ['dipinjam', 'terlambat'], true)) {
    $koneksi->begin_transaction();
    try {
        $u = $koneksi->prepare("UPDATE peminjaman SET status='dikembalikan', tgl_kembali_aktual=CURDATE() WHERE id=?");
        $u->bind_param('i', $idPinjam);
        $u->execute();
        $b = $koneksi->prepare("UPDATE buku SET stok = stok + 1 WHERE id=?");
        $idBuku = (int)$data['id_buku'];
        $b->bind_param('i', $idBuku);
        $b->execute();
        $koneksi->commit();
        catatAudit($koneksi, 'kembalikan_buku', 'peminjaman', $idPinjam, 'Siswa mengembalikan buku');
    } catch (Throwable $e) {
        $koneksi->rollback();
        die("Gagal memproses pengembalian.");
    }
}
echo "
<script>
alert('Buku berhasil dikembalikan');
window.location='buku_saya.php';
</script>
";
