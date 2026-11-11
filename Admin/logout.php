<?php

session_start();
require_once __DIR__ . '/../connection/koneksi.php';
require_once __DIR__ . '/../lib/audit.php';
catatAudit($koneksi,'logout','users',$_SESSION['id_user'] ?? null,'Logout');

/*
|--------------------------------------------------------------------------
| Hapus semua data session
|--------------------------------------------------------------------------
*/

$_SESSION = [];

/*
|--------------------------------------------------------------------------
| Hapus cookie session jika digunakan
|--------------------------------------------------------------------------
*/

if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

/*
|--------------------------------------------------------------------------
| Hancurkan session
|--------------------------------------------------------------------------
*/

session_destroy();

/*
|--------------------------------------------------------------------------
| Kembali ke halaman login
|--------------------------------------------------------------------------
*/

header("Location: ../auth/login.php");
exit;

?>