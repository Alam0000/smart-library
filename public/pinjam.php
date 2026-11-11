<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

include '../connection/koneksi.php';


/* =====================================================
   CEK LOGIN
===================================================== */

if (!isset($_SESSION['login'])) {

    $id_buku_redirect = isset($_GET['id'])
        ? (int) $_GET['id']
        : 0;

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Login Diperlukan - Smart Library</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {

            min-height: 100vh;

            font-family: 'Poppins', sans-serif;

            background: linear-gradient(
                135deg,
                #f8f2ff 0%,
                #d8b4fe 25%,
                #af69ef 55%,
                #9333ea 100%
            );

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 30px;
        }

        .navbar {

            position: fixed;

            top: 20px;
            left: 50%;

            transform: translateX(-50%);

            width: 90%;
            max-width: 950px;

            background: rgba(255,255,255,.92);

            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);

            border: 1px solid rgba(175,105,239,.12);

            border-radius: 60px;

            padding: 12px 25px;

            box-shadow:
                0 10px 30px rgba(0,0,0,.06);

            z-index: 9999;
        }

        .navbar-brand {

            font-size: 20px;

            font-weight: 600;

            color: #af69ef !important;

            letter-spacing: .5px;

            display: flex;
            align-items: center;

            white-space: nowrap;

            text-decoration: none;
        }

        .navbar-brand img {

            width: 30px;
            height: 30px;

            object-fit: contain;

            display: inline-block;
        }

        .navbar-nav {

            align-items: center;
        }

        .nav-link {

            color: #1e293b !important;

            font-weight: 500;

            position: relative;

            margin: 0 10px;

            padding: 8px 5px !important;

            transition: .3s;

            text-decoration: none;

            white-space: nowrap;
        }

        .nav-link:hover {

            color: #af69ef !important;

            transform: translateY(-2px);
        }

        .nav-link::after {

            content: '';

            position: absolute;

            width: 0;
            height: 2px;

            left: 50%;
            bottom: -5px;

            background: #af69ef;

            transition: .3s;
        }

        .nav-link:hover::after {

            width: 100%;

            left: 0;
        }

        .btn-login {

            background: #af69ef !important;

            color: #fff !important;

            border: none;

            border-radius: 50px;

            padding: 10px 25px;

            font-weight: 600;

            transition: .3s;

            text-decoration: none;

            display: inline-block;

            white-space: nowrap;
        }

        .btn-login:hover {

            background: #9c4fe8 !important;

            color: #fff !important;

            transform: translateY(-2px);

            box-shadow:
                0 10px 25px rgba(175,105,239,.35);
        }

        .login-card {

            width: 100%;

            max-width: 500px;

            background: #fff;

            border-radius: 30px;

            padding: 45px 35px;

            text-align: center;

            box-shadow:
                0 20px 60px rgba(15,23,42,.18);
        }

        .login-icon {

            width: 80px;
            height: 80px;

            margin: 0 auto 20px;

            border-radius: 50%;

            background: #f3e8ff;

            color: #af69ef;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 38px;
        }

        .login-card h2 {

            color: #0f172a;

            font-weight: 700;

            margin-bottom: 12px;
        }

        .login-card p {

            color: #64748b;

            line-height: 1.7;

            margin-bottom: 25px;
        }

        .btn-purple {

            background: linear-gradient(
                135deg,
                #af69ef,
                #8b5cf6
            );

            color: #fff !important;

            border: none;

            border-radius: 50px;

            padding: 12px 28px;

            font-weight: 600;

            text-decoration: none;

            display: inline-block;

            transition: .3s;
        }

        .btn-purple:hover {

            transform: translateY(-2px);

            box-shadow:
                0 10px 25px rgba(175,105,239,.35);
        }

        .btn-back {

            background: #f1f5f9;

            color: #475569;

            border: none;

            border-radius: 50px;

            padding: 12px 25px;

            font-weight: 600;

            text-decoration: none;

            display: inline-block;

            margin-left: 8px;
        }

        .btn-back:hover {

            background: #e2e8f0;

            color: #334155;
        }

        @media(max-width: 992px) {

            .navbar {
                width: 94%;
            }

            .nav-link {
                margin: 5px 0;
            }

            .navbar-collapse {
                padding-top: 15px;
            }
        }

        @media(max-width: 768px) {

            .navbar {

                width: 95%;

                padding: 10px 18px;

                border-radius: 30px;
            }

            .navbar-brand {
                font-size: 20px;
            }

            .login-card {
                padding: 35px 25px;
            }

            .btn-back {

                margin-left: 0;

                margin-top: 10px;
            }
        }

    </style>

