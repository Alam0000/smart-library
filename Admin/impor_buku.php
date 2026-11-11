<?php
session_start();
require_once __DIR__ . '/../connection/koneksi.php';
if (empty($_SESSION['login']) || ($_SESSION['role'] ?? '') !== 'petugas') { header('Location: ../auth/login.php'); exit; }
$msg=''; $err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(empty($_FILES['csv']['tmp_name']) || ($_FILES['csv']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){$err='Pilih file CSV yang valid.';}
 elseif(($_FILES['csv']['size']??0)>5*1024*1024){$err='Ukuran file maksimal 5 MB.';}
 else {
  $fh=fopen($_FILES['csv']['tmp_name'],'r');
  if(!$fh){$err='File tidak dapat dibaca.';} else {
   $header=fgetcsv($fh);
   if(!$header){$err='CSV kosong.';} else {
    $header=array_map(fn($v)=>trim(preg_replace('/^\xEF\xBB\xBF/','',(string)$v)), $header);
    $cols=[]; $qr=$koneksi->query("SHOW COLUMNS FROM buku"); while($qr && $c=$qr->fetch_assoc()) if($c['Field']!=='id' && stripos($c['Extra']??'','auto_increment')===false) $cols[]=$c['Field'];
    $valid=array_values(array_intersect($header,$cols));
    if(!$valid){$err='Header CSV tidak cocok dengan kolom tabel buku. Gunakan file hasil ekspor aplikasi sebagai template.';}
    else {
     $indexes=[]; foreach($valid as $c) $indexes[]=array_search($c,$header,true);
     $quoted=array_map(fn($c)=>'`'.str_replace('`','',$c).'`',$valid);
     $sql='INSERT INTO buku ('.implode(',',$quoted).') VALUES ('.implode(',',array_fill(0,count($valid),'?')).')';
     $st=$koneksi->prepare($sql); $count=0; $skip=0;
     if(!$st){$err='Tidak dapat menyiapkan impor: '.htmlspecialchars($koneksi->error);}
     else {
      while(($row=fgetcsv($fh))!==false){
       if(count(array_filter($row,fn($v)=>trim((string)$v)!==''))===0)continue;
       $vals=[]; foreach($indexes as $idx) $vals[]=isset($row[$idx])?(string)$row[$idx]:'';
       $types=str_repeat('s',count($vals)); $st->bind_param($types,...$vals);
       if($st->execute())$count++; else $skip++;
      }
      $st->close(); $msg="Impor selesai: $count baris berhasil, $skip baris gagal.";
      try{$id=(int)($_SESSION['id_user']??$_SESSION['id']??0);$nama=(string)($_SESSION['username']??'Petugas');$aksi='import_csv';$ent='buku';$detail="Impor CSV: $count berhasil, $skip gagal";$ip=$_SERVER['REMOTE_ADDR']??'';$a=$koneksi->prepare("INSERT INTO audit_log (id_user,nama_pengguna,aksi,entitas,detail,ip_address) VALUES (?,?,?,?,?,?)");if($a){$a->bind_param('isssss',$id,$nama,$aksi,$ent,$detail,$ip);$a->execute();$a->close();}}catch(Throwable $e){}
     }
    }
   }
   fclose($fh);
  }
 }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Impor Buku</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f3f6fb}.main{margin-left:260px;padding:28px}@media(max-width:768px){.main{margin-left:0;padding:18px}}</style></head><body><?php include __DIR__.'/partials/sidebar.php'; ?><main class="main"><h2>Impor Koleksi Buku</h2><p class="text-secondary">Unggah CSV dengan header kolom yang sama seperti hasil ekspor. Kolom ID otomatis tidak diimpor.</p><?php if($msg):?><div class="alert alert-success"><?=htmlspecialchars($msg)?></div><?php endif;?><?php if($err):?><div class="alert alert-danger"><?=$err?></div><?php endif;?><div class="card p-4"><form method="post" enctype="multipart/form-data"><label class="form-label">File CSV (maksimal 5 MB)</label><input class="form-control mb-3" type="file" name="csv" accept=".csv,text/csv" required><button class="btn btn-primary">Impor CSV</button> <a class="btn btn-outline-secondary" href="export_buku.php">Unduh template CSV</a></form></div><div class="alert alert-warning mt-3">Saran: backup database sebelum impor. Baris yang gagal akan dilewati; cek data hasil impor setelah proses selesai.</div></main></body></html>
