<?php
session_start();
include __DIR__ . '/../connection/koneksi.php';
include __DIR__ . '/../lib/saw_engine.php';
include __DIR__ . '/../lib/catat_pencarian.php';

/* =====================================================================
   SMART SEARCH
   - kata kunci (boleh beberapa kata; semua kata harus ada di judul, penulis,
     penerbit, kategori, atau deskripsi/topik)
   - kombinasi filter: kategori, penulis, tahun, rating minimal, ketersediaan,
     bahasa, tingkat kesulitan
   - pengurutan: relevansi, rekomendasi (AHP-SAW), terbaru, populer, rating, tahun, judul
   ===================================================================== */

const PER_HALAMAN = 12;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt($x, $d = 1){ return number_format((float)$x, $d, ',', '.'); }

/* ---------- baca & bersihkan input ---------- */
$q          = trim((string)($_GET['q'] ?? $_GET['cari'] ?? ''));   // 'cari' = kompatibel dengan form di beranda
$q          = mb_substr($q, 0, 100);
$kategori   = trim((string)($_GET['kategori'] ?? ''));
$penulis    = mb_substr(trim((string)($_GET['penulis'] ?? '')), 0, 100);
$tahunDari  = ctype_digit((string)($_GET['dari'] ?? '')) ? (int)$_GET['dari'] : null;
$tahunSampai= ctype_digit((string)($_GET['sampai'] ?? '')) ? (int)$_GET['sampai'] : null;
$ratingMin  = in_array((string)($_GET['rating'] ?? ''), ['3','3.5','4','4.5'], true) ? (float)$_GET['rating'] : 0.0;
$tersedia   = (($_GET['tersedia'] ?? '') === '1');
$bahasa     = mb_substr(trim((string)($_GET['bahasa'] ?? '')), 0, 30);
$kesulitan  = in_array(($_GET['kesulitan'] ?? ''), ['mudah','sedang','sulit'], true) ? $_GET['kesulitan'] : '';

$urutSah = ['relevan','rekomendasi','terbaru','populer','rating','tahun','judul'];
$urut = $_GET['urut'] ?? ($q !== '' ? 'relevan' : 'rekomendasi');
if(!in_array($urut, $urutSah, true)) $urut = ($q !== '' ? 'relevan' : 'rekomendasi');
if($urut === 'relevan' && $q === '') $urut = 'rekomendasi';

$halaman = max(1, (int)($_GET['hal'] ?? 1));

$idUser = !empty($_SESSION['login']) ? (int)($_SESSION['id_user'] ?? 0) : 0;
$isLogin = !empty($_SESSION['login']);

/* pencarian siswa dicatat sebagai bahan profil minat (Personalized Recommendation) */
if($q !== '' && $halaman === 1){ catatPencarian($koneksi, $q); }

/* ---------- susun query ---------- */
$kata = [];
if($q !== ''){
    foreach(preg_split('/\s+/u', $q) as $w){ if($w !== '') $kata[] = $w; }
    $kata = array_slice($kata, 0, 6);
}

$where = []; $tipe = ''; $param = [];
foreach($kata as $w){
    $like = '%' . addcslashes($w, '%_\\') . '%';
    $where[] = "(b.nama_buku LIKE ? OR b.penulis LIKE ? OR b.penerbit LIKE ? OR b.kategori LIKE ? OR b.deskripsi LIKE ?)";
    $tipe .= 'sssss'; array_push($param, $like, $like, $like, $like, $like);
}
if($kategori !== ''){ $where[] = "b.kategori = ?"; $tipe .= 's'; $param[] = $kategori; }
if($penulis !== ''){ $where[] = "b.penulis LIKE ?"; $tipe .= 's'; $param[] = '%' . addcslashes($penulis, '%_\\') . '%'; }
if($tahunDari !== null){ $where[] = "b.tahun_terbit >= ?"; $tipe .= 'i'; $param[] = $tahunDari; }
if($tahunSampai !== null){ $where[] = "b.tahun_terbit <= ?"; $tipe .= 'i'; $param[] = $tahunSampai; }
if($ratingMin > 0){ $where[] = "COALESCE(r.rata, 0) >= ?"; $tipe .= 'd'; $param[] = $ratingMin; }
if($tersedia){ $where[] = "b.stok > 0"; }
if($bahasa !== ''){ $where[] = "b.bahasa = ?"; $tipe .= 's'; $param[] = $bahasa; }
if($kesulitan === 'mudah'){ $where[] = "b.tingkat_kesulitan <= 2"; }
if($kesulitan === 'sedang'){ $where[] = "b.tingkat_kesulitan = 3"; }
if($kesulitan === 'sulit'){ $where[] = "b.tingkat_kesulitan >= 4"; }

