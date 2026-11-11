<?php
session_start();
require_once __DIR__ . '/../connection/koneksi.php';
require_once __DIR__ . '/../lib/notifikasi.php';
if (empty($_SESSION['login'])) { header('Location: ../auth/login.php'); exit; }
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_SESSION['id_user'] ?? 0);
sinkronNotifikasi($koneksi, $id, true);

$daftar = [];
try {
    $st = $koneksi->prepare("SELECT id, jenis, judul, pesan, tautan, dibaca, created_at FROM notifikasi WHERE id_user=? ORDER BY created_at DESC, id DESC LIMIT 50");
    $st->bind_param('i', $id);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) $daftar[] = $r;
    $st->close();
    // tandai dibaca setelah daftar diambil (yang baru tetap tersorot pada tampilan ini)
    $up = $koneksi->prepare("UPDATE notifikasi SET dibaca=1 WHERE id_user=? AND dibaca=0");
    $up->bind_param('i', $id);
    $up->execute();
} catch (Throwable $e) {}

$gaya = [
    'terlambat'    => ['danger',  'bi-exclamation-triangle'],
    'pengingat'    => ['warning', 'bi-alarm'],
    'ketersediaan' => ['success', 'bi-check-circle'],
    'alternatif'   => ['info',    'bi-shuffle'],
    'rekomendasi'  => ['primary', 'bi-stars'],
];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Notifikasi - Smart Library</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
body{font-family:Arial,sans-serif;background:#f6f7fb;color:#1f2937}
.main{max-width:900px;margin:0 auto;padding:100px 20px 40px}
.item{border:0;border-radius:14px;box-shadow:0 4px 18px #1118270b;background:#fff}
.item.baru{border-left:5px solid #2563eb}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-white fixed-top shadow-sm"><div class="container">
  <a class="navbar-brand fw-bold" href="index.php">📚 Smart Library</a>
  <div><a class="btn btn-outline-primary btn-sm" href="rekomendasi.php">Rekomendasi</a>
       <a class="btn btn-outline-primary btn-sm" href="koleksi.php">Cari buku</a>
       <a class="btn btn-outline-secondary btn-sm" href="index.php">Beranda</a></div>
</div></nav>
<main class="main">
  <h2 class="fw-bold">Notifikasi</h2>
  <p class="text-secondary">Pengingat pengembalian, ketersediaan buku tersimpan, alternatif buku, dan rekomendasi untuk Anda.</p>
  <?php if (!$daftar): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle"></i> Belum ada notifikasi.</div>
  <?php endif; ?>
  <?php foreach ($daftar as $n): $g = $gaya[$n['jenis'] ?? ''] ?? ['secondary', 'bi-bell']; ?>
    <div class="item p-3 mb-3 d-flex gap-3 <?= empty($n['dibaca']) ? 'baru' : '' ?>">
      <i class="bi <?= h($g[1]) ?> fs-3 text-<?= h($g[0]) ?>"></i>
      <div class="flex-grow-1">
        <div class="d-flex justify-content-between flex-wrap">
          <strong><?= h($n['judul']) ?></strong>
          <span class="small text-secondary"><?= h(date('d-m-Y H:i', strtotime($n['created_at']))) ?></span>
        </div>
        <p class="mb-2"><?= h($n['pesan']) ?></p>
        <?php if (!empty($n['tautan'])): ?><a class="btn btn-outline-<?= h($g[0]) ?> btn-sm" href="<?= h($n['tautan']) ?>">Buka</a><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</main>
</body>
</html>
