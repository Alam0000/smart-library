<?php
session_start();
require_once __DIR__ . '/../connection/koneksi.php';
if (empty($_SESSION['login']) || ($_SESSION['role'] ?? '') !== 'petugas') { header('Location: ../auth/login.php'); exit; }
if (!isset($_SESSION['id_user']) && !isset($_SESSION['id'])) { http_response_code(403); exit('Akses ditolak.'); }
$res=$koneksi->query("SELECT * FROM buku ORDER BY id DESC");
if(!$res){http_response_code(500);exit('Gagal mengambil data buku.');}
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="smart-library-buku-'.date('Ymd-His').'.csv"');
$out=fopen('php://output','w'); fwrite($out, "\xEF\xBB\xBF");
$fields=[]; while($f=$res->fetch_field()) $fields[]=$f->name;
fputcsv($out,$fields);
while($row=$res->fetch_assoc()) { $line=[]; foreach($fields as $f) $line[]=$row[$f]??''; fputcsv($out,$line); }
fclose($out);
try { $id=(int)($_SESSION['id_user']??$_SESSION['id']??0); $nama=(string)($_SESSION['username']??'Petugas'); $aksi='export_csv'; $ent='buku'; $detail='Ekspor koleksi buku ke CSV'; $ip=$_SERVER['REMOTE_ADDR']??''; $st=$koneksi->prepare("INSERT INTO audit_log (id_user,nama_pengguna,aksi,entitas,detail,ip_address) VALUES (?,?,?,?,?,?)"); if($st){$st->bind_param('isssss',$id,$nama,$aksi,$ent,$detail,$ip);$st->execute();$st->close();} } catch(Throwable $e) {}
exit;