$sql = "SELECT b.*, COALESCE(r.rata, 0) AS rating_rata, COALESCE(r.jumlah, 0) AS rating_jumlah
        FROM buku b
        LEFT JOIN (SELECT id_buku, AVG(rating) rata, COUNT(*) jumlah FROM rating_buku GROUP BY id_buku) r
               ON r.id_buku = b.id"
     . ($where ? " WHERE " . implode(' AND ', $where) : '');

$st = $koneksi->prepare($sql);
if($param){ $st->bind_param($tipe, ...$param); }
$st->execute();
$res = $st->get_result();
$hasil = [];
while($r = $res->fetch_assoc()){ $hasil[] = $r; }
$st->close();

/* ---------- skor rekomendasi AHP-SAW (personal bila siswa punya histori) ---------- */
$rek = rekomendasi($koneksi, null, $idUser ?: null);
$skorRek = []; $rankRek = [];
foreach($rek as $x){ $skorRek[$x['id_buku']] = $x['skor']; $rankRek[$x['id_buku']] = $x['ranking']; }

/* ---------- relevansi kata kunci ---------- */
function hitungRelevansi(array $b, array $kata, string $q): int {
    if(!$kata) return 0;
    $skor = 0;
    $nama = mb_strtolower($b['nama_buku']);
    if(mb_stripos($nama, mb_strtolower($q)) !== false) $skor += 5;   // frasa utuh di judul
    foreach($kata as $w){
        $w = mb_strtolower($w);
        if(mb_strpos($nama, $w) !== false) $skor += 5;
        if(mb_strpos(mb_strtolower($b['penulis']), $w) !== false) $skor += 3;
        if(mb_strpos(mb_strtolower($b['kategori']), $w) !== false) $skor += 2;
        if(mb_strpos(mb_strtolower((string)$b['penerbit']), $w) !== false) $skor += 1;
        if(mb_strpos(mb_strtolower((string)$b['deskripsi']), $w) !== false) $skor += 1;
    }
    return $skor;
}
foreach($hasil as &$b){
    $b['relevansi'] = hitungRelevansi($b, $kata, $q);
    $b['skor_rek']  = $skorRek[$b['id']] ?? 0;
    $b['rank_rek']  = $rankRek[$b['id']] ?? null;
}
unset($b);

/* ---------- urutkan ---------- */
usort($hasil, function($a, $b) use ($urut){
    switch($urut){
        case 'relevan':     $c = $b['relevansi'] <=> $a['relevansi']; if($c) return $c; return $b['skor_rek'] <=> $a['skor_rek'];
        case 'terbaru':     return $b['id'] <=> $a['id'];
        case 'populer':     $c = $b['jumlah_dipinjam'] <=> $a['jumlah_dipinjam']; if($c) return $c; return $b['view_buku'] <=> $a['view_buku'];
        case 'rating':      $c = $b['rating_rata'] <=> $a['rating_rata']; if($c) return $c; return $b['rating_jumlah'] <=> $a['rating_jumlah'];
        case 'tahun':       return $b['tahun_terbit'] <=> $a['tahun_terbit'];
        case 'judul':       return strcasecmp($a['nama_buku'], $b['nama_buku']);
        default:            return $b['skor_rek'] <=> $a['skor_rek'];   // rekomendasi
    }
});

/* ---------- halaman ---------- */
$total = count($hasil);
$jumlahHal = max(1, (int)ceil($total / PER_HALAMAN));
$halaman = min($halaman, $jumlahHal);
$tampil = array_slice($hasil, ($halaman - 1) * PER_HALAMAN, PER_HALAMAN);

/* ---------- data pilihan filter ---------- */
$daftarKategori = []; $daftarBahasa = [];
$qq = $koneksi->query("SELECT DISTINCT kategori FROM buku WHERE kategori IS NOT NULL AND kategori <> '' ORDER BY kategori");
while($x = $qq->fetch_assoc()) $daftarKategori[] = $x['kategori'];
$qq = $koneksi->query("SELECT DISTINCT bahasa FROM buku WHERE bahasa IS NOT NULL AND bahasa <> '' ORDER BY bahasa");
while($x = $qq->fetch_assoc()) $daftarBahasa[] = $x['bahasa'];

/* ---------- helper URL & tampilan ---------- */
$semuaParam = ['q'=>$q,'kategori'=>$kategori,'penulis'=>$penulis,'dari'=>$tahunDari,'sampai'=>$tahunSampai,
               'rating'=>$ratingMin > 0 ? (string)$ratingMin : '','tersedia'=>$tersedia ? '1' : '',
               'bahasa'=>$bahasa,'kesulitan'=>$kesulitan,'urut'=>$urut];

function urlKoleksi(array $param, array $ubah = []): string {
    $p = array_merge($param, $ubah);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 0);
    return 'koleksi.php' . ($p ? '?' . http_build_query($p) : '');
}

function sorot(string $teks, array $kata): string {
    $aman = h($teks);
    foreach($kata as $w){
        $aman = preg_replace('/(' . preg_quote(h($w), '/') . ')/iu', '<mark>$1</mark>', $aman) ?? $aman;
    }
    return $aman;
}

