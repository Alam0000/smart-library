<?php
session_start();
require_once __DIR__ . '/../connection/koneksi.php';
require_once __DIR__ . '/../lib/saw_engine.php';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
$st = $koneksi->prepare("SELECT * FROM buku WHERE id=?");
$st->bind_param('i', $id);
$st->execute();
$buku = $st->get_result()->fetch_assoc();
if (!$buku) {
    http_response_code(404);
    exit('Buku tidak ditemukan. <a href="koleksi.php">Kembali ke koleksi</a>');
}

// hitung tampilan sekali per sesi agar tidak digelembungkan oleh refresh
if (empty($_SESSION['dilihat'][$id])) {
    $_SESSION['dilihat'][$id] = true;
    $up = $koneksi->prepare("UPDATE buku SET view_buku = COALESCE(view_buku,0) + 1 WHERE id=?");
    $up->bind_param('i', $id);
    $up->execute();
}

$rt = $koneksi->prepare("SELECT AVG(rating) a, COUNT(*) n FROM rating_buku WHERE id_buku=?");
$rt->bind_param('i', $id);
$rt->execute();
$rating = $rt->get_result()->fetch_assoc();

$tersedia = (int)$buku['stok'] > 0;
$perkiraan = null;
if (!$tersedia) {
    $pk = $koneksi->prepare("SELECT MIN(tanggal_kembali) t FROM peminjaman WHERE id_buku=? AND status IN ('dipinjam','terlambat')");
    $pk->bind_param('i', $id);
    $pk->execute();
    $perkiraan = $pk->get_result()->fetch_assoc()['t'] ?? null;
}

// peringkat SAW + penjelasan + alternatif
$idUser = !empty($_SESSION['login']) ? (int)($_SESSION['id_user'] ?? 0) : 0;
$ini = null;
$alternatif = [];
$total = 0;
try {
    $semua = rekomendasi($koneksi, null, $idUser ?: null);
    $total = count($semua);
    foreach ($semua as $r) {
        if ((int)$r['id_buku'] === $id) { $ini = $r; continue; }
        if ((int)($r['buku']['stok'] ?? 0) > 0) $alternatif[] = $r;
    }
    usort($alternatif, function ($a, $b) use ($buku) {
        $sa = (($a['buku']['kategori'] ?? '') === $buku['kategori']) ? 1 : 0;
        $sb = (($b['buku']['kategori'] ?? '') === $buku['kategori']) ? 1 : 0;
        if ($sa !== $sb) return $sb <=> $sa;
        return $b['skor'] <=> $a['skor'];
    });
    $alternatif = array_slice($alternatif, 0, 4);
} catch (Throwable $e) {
    $ini = null; $alternatif = [];
}

