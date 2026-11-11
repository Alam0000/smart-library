<?php
// Form tambah/edit koleksi buku. Dipanggil oleh tambah.php dan edit.php.
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../lib/auth.php';
wajibPetugas('../auth/login.php');
require_once __DIR__ . '/../connection/koneksi.php';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$mode = $mode ?? (isset($_GET['id']) ? 'edit' : 'tambah');
$id   = (int)($_GET['id'] ?? 0);
$buku = ['kode_buku'=>'','nama_buku'=>'','penulis'=>'','penerbit'=>'','tahun_terbit'=>date('Y'),
         'kategori'=>'','stok'=>1,'bahasa'=>'Indonesia','jumlah_halaman'=>'','tingkat_kesulitan'=>3,
         'deskripsi'=>'','gambar'=>''];

if ($mode === 'edit') {
    $st = $koneksi->prepare("SELECT * FROM buku WHERE id=?");
    $st->bind_param('i', $id);
    $st->execute();
    $ada = $st->get_result()->fetch_assoc();
    if (!$ada) { http_response_code(404); exit('Buku tidak ditemukan.'); }
    $buku = $ada;
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Token tidak valid.'); }

    $f = [];
    foreach (['kode_buku','nama_buku','penulis','penerbit','kategori','bahasa','deskripsi'] as $k) {
        $f[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $f['tahun_terbit']      = (int)($_POST['tahun_terbit'] ?? 0);
    $f['stok']              = (int)($_POST['stok'] ?? 0);
    $f['jumlah_halaman']    = (($_POST['jumlah_halaman'] ?? '') === '') ? null : (int)$_POST['jumlah_halaman'];
    $f['tingkat_kesulitan'] = (int)($_POST['tingkat_kesulitan'] ?? 3);
    $f['gambar']            = $buku['gambar'] ?? '';

    if ($f['kode_buku'] === '' || $f['nama_buku'] === '') $error = 'Kode dan nama buku wajib diisi.';
    elseif ($f['tahun_terbit'] < 1900 || $f['tahun_terbit'] > (int)date('Y') + 1) $error = 'Tahun terbit tidak valid.';
    elseif ($f['stok'] < 0) $error = 'Stok tidak boleh negatif.';
    elseif ($f['tingkat_kesulitan'] < 1 || $f['tingkat_kesulitan'] > 5) $error = 'Tingkat kesulitan harus 1 sampai 5.';
    elseif ($f['jumlah_halaman'] !== null && $f['jumlah_halaman'] < 1) $error = 'Jumlah halaman tidak valid.';
    if ($f['bahasa'] === '') $f['bahasa'] = 'Indonesia';

    if (!$error && !empty($_FILES['gambar']['name'])) {
        $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
        if ($_FILES['gambar']['error'] !== UPLOAD_ERR_OK) $error = 'Upload gambar gagal.';
        elseif (!in_array($ext, ['jpg','jpeg','png','webp'], true)) $error = 'Format gambar harus JPG, PNG, atau WEBP.';
        elseif ($_FILES['gambar']['size'] > 2 * 1024 * 1024) $error = 'Ukuran gambar maksimal 2 MB.';
        elseif (@getimagesize($_FILES['gambar']['tmp_name']) === false) $error = 'File bukan gambar yang valid.';
        else {
            $nama = time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            if (move_uploaded_file($_FILES['gambar']['tmp_name'], __DIR__ . '/../gambar/' . $nama)) $f['gambar'] = $nama;
            else $error = 'Gagal menyimpan gambar.';
        }
    }

    if (!$error) {
        $cek = $koneksi->prepare("SELECT id FROM buku WHERE kode_buku=? AND id<>?");
        $cek->bind_param('si', $f['kode_buku'], $id);
        $cek->execute();
        if ($cek->get_result()->num_rows > 0) $error = 'Kode buku sudah dipakai buku lain.';
    }

    if (!$error) {
        if ($mode === 'edit') {
            $q = $koneksi->prepare("UPDATE buku SET kode_buku=?, nama_buku=?, penulis=?, penerbit=?, tahun_terbit=?, kategori=?, stok=?, gambar=?, deskripsi=?, bahasa=?, jumlah_halaman=?, tingkat_kesulitan=? WHERE id=?");
            $q->bind_param('ssssisisssiii', $f['kode_buku'], $f['nama_buku'], $f['penulis'], $f['penerbit'], $f['tahun_terbit'], $f['kategori'], $f['stok'], $f['gambar'], $f['deskripsi'], $f['bahasa'], $f['jumlah_halaman'], $f['tingkat_kesulitan'], $id);
            $q->execute();
            catatAudit($koneksi, 'ubah_buku', 'buku', $id, 'Ubah buku ' . $f['kode_buku'] . ' - ' . $f['nama_buku']);
        } else {
            $q = $koneksi->prepare("INSERT INTO buku (kode_buku, nama_buku, penulis, penerbit, tahun_terbit, kategori, stok, gambar, deskripsi, bahasa, jumlah_halaman, tingkat_kesulitan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $q->bind_param('ssssisisssii', $f['kode_buku'], $f['nama_buku'], $f['penulis'], $f['penerbit'], $f['tahun_terbit'], $f['kategori'], $f['stok'], $f['gambar'], $f['deskripsi'], $f['bahasa'], $f['jumlah_halaman'], $f['tingkat_kesulitan']);
            $q->execute();
            catatAudit($koneksi, 'tambah_buku', 'buku', $q->insert_id, 'Tambah buku ' . $f['kode_buku'] . ' - ' . $f['nama_buku']);
        }
        header('Location: kel_buku.php');
        exit;
    }
    $buku = array_merge($buku, $f);   // tampilkan kembali isian jika gagal
}

$kategoriAda = [];
$qk = $koneksi->query("SELECT DISTINCT kategori FROM buku WHERE kategori IS NOT NULL AND kategori <> '' ORDER BY kategori");
while ($qk && $r = $qk->fetch_assoc()) $kategoriAda[] = $r['kategori'];
$judul = $mode === 'edit' ? 'Edit Buku' : 'Tambah Buku';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($judul) ?> - Smart Library</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/style.css">
<style>
body{background:#f3f6fb;color:#1e293b;font-family:Arial,sans-serif}
.main-content{margin-left:280px;padding:30px}
.panel{background:#fff;border-radius:16px;box-shadow:0 6px 22px #0f172a0c;padding:28px}
@media(max-width:768px){.main-content{margin-left:0;padding:15px}}
</style>
</head>
<body>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h2 class="fw-bold mb-0"><i class="bi bi-book"></i> <?= h($judul) ?></h2>
    <a href="kel_buku.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
  </div>
  <div class="panel">
    <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Kode buku *</label><input name="kode_buku" class="form-control" required maxlength="20" value="<?= h($buku['kode_buku']) ?>"></div>
        <div class="col-md-9"><label class="form-label">Judul buku *</label><input name="nama_buku" class="form-control" required maxlength="200" value="<?= h($buku['nama_buku']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Penulis</label><input name="penulis" class="form-control" maxlength="100" value="<?= h($buku['penulis']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Penerbit</label><input name="penerbit" class="form-control" maxlength="100" value="<?= h($buku['penerbit']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Kategori</label>
          <input name="kategori" list="daftarKategori" class="form-control" maxlength="100" value="<?= h($buku['kategori']) ?>">
          <datalist id="daftarKategori"><?php foreach ($kategoriAda as $k): ?><option value="<?= h($k) ?>"><?php endforeach; ?></datalist></div>
        <div class="col-md-2"><label class="form-label">Tahun terbit</label><input type="number" name="tahun_terbit" class="form-control" min="1900" max="<?= (int)date('Y') + 1 ?>" value="<?= h($buku['tahun_terbit']) ?>"></div>
        <div class="col-md-2"><label class="form-label">Stok</label><input type="number" name="stok" class="form-control" min="0" value="<?= h($buku['stok']) ?>"></div>
        <div class="col-md-2"><label class="form-label">Jumlah halaman</label><input type="number" name="jumlah_halaman" class="form-control" min="1" value="<?= h($buku['jumlah_halaman']) ?>"></div>
        <div class="col-md-2"><label class="form-label">Bahasa</label><input name="bahasa" class="form-control" maxlength="30" value="<?= h($buku['bahasa']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Tingkat kesulitan</label>
          <select name="tingkat_kesulitan" class="form-select">
            <?php foreach ([1=>'1 - Sangat mudah',2=>'2 - Mudah',3=>'3 - Sedang',4=>'4 - Sulit',5=>'5 - Sangat sulit'] as $v => $t): ?>
              <option value="<?= $v ?>" <?= (int)$buku['tingkat_kesulitan'] === $v ? 'selected' : '' ?>><?= h($t) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="col-md-8"><label class="form-label">Cover (JPG/PNG/WEBP, maks. 2 MB)</label><input type="file" name="gambar" class="form-control" accept=".jpg,.jpeg,.png,.webp">
          <?php if (!empty($buku['gambar'])): ?><div class="form-text">Cover saat ini: <?= h($buku['gambar']) ?>. Kosongkan jika tidak ingin mengganti.</div><?php endif; ?></div>
        <div class="col-12"><label class="form-label">Deskripsi</label><textarea name="deskripsi" rows="4" class="form-control"><?= h($buku['deskripsi']) ?></textarea></div>
      </div>
      <div class="mt-4"><button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button></div>
    </form>
  </div>
</div>
</body>
</html>
