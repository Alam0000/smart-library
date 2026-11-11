<?php
session_start();
require_once __DIR__ . '/../lib/auth.php';
wajibPetugas('../auth/login.php');
require_once __DIR__ . '/../connection/koneksi.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

$pesan = ''; $galat = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Token tidak valid.'); }
    $idT    = (int)($_POST['id'] ?? 0);
    $status = (string)($_POST['status'] ?? '');
    $alasan = trim((string)($_POST['alasan'] ?? ''));
    $st = $koneksi->prepare("SELECT id, role FROM users WHERE id=?");
    $st->bind_param('i', $idT);
    $st->execute();
    $u = $st->get_result()->fetch_assoc();
    if (!in_array($status, ['aktif', 'diblokir', 'suspend'], true)) $galat = 'Status tidak valid.';
    elseif (!$u || $u['role'] !== 'siswa') $galat = 'Hanya akun siswa yang dapat diubah.';
    elseif ($status === 'aktif') {
        $cd = $koneksi->prepare("SELECT COUNT(*) n FROM peminjaman WHERE id_user=? AND denda > 0 AND status_bayar NOT IN ('lunas','-')");
        $cd->bind_param('i', $idT);
        $cd->execute();
        if ((int)$cd->get_result()->fetch_assoc()['n'] > 0) {
            $galat = 'Akun tidak dapat diaktifkan karena masih ada denda yang belum lunas.';
        } else {
            $q = $koneksi->prepare("UPDATE users SET status_akun='aktif', alasan_blokir=NULL, tgl_diblokir=NULL WHERE id=? AND role='siswa'");
            $q->bind_param('i', $idT);
            $q->execute();
            catatAudit($koneksi, 'aktifkan_akun', 'users', $idT, 'Akun siswa diaktifkan petugas, user ID ' . $idT);
            $pesan = 'Akun diaktifkan.';
        }
    } elseif ($alasan === '') {
        $galat = 'Alasan wajib diisi untuk memblokir atau menangguhkan akun.';
    } else {
        $q = $koneksi->prepare("UPDATE users SET status_akun=?, alasan_blokir=?, tgl_diblokir=NOW() WHERE id=? AND role='siswa'");
        $q->bind_param('ssi', $status, $alasan, $idT);
        $q->execute();
        catatAudit($koneksi, $status . '_akun', 'users', $idT, 'Status akun menjadi ' . $status . ': ' . $alasan);
        $pesan = 'Status akun diperbarui.';
    }
}

function baris(mysqli $db, string $sql, string $tipe = '', array $par = []): array {
    $o = [];
    try {
        $st = $db->prepare($sql);
        if ($tipe !== '') $st->bind_param($tipe, ...$par);
        $st->execute();
        $r = $st->get_result();
        while ($x = $r->fetch_assoc()) $o[] = $x;
    } catch (Throwable $e) {}
    return $o;
}

$detailId = (int)($_GET['id'] ?? 0);
$detail = null;
if ($detailId > 0) {
    $d = baris($koneksi, "SELECT * FROM users WHERE id=? AND role='siswa'", 'i', [$detailId]);
    $detail = $d[0] ?? null;
}

