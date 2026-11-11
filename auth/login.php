<?php
session_start();
include '../connection/koneksi.php';

$error = "";
$success = "";

$kode_rahasia = "PETUGAS123";

/* =========================
   HITUNG JUMLAH PETUGAS
   ========================= */
$qPetugas = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) as total
     FROM users
     WHERE role='petugas'"
);

$jmlPetugas = mysqli_fetch_assoc($qPetugas)['total'];


/* =========================
   PROSES LOGIN
   ========================= */
if (isset($_POST['login'])) {

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $koneksi->prepare("
        SELECT *
        FROM users
        WHERE username = ?
    ");

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {

        /*
        |--------------------------------------------------------------------------
        | CEK STATUS AKUN SISWA
        |--------------------------------------------------------------------------
        | Jika akun suspend, jangan buat session login.
        | Siswa tetap berada di halaman login.
        */
        if (
            $user['role'] === 'siswa' &&
            isset($user['status_akun']) &&
            $user['status_akun'] === 'suspend'
        ) {

            $error = "Akun Anda Ditangguhkan. Silakan lunasi denda perpustakaan terlebih dahulu.";

        } else {

            // Login berhasil
            session_regenerate_id(true);

            $_SESSION['login']    = true;
            $_SESSION['id_user']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            // Supaya kompatibel dengan halaman admin yang mungkin memakai $_SESSION['id']
            $_SESSION['id'] = $user['id'];
            require_once __DIR__ . '/../lib/audit.php';
            catatAudit($koneksi,'login','users',$user['id'],'Login sebagai '.$user['role']);

            if ($user['role'] === "petugas") {

                header("Location: ../Admin/dashboard.php");

            } else {

                header("Location: ../public/index.php");

            }

            exit;
        }

    } else {

        $error = "Username atau password salah!";

    }
}

/* =========================
   PROSES REGISTRASI
   ========================= */
if (isset($_POST['daftar'])) {

    $nama       = trim($_POST['nama_lengkap']);
    $username   = trim($_POST['reg_username']);
    $password   = $_POST['reg_password'];
    $konfirmasi = $_POST['konfirmasi_password'];
    $role       = $_POST['role'];
    $kode       = $_POST['kode_petugas'] ?? '';

    if ($password != $konfirmasi) {

        $error = "Konfirmasi password tidak sesuai!";

    } else {

        $cek = $koneksi->prepare("
            SELECT id
            FROM users
            WHERE username=?
        ");

        $cek->bind_param("s", $username);
        $cek->execute();

        if ($cek->get_result()->num_rows > 0) {

            $error = "Username sudah digunakan!";

        } else {

            if ($role == "petugas") {

                if ($jmlPetugas >= 3) {

                    $error = "Kuota petugas sudah penuh!";

                } elseif ($kode != $kode_rahasia) {

                    $error = "Kode petugas salah!";
                }
            }

            if (empty($error)) {

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

                if ($stmt->execute()) {

                    $success = "Registrasi berhasil!";

                } else {

                    $error = "Gagal registrasi!";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <script
        src="https://kit.fontawesome.com/64d58efce2.js"
        crossorigin="anonymous">
    </script>

    <link rel="stylesheet" href="style.css">

    <title>Smart Library</title>


    <!-- =========================
         PENYESUAIAN PANEL MOBILE
         ========================= -->

    <style>

        @media (max-width: 768px) {

            /* PANEL UNGU */
            .panels-container {
                height: 250px !important;
                min-height: 250px !important;
            }

            .panel {
                height: 250px !important;
                min-height: 250px !important;
                padding: 20px 15px !important;
                box-sizing: border-box;
            }
            


            /* KONTEN PANEL */
            .panel .content {
                width: 100% !important;
                max-width: 230px !important;
                margin: 0 auto !important;
                text-align: center;
                position: relative;
                z-index: 10;
            }


            /* JUDUL */
            .panel .content h3 {
                font-size: 20px !important;
                line-height: 1.1 !important;
                margin: 0 0 8px 0 !important;
                transform: none !important;
            }


            /* DESKRIPSI */
            .panel .content p {
                font-size: 11px !important;
                line-height: 1.4 !important;
                margin: 5px auto 8px !important;
                max-width: 200px;
            }


            /* BUTTON SIGN UP / SIGN IN */
            .panel .content .btn {
                width: 105px !important;
                height: 32px !important;
                min-height: 32px !important;
                padding: 5px 10px !important;
                font-size: 10px !important;
                margin: 5px auto !important;
                display: block;
            }


            /* LOGO */
            .panel .image {
                width: 145px !important;
                height: auto !important;
                display: block !important;
                margin: 5px auto 0 auto !important;
                position: relative;
                z-index: 5;
            }


            /* KHUSUS PANEL KIRI */
            .left-panel .content {
                margin-top: 0 !important;
            }


            /* KHUSUS PANEL KANAN */
            .right-panel .content {
                margin-top: 0 !important;
            }

        }


        /* HP LEBIH KECIL */
        @media (max-width: 480px) {

            .panels-container {
                height: 235px !important;
                min-height: 235px !important;
            }

            .panel {
                height: 235px !important;
                min-height: 235px !important;
                padding: 15px 10px !important;
            }

            .panel .content {
                max-width: 210px !important;
            }

            .panel .content h3 {
                font-size: 18px !important;
            }

            .panel .content p {
                font-size: 10px !important;
                line-height: 1.3 !important;
                max-width: 185px;
            }

            .panel .content .btn {
                width: 95px !important;
                height: 29px !important;
                min-height: 29px !important;
                font-size: 9px !important;
            }

            .panel .image {
                width: 130px !important;
                margin-top: 3px !important;
            }

        }
        /* =========================================
   KUNCI GAMBAR AGAR TIDAK KELUAR PANEL
   ========================================= */

@media (max-width: 768px) {

    .panels-container {
        overflow: hidden !important;
    }

    .panel {
        position: relative !important;
        overflow: hidden !important;
    }

    /* GAMBAR PANEL SIGN UP */
    .left-panel .image {
        position: absolute !important;
        width: 125px !important;
        height: auto !important;

        top: 12px !important;
        right: 15px !important;
        bottom: auto !important;
        left: auto !important;

        margin: 0 !important;
        transform: none !important;

        z-index: 5 !important;
    }

    /* GAMBAR PANEL SIGN IN */
    .right-panel .image {
        position: absolute !important;
        width: 125px !important;
        height: auto !important;

        top: 12px !important;
        left: 15px !important;
        right: auto !important;
        bottom: auto !important;

        margin: 0 !important;
        transform: none !important;

        z-index: 5 !important;
    }
}


/* =========================================
   HP KECIL
   ========================================= */

@media (max-width: 480px) {

    .left-panel .image {
        width: 110px !important;
        top: 10px !important;
        right: 8px !important;
    }

    .right-panel .image {
        width: 110px !important;
        top: 10px !important;
        left: 8px !important;
    }

}

/* TOMBOL SIGN IN KHUSUS MOBILE */
.mobile-signin {
    display: none;
}

@media (max-width: 768px) {
    .mobile-signin {
        display: block !important;
        width: 105px !important;
        height: 32px !important;
        margin: 8px auto 0 !important;

        border: 2px solid #5995fd !important;
        border-radius: 25px !important;

        background: transparent !important;
        color: #5995fd !important;

        font-size: 10px !important;
        font-weight: 600 !important;

        cursor: pointer !important;
        position: relative !important;
        z-index: 9999 !important;
    }

    .mobile-signin:hover {
        background: #5995fd !important;
        color: white !important;
    }
}

@media (max-width: 480px) {
    .mobile-signin {
        width: 95px !important;
        height: 29px !important;
        margin-top: 6px !important;
        font-size: 9px !important;
    }
}
/* =========================================
   KHUSUS TOMBOL SIGN IN DI FORM SIGN UP
   JANGAN UBAH CONTAINER / PANEL UNGU
   ========================================= */

/* SIGN IN DI BAWAH SIGN UP - MOBILE */

@media (max-width: 768px) {

    .sign-up-form .mobile-signin {

        position: relative !important;

        z-index: 99999 !important;

        transform: translateY(-15px) !important;

        /* WARNA SAMA SEPERTI SIGN UP */
        background: #b100ff !important;

        color: white !important;

        border: none !important;

        border-radius: 30px !important;

    }

}

@media (max-width: 480px) {

    .sign-up-form .mobile-signin {

        transform: translateY(-20px) !important;

    }

}

    </style>

</head>


<body>

<div class="container">

    <!-- =========================
         FORM CONTAINER
         ========================= -->

    <div class="forms-container">

        <div class="signin-signup">


            <!-- =========================
                 LOGIN
                 ========================= -->

            <form method="POST" class="sign-in-form">

                <h2 class="title">
                    Sign In
                </h2>

                <?php if ($error) { ?>

                    <p style="color:red;text-align:center;">
                        <?= htmlspecialchars($error) ?>
                    </p>

                <?php } ?>


                <div class="input-field">

                    <i class="fas fa-user"></i>

                    <input
                        type="text"
                        name="username"
                        placeholder="Username"
                        required
                    />

                </div>


                <div class="input-field">

                    <i class="fas fa-lock"></i>

                    <input
                        type="password"
                        name="password"
                        placeholder="Password"
                        required
                    />

                </div>


                <input
                    type="submit"
                    name="login"
                    value="Login"
                    class="btn solid"
                />

            </form>



            <!-- =========================
                 REGISTER
                 ========================= -->

            <form method="POST" class="sign-up-form">

                <h2 class="title">
                    Sign Up
                </h2>

                <?php if ($success) { ?>

                    <p style="color:green;text-align:center;">
                        <?= htmlspecialchars($success) ?>
                    </p>

                <?php } ?>


                <div class="input-field">

                    <i class="fas fa-id-card"></i>

                    <input
                        type="text"
                        name="nama_lengkap"
                        placeholder="Nama Lengkap"
                        required
                    />

                </div>


                <div class="input-field">

                    <i class="fas fa-user"></i>

                    <input
                        type="text"
                        name="reg_username"
                        placeholder="Username"
                        required
                    />

                </div>


                <div class="input-field">

                    <i class="fas fa-lock"></i>

                    <input
                        type="password"
                        name="reg_password"
                        placeholder="Password"
                        required
                    />

                </div>


                <div class="input-field">

                    <i class="fas fa-lock"></i>

                    <input
                        type="password"
                        name="konfirmasi_password"
                        placeholder="Konfirmasi Password"
                        required
                    />

                </div>


                <div class="input-field">

                    <i class="fas fa-user-tag"></i>

                    <select
                        name="role"
                        id="role"
                        required
                        style="
                            width:100%;
                            border:none;
                            outline:none;
                            background:none;
                            font-size:1rem;
                        "
                    >

                        <option value="siswa">
                            Siswa
                        </option>

                        <option
                            value="petugas"
                            <?= ($jmlPetugas >= 3) ? 'disabled' : '' ?>
                        >

                            Petugas
                            <?= ($jmlPetugas >= 3) ? '(Penuh)' : '' ?>

                        </option>

                    </select>

                </div>


                <div
                    class="input-field"
                    id="kodeBox"
                    style="display:none;"
                >

                    <i class="fas fa-key"></i>

                    <input
                        type="text"
                        name="kode_petugas"
                        placeholder="Kode Petugas"
                    />

                </div>


                <input
    type="submit"
    name="daftar"
    class="btn"
    value="Sign Up"
/>

<button
    type="button"
    class="mobile-signin"
    id="mobile-signin"
>
    Sign In
</button>

            </form>

        </div>

    </div>



    <!-- =========================
         PANEL
         ========================= -->

    <div class="panels-container">


        <!-- =========================
             PANEL KIRI
             ========================= -->

        <div class="panel left-panel">

            <div class="content">

                <h3>
                    Belum Punya Akun?
                </h3>

                <p>
                    Daftar sekarang untuk menggunakan
                    Smart Library dan mendapatkan
                    rekomendasi buku terbaik.
                </p>

                <button
                    class="btn transparent"
                    id="sign-up-btn"
                    type="button"
                >
                    Sign Up
                </button>

            </div>


            <img
                src="../assets/img/bg1.png"
                class="image"
                alt="Logo SMP Negeri 4 Solear"
            />

        </div>



        <!-- =========================
             PANEL KANAN
             ========================= -->

        <div class="panel right-panel">

            <div class="content">

                <h3>
                    Sudah Punya Akun?
                </h3>

                <p>
                    Masuk menggunakan akun yang telah
                    terdaftar untuk mengakses sistem.
                </p>

                <button
                    class="btn transparent"
                    id="sign-in-btn"
                    type="button"
                >
                    Sign In
                </button>

            </div>


            <img
                src="../assets/img/bg1.png"
                class="image"
                alt="Logo SMP Negeri 4 Solear"
            />

        </div>

    </div>

</div>


<script src="app.js"></script>


<script>

const role = document.getElementById("role");

if (role) {

    role.addEventListener("change", function () {

        const kodeBox = document.getElementById("kodeBox");

        if (this.value === "petugas") {

            kodeBox.style.display = "grid";

        } else {

            kodeBox.style.display = "none";

        }

    });

}
// Tombol Sign In di bawah Sign Up khusus mobile
const mobileSignin = document.getElementById("mobile-signin");
const signinBtn = document.getElementById("sign-in-btn");

if (mobileSignin && signinBtn) {
    mobileSignin.addEventListener("click", function () {
        signinBtn.click();
    });
}

</script>


</body>
</html>