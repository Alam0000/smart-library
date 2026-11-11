<?php
session_start();
include '../connection/koneksi.php';

$error = "";
$success = "";

$kode_rahasia = "PETUGAS123";

// Hitung jumlah petugas
$qPetugas = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) as total
     FROM users
     WHERE role='petugas'"
);

$jmlPetugas = mysqli_fetch_assoc($qPetugas)['total'];

if(isset($_POST['daftar'])){

    $nama       = trim($_POST['nama_lengkap']);
    $username   = trim($_POST['username']);
    $password   = $_POST['password'];
    $konfirmasi = $_POST['konfirmasi_password'];
    $role       = $_POST['role'];
    $kode       = $_POST['kode_petugas'] ?? '';

    // validasi password
    if($password != $konfirmasi){

        $error = "Konfirmasi password tidak sesuai!";

    }else{

        // cek username
        $cek = $koneksi->prepare("
            SELECT id
            FROM users
            WHERE username=?
        ");

        $cek->bind_param("s",$username);
        $cek->execute();

        if($cek->get_result()->num_rows > 0){

            $error = "Username sudah digunakan!";

        }else{

            // validasi petugas
            if($role == "petugas"){

                if($jmlPetugas >= 3){

                    $error = "Kuota petugas sudah penuh!";

                }

                elseif($kode != $kode_rahasia){

                    $error = "Kode petugas salah!";

                }

            }

            if(empty($error)){

                $hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $koneksi->prepare("
                    INSERT INTO users
                    (
                        nama_lengkap,
                        username,
                        password,
                        role
                    )
                    VALUES(?,?,?,?)
                ");

                $stmt->bind_param(
                    "ssss",
                    $nama,
                    $username,
                    $hash,
                    $role
                );

                if($stmt->execute()){

                    $success = "Registrasi berhasil!";

                }else{

                    $error = "Gagal registrasi!";

                }

            }

        }

    }

}
?>

<!DOCTYPE html>
<html>
<head>

<title>Register Smart Library</title>

<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f4f6f9;
    font-family:'Segoe UI',sans-serif;
}

.card{
    border:none;
    border-radius:20px;
    box-shadow:0 5px 20px rgba(0,0,0,0.1);
}

</style>

</head>
<body>

<div class="container mt-5">

<div class="card p-4 col-md-6 mx-auto">

<h3 class="text-center mb-4">
📚 Register Smart Library
</h3>

<?php if($error){ ?>
<div class="alert alert-danger">
<?= $error ?>
</div>
<?php } ?>

<?php if($success){ ?>
<div class="alert alert-success">
<?= $success ?>
</div>
<?php } ?>

<form method="POST">

<div class="mb-3">

<label>Nama Lengkap</label>

<input
type="text"
name="nama_lengkap"
class="form-control"
required>

</div>

<div class="mb-3">

<label>Username</label>

<input
type="text"
name="username"
class="form-control"
required>

</div>

<div class="mb-3">

<label>Password</label>

<input type="password" name="password" class="form-control" required></div>
<div class="mb-3">
<label>Konfirmasi Password</label>
<input type="password" name="konfirmasi_password" class="form-control" required></div>

<div class="mb-3">

<label>Role</label>

<select
name="role"
id="role"
class="form-control"
required>

<option value="siswa">
Siswa
</option>

<option
value="petugas"
<?= ($jmlPetugas >= 3) ? 'disabled' : '' ?>>

Petugas
<?= ($jmlPetugas >= 3) ? '(Penuh)' : '' ?>

</option>

</select>

</div>

<div
class="mb-3"
id="kodeBox"
style="display:none;">

<label>Kode Petugas</label>

<input
type="text"
name="kode_petugas"
class="form-control">

</div>

<button
type="submit"
name="daftar"
class="btn btn-success w-100">

Daftar

</button>

</form>

<hr>

<a
href="login.php"
class="btn btn-primary w-100">

Login

</a>

</div>

</div>

<script>

document
.getElementById("role")
.addEventListener("change",function(){

    if(this.value=="petugas"){

        document
        .getElementById("kodeBox")
        .style.display="block";

    }else{

        document
        .getElementById("kodeBox")
        .style.display="none";

    }

});

</script>

</body>
</html>