$labelKesulitan = ['mudah' => 'Mudah (1-2)', 'sedang' => 'Sedang (3)', 'sulit' => 'Sulit (4-5)'];
$labelUrut = ['relevan'=>'Paling relevan','rekomendasi'=>'Paling direkomendasikan','terbaru'=>'Terbaru ditambahkan',
              'populer'=>'Paling banyak dipinjam','rating'=>'Rating tertinggi','tahun'=>'Tahun terbit terbaru','judul'=>'Judul A-Z'];

/* chip filter aktif (klik untuk menghapus satu filter) */
$chip = [];
if($q !== '')          $chip[] = ['Kata kunci: ' . $q, urlKoleksi($semuaParam, ['q'=>'','hal'=>1])];
if($kategori !== '')   $chip[] = ['Kategori: ' . $kategori, urlKoleksi($semuaParam, ['kategori'=>''])];
if($penulis !== '')    $chip[] = ['Penulis: ' . $penulis, urlKoleksi($semuaParam, ['penulis'=>''])];
if($tahunDari !== null)  $chip[] = ['Tahun ≥ ' . $tahunDari, urlKoleksi($semuaParam, ['dari'=>''])];
if($tahunSampai !== null)$chip[] = ['Tahun ≤ ' . $tahunSampai, urlKoleksi($semuaParam, ['sampai'=>''])];
if($ratingMin > 0)     $chip[] = ['Rating ≥ ' . fmt($ratingMin), urlKoleksi($semuaParam, ['rating'=>''])];
if($tersedia)          $chip[] = ['Hanya yang tersedia', urlKoleksi($semuaParam, ['tersedia'=>''])];
if($bahasa !== '')     $chip[] = ['Bahasa: ' . $bahasa, urlKoleksi($semuaParam, ['bahasa'=>''])];
if($kesulitan !== '')  $chip[] = ['Kesulitan: ' . $labelKesulitan[$kesulitan], urlKoleksi($semuaParam, ['kesulitan'=>''])];
$adaFilter = count($chip) > 0;
$filterLanjutAktif = ($penulis !== '' || $tahunDari !== null || $tahunSampai !== null || $ratingMin > 0
                      || $tersedia || $bahasa !== '' || $kesulitan !== '');

/* hasil kosong -> tawarkan buku yang paling direkomendasikan */
$saran = [];
if(!$total){
    $byId = [];
    $qb = $koneksi->query("SELECT * FROM buku");
    while($x = $qb->fetch_assoc()) $byId[(int)$x['id']] = $x;
    foreach(array_slice($rek, 0, 4) as $x){ if(isset($byId[$x['id_buku']])) $saran[] = $byId[$x['id_buku']]; }
}

/* kartu buku */
function kartuBuku(array $b, array $kata, bool $isLogin, string $urut): void {
    $stok = (int)($b['stok'] ?? 0);
    $kes  = (int)($b['tingkat_kesulitan'] ?? 3);
    $ket  = [1=>'Sangat mudah',2=>'Mudah',3=>'Sedang',4=>'Sulit',5=>'Sangat sulit'][$kes] ?? '-';
?>
    <div class="col-xl-3 col-lg-4 col-md-6 col-6">
        <div class="kol-card">
            <img src="../gambar/<?= h($b['gambar']) ?>" alt="">
            <div class="kol-body">
                <p class="kol-title"><?= sorot($b['nama_buku'], $kata) ?></p>
                <div class="kol-author"><?= sorot((string)$b['penulis'], $kata) ?></div>

                <div class="d-flex flex-wrap gap-1 mb-2">
                    <span class="badge bg-success"><?= h($b['kategori']) ?></span>
                    <span class="badge bg-primary"><?= h($b['tahun_terbit'] ?? '-') ?></span>
                    <?php if($stok > 0){ ?>
                        <span class="badge bg-success">Tersedia (<?= $stok ?>)</span>
                    <?php }else{ ?>
                        <span class="badge bg-danger">Habis</span>
                    <?php } ?>
                </div>

                <div class="kol-meta">
                    <?php if((int)$b['rating_jumlah'] > 0){ ?>
                        <span class="kol-star">&#9733;</span> <b><?= fmt($b['rating_rata']) ?></b>
                        (<?= (int)$b['rating_jumlah'] ?> ulasan)
                    <?php }else{ ?>
                        <span class="text-muted">Belum ada rating</span>
                    <?php } ?>
                </div>
                <div class="kol-meta">📖 Dipinjam <b><?= (int)$b['jumlah_dipinjam'] ?></b> kali</div>
                <div class="kol-meta">🌐 <?= h($b['bahasa'] ?? 'Indonesia') ?>
                    <?php if(!empty($b['jumlah_halaman'])){ ?> &middot; <?= (int)$b['jumlah_halaman'] ?> hlm<?php } ?>
                </div>
                <div class="kol-meta">🎯 Kesulitan <?= $kes ?>/5 (<?= $ket ?>)</div>

                <?php if(!empty($b['rank_rek'])){ ?>
                    <span class="kol-skor">Rekomendasi #<?= (int)$b['rank_rek'] ?> &middot; skor <?= fmt($b['skor_rek'], 3) ?></span>
                <?php } ?>

                <div class="kol-aksi">
                    <a href="detail_buku.php?id=<?= (int)$b['id'] ?>" class="btn btn-primary rounded-pill btn-sm">
                        <i class="bi bi-eye-fill"></i> Detail Buku
                    </a>
                    <?php if($stok > 0){ ?>
                        <a href="pinjam.php?id=<?= (int)$b['id'] ?>" class="btn btn-success rounded-pill btn-sm">
                            <i class="bi bi-book"></i> Pinjam Buku
                        </a>
                    <?php }else{ ?>
                        <span class="btn btn-secondary rounded-pill btn-sm disabled">Stok habis</span>
                    <?php } ?>
                    <?php if($isLogin){ ?>
                        <a href="simpan_buku.php?id=<?= (int)$b['id'] ?>" class="btn btn-outline-warning rounded-pill btn-sm">
                            <i class="bi bi-bookmark-heart-fill"></i> Simpan Buku
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
<?php
}
?>

<!DOCTYPE html>
<html>
<head>

<title>Koleksi Buku</title>

<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- BOOTSTRAP -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- ICON -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<style>

/* =========================
FONT
========================= */

@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');


/* =========================
GLOBAL
========================= */

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:'Poppins',sans-serif;

    background: linear-gradient(
        135deg,
        #f8f2ff 0%,
        #d8b4fe 25%,
        #af69ef 55%,
        #9333ea 100%
    );

    color:#334155;

    min-height:100vh;
}


