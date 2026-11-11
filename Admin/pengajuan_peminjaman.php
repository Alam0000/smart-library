<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../lib/auth.php';
wajibPetugas('../auth/login.php');


include '../connection/koneksi.php';

/* =====================================================
   CEK LOGIN PETUGAS
===================================================== */

if (!isset($_SESSION['login'])) {
    header("Location: ../auth/login.php");
    exit;
}

$id_petugas = isset($_SESSION['id_user'])
    ? (int) $_SESSION['id_user']
    : 0;

$username = $_SESSION['username'] ?? 'Petugas Perpustakaan';


/* =====================================================
   VALIDASI SESSION PETUGAS
===================================================== */

if ($id_petugas <= 0) {
    die("Session id_user petugas tidak ditemukan.");
}


/* =====================================================
   PROSES SETUJUI / TOLAK
===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $aksi = $_POST['aksi'] ?? '';
    $id_peminjaman = isset($_POST['id_peminjaman'])
        ? (int) $_POST['id_peminjaman']
        : 0;


    if ($id_peminjaman <= 0) {

        $_SESSION['pesan_error'] =
            "ID peminjaman tidak valid.";

        header("Location: pengajuan_peminjaman.php");
        exit;
    }


    /* =================================================
       AKSI SETUJUI
    ================================================= */

    if ($aksi === 'setujui') {

        $koneksi->begin_transaction();

        try {

            /* =============================================
               AMBIL DATA PEMINJAMAN + KUNCI BARIS
            ============================================= */

            $stmt = $koneksi->prepare("
                SELECT
                    p.id,
                    p.id_user,
                    p.id_buku,
                    p.status,
                    b.nama_buku,
                    b.stok
                FROM peminjaman p
                INNER JOIN buku b
                    ON b.id = p.id_buku
                WHERE p.id = ?
                LIMIT 1
                FOR UPDATE
            ");

            if (!$stmt) {
                throw new Exception(
                    "Gagal mempersiapkan data peminjaman: "
                    . $koneksi->error
                );
            }

            $stmt->bind_param(
                "i",
                $id_peminjaman
            );

            if (!$stmt->execute()) {
                throw new Exception(
                    "Gagal mengambil data peminjaman: "
                    . $stmt->error
                );
            }

            $result = $stmt->get_result();

            $peminjaman = $result->fetch_assoc();


            /* =============================================
               CEK DATA
            ============================================= */

            if (!$peminjaman) {

                throw new Exception(
                    "Data pengajuan peminjaman tidak ditemukan."
                );
            }


            /* =============================================
               HARUS STATUS MENUNGGU
            ============================================= */

            if (
                strtolower(
                    trim($peminjaman['status'])
                ) !== 'menunggu'
            ) {

                throw new Exception(
                    "Pengajuan ini sudah diproses sebelumnya."
                );
            }


            /* =============================================
               CEK STOK
            ============================================= */

            if ((int) $peminjaman['stok'] <= 0) {

                throw new Exception(
                    "Stok buku sedang habis. Pengajuan belum dapat disetujui."
                );
            }


            /* =============================================
               TANGGAL PEMINJAMAN
            ============================================= */

            $tanggal_pinjam = date('Y-m-d');

            $tanggal_kembali = date(
                'Y-m-d',
                strtotime('+7 days')
            );


            /* =============================================
               UPDATE PEMINJAMAN
            ============================================= */

            $update = $koneksi->prepare("
                UPDATE peminjaman
                SET
                    tanggal_pinjam = ?,
                    tanggal_kembali = ?,
                    status = 'dipinjam',
                    id_petugas_acc = ?,
                    tgl_acc = ?,
                    catatan_tolak = NULL
                WHERE id = ?
                  AND status = 'menunggu'
            ");

            if (!$update) {

                throw new Exception(
                    "Gagal mempersiapkan persetujuan: "
                    . $koneksi->error
                );
            }

            $update->bind_param(
                "ssisi",
                $tanggal_pinjam,
                $tanggal_kembali,
                $id_petugas,
                $tanggal_pinjam,
                $id_peminjaman
            );

            $okAksi = $update->execute();
        if ($okAksi) { catatAudit($koneksi,'setujui_peminjaman','peminjaman',$id_peminjaman,'Pengajuan disetujui, peminjaman ID '.$id_peminjaman); }
        if (!$okAksi) {

                throw new Exception(
                    "Gagal menyetujui peminjaman: "
                    . $update->error
                );
            }


            if ($update->affected_rows !== 1) {

                throw new Exception(
                    "Pengajuan tidak berhasil diperbarui."
                );
            }


            /* =============================================
               UPDATE STOK BUKU
            ============================================= */

            $updateBuku = $koneksi->prepare("
                UPDATE buku
                SET
                    stok = stok - 1,
                    jumlah_dipinjam = jumlah_dipinjam + 1
                WHERE id = ?
                  AND stok > 0
            ");

            if (!$updateBuku) {

                throw new Exception(
                    "Gagal mempersiapkan update stok: "
                    . $koneksi->error
                );
            }

            $updateBuku->bind_param(
                "i",
                $peminjaman['id_buku']
            );

            if (!$updateBuku->execute()) {

                throw new Exception(
                    "Gagal mengurangi stok buku: "
                    . $updateBuku->error
                );
            }


            if ($updateBuku->affected_rows !== 1) {

                throw new Exception(
                    "Stok buku gagal diperbarui."
                );
            }


            /* =============================================
               COMMIT
            ============================================= */

            $koneksi->commit();


            $_SESSION['pesan_sukses'] =
                "Pengajuan peminjaman buku \""
                . $peminjaman['nama_buku']
                . "\" berhasil disetujui. "
                . "Tanggal kembali: "
                . $tanggal_kembali;


        } catch (Exception $e) {

            $koneksi->rollback();

            $_SESSION['pesan_error'] =
                $e->getMessage();
        }


        header("Location: pengajuan_peminjaman.php");
        exit;
    }


    /* =================================================
       AKSI TOLAK
    ================================================= */

    if ($aksi === 'tolak') {

        $alasan = trim(
            $_POST['catatan_tolak'] ?? ''
        );


        if ($alasan === '') {

            $_SESSION['pesan_error'] =
                "Alasan penolakan wajib diisi.";

            header("Location: pengajuan_peminjaman.php");
            exit;
        }


        $stmt = $koneksi->prepare("
            UPDATE peminjaman
            SET
                status = 'ditolak',
                id_petugas_acc = ?,
                tgl_acc = ?,
                catatan_tolak = ?
            WHERE id = ?
              AND status = 'menunggu'
        ");

        if (!$stmt) {

            $_SESSION['pesan_error'] =
                "Gagal mempersiapkan penolakan: "
                . $koneksi->error;

            header("Location: pengajuan_peminjaman.php");
            exit;
        }


        $tanggal_acc = date('Y-m-d');


        $stmt->bind_param(
            "issi",
            $id_petugas,
            $tanggal_acc,
            $alasan,
            $id_peminjaman
        );


        $okAksi = $stmt->execute();
        if ($okAksi) { catatAudit($koneksi,'tolak_peminjaman','peminjaman',$id_peminjaman,'Pengajuan ditolak, peminjaman ID '.$id_peminjaman); }
        if (!$okAksi) {

            $_SESSION['pesan_error'] =
                "Gagal menolak pengajuan: "
                . $stmt->error;

        } elseif ($stmt->affected_rows !== 1) {

            $_SESSION['pesan_error'] =
                "Pengajuan sudah diproses atau tidak ditemukan.";

        } else {

            $_SESSION['pesan_sukses'] =
                "Pengajuan peminjaman berhasil ditolak.";
        }


        header("Location: pengajuan_peminjaman.php");
        exit;
    }
}


/* =====================================================
   AMBIL PESAN
===================================================== */

$pesan_sukses = $_SESSION['pesan_sukses'] ?? '';
$pesan_error = $_SESSION['pesan_error'] ?? '';

unset($_SESSION['pesan_sukses']);
unset($_SESSION['pesan_error']);


/* =====================================================
   AMBIL JUMLAH PENGAJUAN MENUNGGU
===================================================== */

$stmtCount = $koneksi->prepare("
    SELECT COUNT(*) AS total
    FROM peminjaman
    WHERE status = 'menunggu'
");

$stmtCount->execute();

$resultCount = $stmtCount->get_result();

$dataCount = $resultCount->fetch_assoc();

$total_menunggu = (int) ($dataCount['total'] ?? 0);


/* =====================================================
   AMBIL DATA PENGAJUAN
===================================================== */

$query = "
    SELECT
        p.id,
        p.id_user,
        p.id_buku,
        p.tgl_pengajuan,
        p.status,
        p.catatan_tolak,

        u.username,

        b.nama_buku,
        b.stok

    FROM peminjaman p

    INNER JOIN users u
        ON u.id = p.id_user

    INNER JOIN buku b
        ON b.id = p.id_buku

    WHERE p.status = 'menunggu'

    ORDER BY
        p.tgl_pengajuan ASC,
        p.id ASC
";

$result = $koneksi->query($query);

if (!$result) {

    die(
        "Gagal mengambil data pengajuan: "
        . htmlspecialchars($koneksi->error)
    );
}

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Pengajuan Peminjaman - Smart Library
    </title>


    <!-- BOOTSTRAP -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- BOOTSTRAP ICON -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <!-- GOOGLE FONT -->

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <style>

        * {
            box-sizing: border-box;
        }

        body {

            margin: 0;

            font-family: 'Poppins', sans-serif;

            background: #f8f9ff;

            color: #1e293b;
        }


        /* ================================================
           SIDEBAR
        ================================================ */

        .sidebar {

            position: fixed;

            left: 0;
            top: 0;

            width: 280px;

            height: 100vh;

            background:
                linear-gradient(
                    180deg,
                    #182033 0%,
                    #111827 100%
                );

            color: white;

            padding: 25px 18px;

            overflow-y: auto;

            z-index: 1000;
        }


        .sidebar-title {

            font-size: 21px;

            font-weight: 700;

            padding: 5px 12px 25px;
        }


        .menu-label {

            font-size: 11px;

            letter-spacing: 2px;

            color: #64748b;

            margin:

                25px 8px 10px;
        }


        .menu-item {

            display: flex;

            align-items: center;

            gap: 14px;

            padding: 13px 15px;

            margin-bottom: 5px;

            border-radius: 14px;

            color: #cbd5e1;

            text-decoration: none;

            font-size: 15px;

            font-weight: 500;

            transition: .2s;
        }


        .menu-item i {

            font-size: 18px;

            width: 22px;

            text-align: center;
        }


        .menu-item:hover {

            background: rgba(139,92,246,.16);

            color: white;
        }


        .menu-item.active {

            background:
                linear-gradient(
                    135deg,
                    #6366f1,
                    #a855f7
                );

            color: white;

            box-shadow:
                0 10px 25px
                rgba(99,102,241,.28);
        }


        /* ================================================
           MAIN
        ================================================ */

        .main {

            margin-left: 280px;

            min-height: 100vh;

            padding: 35px;
        }


        .topbar {

            background: #fff;

            border-radius: 22px;

            padding: 25px 30px;

            margin-bottom: 25px;

            box-shadow:
                0 8px 30px
                rgba(15,23,42,.06);

            display: flex;

            justify-content: space-between;

            align-items: center;
        }


        .topbar h1 {

            margin: 0;

            font-size: 28px;

            font-weight: 800;

            color: #172033;
        }


        .topbar p {

            margin: 6px 0 0;

            color: #64748b;

            font-size: 14px;
        }


        .admin-user {

            display: flex;

            align-items: center;

            gap: 12px;

            font-weight: 600;
        }


        .admin-icon {

            width: 44px;

            height: 44px;

            border-radius: 50%;

            background:
                linear-gradient(
                    135deg,
                    #6366f1,
                    #a855f7
                );

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 20px;
        }


        /* ================================================
           STAT CARD
        ================================================ */

        .stat-card {

            background:
                linear-gradient(
                    135deg,
                    #6366f1,
                    #8b5cf6
                );

            color: white;

            border-radius: 22px;

            padding: 24px 28px;

            margin-bottom: 25px;

            box-shadow:
                0 15px 35px
                rgba(99,102,241,.22);
        }


        .stat-card .icon {

            width: 48px;

            height: 48px;

            border-radius: 14px;

            background:
                rgba(255,255,255,.18);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 23px;

            margin-bottom: 15px;
        }


        .stat-number {

            font-size: 32px;

            font-weight: 800;

            line-height: 1;
        }


        .stat-label {

            margin-top: 8px;

            opacity: .9;

            font-size: 14px;
        }


        /* ================================================
           ALERT
        ================================================ */

        .alert {

            border: none;

            border-radius: 15px;

            font-size: 14px;
        }


        /* ================================================
           CONTENT CARD
        ================================================ */

        .content-card {

            background: white;

            border-radius: 22px;

            padding: 25px;

            box-shadow:
                0 8px 30px
                rgba(15,23,42,.06);
        }


        .content-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 22px;
        }


        .content-header h2 {

            margin: 0;

            font-size: 20px;

            font-weight: 700;
        }


        .table-responsive {

            border-radius: 15px;

            overflow-x: auto;
        }


        table {

            width: 100%;

            min-width: 900px;
        }


        thead th {

            background: #f8fafc;

            color: #64748b;

            font-size: 12px;

            text-transform: uppercase;

            letter-spacing: .5px;

            padding: 15px;

            border-bottom: 1px solid #e2e8f0;

            white-space: nowrap;
        }


        tbody td {

            padding: 16px 15px;

            border-bottom: 1px solid #f1f5f9;

            vertical-align: middle;

            font-size: 13px;
        }


        tbody tr:hover {

            background: #fafaff;
        }


        .badge-menunggu {

            background: #fff7ed;

            color: #ea580c;

            padding: 7px 12px;

            border-radius: 50px;

            font-size: 11px;

            font-weight: 600;
        }


        .stock-ok {

            color: #16a34a;

            font-weight: 600;
        }


        .stock-empty {

            color: #dc2626;

            font-weight: 600;
        }


        .btn-detail {

            border: none;

            background: #f1f5f9;

            color: #475569;

            border-radius: 10px;

            padding: 8px 12px;

            font-size: 12px;

            font-weight: 600;
        }


        .btn-detail:hover {

            background: #e2e8f0;
        }


        .btn-approve {

            border: none;

            background: #16a34a;

            color: white;

            border-radius: 10px;

            padding: 8px 13px;

            font-size: 12px;

            font-weight: 600;
        }


        .btn-approve:hover {

            background: #15803d;

            color: white;
        }


        .btn-reject {

            border: none;

            background: #fee2e2;

            color: #dc2626;

            border-radius: 10px;

            padding: 8px 13px;

            font-size: 12px;

            font-weight: 600;
        }


        .btn-reject:hover {

            background: #fecaca;

            color: #b91c1c;
        }


        .empty-state {

            text-align: center;

            padding: 70px 20px;

            color: #64748b;
        }


        .empty-state i {

            font-size: 55px;

            color: #cbd5e1;

            display: block;

            margin-bottom: 15px;
        }


        .empty-state h4 {

            color: #334155;

            font-size: 18px;

            font-weight: 700;
        }


        /* ================================================
           MODAL
        ================================================ */

        .modal-content {

            border: none;

            border-radius: 22px;

            overflow: hidden;
        }


        .modal-header {

            background:
                linear-gradient(
                    135deg,
                    #6366f1,
                    #a855f7
                );

            color: white;

            border: none;

            padding: 20px 25px;
        }


        .modal-title {

            font-weight: 700;
        }


        .modal-body {

            padding: 25px;
        }


        .detail-row {

            display: flex;

            justify-content: space-between;

            gap: 20px;

            padding: 12px 0;

            border-bottom: 1px solid #f1f5f9;
        }


        .detail-label {

            color: #64748b;

            font-size: 13px;
        }


        .detail-value {

            font-weight: 600;

            text-align: right;

            font-size: 13px;
        }


        textarea.form-control {

            border-radius: 12px;

            border: 1px solid #e2e8f0;

            min-height: 100px;

            resize: vertical;
        }


        textarea.form-control:focus {

            border-color: #8b5cf6;

            box-shadow:
                0 0 0 3px
                rgba(139,92,246,.1);
        }


        @media(max-width: 992px) {

            .sidebar {

                width: 230px;
            }

            .main {

                margin-left: 230px;

                padding: 25px;
            }
        }


        @media(max-width: 768px) {

            .sidebar {

                position: relative;

                width: 100%;

                height: auto;
            }

            .main {

                margin-left: 0;

                padding: 20px;
            }

            .topbar {

                flex-direction: column;

                align-items: flex-start;

                gap: 15px;
            }
        }

    </style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">

    <div class="sidebar-title">

        <i class="bi bi-grid-fill me-2"></i>

        Smart Library

    </div>


    <a
        href="dashboard.php"
        class="menu-item"
    >

        <i class="bi bi-grid-fill"></i>

        Dashboard

    </a>


    <a
        href="buku.php"
        class="menu-item"
    >

        <i class="bi bi-book-fill"></i>

        Kelola Buku

    </a>


    <a
        href="kriteria.php"
        class="menu-item"
    >

        <i class="bi bi-sliders"></i>

        Kriteria

    </a>


    <a
        href="generate_nilai.php"
        class="menu-item"
    >

        <i class="bi bi-cpu-fill"></i>

        Generate Nilai

    </a>


    <div class="menu-label">
        METODE SPK
    </div>


    <a
        href="input_ahp.php"
        class="menu-item"
    >

        <i class="bi bi-diagram-3-fill"></i>

        Input AHP

    </a>


    <a
        href="hitung_ahp.php"
        class="menu-item"
    >

        <i class="bi bi-bar-chart-fill"></i>

        Hitung AHP

    </a>


    <a
        href="hitung_saw.php"
        class="menu-item"
    >

        <i class="bi bi-calculator-fill"></i>

        Hitung SAW

    </a>


    <a
        href="hasil_ranking.php"
        class="menu-item"
    >

        <i class="bi bi-trophy-fill"></i>

        Hasil Ranking

    </a>


    <div class="menu-label">
        TRANSAKSI
    </div>


    <a
        href="pengajuan_peminjaman.php"
        class="menu-item active"
    >

        <i class="bi bi-hourglass-split"></i>

        Pengajuan Peminjaman

        <?php if ($total_menunggu > 0): ?>

            <span
                class="badge bg-danger ms-auto"
            >
                <?= $total_menunggu ?>
            </span>

        <?php endif; ?>

    </a>


    <a
        href="peminjaman.php"
        class="menu-item"
    >

        <i class="bi bi-journal-bookmark-fill"></i>

        Peminjaman

    </a>


    <a
        href="pengembalian.php"
        class="menu-item"
    >

        <i class="bi bi-arrow-return-left"></i>

        Pengembalian

    </a>


    <a
        href="denda.php"
        class="menu-item"
    >

        <i class="bi bi-cash-stack"></i>

        Denda & Pembayaran

    </a>


    <div class="menu-label">
        ACCOUNT
    </div>


    <a
        href="../auth/logout.php"
        class="menu-item"
    >

        <i class="bi bi-box-arrow-right"></i>

        Logout

    </a>

</aside>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


    <!-- TOPBAR -->

    <div class="topbar">

        <div>

            <h1>
                Pengajuan Peminjaman
            </h1>

            <p>
                Kelola persetujuan peminjaman buku siswa.
            </p>

        </div>


        <div class="admin-user">

            <div class="admin-icon">

                <i class="bi bi-person-fill"></i>

            </div>

            <span>
                <?= htmlspecialchars($username) ?>
            </span>

        </div>

    </div>


    <!-- ALERT SUKSES -->

    <?php if ($pesan_sukses !== ''): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-check-circle-fill me-2"></i>

            <?= htmlspecialchars($pesan_sukses) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <!-- ALERT ERROR -->

    <?php if ($pesan_error !== ''): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-exclamation-triangle-fill me-2"></i>

            <?= htmlspecialchars($pesan_error) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <!-- STAT -->

    <div class="stat-card">

        <div class="icon">

            <i class="bi bi-hourglass-split"></i>

        </div>

        <div class="stat-number">

            <?= $total_menunggu ?>

        </div>

        <div class="stat-label">

            Pengajuan Menunggu Persetujuan

        </div>

    </div>


    <!-- TABLE -->

    <div class="content-card">

        <div class="content-header">

            <h2>

                <i class="bi bi-list-check me-2"></i>

                Daftar Pengajuan

            </h2>

        </div>


        <?php if ($result->num_rows > 0): ?>


            <div class="table-responsive">

                <table class="table mb-0">

                    <thead>

                        <tr>

                            <th>No</th>

                            <th>Siswa</th>

                            <th>Buku</th>

                            <th>Tanggal Pengajuan</th>

                            <th>Stok</th>

                            <th>Status</th>

                            <th>Aksi</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php

                    $no = 1;

                    while ($row = $result->fetch_assoc()):

                    ?>

                        <tr>

                            <td>

                                <?= $no++ ?>

                            </td>


                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $row['username']
                                    ) ?>

                                </strong>

                                <br>

                                <small class="text-muted">

                                    ID User:
                                    <?= (int) $row['id_user'] ?>

                                </small>

                            </td>


                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $row['nama_buku']
                                    ) ?>

                                </strong>

                                <br>

                                <small class="text-muted">

                                    ID Buku:
                                    <?= (int) $row['id_buku'] ?>

                                </small>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    date(
                                        'd-m-Y',
                                        strtotime(
                                            $row['tgl_pengajuan']
                                        )
                                    )
                                ) ?>

                            </td>


                            <td>

                                <?php if ((int) $row['stok'] > 0): ?>

                                    <span class="stock-ok">

                                        <?= (int) $row['stok'] ?>
                                        tersedia

                                    </span>

                                <?php else: ?>

                                    <span class="stock-empty">

                                        Stok habis

                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <span class="badge-menunggu">

                                    <i class="bi bi-clock me-1"></i>

                                    Menunggu

                                </span>

                            </td>


                            <td>

                                <div class="d-flex gap-2">

                                    <!-- DETAIL -->

                                    <button
                                        type="button"
                                        class="btn-detail"
                                        data-bs-toggle="modal"
                                        data-bs-target="#detailModal<?= (int) $row['id'] ?>"
                                    >

                                        <i class="bi bi-eye"></i>

                                    </button>


                                    <!-- SETUJUI -->

                                    <?php if ((int) $row['stok'] > 0): ?>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm(
                                                'Apakah Anda yakin ingin menyetujui pengajuan ini?'
                                            );"
                                        >

                                            <input
                                                type="hidden"
                                                name="id_peminjaman"
                                                value="<?= (int) $row['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="aksi"
                                                value="setujui"
                                            >

                                            <button
                                                type="submit"
                                                class="btn-approve"
                                            >

                                                <i class="bi bi-check-lg"></i>

                                            </button>

                                        </form>

                                    <?php endif; ?>


                                    <!-- TOLAK -->

                                    <button
                                        type="button"
                                        class="btn-reject"
                                        data-bs-toggle="modal"
                                        data-bs-target="#tolakModal<?= (int) $row['id'] ?>"
                                    >

                                        <i class="bi bi-x-lg"></i>

                                    </button>

                                </div>

                            </td>

                        </tr>


                        <!-- =================================================
                             MODAL DETAIL
                        ================================================== -->

                        <div
                            class="modal fade"
                            id="detailModal<?= (int) $row['id'] ?>"
                            tabindex="-1"
                        >

                            <div class="modal-dialog">

                                <div class="modal-content">

                                    <div class="modal-header">

                                        <h5 class="modal-title">

                                            <i class="bi bi-info-circle me-2"></i>

                                            Detail Pengajuan

                                        </h5>

                                        <button
                                            type="button"
                                            class="btn-close btn-close-white"
                                            data-bs-dismiss="modal"
                                        ></button>

                                    </div>


                                    <div class="modal-body">


                                        <div class="detail-row">

                                            <span class="detail-label">
                                                ID Peminjaman
                                            </span>

                                            <span class="detail-value">
                                                #<?= (int) $row['id'] ?>
                                            </span>

                                        </div>


                                        <div class="detail-row">

                                            <span class="detail-label">
                                                Siswa
                                            </span>

                                            <span class="detail-value">

                                                <?= htmlspecialchars(
                                                    $row['username']
                                                ) ?>

                                            </span>

                                        </div>


                                        <div class="detail-row">

                                            <span class="detail-label">
                                                ID User
                                            </span>

                                            <span class="detail-value">

                                                <?= (int) $row['id_user'] ?>

                                            </span>

                                        </div>


                                        <div class="detail-row">

                                            <span class="detail-label">
                                                Buku
                                            </span>

                                            <span class="detail-value">

                                                <?= htmlspecialchars(
                                                    $row['nama_buku']
                                                ) ?>

                                            </span>

                                        </div>


                                        <div class="detail-row">

                                            <span class="detail-label">
                                                ID Buku
                                            </span>

                                            <span class="detail-value">

                                                <?= (int) $row['id_buku'] ?>

                                            </span>

                                        </div>


                                        <div class="detail-row">

                                            <span class="detail-label">
                                                Tanggal Pengajuan
                                            </span>

                                            <span class="detail-value">

                                                <?= htmlspecialchars(
                                                    date(
                                                        'd-m-Y',
                                                        strtotime(
                                                            $row['tgl_pengajuan']
                                                        )
                                                    )
                                                ) ?>

                                            </span>

                                        </div>


                                        <div class="detail-row">

                                            <span class="detail-label">
                                                Stok Saat Ini
                                            </span>

                                            <span class="detail-value">

                                                <?= (int) $row['stok'] ?>

                                                buku

                                            </span>

                                        </div>


                                        <div class="detail-row">

                                            <span class="detail-label">
                                                Status
                                            </span>

                                            <span class="detail-value">

                                                Menunggu Persetujuan

                                            </span>

                                        </div>


                                    </div>


                                    <div class="modal-footer">

                                        <?php if ((int) $row['stok'] > 0): ?>

                                            <form
                                                method="POST"
                                                class="d-inline"
                                                onsubmit="return confirm(
                                                    'Setujui peminjaman ini?'
                                                );"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="id_peminjaman"
                                                    value="<?= (int) $row['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="aksi"
                                                    value="setujui"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-success"
                                                >

                                                    <i class="bi bi-check-circle me-1"></i>

                                                    Setujui

                                                </button>

                                            </form>

                                        <?php endif; ?>


                                        <button
                                            type="button"
                                            class="btn btn-secondary"
                                            data-bs-dismiss="modal"
                                        >
                                            Tutup
                                        </button>

                                    </div>

                                </div>

                            </div>

                        </div>


                        <!-- =================================================
                             MODAL TOLAK
                        ================================================== -->

                        <div
                            class="modal fade"
                            id="tolakModal<?= (int) $row['id'] ?>"
                            tabindex="-1"
                        >

                            <div class="modal-dialog">

                                <div class="modal-content">

                                    <div class="modal-header">

                                        <h5 class="modal-title">

                                            <i class="bi bi-x-circle me-2"></i>

                                            Tolak Pengajuan

                                        </h5>

                                        <button
                                            type="button"
                                            class="btn-close btn-close-white"
                                            data-bs-dismiss="modal"
                                        ></button>

                                    </div>


                                    <form method="POST">

                                        <div class="modal-body">

                                            <p class="text-muted">

                                                Anda akan menolak pengajuan
                                                peminjaman buku:

                                            </p>


                                            <p>

                                                <strong>

                                                    <?= htmlspecialchars(
                                                        $row['nama_buku']
                                                    ) ?>

                                                </strong>

                                            </p>


                                            <input
                                                type="hidden"
                                                name="id_peminjaman"
                                                value="<?= (int) $row['id'] ?>"
                                            >


                                            <input
                                                type="hidden"
                                                name="aksi"
                                                value="tolak"
                                            >


                                            <div class="mb-3">

                                                <label
                                                    class="form-label fw-semibold"
                                                >

                                                    Alasan Penolakan

                                                </label>


                                                <textarea
                                                    name="catatan_tolak"
                                                    class="form-control"
                                                    placeholder="Masukkan alasan penolakan..."
                                                    required
                                                ></textarea>

                                            </div>

                                        </div>


                                        <div class="modal-footer">

                                            <button
                                                type="button"
                                                class="btn btn-secondary"
                                                data-bs-dismiss="modal"
                                            >
                                                Batal
                                            </button>


                                            <button
                                                type="submit"
                                                class="btn btn-danger"
                                            >

                                                <i class="bi bi-x-circle me-1"></i>

                                                Tolak Pengajuan

                                            </button>

                                        </div>

                                    </form>

                                </div>

                            </div>

                        </div>


                    <?php endwhile; ?>

                    </tbody>

                </table>

            </div>


        <?php else: ?>


            <div class="empty-state">

                <i class="bi bi-check2-circle"></i>

                <h4>
                    Tidak Ada Pengajuan
                </h4>

                <p>
                    Saat ini tidak ada pengajuan peminjaman
                    yang menunggu persetujuan.
                </p>

            </div>


        <?php endif; ?>

    </div>


</main>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>