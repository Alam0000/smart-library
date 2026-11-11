<?php
session_start();
require_once __DIR__ . '/../lib/audit.php';

if(!isset($_SESSION['login'])){
    header("location:../auth/login.php");
    exit;
}
if(($_SESSION['role'] ?? '') !== 'petugas'){
    die("Akses ditolak: hanya petugas.");
}

include '../connection/koneksi.php';

/* ---------- aksi ---------- */
$idKr = (int)($_GET['id'] ?? 0);

if(isset($_POST['tambah'])){
    $idKr  = (int)$_POST['id_kriteria'];
    $jenis = $_POST['jenis'] === 'teks' ? 'teks' : 'rentang';
    $nilai = max(1, min(5, (float)$_POST['nilai']));

    if($jenis === 'rentang'){
        $batas = (float)$_POST['batas_min'];
        $stmt = $koneksi->prepare("INSERT INTO skala_nilai (id_kriteria,jenis,batas_min,nilai) VALUES (?,?,?,?)");
        $stmt->bind_param("isdd", $idKr, $jenis, $batas, $nilai);
    }else{
        $kata = trim($_POST['kata_kunci']);
        $stmt = $koneksi->prepare("INSERT INTO skala_nilai (id_kriteria,jenis,kata_kunci,nilai) VALUES (?,?,?,?)");
        $stmt->bind_param("issd", $idKr, $jenis, $kata, $nilai);
    }
    $stmt->execute();
catatAudit($koneksi,'ubah_skala_nilai','skala_nilai',null,'Perubahan skala nilai kriteria');
    header("location:skala_nilai.php?id=$idKr");
    exit;
}

if(isset($_GET['hapus'])){
    $h = (int)$_GET['hapus'];
    $stmt = $koneksi->prepare("DELETE FROM skala_nilai WHERE id=?");
    $stmt->bind_param("i", $h);
    $stmt->execute();
catatAudit($koneksi,'ubah_skala_nilai','skala_nilai',null,'Perubahan skala nilai kriteria');
    header("location:skala_nilai.php?id=$idKr");
    exit;
}

/* ---------- data ---------- */
$kriteria = [];
$q = $koneksi->query("SELECT * FROM kriteria ORDER BY id");
while($r = $q->fetch_assoc()){ $kriteria[$r['id']] = $r; }

if(!$idKr || !isset($kriteria[$idKr])){
    $idKr = $kriteria ? (int)array_key_first($kriteria) : 0;
}

$aturan = [];
if($idKr){
    $stmt = $koneksi->prepare("SELECT * FROM skala_nilai WHERE id_kriteria=? ORDER BY jenis, batas_min, kata_kunci");
    $stmt->bind_param("i", $idKr);
    $stmt->execute();
catatAudit($koneksi,'ubah_skala_nilai','skala_nilai',null,'Perubahan skala nilai kriteria');
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()){ $aturan[] = $r; }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Skala Nilai Kriteria</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/style.css">
<style>
body{ margin:0; background:#0f172a; font-family:'Poppins','Segoe UI',sans-serif; }
.main-content{ margin-left:280px; padding:30px; min-height:100vh; }
.topbar{ background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.08);
         padding:20px 30px; border-radius:20px; margin-bottom:25px; }