/* =========================
MODERN NAVBAR
========================= */

.navbar{
    position:fixed;

    top:20px;
    left:50%;

    transform:translateX(-50%);

    width:90%;
    max-width:950px;

    background:rgba(255,255,255,.92);

    backdrop-filter:blur(20px);

    border:1px solid rgba(175,105,239,.12);

    border-radius:60px;

    padding:12px 25px;

    box-shadow:
        0 10px 30px rgba(0,0,0,.06);

    z-index:9999;

    animation:navFade .8s ease;
}


/* =========================
NAVBAR ANIMATION
========================= */

@keyframes navFade{

    from{
        opacity:0;

        transform:
        translateX(-50%)
        translateY(-30px);
    }

    to{
        opacity:1;

        transform:
        translateX(-50%)
        translateY(0);
    }

}


/* =========================
NAVBAR BRAND
========================= */

.navbar-brand{
    font-size:20px;

    font-weight:600;

    color:#af69ef !important;

    letter-spacing:.5px;
}


/* =========================
NAV LINK
========================= */

.nav-link{
    color:#1e293b !important;

    font-weight:500;

    position:relative;

    margin:0 10px;

    transition:.3s;
}

.nav-link:hover{
    color:#af69ef !important;

    transform:translateY(-2px);
}


/* GARIS NAVBAR */

.nav-link::after{

    content:'';

    position:absolute;

    width:0;

    height:2px;

    left:50%;

    bottom:-5px;

    background:#af69ef;

    transition:.3s;
}

.nav-link:hover::after{

    width:100%;

    left:0;
}


/* =========================
LOGIN BUTTON
========================= */

.btn-login{

    background:#af69ef;

    color:#fff;

    border:none;

    border-radius:50px;

    padding:10px 25px;

    font-weight:600;

    transition:.3s;
}

.btn-login:hover{

    background:#9c4fe8;

    color:#fff;

    transform:translateY(-2px);

    box-shadow:
        0 10px 25px rgba(175,105,239,.35);
}


/* =========================
USER BUTTON
========================= */

.user-btn{

    background:#f3e8ff;

    color:#af69ef;

    border:none;

    border-radius:50px;

    padding:10px 20px;

    font-weight:600;

    transition:.3s;
}

.user-btn:hover{

    background:#e9d5ff;
}


/* =========================
RESPONSIVE NAVBAR
========================= */

@media(max-width:768px){

    .navbar{

        width:95%;

        padding:10px 18px;
    }

    .navbar-brand{

        font-size:20px;
    }

}


/* =========================
HALAMAN REKOMENDASI
========================= */

.rekomendasi-container{

    padding-top:170px;

    padding-bottom:60px;
}


/* =========================
JUDUL
========================= */

.rekomendasi-title{

    color:#ffffff;

    font-weight:700;

    text-shadow:
        0 4px 15px rgba(0,0,0,.25);
}


/* =========================
TABLE CARD
========================= */

