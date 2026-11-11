<?php
session_start();
require_once __DIR__ . '/../connection/koneksi.php';
require_once __DIR__ . '/../lib/audit.php';
if (!empty($_SESSION['login'])) {
    catatAudit($koneksi, 'logout', 'users', $_SESSION['id_user'] ?? null, 'Logout');
}
session_destroy();
header("location:login.php");
exit;