</head>


<body>


<nav class="navbar navbar-expand-lg">

    <div class="container-fluid">

        <a
            class="navbar-brand"
            href="index.php"
        >

            <img
                src="../image/logo.png"
                alt="Logo Sekolah"
                width="30"
                height="30"
                class="me-2"
            >

            SMART LIBRARY

        </a>


        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#menuSmartLibrary"
        >

            <span class="navbar-toggler-icon"></span>

        </button>


        <div
            class="collapse navbar-collapse"
            id="menuSmartLibrary"
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
                        href="index.php#koleksi"
                        class="nav-link"
                    >
                        Koleksi Buku
                    </a>

                </li>

            </ul>


            <a
                href="../auth/login.php"
                class="btn-login"
            >
                Login
            </a>

        </div>

    </div>

</nav>


<div class="login-card">

    <div class="login-icon">

        <i class="bi bi-lock-fill"></i>

    </div>


    <h2>Login Diperlukan</h2>


    <p>

        Silakan login terlebih dahulu untuk dapat
        mengajukan peminjaman buku di Smart Library.

    </p>


    <div>

        <a
            href="../auth/login.php"
            class="btn-purple"
        >

            <i class="bi bi-box-arrow-in-right me-2"></i>

            Login Sekarang

        </a>


        <a
            href="detail_buku.php?id=<?= $id_buku_redirect ?>"
            class="btn-back"
        >

            Kembali

        </a>

    </div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>

<?php

exit;

}

?>


<?php

/* =====================================================
   CEK ID BUKU
===================================================== */

if (
    !isset($_GET['id'])
    ||
    !is_numeric($_GET['id'])
) {

    die("ID buku tidak ditemukan.");
}

$id_buku = (int) $_GET['id'];


/* =====================================================
   CEK SESSION USER
===================================================== */

if (
    !isset($_SESSION['id_user'])
    ||
    !is_numeric($_SESSION['id_user'])
) {

    die("Session id_user belum ditemukan.");
}

$id_user = (int) $_SESSION['id_user'];


/* =====================================================
   CEK KONEKSI DATABASE
===================================================== */

if (!$koneksi) {

    die("Koneksi database gagal.");
}


/* =====================================================
   CEK STATUS AKUN
===================================================== */