if ($detail) {
    $histori = baris($koneksi, "SELECT p.*, b.nama_buku FROM peminjaman p JOIN buku b ON b.id=p.id_buku WHERE p.id_user=? ORDER BY p.id DESC LIMIT 50", 'i', [$detailId]);
    $favorit = baris($koneksi, "SELECT b.kategori label, COUNT(*) total FROM peminjaman p JOIN buku b ON b.id=p.id_buku WHERE p.id_user=? AND p.status NOT IN ('menunggu','ditolak') GROUP BY b.kategori ORDER BY total DESC LIMIT 5", 'i', [$detailId]);
    $cari    = baris($koneksi, "SELECT kata_kunci, MAX(created_at) t FROM riwayat_pencarian WHERE id_user=? GROUP BY kata_kunci ORDER BY t DESC LIMIT 8", 'i', [$detailId]);
    $pref    = baris($koneksi, "SELECT kategori FROM preferensi_kategori WHERE id_user=?", 'i', [$detailId]);
} else {
    $q = trim((string)($_GET['q'] ?? ''));
    $like = '%' . $q . '%';
    $daftar = baris($koneksi, "SELECT u.id, u.nama_lengkap, u.username, u.status_akun, u.created_at,
        (SELECT COUNT(*) FROM peminjaman p WHERE p.id_user=u.id AND p.status NOT IN ('menunggu','ditolak')) total_pinjam,
        (SELECT COUNT(*) FROM peminjaman p WHERE p.id_user=u.id AND p.status IN ('dipinjam','terlambat')) aktif,
        (SELECT COALESCE(SUM(p.denda),0) FROM peminjaman p WHERE p.id_user=u.id AND p.denda > 0 AND p.status_bayar NOT IN ('lunas','-')) denda
        FROM users u WHERE u.role='siswa' AND (u.nama_lengkap LIKE ? OR u.username LIKE ?) ORDER BY u.nama_lengkap", 'ss', [$like, $like]);
}
$warnaStatus = ['aktif' => 'success', 'diblokir' => 'danger', 'suspend' => 'warning'];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kelola Pengguna - Smart Library</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
body{background:#f3f6fb;color:#1e293b;font-family:Arial,sans-serif}
.main{margin-left:260px;padding:28px}
.card-x{border:0;border-radius:16px;box-shadow:0 6px 22px #0f172a0c}
.table-wrap{overflow:auto}
@media(max-width:768px){.main{margin-left:0;padding:18px}}
</style>
</head>
<body>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main class="main">
<?php if ($pesan): ?><div class="alert alert-success"><?= e($pesan) ?></div><?php endif; ?>
<?php if ($galat): ?><div class="alert alert-danger"><?= e($galat) ?></div><?php endif; ?>

<?php if ($detail): ?>
  <a href="pengguna.php" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left"></i> Kembali</a>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card card-x"><div class="card-body">
        <h4 class="fw-bold mb-0"><?= e($detail['nama_lengkap']) ?></h4>
        <div class="text-secondary mb-2">@<?= e($detail['username']) ?></div>
        <span class="badge bg-<?= $warnaStatus[$detail['status_akun']] ?? 'secondary' ?>"><?= e($detail['status_akun']) ?></span>
        <?php if (!empty($detail['alasan_blokir'])): ?><p class="small text-secondary mt-2 mb-0">Alasan: <?= e($detail['alasan_blokir']) ?></p><?php endif; ?>
        <hr>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
          <input type="hidden" name="id" value="<?= (int)$detail['id'] ?>">
          <label class="form-label small">Ubah status akun</label>
          <select name="status" class="form-select mb-2">
            <?php foreach (['aktif', 'suspend', 'diblokir'] as $s): ?><option value="<?= $s ?>" <?= $detail['status_akun'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
          </select>
          <input name="alasan" class="form-control mb-2" maxlength="255" placeholder="Alasan (wajib untuk suspend/blokir)">
          <button class="btn btn-primary btn-sm">Simpan</button>
        </form>
      </div></div>
      <div class="card card-x mt-4"><div class="card-body">
        <h6 class="fw-bold">Pola minat baca</h6>
        <div class="small text-secondary mb-1">Kategori sering dipinjam</div>
        <?php if (!$favorit): ?><p class="small mb-2">Belum ada peminjaman.</p><?php endif; ?>
        <?php foreach ($favorit as $f): ?><div class="d-flex justify-content-between small"><span><?= e($f['label']) ?></span><b><?= e($f['total']) ?>x</b></div><?php endforeach; ?>
        <div class="small text-secondary mt-3 mb-1">Preferensi kategori</div>
        <p class="small mb-2"><?= $pref ? e(implode(', ', array_column($pref, 'kategori'))) : '-' ?></p>
        <div class="small text-secondary mb-1">Pencarian terakhir</div>
        <p class="small mb-0"><?= $cari ? e(implode(', ', array_column($cari, 'kata_kunci'))) : '-' ?></p>
      </div></div>
    </div>
    <div class="col-lg-8">
      <div class="card card-x"><div class="card-body">
        <h5 class="fw-bold">Histori peminjaman</h5>
        <?php if (!$histori): ?><p class="text-secondary mb-0">Belum ada riwayat.</p><?php else: ?>
        <div class="table-wrap"><table class="table table-sm align-middle">
          <thead><tr><th>Buku</th><th>Pinjam</th><th>Jatuh tempo</th><th>Kembali</th><th>Status</th><th class="text-end">Denda</th></tr></thead>
          <tbody>
          <?php foreach ($histori as $h): ?>
            <tr><td><?= e($h['nama_buku']) ?></td><td><?= e($h['tanggal_pinjam'] ?? '-') ?></td><td><?= e($h['tanggal_kembali'] ?? '-') ?></td>
                <td><?= e($h['tgl_kembali_aktual'] ?? '-') ?></td><td><span class="badge bg-secondary"><?= e($h['status']) ?></span></td>
                <td class="text-end"><?= (int)$h['denda'] > 0 ? 'Rp ' . number_format((int)$h['denda'], 0, ',', '.') : '-' ?></td></tr>
          <?php endforeach; ?>
          </tbody></table></div>
        <?php endif; ?>
      </div></div>
    </div>
  </div>

<?php else: ?>
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div><h2 class="fw-bold mb-0">Kelola Pengguna</h2><p class="text-secondary mb-0">Data siswa, histori peminjaman, dan status akun.</p></div>
    <form class="d-flex gap-2" method="get"><input class="form-control" name="q" placeholder="Cari nama / username" value="<?= e($q) ?>"><button class="btn btn-primary">Cari</button></form>
  </div>
  <div class="card card-x"><div class="card-body">
    <div class="table-wrap"><table class="table align-middle mb-0">
      <thead><tr><th>Nama</th><th>Username</th><th class="text-end">Total pinjam</th><th class="text-end">Sedang dipinjam</th><th class="text-end">Denda belum lunas</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php if (!$daftar): ?><tr><td colspan="7" class="text-secondary">Tidak ada pengguna.</td></tr><?php endif; ?>
      <?php foreach ($daftar as $d): ?>
        <tr><td><?= e($d['nama_lengkap']) ?></td><td><?= e($d['username']) ?></td>
            <td class="text-end"><?= (int)$d['total_pinjam'] ?></td><td class="text-end"><?= (int)$d['aktif'] ?></td>
            <td class="text-end"><?= (int)$d['denda'] > 0 ? 'Rp ' . number_format((int)$d['denda'], 0, ',', '.') : '-' ?></td>
            <td><span class="badge bg-<?= $warnaStatus[$d['status_akun']] ?? 'secondary' ?>"><?= e($d['status_akun']) ?></span></td>
            <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="pengguna.php?id=<?= (int)$d['id'] ?>">Detail</a></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
  </div></div>
<?php endif; ?>
</main>
</body>
</html>
