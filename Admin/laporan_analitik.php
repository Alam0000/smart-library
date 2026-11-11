<?php
session_start();
require_once __DIR__ . '/../connection/koneksi.php';
if (empty($_SESSION['login']) || ($_SESSION['role'] ?? '') !== 'petugas') {
    header('Location: ../auth/login.php'); exit;
}
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function scalarCount(mysqli $db, string $sql): int {
    try { $r=$db->query($sql); if($r){$x=$r->fetch_assoc(); return (int)($x['total']??0);} } catch(Throwable $e) {}
    return 0;
}
$totalBuku=scalarCount($koneksi,"SELECT COUNT(*) total FROM buku");
$totalUser=scalarCount($koneksi,"SELECT COUNT(*) total FROM users");
$totalPinjam=scalarCount($koneksi,"SELECT COUNT(*) total FROM peminjaman");
$aktif=scalarCount($koneksi,"SELECT COUNT(*) total FROM peminjaman WHERE status IN ('dipinjam','terlambat')");
$terlambat=scalarCount($koneksi,"SELECT COUNT(*) total FROM peminjaman WHERE status='terlambat' OR (status='dipinjam' AND tanggal_kembali<CURDATE())");
$popular=[];
try {
 $q=$koneksi->query("SELECT b.nama_buku, COALESCE(b.jumlah_dipinjam,0) jumlah FROM buku b ORDER BY jumlah DESC LIMIT 8");
 while($q && $r=$q->fetch_assoc()) $popular[]=$r;
} catch(Throwable $e) {}
$audit=[];
try {$q=$koneksi->query("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 20"); while($q && $r=$q->fetch_assoc())$audit[]=$r;} catch(Throwable $e){}
function queryRows(mysqli $db, string $sql): array { $o=[]; try{ $q=$db->query($sql); while($q && $r=$q->fetch_assoc()) $o[]=$r; }catch(Throwable $e){} return $o; }
function barList(array $rows): string {
    if(!$rows) return '<p class="text-secondary mb-0">Data belum tersedia.</p>';
    $max=1; foreach($rows as $r) $max=max($max,(int)$r['total']);
    $h='';
    foreach($rows as $r){ $p=(int)round(((int)$r['total']/$max)*100);
        $h.='<div class="mb-2"><div class="d-flex justify-content-between small"><span>'.e($r['label']).'</span><b>'.e($r['total']).'</b></div><div class="progress" style="height:8px"><div class="progress-bar" style="width:'.$p.'%"></div></div></div>';
    }
    return $h;
}
$polaKategori = queryRows($koneksi,"SELECT b.kategori label, COUNT(*) total FROM peminjaman p JOIN buku b ON b.id=p.id_buku WHERE p.status NOT IN ('ditolak','menunggu') GROUP BY b.kategori ORDER BY total DESC LIMIT 8");
$polaCari     = queryRows($koneksi,"SELECT kata_kunci label, COUNT(*) total FROM riwayat_pencarian GROUP BY kata_kunci ORDER BY total DESC LIMIT 8");
$polaPref     = queryRows($koneksi,"SELECT kategori label, COUNT(*) total FROM preferensi_kategori GROUP BY kategori ORDER BY total DESC LIMIT 8");
$sebaran      = queryRows($koneksi,"SELECT kategori label, COUNT(*) total FROM buku GROUP BY kategori ORDER BY total DESC LIMIT 10");
$tren         = queryRows($koneksi,"SELECT b.nama_buku label,
    SUM(COALESCE(p.tanggal_pinjam,p.tgl_pengajuan) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) sekarang,
    SUM(COALESCE(p.tanggal_pinjam,p.tgl_pengajuan) < DATE_SUB(CURDATE(), INTERVAL 30 DAY)) sebelumnya
    FROM peminjaman p JOIN buku b ON b.id=p.id_buku
    WHERE p.status NOT IN ('ditolak','menunggu') AND COALESCE(p.tanggal_pinjam,p.tgl_pengajuan) >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
    GROUP BY b.id, b.nama_buku
    HAVING SUM(COALESCE(p.tanggal_pinjam,p.tgl_pengajuan) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) > 0
    ORDER BY (SUM(COALESCE(p.tanggal_pinjam,p.tgl_pengajuan) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) - SUM(COALESCE(p.tanggal_pinjam,p.tgl_pengajuan) < DATE_SUB(CURDATE(), INTERVAL 30 DAY))) DESC LIMIT 8");
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Laporan Analitik - Smart Library</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"><style>body{background:#f3f6fb;color:#1e293b;font-family:Arial,sans-serif}.main{margin-left:260px;padding:28px}.metric{border:0;border-radius:16px;box-shadow:0 6px 22px #0f172a0c}.metric .num{font-size:2rem;font-weight:700}.table-wrap{overflow:auto}@media(max-width:768px){.main{margin-left:0;padding:18px}}</style></head><body>
<?php include __DIR__.'/partials/sidebar.php'; ?><main class="main"><div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2"><div><h2 class="fw-bold">Laporan & Analitik</h2><p class="text-secondary mb-0">Ringkasan koleksi, transaksi, keterlambatan, dan aktivitas pengelolaan.</p></div><a class="btn btn-primary" href="export_buku.php"><i class="bi bi-download"></i> Ekspor CSV buku</a></div>
<div class="row g-3 mb-4"><?php foreach([['Total koleksi',$totalBuku,'bi-book'],['Pengguna',$totalUser,'bi-people'],['Transaksi peminjaman',$totalPinjam,'bi-journal-bookmark'],['Sedang dipinjam',$aktif,'bi-clock'],['Terlambat',$terlambat,'bi-exclamation-triangle']] as $m): ?><div class="col-6 col-lg"><div class="card metric h-100"><div class="card-body"><div class="text-secondary small"><i class="bi <?=e($m[2])?>"></i> <?=e($m[0])?></div><div class="num"><?=e($m[1])?></div></div></div></div><?php endforeach; ?></div>
<div class="row g-4"><div class="col-lg-6"><div class="card metric"><div class="card-body"><h5>Buku paling banyak dipinjam</h5><?php if(!$popular): ?><p class="text-secondary">Data belum tersedia.</p><?php else: ?><div class="table-wrap"><table class="table align-middle"><thead><tr><th>Buku</th><th class="text-end">Jumlah peminjaman</th></tr></thead><tbody><?php foreach($popular as $p): ?><tr><td><?=e($p['nama_buku'])?></td><td class="text-end"><?=e($p['jumlah'])?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div></div>
<div class="col-lg-6"><div class="card metric"><div class="card-body"><h5>Audit trail terbaru</h5><?php if(!$audit): ?><p class="text-secondary">Belum ada catatan audit. Pastikan migrasi database dijalankan dan fungsi pencatatan digunakan pada aksi admin.</p><?php else: ?><div class="table-wrap"><table class="table table-sm"><thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Detail</th></tr></thead><tbody><?php foreach($audit as $a): ?><tr><td><?=e($a['created_at'])?></td><td><?=e($a['nama_pengguna']??'-')?></td><td><?=e($a['aksi'])?></td><td><?=e($a['detail']??'')?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div></div></div>
<div class="row g-4 mt-1">
<div class="col-lg-6"><div class="card metric"><div class="card-body"><h5>Pola minat baca: kategori paling banyak dipinjam</h5><?= barList($polaKategori) ?></div></div></div>
<div class="col-lg-6"><div class="card metric"><div class="card-body"><h5>Kata kunci pencarian terbanyak</h5><?= barList($polaCari) ?></div></div></div>
<div class="col-lg-6"><div class="card metric"><div class="card-body"><h5>Kategori yang dipilih sebagai preferensi pengguna</h5><?= barList($polaPref) ?></div></div></div>
<div class="col-lg-6"><div class="card metric"><div class="card-body"><h5>Sebaran koleksi per kategori (jumlah judul)</h5><?= barList($sebaran) ?></div></div></div>
<div class="col-12"><div class="card metric"><div class="card-body"><h5>Buku yang sedang tren (30 hari terakhir dibanding 30 hari sebelumnya)</h5>
<?php if(!$tren): ?><p class="text-secondary mb-0">Belum ada peminjaman dalam 30 hari terakhir.</p><?php else: ?>
<div class="table-wrap"><table class="table align-middle mb-0"><thead><tr><th>Buku</th><th class="text-end">30 hari terakhir</th><th class="text-end">30 hari sebelumnya</th><th class="text-end">Perubahan</th></tr></thead><tbody>
<?php foreach($tren as $t): $d=(int)$t['sekarang']-(int)$t['sebelumnya']; ?>
<tr><td><?=e($t['label'])?></td><td class="text-end"><?=e($t['sekarang'])?></td><td class="text-end"><?=e($t['sebelumnya'])?></td><td class="text-end"><span class="badge <?= $d>0?'bg-success':($d<0?'bg-danger':'bg-secondary') ?>"><?= $d>0?'+':'' ?><?= $d ?></span></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></div></div></div>
</div>
<p class="text-secondary small mt-4">Catatan: statistik diambil langsung dari database; pastikan kolom tanggal dan status pada tabel peminjaman sesuai dengan aplikasi yang digunakan.</p></main></body></html>