.rekomendasi-table{

    background:#fff;

    border-radius:20px;

    overflow:hidden;

    box-shadow:
        0 15px 40px rgba(15,23,42,.15);

    border:1px solid rgba(175,105,239,.12);
}


/* =========================
TABLE
========================= */

.rekomendasi-table table{

    margin-bottom:0;
}

.rekomendasi-table th{

    background:#0f172a !important;

    color:#fff;

    padding:16px;

    font-weight:600;
}

.rekomendasi-table td{

    padding:16px;

    vertical-align:middle;

    color:#334155;
}


/* =========================
RANKING
========================= */

.rekomendasi-table tbody tr{

    transition:.3s;
}

.rekomendasi-table tbody tr:hover{

    background:#f8f2ff;
}


/* =========================
NILAI
========================= */

.nilai{

    color:#af69ef;

    font-weight:700;
}


/* =========================
RANKING NUMBER
========================= */

.ranking{

    width:100px;

    text-align:center;

    font-weight:700;

    color:#af69ef;
}


/* =========================
MOBILE
========================= */

@media(max-width:768px){

    .rekomendasi-container{

        padding-top:110px;

        padding-left:15px;

        padding-right:15px;
    }

    .rekomendasi-title{

        font-size:24px;
    }

    .rekomendasi-table{

        border-radius:15px;
    }

    .rekomendasi-table th,
    .rekomendasi-table td{

        padding:12px 10px;

        font-size:14px;
    }

}

</style>