$stmtUser = $koneksi->prepare("
    SELECT
        status_akun
    FROM users
    WHERE id = ?
    LIMIT 1
");

if ($stmtUser) {

    $stmtUser->bind_param(
        "i",
        $id_user
    );

    $stmtUser->execute();

    $resultUser = $stmtUser->get_result();
    $user = $resultUser->fetch_assoc();

    if (!$user) {
        die("Data pengguna tidak ditemukan.");
    }

    $status_akun = strtolower(
        trim($user['status_akun'] ?? '')
    );


    /* =================================================
       AKUN DIBLOKIR
    ================================================= */

    if ($status_akun === 'diblokir') {

        echo "
        <script>
            alert(
                '⛔ AKUN DIBLOKIR\\n\\n' +
                'Akun Anda sedang diblokir.\\n' +
                'Silakan hubungi petugas perpustakaan.'
            );

            window.location = 'buku_saya.php';
        </script>
        ";

        exit;
    }


    /* =================================================
       AKUN SUSPEND
    ================================================= */

    if ($status_akun === 'suspend') {

        echo "
        <script>
            alert(
                '🔒 AKUN DITANGGUHKAN\\n\\n' +
                'Akun Anda sedang ditangguhkan karena masih memiliki ' +
                'denda perpustakaan yang belum lunas.\\n\\n' +
                'Silakan lunasi denda terlebih dahulu di perpustakaan.'
            );

            window.location = 'buku_saya.php';
        </script>
        ";

        exit;
    }
}

/* =====================================================
   AMBIL DATA BUKU
===================================================== */

$stmtBuku = $koneksi->prepare("

    SELECT

        id,
        nama_buku,
        stok,
        jumlah_dipinjam

    FROM buku

    WHERE id = ?

    LIMIT 1

");

if (!$stmtBuku) {

    die(
        "Gagal mempersiapkan query buku: "
        . htmlspecialchars($koneksi->error)
    );
}


$stmtBuku->bind_param(
    "i",
    $id_buku
);


if (!$stmtBuku->execute()) {

    die(
        "Gagal mengambil data buku: "
        . htmlspecialchars($stmtBuku->error)
    );
}


$resultBuku = $stmtBuku->get_result();

$buku = $resultBuku->fetch_assoc();


if (!$buku) {

    die(
        "Buku dengan ID "
        . $id_buku
        . " tidak ditemukan."
    );
}


/* =====================================================
   CEK STOK
===================================================== */

if ((int) $buku['stok'] <= 0) {

    echo "

    <script>

        alert('Stok buku sedang habis.');

        window.location='detail_buku.php?id={$id_buku}';

    </script>

    ";

    exit;
}


/* =====================================================
   CEK PEMINJAMAN / PENGAJUAN AKTIF
===================================================== */

$cekPinjam = $koneksi->prepare("

    SELECT

        id,
        status,
        tanggal_pinjam,
        tanggal_kembali,
        denda,
        status_bayar

    FROM peminjaman

    WHERE id_user = ?

      AND id_buku = ?

      AND status IN (

          'menunggu',
          'disetujui',
          'dipinjam',
          'terlambat'

      )

    ORDER BY id DESC

    LIMIT 1

");


if (!$cekPinjam) {

    die(

        "Gagal mempersiapkan pengecekan peminjaman: "
        . htmlspecialchars($koneksi->error)

    );

}


$cekPinjam->bind_param(

    "ii",

    $id_user,
    $id_buku

);


if (!$cekPinjam->execute()) {

    die(

        "Gagal mengecek peminjaman: "
        . htmlspecialchars($cekPinjam->error)

    );

}


$resultCek = $cekPinjam->get_result();


/* =====================================================
   JIKA SUDAH ADA PEMINJAMAN AKTIF
===================================================== */

if ($resultCek->num_rows > 0) {

    $dataCek = $resultCek->fetch_assoc();


    $idPinjamLama =
        (int) $dataCek['id'];

    $statusLama =
        strtolower(
            trim($dataCek['status'])
        );

    $tanggalPinjam =
        $dataCek['tanggal_pinjam'];

    $tanggalKembali =
        $dataCek['tanggal_kembali'];

    $dendaLama =
        (int) ($dataCek['denda'] ?? 0);


    /* =================================================
       CEK JATUH TEMPO

       Jika status dipinjam/disetujui
       dan tanggal kembali sudah lewat,
       otomatis menjadi TERLAMBAT.
    ================================================= */

    if (

        in_array(
            $statusLama,
            ['dipinjam', 'disetujui'],
            true
        )

        && !empty($tanggalKembali)

        && strtotime($tanggalKembali)
            < strtotime(date('Y-m-d'))

    ) {


        /* =============================================
           HITUNG HARI TERLAMBAT
        ============================================= */

        $hariTerlambat = floor(

            (
                strtotime(date('Y-m-d'))
                -
                strtotime($tanggalKembali)

            ) / 86400

        );


        if ($hariTerlambat < 1) {

            $hariTerlambat = 1;

        }


        /* =============================================
           DENDA

           Rp1.000 per hari
        ============================================= */

        $dendaBaru =
            $hariTerlambat * 1000;


        /* =============================================
           UPDATE DATABASE

           dipinjam → terlambat
        ============================================= */

        $updateTerlambat = $koneksi->prepare("

            UPDATE peminjaman

            SET

                status = 'terlambat',

                denda = ?,

                status_bayar = 'belum_bayar'

            WHERE id = ?

              AND status IN (
                  'dipinjam',
                  'disetujui'
              )

              AND tgl_kembali_aktual IS NULL

        ");


        if ($updateTerlambat) {

            $updateTerlambat->bind_param(

                "ii",

                $dendaBaru,
                $idPinjamLama

            );

            $updateTerlambat->execute();

            $updateTerlambat->close();

        }


        /* =============================================
           PESAN KE SISWA
        ============================================= */

        $pesan =

            "⚠️ PEMINJAMAN SUDAH TERLAMBAT!\n\n"

            .

            "Buku: "
            . $buku['nama_buku']
            . "\n\n"

            .

            "Batas pengembalian: "
            .
            date(
                'd-m-Y',
                strtotime($tanggalKembali)
            )

            .

            "\n"

            .

            "Terlambat: "
            .
            $hariTerlambat
            .
            " hari"

            .

            "\n"

            .

            "Denda saat ini: Rp"
            .
            number_format(
                $dendaBaru,
                0,
                ',',
                '.'
            )

            .

            "\n\n"

            .

            "Silakan segera kembalikan buku dan selesaikan denda di perpustakaan.";


        echo "

        <script>

            alert(
                " . json_encode($pesan) . "
            );

            window.location =
                'detail_buku.php?id={$id_buku}';

        </script>

        ";

        exit;

    }


    /* =================================================
       STATUS MENUNGGU
    ================================================= */

    if ($statusLama === 'menunggu') {

        $pesan =

            "⏳ PENGAJUAN MASIH MENUNGGU\n\n"

            .

            "Pengajuan peminjaman buku ini masih menunggu "
            .
            "persetujuan petugas perpustakaan.";

    }


    /* =================================================
       STATUS DISETUJUI
    ================================================= */

    elseif ($statusLama === 'disetujui') {

        $pesan =

            "✅ PEMINJAMAN DISETUJUI\n\n"

            .

            "Pengajuan buku ini sudah disetujui petugas.";

    }


    /* =================================================
       STATUS TERLAMBAT
    ================================================= */

    elseif ($statusLama === 'terlambat') {

        $pesan =

            "⚠️ PEMINJAMAN SUDAH TERLAMBAT!\n\n"

            .

            "Buku: "
            .
            $buku['nama_buku']

            .

            "\n\n"

            .

            "Batas pengembalian: "
            .
            (
                !empty($tanggalKembali)
                ?
                date(
                    'd-m-Y',
                    strtotime($tanggalKembali)
                )
                :
                '-'
            )

            .

            "\n"

            .

            "Denda saat ini: Rp"
            .
            number_format(
                $dendaLama,
                0,
                ',',
                '.'
            )

            .

            "\n\n"

            .

            "Silakan segera kembalikan buku dan selesaikan denda di perpustakaan.";

    }


    /* =================================================
       STATUS DIPINJAM
       BELUM JATUH TEMPO
    ================================================= */

    else {

        $pesan =

            "📚 BUKU MASIH ANDA PINJAM\n\n"

            .

            "Buku: "
            .
            $buku['nama_buku']

            .

            "\n\n"

            .

            "Batas pengembalian: "
            .

            (
                !empty($tanggalKembali)
                ?
                date(
                    'd-m-Y',
                    strtotime($tanggalKembali)
                )
                :
                '-'
            )

            .

            "\n\n"

            .

            "Silakan kembalikan buku sebelum batas waktu.";

    }


    /* =================================================
       TAMPILKAN PESAN
    ================================================= */

    echo "

    <script>

        alert(
            " . json_encode($pesan) . "
        );

        window.location =
            'detail_buku.php?id={$id_buku}';

    </script>

    ";

    exit;
}


/* =====================================================
   TANGGAL PENGAJUAN
===================================================== */

$tgl_pengajuan =
    date('Y-m-d');

$status =
    'menunggu';

$status_bayar =
    '-';


/* =====================================================
   INSERT PENGAJUAN

   BELUM DISETUJUI:
   - tanggal_pinjam = NULL
   - tanggal_kembali = NULL
   - denda = 0
   - stok TIDAK DIKURANGI
===================================================== */

$koneksi->begin_transaction();


try {


    $stmt = $koneksi->prepare("

        INSERT INTO peminjaman

        (

            id_user,

            id_buku,

            tgl_pengajuan,

            tanggal_pinjam,

            tanggal_kembali,

            tgl_kembali_aktual,

            denda,

            status_bayar,

            batas_bayar_denda,

            bukti_bayar,

            tgl_bayar,

            status,

            id_petugas_acc,

            tgl_acc,

            catatan_tolak

        )

        VALUES

        (

            ?,

            ?,

            ?,

            NULL,

            NULL,

            NULL,

            0,

            ?,

            NULL,

            NULL,

            NULL,

            ?,

            NULL,

            NULL,

            NULL

        )

    ");


    if (!$stmt) {

        throw new Exception(

            "Prepare INSERT pengajuan gagal: "
            .
            $koneksi->error

        );

    }


    $stmt->bind_param(

        "iisss",

        $id_user,

        $id_buku,

        $tgl_pengajuan,

        $status_bayar,

        $status

    );


    if (!$stmt->execute()) {

        throw new Exception(

            "INSERT pengajuan gagal: "
            .
            $stmt->error

        );

    }


    $stmt->close();


    /* =================================================
       COMMIT

       STOK TIDAK DIKURANGI
    ================================================= */

    $koneksi->commit();


    /* =================================================
       BERHASIL
    ================================================= */

    echo "

    <script>

        alert(

            'Pengajuan peminjaman berhasil dikirim!\\n\\n' +

            'Status: Menunggu persetujuan petugas.\\n\\n' +

            'Silakan menunggu petugas perpustakaan menyetujui pengajuan Anda.'

        );

        window.location =
            'buku_saya.php';

    </script>

    ";

    exit;


} catch (Exception $e) {


    $koneksi->rollback();

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Gagal Mengajukan Peminjaman</title>


    <style>

        body {

            margin: 0;

            padding: 40px;

            font-family: Arial, sans-serif;

            background: #f8fafc;
        }


        .error-box {

            max-width: 800px;

            margin: 50px auto;

            padding: 30px;

            background: #fff;

            border: 1px solid #fecaca;

            border-radius: 15px;

            box-shadow:
                0 10px 30px rgba(0,0,0,.08);
        }


        h2 {

            color: #dc2626;
        }


        .info {

            background: #fef2f2;

            padding: 15px;

            border-radius: 10px;

            margin: 15px 0;
        }


        a {

            display: inline-block;

            padding: 10px 18px;

            background: #8b5cf6;

            color: white;

            text-decoration: none;

            border-radius: 8px;
        }


        code {

            background: #f1f5f9;

            padding: 3px 6px;

            border-radius: 5px;
        }

    </style>

</head>


<body>


<div class="error-box">

    <h2>
        Gagal Mengajukan Peminjaman
    </h2>


    <div class="info">

        <p>

            <strong>ID User:</strong>

            <?= htmlspecialchars(
                (string) $id_user
            ) ?>

        </p>


        <p>

            <strong>ID Buku:</strong>

            <?= htmlspecialchars(
                (string) $id_buku
            ) ?>

        </p>


        <p>

            <strong>Error:</strong>

            <?= htmlspecialchars(
                $e->getMessage()
            ) ?>

        </p>

    </div>


    <a
        href="detail_buku.php?id=<?= $id_buku ?>"
    >

        Kembali ke Detail Buku

    </a>

</div>


</body>

</html>

<?php

}

?>