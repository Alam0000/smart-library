<?php
session_start();
include '../connection/koneksi.php';

/* =====================================================
   CEK LOGIN
===================================================== */
if (!isset($_SESSION['login'])) {
    echo "
    <script>
        alert('Silakan login terlebih dahulu.');
        window.location='../auth/login.php';
    </script>
    ";
    exit;
}

/* =====================================================
   DATA USER
===================================================== */
$id_user = isset($_SESSION['id_user'])
    ? (int) $_SESSION['id_user']
    : 0;

$username = $_SESSION['username'] ?? '';
$role     = $_SESSION['role'] ?? '';

/* =====================================================
   CEK ID USER
===================================================== */
if ($id_user <= 0) {
    die("
        <div style='
            font-family:Arial;
            padding:30px;
            text-align:center;
        '>
            <h3>Session user tidak ditemukan.</h3>
            <p>Silakan logout dan login kembali.</p>
            <a href='../auth/login.php'>Login Kembali</a>
        </div>
    ");
}

/* =====================================================
   CEK ID BUKU
===================================================== */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "
    <script>
        alert('Buku tidak ditemukan.');
        window.location='buku_saya.php';
    </script>
    ";
    exit;
}

$id_buku = (int) $_GET['id'];

/* =====================================================
   AMBIL DATA BUKU
===================================================== */
$stmt_buku = $koneksi->prepare("
    SELECT id, nama_buku, penulis, gambar
    FROM buku
    WHERE id = ?
");

$stmt_buku->bind_param("i", $id_buku);
$stmt_buku->execute();

$result_buku = $stmt_buku->get_result();
$buku = $result_buku->fetch_assoc();

if (!$buku) {
    echo "
    <script>
        alert('Data buku tidak ditemukan.');
        window.location='buku_saya.php';
    </script>
    ";
    exit;
}

/* =====================================================
   SIMPAN RATING
===================================================== */
if (isset($_POST['simpan'])) {

    $rating   = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;
    $komentar = trim($_POST['komentar'] ?? '');

    /* VALIDASI RATING */
    if ($rating < 1 || $rating > 5) {
        echo "
        <script>
            alert('Rating harus antara 1 sampai 5.');
        </script>
        ";
    } else {

        /* CEK APAKAH SUDAH PERNAH RATING */
        $stmt_cek = $koneksi->prepare("
            SELECT id_rating
            FROM rating_buku
            WHERE id_buku = ?
            AND id_user = ?
        ");

        $stmt_cek->bind_param(
            "ii",
            $id_buku,
            $id_user
        );

        $stmt_cek->execute();

        $cek = $stmt_cek->get_result();

        if ($cek->num_rows > 0) {

            echo "
            <script>
                alert('Anda sudah memberi rating untuk buku ini.');
                window.location='buku_saya.php';
            </script>
            ";
            exit;

        } else {

            /* INSERT RATING */
            $stmt = $koneksi->prepare("
                INSERT INTO rating_buku
                (id_buku, id_user, rating, komentar)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "iiis",
                $id_buku,
                $id_user,
                $rating,
                $komentar
            );

            if ($stmt->execute()) {

                echo "
                <script>
                    alert('Rating berhasil disimpan.');
                    window.location='buku_saya.php';
                </script>
                ";
                exit;

            } else {

                echo "
                <script>
                    alert('Rating gagal disimpan.');
                </script>
                ";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Rating Buku - Smart Library</title>

    <!-- BOOTSTRAP -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- BOOTSTRAP ICON -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
    >

    <!-- GOOGLE FONT -->
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
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            color: #334155;

            background:
                linear-gradient(
                    135deg,
                    #f8f2ff 0%,
                    #d8b4fe 25%,
                    #af69ef 55%,
                    #9333ea 100%
                );

            padding-top: 120px;
        }

        /* =====================================================
           NAVBAR
        ===================================================== */

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

        /* =====================================================
           LOGO
        ===================================================== */

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

        /* =====================================================
           MENU
        ===================================================== */

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

        /* =====================================================
           USER BUTTON
        ===================================================== */

        .user-btn {
            background: #f3e8ff !important;

            color: #af69ef !important;

            border: none;

            border-radius: 50px;

            padding: 10px 20px;

            font-weight: 600;

            transition: .3s;

            white-space: nowrap;
        }

        .user-btn:hover {
            background: #e9d5ff !important;

            color: #9c4fe8 !important;
        }

        /* =====================================================
           DROPDOWN
        ===================================================== */

        .dropdown-menu {
            border: none;

            border-radius: 16px;

            padding: 8px;

            margin-top: 10px !important;

            box-shadow:
                0 10px 30px rgba(0,0,0,.12);
        }

        .dropdown-item {
            border-radius: 10px;

            padding: 10px 14px;

            font-size: 14px;

            transition: .2s;
        }

        .dropdown-item:hover {
            background: #f3e8ff;

            color: #af69ef;
        }

        /* =====================================================
           CONTAINER
        ===================================================== */

        .page-container {
            width: 92%;
            max-width: 850px;

            margin: auto;
        }

        /* =====================================================
           CARD
        ===================================================== */

        .rating-card {
            background: rgba(255,255,255,.96);

            border-radius: 28px;

            padding: 35px;

            box-shadow:
                0 15px 40px rgba(15,23,42,.10);

            border: 1px solid rgba(175,105,239,.10);
        }

        /* =====================================================
           HEADER
        ===================================================== */

        .rating-header {
            text-align: center;

            margin-bottom: 30px;
        }

        .rating-icon {
            width: 75px;
            height: 75px;

            margin: 0 auto 15px;

            border-radius: 22px;

            background: #fef3c7;

            color: #f59e0b;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 35px;
        }

        .rating-header h2 {
            margin: 0;

            color: #0f172a;

            font-weight: 700;

            font-size: 26px;
        }

        .rating-header p {
            color: #64748b;

            font-size: 14px;

            margin-top: 6px;
        }

        /* =====================================================
           BUKU
        ===================================================== */

        .book-info {
            display: flex;

            align-items: center;

            gap: 20px;

            background: #faf5ff;

            border-radius: 18px;

            padding: 18px;

            margin-bottom: 25px;
        }

        .book-cover {
            width: 75px;
            height: 100px;

            object-fit: cover;

            border-radius: 10px;

            box-shadow:
                0 5px 15px rgba(15,23,42,.12);
        }

        .no-cover {
            width: 75px;
            height: 100px;

            border-radius: 10px;

            background: #f3e8ff;

            color: #af69ef;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 30px;

            flex-shrink: 0;
        }

        .book-info h4 {
            margin: 0 0 5px;

            color: #0f172a;

            font-size: 18px;

            font-weight: 600;
        }

        .book-info p {
            margin: 0;

            color: #64748b;

            font-size: 13px;
        }

        /* =====================================================
           FORM
        ===================================================== */

        .form-label {
            color: #0f172a;

            font-weight: 600;

            font-size: 14px;
        }

        .form-select,
        .form-control {
            border: 1px solid #e2e8f0;

            border-radius: 12px;

            padding: 12px 14px;

            font-size: 14px;
        }

        .form-select:focus,
        .form-control:focus {
            border-color: #af69ef;

            box-shadow:
                0 0 0 .2rem rgba(175,105,239,.15);
        }

        textarea.form-control {
            min-height: 130px;

            resize: vertical;
        }

        /* =====================================================
           BUTTON
        ===================================================== */

        .button-wrapper {
            display: flex;

            gap: 12px;

            margin-top: 25px;
        }

        .btn-save {
            flex: 1;

            background:
                linear-gradient(
                    135deg,
                    #af69ef,
                    #8b5cf6
                );

            color: #fff;

            border: none;

            border-radius: 50px;

            padding: 12px 20px;

            font-weight: 600;

            transition: .3s;
        }

        .btn-save:hover {
            color: #fff;

            transform: translateY(-2px);

            box-shadow:
                0 10px 25px rgba(175,105,239,.35);
        }

        .btn-cancel {
            flex: 1;

            background: #f1f5f9;

            color: #475569;

            border: none;

            border-radius: 50px;

            padding: 12px 20px;

            font-weight: 600;

            text-decoration: none;

            text-align: center;

            transition: .3s;
        }

        .btn-cancel:hover {
            background: #e2e8f0;

            color: #334155;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media(max-width:992px) {

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

        @media(max-width:768px) {

            body {
                padding-top: 105px;
            }

            .navbar {
                width: 95%;

                padding: 10px 18px;

                border-radius: 30px;
            }

            .navbar-brand {
                font-size: 20px;
            }

            .page-container {
                width: 94%;
            }

            .rating-card {
                padding: 22px;

                border-radius: 22px;
            }

            .book-info {
                align-items: flex-start;
            }

            .button-wrapper {
                flex-direction: column;
            }

        }

    </style>

</head>

<body>

<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="navbar navbar-expand-lg">

    <div class="container-fluid">

        <!-- LOGO -->

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


        <!-- MOBILE -->

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#menuSmartLibrary"
            aria-controls="menuSmartLibrary"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >

            <span class="navbar-toggler-icon"></span>

        </button>


        <!-- MENU -->

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


                <li class="nav-item">

                    <a
                        href="buku_saya.php"
                        class="nav-link"
                    >
                        Buku Saya
                    </a>

                </li>

            </ul>


            <!-- USER -->

            <div class="dropdown">

                <button
                    class="user-btn dropdown-toggle"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >

                    👤 <?= htmlspecialchars($username) ?>

                </button>


                <ul class="dropdown-menu dropdown-menu-end">

                    <?php if ($role == 'petugas') { ?>

                        <li>

                            <a
                                class="dropdown-item"
                                href="../Admin/dashboard.php"
                            >

                                <i class="bi bi-speedometer2 me-2"></i>

                                Dashboard Petugas

                            </a>

                        </li>


                        <li>

                            <hr class="dropdown-divider">

                        </li>

                    <?php } ?>


                    <li>

                        <a
                            class="dropdown-item"
                            href="../auth/logout.php"
                        >

                            <i class="bi bi-box-arrow-right me-2"></i>

                            Logout

                        </a>

                    </li>

                </ul>

            </div>

        </div>

    </div>

</nav>


<!-- =====================================================
     CONTENT
===================================================== -->

<div class="page-container">

    <div class="rating-card">

        <!-- HEADER -->

        <div class="rating-header">

            <div class="rating-icon">

                <i class="bi bi-star-fill"></i>

            </div>

            <h2>Beri Rating Buku</h2>

            <p>
                Bagikan penilaian dan pendapat kamu tentang buku ini.
            </p>

        </div>


        <!-- INFO BUKU -->

        <div class="book-info">

            <?php if (!empty($buku['gambar'])) { ?>

                <img
                    src="../gambar/<?= htmlspecialchars($buku['gambar']) ?>"
                    class="book-cover"
                    alt="<?= htmlspecialchars($buku['nama_buku']) ?>"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                >

                <div
                    class="no-cover"
                    style="display:none;"
                >
                    <i class="bi bi-book"></i>
                </div>

            <?php } else { ?>

                <div class="no-cover">

                    <i class="bi bi-book"></i>

                </div>

            <?php } ?>


            <div>

                <h4>
                    <?= htmlspecialchars($buku['nama_buku']) ?>
                </h4>

                <p>
                    Penulis:
                    <?= htmlspecialchars($buku['penulis'] ?? '-') ?>
                </p>

            </div>

        </div>


        <!-- FORM -->

        <form method="POST">

            <!-- RATING -->

            <div class="mb-3">

                <label class="form-label">
                    Rating
                </label>

                <select
                    name="rating"
                    class="form-select"
                    required
                >

                    <option value="5">
                        ⭐⭐⭐⭐⭐ — Sangat Bagus
                    </option>

                    <option value="4">
                        ⭐⭐⭐⭐ — Bagus
                    </option>

                    <option value="3">
                        ⭐⭐⭐ — Cukup
                    </option>

                    <option value="2">
                        ⭐⭐ — Kurang
                    </option>

                    <option value="1">
                        ⭐ — Sangat Kurang
                    </option>

                </select>

            </div>


            <!-- KOMENTAR -->

            <div class="mb-3">

                <label class="form-label">
                    Komentar
                </label>

                <textarea
                    name="komentar"
                    class="form-control"
                    placeholder="Tulis komentar kamu tentang buku ini..."
                ></textarea>

            </div>


            <!-- BUTTON -->

            <div class="button-wrapper">

                <a
                    href="buku_saya.php"
                    class="btn-cancel"
                >
                    <i class="bi bi-arrow-left me-1"></i>
                    Kembali
                </a>


                <button
                    type="submit"
                    name="simpan"
                    class="btn-save"
                >

                    <i class="bi bi-star-fill me-1"></i>

                    Simpan Rating

                </button>

            </div>

        </form>

    </div>

</div>


<!-- =====================================================
     BOOTSTRAP JS
===================================================== -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>