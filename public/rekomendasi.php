<?php
/**
 * Halaman Rekomendasi Buku (AHP + SAW)
 *  - rekomendasi personal (jika siswa punya histori) atau umum
 *  - Explainable Recommendation: alasan + kontribusi tiap kriteria
 *  - What-If Analysis: ubah prioritas, ranking dihitung ulang otomatis
 */
session_start();
require_once __DIR__ . '/../connection/koneksi.php';
require_once __DIR__ . '/../lib/saw_engine.php';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$idUser  = !empty($_SESSION['login']) ? (int)($_SESSION['id_user'] ?? 0) : 0;
$isLogin = $idUser > 0;

$profil   = $isLogin ? muatProfil($koneksi, $idUser) : null;
$personal = $profil && !empty($profil['ada']);

$kriteria = muatKriteria($koneksi, $personal);
$idAktif  = array_map('intval', array_column($kriteria, 'id'));

// bobot AHP tersimpan (dinormalisasi hanya untuk kriteria yang aktif)
$bobotDefault = normalisasiBobot(array_intersect_key(muatBobot($koneksi), array_flip($idAktif)));
if (!$bobotDefault) {
    foreach ($idAktif as $i) $bobotDefault[$i] = 1;
    $bobotDefault = normalisasiBobot($bobotDefault);
}

/* ---------- What-If: bobot dari slider atau preset ---------- */
$presetMap = [
    'rating'   => ['label' => 'Utamakan rating',            'sumber' => ['rating']],
    'terbaru'  => ['label' => 'Utamakan buku terbaru',      'sumber' => ['tahun_terbit']],
    'populer'  => ['label' => 'Utamakan popularitas',       'sumber' => ['jumlah_dipinjam', 'view_buku']],
    'kategori' => ['label' => 'Utamakan kesesuaian kategori','sumber' => ['minat_kategori', 'kategori', 'kemiripan_buku']],
];
$mode  = 'default';
$bobot = $bobotDefault;
$pesan = '';

if (isset($_GET['w']) && is_array($_GET['w'])) {
    $tmp = [];
    foreach ($idAktif as $i) {
        $v = (float)($_GET['w'][$i] ?? 0);
        $tmp[$i] = max(0, min(100, $v));
    }
    if (array_sum($tmp) > 0) { $bobot = normalisasiBobot($tmp); $mode = 'manual'; }
    else $pesan = 'Semua prioritas bernilai 0, bobot AHP bawaan dipakai.';
} elseif (isset($presetMap[$_GET['preset'] ?? ''])) {
    $p = $_GET['preset'];
    $tmp = $bobotDefault;
    $kena = false;
    foreach ($kriteria as $k) {
        if (in_array($k['sumber_data'], $presetMap[$p]['sumber'], true)) {
            $tmp[(int)$k['id']] = ($tmp[(int)$k['id']] ?? 0) * 4 + 0.05;
            $kena = true;
        }
    }
    if ($kena) { $bobot = normalisasiBobot($tmp); $mode = 'preset'; }
    else $pesan = 'Prioritas ini membutuhkan kriteria yang belum aktif (misalnya kriteria personal yang butuh histori baca).';
}

$hanyaTersedia = (($_GET['tersedia'] ?? '') === '1');

/* ---------- hitung ---------- */
$dasar = rekomendasi($koneksi, null, $idUser ?: null);
$rankDasar = [];
foreach ($dasar as $r) $rankDasar[$r['id_buku']] = $r['ranking'];
$hasil = ($mode === 'default') ? $dasar : rekomendasi($koneksi, $bobot, $idUser ?: null);