<style>
/* =========================
EXPLAINABLE RECOMMENDATION
========================= */
.rek-intro{
    background:rgba(255,255,255,.92);
    border-radius:20px;
    padding:18px 22px;
    margin-bottom:22px;
    box-shadow:0 8px 25px rgba(0,0,0,.08);
    font-size:14px;
}
.rek-card{
    background:rgba(255,255,255,.96);
    border-radius:22px;
    padding:20px 22px;
    margin-bottom:18px;
    box-shadow:0 10px 30px rgba(0,0,0,.10);
}
.rek-head{ display:flex; gap:16px; align-items:center; }
.rek-rank{
    min-width:52px; height:52px; border-radius:16px;
    background:linear-gradient(135deg,#af69ef,#9333ea);
    color:#fff; font-weight:700; font-size:20px;
    display:flex; align-items:center; justify-content:center;
}
.rek-rank.top{ background:linear-gradient(135deg,#f59e0b,#f97316); }
.rek-cover{ width:54px; height:72px; object-fit:cover; border-radius:8px; background:#e2e8f0; }
.rek-title{ font-weight:600; font-size:17px; color:#1e293b; margin:0; }
.rek-meta{ font-size:12.5px; color:#64748b; }
.rek-skor{ margin-left:auto; text-align:right; }
.rek-skor b{ font-size:22px; color:#9333ea; display:block; line-height:1; }
.rek-skor small{ color:#94a3b8; }
.rek-alasan{ margin:14px 0 8px; font-size:14px; color:#334155; }
.rek-alasan i{ color:#f59e0b; }
.rek-bar{ display:flex; height:10px; border-radius:6px; overflow:hidden; background:#e2e8f0; margin:6px 0 4px; }
.rek-bar span{ display:block; height:100%; }
.rek-legend{ font-size:11.5px; color:#64748b; display:flex; flex-wrap:wrap; gap:4px 14px; }
.rek-legend i{ display:inline-block; width:9px; height:9px; border-radius:3px; margin-right:4px; }
.rek-detail-btn{ font-size:13px; padding:0; color:#9333ea; text-decoration:none; font-weight:500; }
.rek-table{ font-size:13px; margin:12px 0 0; }
.rek-table th{ background:#f1f5f9; font-weight:600; color:#475569; }
.rek-mini{ height:7px; border-radius:4px; background:#e2e8f0; overflow:hidden; min-width:60px; }
.rek-mini span{ display:block; height:100%; background:#9333ea; }
@media(max-width:576px){
    .rek-head{ flex-wrap:wrap; }
    .rek-skor{ margin-left:0; text-align:left; width:100%; }
    .rek-cover{ display:none; }
}

/* =========================
WHAT-IF ANALYSIS
========================= */
.rek-whatif{
    background:rgba(255,255,255,.96);
    border-radius:22px;
    padding:20px 22px;
    margin-bottom:22px;
    box-shadow:0 10px 30px rgba(0,0,0,.10);
}
.rek-whatif h5{ font-weight:700; color:#1e293b; margin:0; }
.rek-status{
    font-size:12px; font-weight:600; padding:5px 12px; border-radius:30px;
    background:#ede9fe; color:#7c3aed; white-space:nowrap;
}
.rek-status.aktif{ background:#fef3c7; color:#b45309; }
.rek-preset{ display:flex; flex-wrap:wrap; gap:8px; margin:14px 0 4px; }
.rek-preset button{
    border:1px solid #d8b4fe; background:#faf5ff; color:#7c3aed;
    border-radius:30px; font-size:12.5px; padding:6px 14px; font-weight:500;
}
.rek-preset button:hover{ background:#9333ea; color:#fff; }
.rek-preset button.reset{ border-color:#cbd5e1; background:#f8fafc; color:#475569; }
.rek-preset button.reset:hover{ background:#475569; color:#fff; }
.rek-slider{ margin-top:14px; }
.rek-slider .top{ display:flex; justify-content:space-between; font-size:13px; font-weight:500; color:#334155; }
.rek-slider .eff{ color:#9333ea; font-weight:700; }
.rek-slider small{ color:#94a3b8; font-size:11.5px; }
.rek-slider input[type=range]{ accent-color:#9333ea; }
/* =========================
MINAT PENGGUNA
========================= */
.rek-minat{
    background:rgba(255,255,255,.96);
    border-radius:22px;
    padding:20px 22px;
    margin-bottom:22px;
    box-shadow:0 10px 30px rgba(0,0,0,.10);
}
.rek-minat h5{ font-weight:700; color:#1e293b; margin:0; }
.rek-chip{
    border:1px solid #cbd5e1; background:#f8fafc; color:#475569;
    border-radius:30px; font-size:13px; padding:6px 16px; font-weight:500;
}
.rek-chip.aktif{ background:#9333ea; border-color:#9333ea; color:#fff; }
.rek-tag.habis{ background:#fee2e2; color:#b91c1c; margin-left:2px; }
.rek-tag{
    display:inline-block; font-size:11px; font-weight:600; padding:2px 9px; margin-left:6px;
    border-radius:20px; background:#dcfce7; color:#15803d; vertical-align:middle;
}
#daftarRek{ transition:opacity .2s; }
#daftarRek.memuat{ opacity:.45; pointer-events:none; }
.rek-rankbox{ display:flex; flex-direction:column; align-items:center; gap:4px; }
.rek-geser{ font-size:11.5px; font-weight:700; }
.rek-geser.naik{ color:#16a34a; }
.rek-geser.turun{ color:#dc2626; }
.rek-geser.tetap{ color:#94a3b8; }
</style>

<style>
/* =========================
SMART SEARCH / KOLEKSI
========================= */
.kol-panel{
    background:rgba(255,255,255,.96);
    border-radius:22px;
    padding:20px 22px;
    margin-bottom:20px;
    box-shadow:0 10px 30px rgba(0,0,0,.10);
}
.kol-panel label{ font-size:12.5px; font-weight:600; color:#475569; margin-bottom:4px; }
.kol-chip{
    display:inline-flex; align-items:center; gap:6px;
    background:#ede9fe; color:#6d28d9; border-radius:30px;
    font-size:12.5px; font-weight:500; padding:4px 12px; text-decoration:none;
}
.kol-chip:hover{ background:#ddd6fe; color:#5b21b6; }
.kol-info{ color:#fff; font-size:14px; }
.kol-card{
    background:#fff; border-radius:20px; overflow:hidden; height:100%;
    box-shadow:0 10px 25px rgba(0,0,0,.12); display:flex; flex-direction:column;
}
.kol-card img{ width:100%; height:220px; object-fit:cover; background:#e2e8f0; }
.kol-body{ padding:16px; display:flex; flex-direction:column; flex:1; }
.kol-title{ font-weight:600; font-size:15.5px; color:#1e293b; line-height:1.3; margin:0 0 2px; }
.kol-author{ font-size:12.5px; color:#64748b; margin-bottom:8px; }
.kol-meta{ font-size:12.5px; color:#475569; margin:2px 0; }
.kol-meta b{ color:#1e293b; }
.kol-star{ color:#f59e0b; }
.kol-skor{
    font-size:11.5px; font-weight:600; color:#7c3aed; background:#f5f3ff;
    border-radius:10px; padding:3px 9px; display:inline-block; margin-top:6px;
}
.kol-body mark{ background:#fde68a; padding:0 2px; border-radius:3px; }
.kol-aksi{ margin-top:auto; padding-top:12px; display:grid; gap:8px; }
.kol-kosong{
    background:rgba(255,255,255,.96); border-radius:22px; padding:28px; text-align:center;
    box-shadow:0 10px 30px rgba(0,0,0,.10); margin-bottom:24px;
}
.kol-page .page-link{ border-radius:10px !important; margin:0 3px; color:#7c3aed; }
.kol-page .active .page-link{ background:#9333ea; border-color:#9333ea; color:#fff; }
</style>
</head>


<body>


<!-- =========================
NAVBAR
========================= -->

<nav class="navbar navbar-expand-lg">

    <div class="container-fluid">

        <a class="navbar-brand" href="index.php">

            <img
                src="../assets/img/bg1.png"
                alt="Logo Sekolah"
                width="30"
                height="30"
                class="d-inline-block align-top me-2"
            >

            SMART LIBRARY

        </a>


        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#menu"
        >

            <span class="navbar-toggler-icon"></span>

        </button>


        <div
            class="collapse navbar-collapse"
            id="menu"
        >

            <ul class="navbar-nav mx-auto">

                <li class="nav-item">

                    <a
                        href="index.php"
                        class="nav-link"
                    >
                        Beranda
                    </a>

                </li>


                <li class="nav-item">

                    <a
                        href="index.php#layanan"
                        class="nav-link"
                    >
                        Layanan
                    </a>

                </li>


                <li class="nav-item">

                    <a
                        href="index.php#rekomendasi"
                        class="nav-link"
                    >
                        Rekomendasi Buku
                    </a>

                </li>


                <li class="nav-item">

                    <a
                        href="koleksi.php"
                        class="nav-link"
                    >
                        Koleksi Buku
                    </a>

                </li>


                <?php if(isset($_SESSION['login'])){ ?>

                <li class="nav-item">

                    <a
                        href="buku_saya.php"
                        class="nav-link"
                    >
                        Buku Saya
                    </a>

                </li>

                <?php } ?>

            </ul>


            <?php if(!isset($_SESSION['login'])){ ?>

                <a
                    href="../auth/login.php"
                    class="btn btn-login"
                >
                    Login
                </a>

            <?php } else { ?>

                <div class="dropdown">

                    <button
                        class="btn user-btn dropdown-toggle"
                        data-bs-toggle="dropdown"
                    >

                        👤 <?= $_SESSION['username']; ?>

                    </button>


                    <ul class="dropdown-menu dropdown-menu-end">

                        <?php if(($_SESSION['role'] ?? '') == 'petugas'){ ?>

                        <li>

                            <a
                                class="dropdown-item"
                                href="../Admin/dashboard.php"
                            >
                                Dashboard Petugas
                            </a>

                        </li>

                        <?php } ?>


                        <li>

                            <a
                                class="dropdown-item"
                                href="../auth/logout.php"
                            >
                                Logout
                            </a>

                        </li>

                    </ul>

                </div>

            <?php } ?>


        </div>

    </div>

</nav>



<!-- =========================
KONTEN KOLEKSI (SMART SEARCH)
========================= -->

<div class="container rekomendasi-container">

    <h3 class="mb-4 rekomendasi-title">📚 Koleksi Buku</h3>

    <!-- ========== FORM PENCARIAN ========== -->
    <form method="GET" action="koleksi.php" class="kol-panel" id="formCari">

        <div class="row g-3 align-items-end">

            <div class="col-lg-5">
                <label for="q">Kata kunci</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="q" name="q" class="form-control"
                           value="<?= h($q) ?>"
                           placeholder="Judul, penulis, penerbit, atau topik...">
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <label for="kategori">Kategori</label>
                <select id="kategori" name="kategori" class="form-select">
                    <option value="">Semua kategori</option>
                    <?php foreach($daftarKategori as $k){ ?>
                        <option value="<?= h($k) ?>" <?= $k === $kategori ? 'selected' : '' ?>><?= h($k) ?></option>
                    <?php } ?>
                </select>
            </div>

            <div class="col-lg-2 col-md-6">
                <label for="urut">Urutkan</label>
                <select id="urut" name="urut" class="form-select">
                    <?php foreach($labelUrut as $kode => $nama){
                        if($kode === 'relevan' && $q === '') continue; ?>
                        <option value="<?= $kode ?>" <?= $kode === $urut ? 'selected' : '' ?>><?= h($nama) ?></option>
                    <?php } ?>
                </select>
            </div>

            <div class="col-lg-2">
                <button class="btn btn-primary w-100"><i class="bi bi-search"></i> Cari</button>
            </div>

        </div>

        <a class="btn btn-link btn-sm px-0 mt-2" data-bs-toggle="collapse" href="#filterLanjut">
            <i class="bi bi-sliders"></i> Filter lanjutan
        </a>

        <div class="collapse <?= $filterLanjutAktif ? 'show' : '' ?>" id="filterLanjut">
            <div class="row g-3 mt-1">

                <div class="col-lg-3 col-md-6">
                    <label for="penulis">Penulis</label>
                    <input type="text" id="penulis" name="penulis" class="form-control" value="<?= h($penulis) ?>">
                </div>

                <div class="col-lg-2 col-6">
                    <label for="dari">Tahun dari</label>
                    <input type="number" id="dari" name="dari" class="form-control" min="1900" max="2100"
                           value="<?= $tahunDari ?? '' ?>">
                </div>

                <div class="col-lg-2 col-6">
                    <label for="sampai">Tahun sampai</label>
                    <input type="number" id="sampai" name="sampai" class="form-control" min="1900" max="2100"
                           value="<?= $tahunSampai ?? '' ?>">
                </div>

                <div class="col-lg-2 col-6">
                    <label for="rating">Rating minimal</label>
                    <select id="rating" name="rating" class="form-select">
                        <option value="">Semua</option>
                        <?php foreach(['3' => '3 ke atas', '3.5' => '3,5 ke atas', '4' => '4 ke atas', '4.5' => '4,5 ke atas'] as $v => $l){ ?>
                            <option value="<?= $v ?>" <?= (string)$ratingMin === (string)(float)$v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div class="col-lg-3 col-6">
                    <label for="bahasa">Bahasa</label>
                    <select id="bahasa" name="bahasa" class="form-select">
                        <option value="">Semua bahasa</option>
                        <?php foreach($daftarBahasa as $b){ ?>
                            <option value="<?= h($b) ?>" <?= $b === $bahasa ? 'selected' : '' ?>><?= h($b) ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div class="col-lg-3 col-6">
                    <label for="kesulitan">Tingkat kesulitan</label>
                    <select id="kesulitan" name="kesulitan" class="form-select">
                        <option value="">Semua tingkat</option>
                        <?php foreach($labelKesulitan as $v => $l){ ?>
                            <option value="<?= $v ?>" <?= $v === $kesulitan ? 'selected' : '' ?>><?= h($l) ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div class="col-lg-3 col-6 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="tersedia" name="tersedia" value="1" <?= $tersedia ? 'checked' : '' ?>>
                        <label class="form-check-label" for="tersedia" style="font-size:14px;font-weight:500">
                            Hanya yang tersedia
                        </label>
                    </div>
                </div>

            </div>
        </div>

        <?php if($adaFilter){ ?>
            <div class="d-flex flex-wrap gap-2 align-items-center mt-3">
                <?php foreach($chip as $c){ ?>
                    <a class="kol-chip" href="<?= h($c[1]) ?>" title="Hapus filter ini"><?= h($c[0]) ?> &times;</a>
                <?php } ?>
                <a href="koleksi.php" class="btn btn-sm btn-outline-secondary ms-1">Reset semua</a>
            </div>
        <?php } ?>

    </form>

    <!-- ========== INFO HASIL ========== -->
    <div class="kol-info mb-3">
        <?php if($total){ ?>
            Menampilkan <b><?= ($halaman - 1) * PER_HALAMAN + 1 ?>&ndash;<?= min($halaman * PER_HALAMAN, $total) ?></b>
            dari <b><?= $total ?></b> buku
            &middot; urutan: <b><?= h($labelUrut[$urut]) ?></b>
            <?php if($urut === 'rekomendasi' && $idUser){ ?>(dipersonalisasi bila Anda punya histori)<?php } ?>
        <?php }else{ ?>
            Tidak ada buku yang cocok.
        <?php } ?>
    </div>

    <!-- ========== HASIL ========== -->
    <?php if($total){ ?>
        <div class="row g-4 mb-4">
            <?php foreach($tampil as $b){ kartuBuku($b, $kata, $isLogin, $urut); } ?>
        </div>

        <?php if($jumlahHal > 1){ ?>
            <nav class="kol-page d-flex justify-content-center">
                <ul class="pagination">
                    <li class="page-item <?= $halaman <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= h(urlKoleksi($semuaParam, ['hal' => $halaman - 1])) ?>">&laquo;</a>
                    </li>
                    <?php for($i = 1; $i <= $jumlahHal; $i++){ ?>
                        <li class="page-item <?= $i === $halaman ? 'active' : '' ?>">
                            <a class="page-link" href="<?= h(urlKoleksi($semuaParam, ['hal' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php } ?>
                    <li class="page-item <?= $halaman >= $jumlahHal ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= h(urlKoleksi($semuaParam, ['hal' => $halaman + 1])) ?>">&raquo;</a>
                    </li>
                </ul>
            </nav>
        <?php } ?>

    <?php }else{ ?>

        <div class="kol-kosong">
            <h5>Belum ada yang cocok</h5>
            <p class="text-muted mb-2">
                Coba kurangi filter atau gunakan kata kunci yang lebih singkat.
            </p>
            <?php if($adaFilter){ ?>
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <?php foreach($chip as $c){ ?>
                        <a class="kol-chip" href="<?= h($c[1]) ?>">Hapus: <?= h($c[0]) ?></a>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

        <?php if($saran){ ?>
            <div class="kol-info mb-3"><b>Mungkin Anda suka (paling direkomendasikan):</b></div>
            <div class="row g-4 mb-4">
                <?php foreach($saran as $b){
                    $b['rating_rata'] = 0; $b['rating_jumlah'] = 0;
                    $b['rank_rek'] = $rankRek[$b['id']] ?? null; $b['skor_rek'] = $skorRek[$b['id']] ?? 0;
                    kartuBuku($b, [], $isLogin, 'rekomendasi');
                } ?>
            </div>
        <?php } ?>

    <?php } ?>

</div>

<!-- BOOTSTRAP JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