$sulit = [1=>'Sangat mudah',2=>'Mudah',3=>'Sedang',4=>'Sulit',5=>'Sangat sulit'];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($buku['nama_buku']) ?> - Smart Library</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
body{background:#f3f6fb;color:#1e293b;font-family:Arial,sans-serif}
.panel{background:#fff;border-radius:16px;box-shadow:0 6px 22px #0f172a0c;padding:24px}
.cover{width:100%;max-height:360px;object-fit:cover;border-radius:12px;background:#e2e8f0}
</style>
</head>
<body>
<div class="container py-4">
  <a href="koleksi.php" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left"></i> Kembali ke koleksi</a>

  <div class="panel mb-4">
    <div class="row g-4">
      <div class="col-md-3">
        <?php if (!empty($buku['gambar'])): ?>
          <img class="cover" src="../gambar/<?= h(rawurlencode($buku['gambar'])) ?>" alt="Cover <?= h($buku['nama_buku']) ?>">
        <?php endif; ?>
      </div>
      <div class="col-md-9">
        <span class="badge bg-info"><?= h($buku['kategori']) ?></span>
        <h2 class="fw-bold mt-2"><?= h($buku['nama_buku']) ?></h2>
        <p class="text-secondary mb-2"><?= h($buku['penulis']) ?> &middot; <?= h($buku['penerbit']) ?> &middot; <?= h($buku['tahun_terbit']) ?></p>
        <div class="row g-2 mb-3 small">
          <div class="col-6 col-md-3"><b>Bahasa</b><br><?= h($buku['bahasa']) ?></div>
          <div class="col-6 col-md-3"><b>Halaman</b><br><?= $buku['jumlah_halaman'] ? h($buku['jumlah_halaman']) : '-' ?></div>
          <div class="col-6 col-md-3"><b>Tingkat kesulitan</b><br><?= h($sulit[(int)$buku['tingkat_kesulitan']] ?? '-') ?></div>
          <div class="col-6 col-md-3"><b>Rating</b><br><?= ((int)$rating['n'] > 0) ? number_format((float)$rating['a'], 1) . '/5 (' . (int)$rating['n'] . ' ulasan)' : 'Belum ada rating' ?></div>
          <div class="col-6 col-md-3"><b>Dipinjam</b><br><?= (int)$buku['jumlah_dipinjam'] ?>x</div>
          <div class="col-6 col-md-3"><b>Dilihat</b><br><?= (int)$buku['view_buku'] ?>x</div>
          <div class="col-6 col-md-3"><b>Ketersediaan</b><br>
            <?php if ($tersedia): ?><span class="badge bg-success">Tersedia (<?= (int)$buku['stok'] ?>)</span>
            <?php else: ?><span class="badge bg-danger">Sedang dipinjam</span><?php endif; ?></div>
        </div>
        <?php if (!$tersedia && $perkiraan): ?>
          <p class="text-secondary small">Perkiraan tersedia kembali sekitar <b><?= h(date('d-m-Y', strtotime($perkiraan))) ?></b>.</p>
        <?php endif; ?>
        <p><?= nl2br(h($buku['deskripsi'])) ?></p>
        <?php if ($tersedia): ?>
          <a class="btn btn-success" href="pinjam.php?id=<?= (int)$buku['id'] ?>"><i class="bi bi-journal-bookmark"></i> Pinjam buku</a>
        <?php else: ?>
          <button class="btn btn-secondary" disabled>Stok sedang habis</button>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($ini): ?>
  <div class="panel mb-4">
    <h5 class="fw-bold">Mengapa buku ini berada di peringkat <?= (int)$ini['ranking'] ?> dari <?= (int)$total ?>?</h5>
    <p class="mb-3"><?= h($ini['alasan']['kalimat']) ?></p>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Kriteria</th><th class="text-end">Bobot</th><th class="text-end">Nilai ternormalisasi</th><th class="text-end">Kontribusi skor</th></tr></thead>
        <tbody>
        <?php foreach ($ini['kontribusi'] as $c): ?>
          <tr><td><?= h($c['kode']) ?> &ndash; <?= h($c['nama']) ?></td>
              <td class="text-end"><?= number_format($c['bobot'] * 100, 1) ?>%</td>
              <td class="text-end"><?= number_format($c['normal'], 2) ?></td>
              <td class="text-end"><?= number_format($c['kontribusi'], 3) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th colspan="3" class="text-end">Skor akhir SAW</th><th class="text-end"><?= number_format($ini['skor'], 3) ?></th></tr></tfoot>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($alternatif): ?>
  <div class="panel">
    <h5 class="fw-bold"><?= $tersedia ? 'Buku serupa yang mungkin Anda suka' : 'Buku ini sedang dipinjam. Alternatif yang tersedia:' ?></h5>
    <div class="row g-3 mt-1">
      <?php foreach ($alternatif as $a): $b = $a['buku']; ?>
        <div class="col-md-6 col-lg-3">
          <div class="card h-100 border-0 shadow-sm"><div class="card-body">
            <div class="small text-secondary"><?= h($b['kategori'] ?? '') ?></div>
            <h6 class="fw-bold mt-1"><?= h($b['nama_buku'] ?? '') ?></h6>
            <div class="small text-secondary mb-2"><?= h($b['penulis'] ?? '') ?></div>
            <p class="small mb-2"><?= h($a['alasan']['kalimat']) ?></p>
            <a class="btn btn-outline-primary btn-sm" href="detail_buku.php?id=<?= (int)$a['id_buku'] ?>">Lihat detail</a>
          </div></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