.page-title{ color:white; font-weight:700; margin-bottom:5px; }
.card-custom{ background:white; border:none; border-radius:20px; box-shadow:0 5px 15px rgba(0,0,0,0.15); }
.table th{ background:#0d6efd; color:white; padding:12px; }
.table td{ padding:12px; vertical-align:middle; color:#334155; }
.btn-custom{ border-radius:10px; padding:10px 18px; }
@media(max-width:768px){ .main-content{ margin-left:0; padding:15px; } }
</style>
</head>
<body>

<?php include 'partials/sidebar.php'; ?>

<div class="main-content">

    <div class="topbar">
        <h2 class="page-title">🎚️ Skala Nilai Kriteria</h2>
        <p class="text-white-50 mb-0">
            Atur aturan konversi nilai mentah ke skor 1-5 untuk setiap kriteria
        </p>
    </div>

    <div class="card-custom p-4">

        <form method="GET" class="mb-4">
            <label class="form-label fw-bold">Kriteria</label>
            <select name="id" class="form-select" onchange="this.form.submit()">
                <?php foreach($kriteria as $k){ ?>
                    <option value="<?= $k['id'] ?>" <?= $k['id']==$idKr ? 'selected' : '' ?>>
                        <?= htmlspecialchars($k['kode_kriteria'].' - '.$k['nama_kriteria']) ?>
                        (<?= htmlspecialchars($k['tipe']) ?>, sumber: <?= htmlspecialchars($k['sumber_data'] ?? '-') ?>)
                    </option>
                <?php } ?>
            </select>
        </form>

        <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr><th>Jenis</th><th>Aturan</th><th>Skor</th><th style="width:90px">Aksi</th></tr>
            </thead>
            <tbody>
            <?php foreach($aturan as $a){ ?>
                <tr>
                    <td><?= $a['jenis']=='rentang' ? 'Rentang angka' : 'Mengandung teks' ?></td>
                    <td>
                        <?php if($a['jenis']=='rentang'){ ?>
                            nilai &ge; <?= rtrim(rtrim(number_format($a['batas_min'],2,'.',''),'0'),'.') ?>
                        <?php }else{ ?>
                            mengandung "<?= htmlspecialchars($a['kata_kunci']) ?>"
                        <?php } ?>
                    </td>
                    <td><b><?= rtrim(rtrim(number_format($a['nilai'],2,'.',''),'0'),'.') ?></b></td>
                    <td>
                        <a href="?id=<?= $idKr ?>&hapus=<?= $a['id'] ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Hapus aturan ini?')">
                            <i class="bi bi-trash"></i>
                        </a>
                    </td>
                </tr>
            <?php } ?>
            <?php if(!$aturan){ ?>
                <tr><td colspan="4" class="text-center text-muted">
                    Belum ada aturan. Untuk sumber berupa angka tanpa aturan, nilai mentah dipakai apa adanya;
                    untuk teks tanpa aturan, nilainya 1.
                </td></tr>
            <?php } ?>
            </tbody>
        </table>
        </div>

        <h5 class="mt-4">Tambah Aturan</h5>
        <form method="POST" class="row g-2 align-items-end">
            <input type="hidden" name="id_kriteria" value="<?= $idKr ?>">
            <div class="col-md-3">
                <label class="form-label">Jenis</label>
                <select name="jenis" id="jenis" class="form-select" onchange="ganti()">
                    <option value="rentang">Rentang angka (&ge; batas)</option>
                    <option value="teks">Mengandung teks</option>
                </select>
            </div>
            <div class="col-md-3" id="boxBatas">
                <label class="form-label">Batas minimum</label>
                <input type="number" step="any" name="batas_min" class="form-control" value="0">
            </div>
            <div class="col-md-3 d-none" id="boxKata">
                <label class="form-label">Kata kunci</label>
                <input type="text" name="kata_kunci" class="form-control" placeholder="mis. gramedia">
            </div>
            <div class="col-md-2">
                <label class="form-label">Skor (1-5)</label>
                <input type="number" step="0.5" min="1" max="5" name="nilai" class="form-control" required>
            </div>
            <div class="col-md-2">
                <button name="tambah" class="btn btn-primary btn-custom w-100">
                    <i class="bi bi-plus-circle"></i> Tambah
                </button>
            </div>
        </form>

        <a href="kriteria.php" class="btn btn-secondary btn-custom mt-4">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<script>
function ganti(){
    const teks = document.getElementById('jenis').value === 'teks';
    document.getElementById('boxBatas').classList.toggle('d-none', teks);
    document.getElementById('boxKata').classList.toggle('d-none', !teks);
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