if ($hanyaTersedia) {
    $hasil = array_values(array_filter($hasil, fn($r) => (int)($r['buku']['stok'] ?? 0) > 0));
}
$tampil = array_slice($hasil, 0, 10);
$warna  = ['#2563eb','#16a34a','#f59e0b','#9333ea','#dc2626','#0891b2','#64748b','#db2777','#65a30d','#ea580c'];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rekomendasi Buku - Smart Library</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
body{background:#f3f6fb;color:#1e293b;font-family:Arial,sans-serif}
.panel{background:#fff;border-radius:16px;box-shadow:0 6px 22px #0f172a0c;padding:22px}
.cover{width:72px;height:96px;object-fit:cover;border-radius:8px;background:#e2e8f0}
.rank{font-size:1.6rem;font-weight:700;color:#2563eb;min-width:44px;text-align:center}
.stack{display:flex;height:10px;border-radius:6px;overflow:hidden;background:#e2e8f0}
.stack span{display:block;height:100%}
.legend span{display:inline-flex;align-items:center;gap:5px;margin-right:12px;font-size:12px}
.legend i{width:10px;height:10px;border-radius:3px;display:inline-block}
.eff{color:#9333ea;font-weight:700}
</style>
</head>
<body>
<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-stars"></i> Rekomendasi Buku</h2>
      <p class="text-secondary mb-0">
        <?php if ($personal): ?>Rekomendasi personal berdasarkan riwayat pinjam, pencarian, rating, dan preferensi kategori Anda.
        <?php elseif ($isLogin): ?>Anda belum memiliki riwayat baca yang cukup, jadi yang ditampilkan adalah rekomendasi umum.
        <?php else: ?>Rekomendasi umum. <a href="../auth/login.php">Masuk</a> untuk mendapat rekomendasi personal.<?php endif; ?>
      </p>
    </div>
    <div class="d-flex gap-2">
      <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-house"></i> Beranda</a>
      <a href="koleksi.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-collection"></i> Koleksi</a>
    </div>
  </div>

  <!-- WHAT-IF -->
  <div class="panel mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h5 class="fw-bold mb-0"><i class="bi bi-sliders"></i> What-If Analysis</h5>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($presetMap as $key => $pm): ?>
          <a class="btn btn-sm <?= ($mode === 'preset' && ($_GET['preset'] ?? '') === $key) ? 'btn-primary' : 'btn-outline-primary' ?>"
             href="?preset=<?= h($key) ?><?= $hanyaTersedia ? '&tersedia=1' : '' ?>"><?= h($pm['label']) ?></a>
        <?php endforeach; ?>
        <a class="btn btn-sm btn-outline-secondary" href="rekomendasi.php">Bobot AHP bawaan</a>
      </div>
    </div>
    <p class="text-secondary small mt-2 mb-3">Geser prioritas tiap kriteria lalu tekan <b>Hitung ulang</b>. Nilai dinormalisasi otomatis menjadi bobot (total 100%). Bobot bawaan berasal dari perhitungan AHP.</p>
    <?php if ($pesan): ?><div class="alert alert-warning py-2"><?= h($pesan) ?></div><?php endif; ?>

    <form method="get">
      <div class="row g-3">
        <?php foreach ($kriteria as $k): $i = (int)$k['id']; $pct = round(($bobot[$i] ?? 0) * 100, 1); ?>
          <div class="col-md-6 col-lg-4">
            <div class="d-flex justify-content-between small fw-semibold">
              <span><?= h($k['kode_kriteria']) ?> &ndash; <?= h($k['nama_kriteria']) ?></span>
              <span class="eff"><?= number_format($pct, 1) ?>%</span>
            </div>
            <input type="range" class="form-range" name="w[<?= $i ?>]" min="0" max="100" step="1" value="<?= (int)round($pct) ?>">
            <div class="small text-secondary">Bobot AHP bawaan: <?= number_format(($bobotDefault[$i] ?? 0) * 100, 1) ?>%</div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="d-flex align-items-center gap-3 mt-3 flex-wrap">
        <button class="btn btn-primary"><i class="bi bi-arrow-repeat"></i> Hitung ulang</button>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="tersedia" value="1" id="cbT" <?= $hanyaTersedia ? 'checked' : '' ?>>
          <label class="form-check-label" for="cbT">Hanya yang tersedia</label>
        </div>
        <?php if ($mode !== 'default'): ?><span class="badge bg-purple text-bg-light border">Mode simulasi aktif</span><?php endif; ?>
      </div>
    </form>
  </div>

  <!-- HASIL -->
  <div class="panel">
    <h5 class="fw-bold mb-1">10 buku teratas</h5>
    <div class="legend mb-3 mt-2">
      <?php foreach ($kriteria as $n => $k): ?><span><i style="background:<?= $warna[$n % count($warna)] ?>"></i><?= h($k['nama_kriteria']) ?></span><?php endforeach; ?>
    </div>

    <?php if (!$tampil): ?>
      <p class="text-secondary mb-0">Belum ada buku yang cocok.</p>
    <?php endif; ?>

    <?php foreach ($tampil as $pos => $r): $b = $r['buku']; $selisih = ($mode !== 'default') ? (($rankDasar[$r['id_buku']] ?? 0) - $r['ranking']) : 0; ?>
      <div class="d-flex gap-3 py-3 <?= $pos ? 'border-top' : '' ?>">
        <div class="rank">#<?= $pos + 1 ?></div>
        <?php if (!empty($b['gambar'])): ?><img class="cover" src="../gambar/<?= h(rawurlencode($b['gambar'])) ?>" alt=""><?php endif; ?>
        <div class="flex-grow-1">
          <div class="d-flex justify-content-between flex-wrap gap-1">
            <div>
              <a class="fw-bold text-decoration-none" href="detail_buku.php?id=<?= (int)$r['id_buku'] ?>"><?= h($b['nama_buku'] ?? '') ?></a>
              <div class="small text-secondary"><?= h($b['penulis'] ?? '') ?> &middot; <?= h($b['kategori'] ?? '') ?> &middot; <?= h($b['tahun_terbit'] ?? '') ?></div>
            </div>
            <div class="text-end">
              <div class="fw-bold">Skor <?= number_format($r['skor'], 3) ?></div>
              <?php if ($selisih > 0): ?><span class="badge bg-success">naik <?= $selisih ?> posisi</span>
              <?php elseif ($selisih < 0): ?><span class="badge bg-danger">turun <?= abs($selisih) ?> posisi</span><?php endif; ?>
            </div>
          </div>
          <p class="small mb-2 mt-1"><?= h($r['alasan']['kalimat']) ?></p>
          <div class="stack mb-1">
            <?php $n = 0; foreach ($kriteria as $k): $c = $r['kontribusi'][(int)$k['id']] ?? null;
                  $lebar = ($c && $r['skor'] > 0) ? ($c['kontribusi'] / $r['skor']) * 100 : 0; ?>
              <span title="<?= h($k['nama_kriteria']) ?>: <?= $c ? number_format($c['kontribusi'], 3) : '0' ?>" style="width:<?= number_format($lebar, 2, '.', '') ?>%;background:<?= $warna[$n % count($warna)] ?>"></span>
            <?php $n++; endforeach; ?>
          </div>
          <div class="small">
            <?php if ((int)($b['stok'] ?? 0) > 0): ?><span class="badge bg-success">Tersedia</span><?php else: ?><span class="badge bg-danger">Sedang dipinjam</span><?php endif; ?>
            <?php if (!empty($b['sudah_dipinjam'])): ?><span class="badge bg-secondary">Pernah Anda pinjam</span><?php endif; ?>
            <a class="ms-2" href="detail_buku.php?id=<?= (int)$r['id_buku'] ?>">Lihat rincian kontribusi</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
