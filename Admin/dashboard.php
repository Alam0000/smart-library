<?php
session_start();

include '../connection/koneksi.php';

/* =========================================================
   CEK LOGIN PETUGAS
========================================================= */

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'petugas') {
    header("Location: ../auth/login.php");
    exit;
}


/* =========================================================
   HELPER
========================================================= */

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rupiah($angka)
{
    return 'Rp' . number_format((float)$angka, 0, ',', '.');
}

function getCount($koneksi, $query)
{
    $result = $koneksi->query($query);

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();

    return (int)($row['total'] ?? 0);
}


/* =========================================================
   ID PETUGAS
========================================================= */

$id_petugas = $_SESSION['id'] ?? $_SESSION['id_user'] ?? 0;


/* =========================================================
   PESAN
========================================================= */

$success = '';
$error = '';


/* =========================================================
   PROSES POST
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $aksi = $_POST['aksi'] ?? '';
    $id   = (int)($_POST['id'] ?? 0);


    /* =====================================================
       1. SETUJUI PEMINJAMAN
    ===================================================== */

    if ($aksi === 'setujui' && $id > 0) {

        $koneksi->begin_transaction();

        try {

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
                FOR UPDATE
            ");

            $stmt->bind_param("i", $id);
            $stmt->execute();

            $result = $stmt->get_result();
            $data = $result->fetch_assoc();

            $stmt->close();


            if (!$data) {
                throw new Exception(
                    'Data peminjaman tidak ditemukan.'
                );
            }


            if ($data['status'] !== 'menunggu') {
                throw new Exception(
                    'Peminjaman ini sudah diproses sebelumnya.'
                );
            }


            if ((int)$data['stok'] <= 0) {
                throw new Exception(
                    'Stok buku "' .
                    $data['nama_buku'] .
                    '" sedang habis.'
                );
            }


            if (!$id_petugas) {
                throw new Exception(
                    'ID petugas tidak ditemukan dalam session.'
                );
            }


            $tanggal_pinjam = date('Y-m-d');

            $tanggal_kembali = date(
                'Y-m-d',
                strtotime('+7 days')
            );


            /* Update peminjaman */

            $stmtUpdate = $koneksi->prepare("
                UPDATE peminjaman
                SET
                    status = 'dipinjam',
                    tanggal_pinjam = ?,
                    tanggal_kembali = ?,
                    id_petugas_acc = ?,
                    tgl_acc = NOW()
                WHERE id = ?
                  AND status = 'menunggu'
            ");

            $stmtUpdate->bind_param(
                "ssii",
                $tanggal_pinjam,
                $tanggal_kembali,
                $id_petugas,
                $id
            );


            if (!$stmtUpdate->execute()) {
                throw new Exception(
                    'Gagal memperbarui status peminjaman.'
                );
            }


            if ($stmtUpdate->affected_rows !== 1) {
                throw new Exception(
                    'Peminjaman sudah diproses sebelumnya.'
                );
            }

            $stmtUpdate->close();


            /* Kurangi stok */

            $stmtStok = $koneksi->prepare("
                UPDATE buku
                SET
                    stok = stok - 1,
                    jumlah_dipinjam =
                        COALESCE(jumlah_dipinjam, 0) + 1
                WHERE id = ?
                  AND stok > 0
            ");

            $stmtStok->bind_param(
                "i",
                $data['id_buku']
            );


            if (
                !$stmtStok->execute()
                ||
                $stmtStok->affected_rows !== 1
            ) {

                throw new Exception(
                    'Stok buku gagal diperbarui.'
                );
            }

            $stmtStok->close();


            $koneksi->commit();


            $success =
                'Peminjaman buku "' .
                $data['nama_buku'] .
                '" berhasil disetujui.';


        } catch (Exception $ex) {

            $koneksi->rollback();

            $error = $ex->getMessage();
        }
    }


    /* =====================================================
       2. TOLAK PEMINJAMAN
    ===================================================== */

    elseif ($aksi === 'tolak' && $id > 0) {

        $catatan = trim(
            $_POST['catatan_tolak'] ?? ''
        );


        if ($catatan === '') {

            $catatan =
                'Pengajuan peminjaman ditolak oleh petugas.';
        }


        $stmt = $koneksi->prepare("
            UPDATE peminjaman
            SET
                status = 'ditolak',
                catatan_tolak = ?
            WHERE id = ?
              AND status = 'menunggu'
        ");


        $stmt->bind_param(
            "si",
            $catatan,
            $id
        );


        if ($stmt->execute()) {

            if ($stmt->affected_rows > 0) {

                $success =
                    'Pengajuan peminjaman berhasil ditolak.';

            } else {

                $error =
                    'Pengajuan sudah diproses sebelumnya.';
            }

        } else {

            $error =
                'Gagal menolak pengajuan.';
        }


        $stmt->close();
    }


    /* =====================================================
       3. BAYAR / KONFIRMASI PEMBAYARAN
       
       Pembayaran dilakukan langsung di perpustakaan.
       Petugas yang mengonfirmasi pembayaran.
    ===================================================== */

    elseif ($aksi === 'bayar' && $id > 0) {

        $stmt = $koneksi->prepare("
            SELECT
                p.id,
                p.id_user,
                p.denda,
                p.status_bayar,
                u.nama_lengkap
            FROM peminjaman p
            INNER JOIN users u
                ON u.id = p.id_user
            WHERE p.id = ?
        ");


        $stmt->bind_param(
            "i",
            $id
        );

        $stmt->execute();


        $result = $stmt->get_result();

        $data = $result->fetch_assoc();

        $stmt->close();


        if (!$data) {

            $error =
                'Data denda tidak ditemukan.';

        } elseif ((float)$data['denda'] <= 0) {

            $error =
                'Data ini tidak memiliki denda.';

        } elseif ($data['status_bayar'] === 'lunas') {

            $error =
                'Denda sudah tercatat lunas.';

        } else {

            $stmt = $koneksi->prepare("
                UPDATE peminjaman
                SET
                    status_bayar = 'lunas',
                    tgl_bayar = NOW(),
                    batas_bayar_denda = NULL
                WHERE id = ?
                  AND denda > 0
                  AND status_bayar <> 'lunas'
            ");


            $stmt->bind_param(
                "i",
                $id
            );


            if ($stmt->execute()) {

                if ($stmt->affected_rows > 0) {

                    $success =
                        'Pembayaran denda atas nama ' .
                        $data['nama_lengkap'] .
                        ' berhasil dikonfirmasi. ' .
                        'Status menjadi Lunas.';

                } else {

                    $error =
                        'Denda sudah diproses sebelumnya.';
                }

            } else {

                $error =
                    'Gagal mengonfirmasi pembayaran.';
            }


            $stmt->close();
        }
    }


    /* =====================================================
       4. SUSPEND AKUN
       
       Suspend dilakukan MANUAL oleh petugas.
       Tidak ada otomatis berdasarkan tanggal.
    ===================================================== */

    elseif ($aksi === 'suspend' && $id > 0) {

        $stmt = $koneksi->prepare("
            SELECT
                p.id,
                p.id_user,
                p.denda,
                p.status_bayar,
                u.nama_lengkap,
                u.status_akun
            FROM peminjaman p
            INNER JOIN users u
                ON u.id = p.id_user
            WHERE p.id = ?
        ");


        $stmt->bind_param(
            "i",
            $id
        );

        $stmt->execute();


        $result = $stmt->get_result();

        $data = $result->fetch_assoc();

        $stmt->close();


        if (!$data) {

            $error =
                'Data denda tidak ditemukan.';

        } elseif ((float)$data['denda'] <= 0) {

            $error =
                'Data ini tidak memiliki denda.';

        } elseif ($data['status_bayar'] === 'lunas') {

            $error =
                'Denda ini sudah lunas. Akun tidak perlu disuspend.';

        } elseif ($data['status_akun'] === 'suspend') {

            $error =
                'Akun siswa sudah dalam status suspend.';

        } else {

            $alasan =
                'Akun ditangguhkan karena belum menyelesaikan pembayaran denda perpustakaan.';


            $stmt = $koneksi->prepare("
                UPDATE users
                SET
                    status_akun = 'suspend',
                    alasan_blokir = ?,
                    tgl_diblokir = NOW()
                WHERE id = ?
                  AND role = 'siswa'
                  AND status_akun <> 'suspend'
            ");


            $stmt->bind_param(
                "si",
                $alasan,
                $data['id_user']
            );


            if ($stmt->execute()) {
catatAudit($koneksi,'suspend_akun','users',$data['id_user'],'Akun siswa disuspend karena denda, user ID '.$data['id_user']);

                if ($stmt->affected_rows > 0) {

                    $success =
                        'Akun siswa ' .
                        $data['nama_lengkap'] .
                        ' berhasil ditangguhkan (Suspend).';

                } else {

                    $error =
                        'Akun siswa sudah diproses sebelumnya.';
                }

            } else {

                $error =
                    'Gagal menangguhkan akun siswa.';
            }


            $stmt->close();
        }
    }


    /* =====================================================
       5. AKTIFKAN AKUN
       
       Hanya dapat dilakukan jika semua denda siswa
       sudah lunas.
    ===================================================== */

    elseif ($aksi === 'aktifkan_akun' && $id > 0) {

        /*
         * PENTING:
         * ID yang dikirim dari tabel suspend adalah ID USER,
         * bukan ID PEMINJAMAN.
         */

        $stmt = $koneksi->prepare("
            SELECT
                u.id,
                u.nama_lengkap,
                u.status_akun
            FROM users u
            WHERE u.id = ?
              AND u.role = 'siswa'
        ");


        $stmt->bind_param(
            "i",
            $id
        );

        $stmt->execute();


        $result = $stmt->get_result();

        $user = $result->fetch_assoc();

        $stmt->close();


        if (!$user) {

            $error =
                'Data siswa tidak ditemukan.';

        } else {

            /* =============================================
               CEK SELURUH DENDA SISWA
            ============================================= */

            $stmt = $koneksi->prepare("
                SELECT
                    COUNT(*) AS total
                FROM peminjaman
                WHERE id_user = ?
                  AND denda > 0
                  AND status_bayar <> 'lunas'
            ");


            $stmt->bind_param(
                "i",
                $id
            );

            $stmt->execute();


            $result = $stmt->get_result();

            $cek = $result->fetch_assoc();

            $stmt->close();


            /*
             * Jika masih ada satu saja denda yang belum lunas,
             * akun tidak boleh diaktifkan.
             */

            if ((int)$cek['total'] > 0) {

                $error =
                    'Akun tidak dapat diaktifkan karena masih terdapat denda yang belum lunas.';

            } else {

                $stmt = $koneksi->prepare("
                    UPDATE users
                    SET
                        status_akun = 'aktif',
                        alasan_blokir = NULL,
                        tgl_diblokir = NULL
                    WHERE id = ?
                      AND role = 'siswa'
                ");


                $stmt->bind_param(
                    "i",
                    $id
                );


                if ($stmt->execute()) {
catatAudit($koneksi,'aktifkan_akun','users',$id,'Akun siswa diaktifkan kembali, user ID '.$id);

                    if ($stmt->affected_rows > 0) {

                        $success =
                            'Akun siswa ' .
                            $user['nama_lengkap'] .
                            ' berhasil diaktifkan kembali.';

                    } else {

                        $error =
                            'Akun siswa sudah aktif atau tidak mengalami perubahan.';
                    }

                } else {

                    $error =
                        'Gagal mengaktifkan akun.';
                }


                $stmt->close();
            }
        }
    }
}


/* =========================================================
   STATISTIK DASHBOARD
========================================================= */

$total_buku = getCount(
    $koneksi,
    "SELECT COUNT(*) AS total FROM buku"
);


$total_siswa = getCount(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'siswa'"
);


$total_pending = getCount(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM peminjaman
     WHERE status = 'menunggu'"
);


$total_denda = getCount(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM peminjaman
     WHERE denda > 0
       AND status_bayar <> 'lunas'"
);


$total_peminjaman = getCount(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM peminjaman"
);


$total_dipinjam = getCount(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM peminjaman
     WHERE status = 'dipinjam'"
);


$total_kriteria = getCount(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM kriteria"
);


$total_suspend = getCount(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'siswa'
       AND status_akun = 'suspend'"
);


/* =========================================================
   DATA PENGAJUAN PEMINJAMAN
========================================================= */

$queryPending = "
    SELECT
        p.id,
        p.id_user,
        p.id_buku,
        p.tgl_pengajuan,
        p.status,
        u.nama_lengkap,
        u.username,
        b.kode_buku,
        b.nama_buku,
        b.stok
    FROM peminjaman p
    INNER JOIN users u
        ON u.id = p.id_user
    INNER JOIN buku b
        ON b.id = p.id_buku
    WHERE p.status = 'menunggu'
    ORDER BY p.tgl_pengajuan DESC
";


$resultPending = $koneksi->query(
    $queryPending
);


/* =========================================================
   DATA DENDA
========================================================= */

$queryDenda = "
    SELECT
        p.id,
        p.id_user,
        p.id_buku,
        p.tanggal_kembali,
        p.tgl_kembali_aktual,
        p.denda,
        p.status_bayar,
        p.batas_bayar_denda,
        p.tgl_bayar,
        p.status,
        u.nama_lengkap,
        u.username,
        u.status_akun,
        b.nama_buku
    FROM peminjaman p
    INNER JOIN users u
        ON u.id = p.id_user
    INNER JOIN buku b
        ON b.id = p.id_buku
    WHERE p.denda > 0
    ORDER BY
        CASE
            WHEN p.status_bayar = 'lunas' THEN 2
            ELSE 1
        END,
        p.id DESC
";


$resultDenda = $koneksi->query(
    $queryDenda
);


/* =========================================================
   DATA SISWA SUSPEND
========================================================= */

$querySuspend = "
    SELECT
        u.id,
        u.nama_lengkap,
        u.username,
        u.status_akun,
        u.alasan_blokir,
        u.tgl_diblokir,

        COALESCE(
            SUM(
                CASE
                    WHEN p.denda > 0
                     AND p.status_bayar <> 'lunas'
                    THEN p.denda
                    ELSE 0
                END
            ),
            0
        ) AS total_denda,

        COUNT(
            CASE
                WHEN p.denda > 0
                 AND p.status_bayar <> 'lunas'
                THEN 1
                ELSE NULL
            END
        ) AS jumlah_denda_belum_lunas

    FROM users u

    LEFT JOIN peminjaman p
        ON p.id_user = u.id

    WHERE u.role = 'siswa'
      AND u.status_akun = 'suspend'

    GROUP BY
        u.id,
        u.nama_lengkap,
        u.username,
        u.status_akun,
        u.alasan_blokir,
        u.tgl_diblokir

    ORDER BY
        u.tgl_diblokir DESC
";


$resultSuspend = $koneksi->query(
    $querySuspend
);

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
        Dashboard Petugas - Perpustakaan
    </title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            background: #f1f5f9;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            color: #1e293b;
        }


        /* =====================================================
           MAIN CONTENT
        ===================================================== */

        .main-content {
            margin-left: 260px;
            padding: 25px;
            min-height: 100vh;
        }


        /* =====================================================
           TOPBAR
        ===================================================== */

        .topbar {
            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #1d4ed8
                );

            border-radius: 18px;

            padding: 25px 30px;

            margin-bottom: 25px;

            color: white;

            box-shadow:
                0 8px 20px
                rgba(37, 99, 235, 0.20);
        }


        .page-title {
            margin: 0;

            font-weight: 700;

            font-size: 28px;
        }


        .topbar p {
            margin: 5px 0 0;

            opacity: .9;
        }


        /* =====================================================
           STAT CARD
        ===================================================== */

        .stat-card {
            background: white;

            border-radius: 18px;

            padding: 22px;

            box-shadow:
                0 5px 15px
                rgba(0,0,0,.08);

            height: 100%;

            transition: .2s;
        }


        .stat-card:hover {
            transform: translateY(-3px);
        }


        .stat-icon {
            width: 52px;
            height: 52px;

            border-radius: 14px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 24px;

            margin-bottom: 15px;
        }


        .stat-number {
            font-size: 28px;

            font-weight: 700;

            margin-bottom: 2px;
        }


        .stat-label {
            color: #64748b;

            font-size: 14px;
        }


        /* =====================================================
           CARD
        ===================================================== */

        .card-custom {
            background: white;

            border: none;

            border-radius: 18px;

            box-shadow:
                0 5px 15px
                rgba(0,0,0,.08);

            margin-bottom: 25px;

            overflow: hidden;
        }


        .card-header-custom {
            padding: 20px 22px;

            border-bottom:
                1px solid #e2e8f0;

            display: flex;

            align-items: center;

            justify-content: space-between;
        }


        .card-header-custom h5 {
            margin: 0;

            font-weight: 700;

            color: #1e293b;
        }


        .card-body-custom {
            padding: 20px;
        }


        /* =====================================================
           TABLE
        ===================================================== */

        .table {
            margin-bottom: 0;

            vertical-align: middle;
        }


        .table thead th {
            background: #f8fafc;

            color: #475569;

            font-size: 13px;

            font-weight: 700;

            white-space: nowrap;
        }


        .table tbody td {
            font-size: 14px;

            color: #334155;
        }


        /* =====================================================
           BADGE
        ===================================================== */

        .badge-status {
            display: inline-block;

            padding: 6px 10px;

            border-radius: 8px;

            font-size: 12px;

            font-weight: 600;
        }


        .badge-menunggu {
            background: #fef3c7;
            color: #92400e;
        }


        .badge-dipinjam {
            background: #dbeafe;
            color: #1d4ed8;
        }


        .badge-lunas {
            background: #dcfce7;
            color: #166534;
        }


        .badge-belum {
            background: #fee2e2;
            color: #991b1b;
        }


        .badge-suspend {
            background: #fee2e2;
            color: #991b1b;
        }


        .badge-aktif {
            background: #dcfce7;
            color: #166534;
        }


        /* =====================================================
           BUTTON
        ===================================================== */

        .btn-action {
            border-radius: 8px;

            font-size: 12px;

            font-weight: 600;

            padding: 7px 11px;
        }


        /* =====================================================
           EMPTY
        ===================================================== */

        .empty-data {
            text-align: center;

            padding: 35px 15px;

            color: #94a3b8;
        }


        .empty-data i {
            font-size: 40px;

            margin-bottom: 10px;
        }


        /* =====================================================
           ALERT
        ===================================================== */

        .alert-custom {
            border: none;

            border-radius: 12px;

            margin-bottom: 20px;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media(max-width: 992px) {

            .main-content {
                margin-left: 0;

                padding: 15px;
            }

        }


        @media(max-width: 768px) {

            .topbar {
                padding: 20px;
            }

            .page-title {
                font-size: 23px;
            }

            .table-responsive {
                border-radius: 10px;
            }

        }

    </style>

</head>


<body>


<?php include 'partials/sidebar.php'; ?>


<div class="main-content">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="topbar">

        <h2 class="page-title">
            📚 Dashboard Petugas
        </h2>

        <p>
            Kelola perpustakaan, peminjaman, denda, dan akun siswa
        </p>

    </div>


    <!-- =====================================================
         ALERT
    ====================================================== -->

    <?php if ($success !== '') { ?>

        <div class="alert alert-success alert-custom">

            <i class="bi bi-check-circle-fill"></i>

            <?= e($success) ?>

        </div>

    <?php } ?>


    <?php if ($error !== '') { ?>

        <div class="alert alert-danger alert-custom">

            <i class="bi bi-exclamation-circle-fill"></i>

            <?= e($error) ?>

        </div>

    <?php } ?>


    <!-- =====================================================
         STATISTIK
    ====================================================== -->

    <div class="row g-4 mb-4">


        <!-- BUKU -->

        <div class="col-xl-3 col-md-6">

            <div class="stat-card">

                <div
                    class="stat-icon"
                    style="
                        background:#dbeafe;
                        color:#2563eb;
                    "
                >

                    <i class="bi bi-book"></i>

                </div>


                <div class="stat-number">
                    <?= $total_buku ?>
                </div>


                <div class="stat-label">
                    Total Buku
                </div>

            </div>

        </div>


        <!-- SISWA -->

        <div class="col-xl-3 col-md-6">

            <div class="stat-card">

                <div
                    class="stat-icon"
                    style="
                        background:#dcfce7;
                        color:#16a34a;
                    "
                >

                    <i class="bi bi-people"></i>

                </div>


                <div class="stat-number">
                    <?= $total_siswa ?>
                </div>


                <div class="stat-label">
                    Total Siswa
                </div>

            </div>

        </div>


        <!-- PENDING -->

        <div class="col-xl-3 col-md-6">

            <div class="stat-card">

                <div
                    class="stat-icon"
                    style="
                        background:#fef3c7;
                        color:#d97706;
                    "
                >

                    <i class="bi bi-hourglass-split"></i>

                </div>


                <div class="stat-number">
                    <?= $total_pending ?>
                </div>


                <div class="stat-label">
                    Pengajuan Menunggu
                </div>

            </div>

        </div>


        <!-- DENDA -->

        <div class="col-xl-3 col-md-6">

            <div class="stat-card">

                <div
                    class="stat-icon"
                    style="
                        background:#fee2e2;
                        color:#dc2626;
                    "
                >

                    <i class="bi bi-cash-stack"></i>

                </div>


                <div class="stat-number">
                    <?= $total_denda ?>
                </div>


                <div class="stat-label">
                    Denda Belum Lunas
                </div>

            </div>

        </div>


        <!-- PEMINJAMAN -->

        <div class="col-xl-3 col-md-6">

            <div class="stat-card">

                <div
                    class="stat-icon"
                    style="
                        background:#e0e7ff;
                        color:#4f46e5;
                    "
                >

                    <i class="bi bi-journal-bookmark"></i>

                </div>


                <div class="stat-number">
                    <?= $total_peminjaman ?>
                </div>


                <div class="stat-label">
                    Total Peminjaman
                </div>

            </div>

        </div>


        <!-- DIPINJAM -->

        <div class="col-xl-3 col-md-6">

            <div class="stat-card">

                <div
                    class="stat-icon"
                    style="
                        background:#cffafe;
                        color:#0891b2;
                    "
                >

                    <i class="bi bi-bookmark-check"></i>

                </div>


                <div class="stat-number">
                    <?= $total_dipinjam ?>
                </div>


                <div class="stat-label">
                    Buku Sedang Dipinjam
                </div>

            </div>

        </div>


        <!-- KRITERIA -->

        <div class="col-xl-3 col-md-6">

            <div class="stat-card">

                <div
                    class="stat-icon"
                    style="
                        background:#f3e8ff;
                        color:#9333ea;
                    "
                >

                    <i class="bi bi-sliders"></i>

                </div>


                <div class="stat-number">
                    <?= $total_kriteria ?>
                </div>


                <div class="stat-label">
                    Kriteria AHP
                </div>

            </div>

        </div>


        <!-- SUSPEND -->

        <div class="col-xl-3 col-md-6">

            <div class="stat-card">

                <div
                    class="stat-icon"
                    style="
                        background:#fee2e2;
                        color:#b91c1c;
                    "
                >

                    <i class="bi bi-person-x"></i>

                </div>


                <div class="stat-number">
                    <?= $total_suspend ?>
                </div>


                <div class="stat-label">
                    Akun Suspend
                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         PENGAJUAN PEMINJAMAN
    ====================================================== -->

    <div class="card-custom">

        <div class="card-header-custom">

            <h5>

                <i class="bi bi-clock-history"></i>

                Pengajuan Peminjaman

            </h5>


            <span class="badge bg-warning text-dark">

                <?= $total_pending ?>

                Menunggu

            </span>

        </div>


        <div class="card-body-custom">

            <?php if (
                $resultPending
                &&
                $resultPending->num_rows > 0
            ) { ?>

                <div class="table-responsive">

                    <table class="table table-hover">

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

                        while (
                            $row =
                            $resultPending->fetch_assoc()
                        ) {

                        ?>

                            <tr>

                                <td>
                                    <?= $no++ ?>
                                </td>


                                <td>

                                    <strong>
                                        <?= e(
                                            $row['nama_lengkap']
                                        ) ?>
                                    </strong>

                                    <br>

                                    <small class="text-muted">

                                        <?= e(
                                            $row['username']
                                        ) ?>

                                    </small>

                                </td>


                                <td>

                                    <strong>
                                        <?= e(
                                            $row['nama_buku']
                                        ) ?>
                                    </strong>

                                    <br>

                                    <small class="text-muted">

                                        <?= e(
                                            $row['kode_buku']
                                        ) ?>

                                    </small>

                                </td>


                                <td>

                                    <?= date(
                                        'd-m-Y H:i',
                                        strtotime(
                                            $row['tgl_pengajuan']
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?php if (
                                        (int)$row['stok'] > 0
                                    ) { ?>

                                        <span class="badge bg-success">

                                            <?= (int)$row['stok'] ?>

                                        </span>

                                    <?php } else { ?>

                                        <span class="badge bg-danger">
                                            Habis
                                        </span>

                                    <?php } ?>

                                </td>


                                <td>

                                    <span
                                        class="
                                            badge-status
                                            badge-menunggu
                                        "
                                    >

                                        Menunggu

                                    </span>

                                </td>


                                <td>

                                    <div class="d-flex gap-2">


                                        <!-- SETUJUI -->

                                        <form
                                            method="POST"
                                            onsubmit="
                                                return confirm(
                                                    'Setujui peminjaman buku ini?'
                                                );
                                            "
                                        >

                                            <input
                                                type="hidden"
                                                name="aksi"
                                                value="setujui"
                                            >


                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$row['id'] ?>"
                                            >


                                            <button
                                                type="submit"
                                                class="
                                                    btn
                                                    btn-success
                                                    btn-action
                                                "
                                                <?= (int)$row['stok'] <= 0
                                                    ? 'disabled'
                                                    : '' ?>
                                            >

                                                <i class="bi bi-check-lg"></i>

                                                Setujui

                                            </button>

                                        </form>


                                        <!-- TOLAK -->

                                        <form
                                            method="POST"
                                            onsubmit="
                                                return confirm(
                                                    'Tolak pengajuan peminjaman ini?'
                                                );
                                            "
                                        >

                                            <input
                                                type="hidden"
                                                name="aksi"
                                                value="tolak"
                                            >


                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$row['id'] ?>"
                                            >


                                            <input
                                                type="hidden"
                                                name="catatan_tolak"
                                                value="Pengajuan ditolak oleh petugas."
                                            >


                                            <button
                                                type="submit"
                                                class="
                                                    btn
                                                    btn-danger
                                                    btn-action
                                                "
                                            >

                                                <i class="bi bi-x-lg"></i>

                                                Tolak

                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php } ?>

                        </tbody>

                    </table>

                </div>

            <?php } else { ?>

                <div class="empty-data">

                    <i class="bi bi-inbox"></i>

                    <div>
                        Tidak ada pengajuan peminjaman yang menunggu.
                    </div>

                </div>

            <?php } ?>

        </div>

    </div>


    <!-- =====================================================
         DENDA
    ====================================================== -->

    <div class="card-custom">

        <div class="card-header-custom">

            <h5>

                <i class="bi bi-cash-coin"></i>

                Data Denda

            </h5>


            <span class="badge bg-danger">

                <?= $total_denda ?>

                Belum Lunas

            </span>

        </div>


        <div class="card-body-custom">

            <?php if (
                $resultDenda
                &&
                $resultDenda->num_rows > 0
            ) { ?>

                <div class="table-responsive">

                    <table class="table table-hover">

                        <thead>

                            <tr>

                                <th>No</th>

                                <th>Siswa</th>

                                <th>Buku</th>

                                <th>Denda</th>

                                <th>Status</th>

                                <th>Status Akun</th>

                                <th>Aksi</th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php

                        $noDenda = 1;

                        while (
                            $row =
                            $resultDenda->fetch_assoc()
                        ) {

                            $isLunas =
                                $row['status_bayar'] === 'lunas';

                        ?>

                            <tr>

                                <td>
                                    <?= $noDenda++ ?>
                                </td>


                                <!-- SISWA -->

                                <td>

                                    <strong>

                                        <?= e(
                                            $row['nama_lengkap']
                                        ) ?>

                                    </strong>

                                    <br>

                                    <small class="text-muted">

                                        <?= e(
                                            $row['username']
                                        ) ?>

                                    </small>

                                </td>


                                <!-- BUKU -->

                                <td>

                                    <?= e(
                                        $row['nama_buku']
                                    ) ?>

                                </td>


                                <!-- DENDA -->

                                <td>

                                    <strong>

                                        <?= rupiah(
                                            $row['denda']
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- STATUS DENDA -->

                                <td>

                                    <?php if ($isLunas) { ?>

                                        <span
                                            class="
                                                badge-status
                                                badge-lunas
                                            "
                                        >

                                            <i
                                                class="
                                                    bi
                                                    bi-check-circle
                                                "
                                            ></i>

                                            Lunas

                                        </span>

                                    <?php } else { ?>

                                        <span
                                            class="
                                                badge-status
                                                badge-belum
                                            "
                                        >

                                            Belum Bayar

                                        </span>

                                    <?php } ?>

                                </td>


                                <!-- STATUS AKUN -->

                                <td>

                                    <?php if (
                                        $row['status_akun']
                                        === 'suspend'
                                    ) { ?>

                                        <span
                                            class="
                                                badge-status
                                                badge-suspend
                                            "
                                        >

                                            <i
                                                class="
                                                    bi
                                                    bi-person-x
                                                "
                                            ></i>

                                            Suspend

                                        </span>

                                    <?php } else { ?>

                                        <span
                                            class="
                                                badge-status
                                                badge-aktif
                                            "
                                        >

                                            <i
                                                class="
                                                    bi
                                                    bi-person-check
                                                "
                                            ></i>

                                            Aktif

                                        </span>

                                    <?php } ?>

                                </td>


                                <!-- AKSI DENDA -->

                                <td>

                                    <?php if (
                                        $row['status_akun']
                                        === 'suspend'
                                    ) { ?>

                                        <div
                                            class="
                                                d-flex
                                                gap-2
                                                flex-wrap
                                            "
                                        >

                                            <?php if (!$isLunas) { ?>

                                                <!-- BAYAR LUNAS -->

                                                <form
                                                    method="POST"
                                                    onsubmit="
                                                        return confirm(
                                                            'Pastikan siswa sudah membayar denda secara langsung di perpustakaan. Tandai denda ini sebagai Lunas?'
                                                        );
                                                    "
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="aksi"
                                                        value="bayar"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int)$row['id'] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="
                                                            btn
                                                            btn-success
                                                            btn-action
                                                        "
                                                    >

                                                        <i
                                                            class="
                                                                bi
                                                                bi-cash-check
                                                            "
                                                        ></i>

                                                        Bayar Lunas

                                                    </button>

                                                </form>

                                            <?php } else { ?>

                                                <!-- SUDAH LUNAS -->

                                                <span
                                                    class="
                                                        badge-status
                                                        badge-lunas
                                                    "
                                                >

                                                    <i
                                                        class="
                                                            bi
                                                            bi-check-circle
                                                        "
                                                    ></i>

                                                    Selesai

                                                </span>

                                            <?php } ?>


                                            <!-- AKTIFKAN AKUN -->

                                            <form
                                                method="POST"
                                                onsubmit="
                                                    return confirm(
                                                        'Aktifkan kembali akun siswa ini?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="aksi"
                                                    value="aktifkan_akun"
                                                >

                                                <!--
                                                    PENTING:
                                                    Yang dikirim adalah ID USER,
                                                    bukan ID PEMINJAMAN.
                                                -->

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int)$row['id_user'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="
                                                        btn
                                                        btn-primary
                                                        btn-action
                                                    "
                                                >

                                                    <i
                                                        class="
                                                            bi
                                                            bi-person-check
                                                        "
                                                    ></i>

                                                    Aktifkan Akun

                                                </button>

                                            </form>

                                        </div>

                                    <?php } else { ?>


                                        <!-- AKUN AKTIF -->

                                        <?php if ($isLunas) { ?>

                                            <span
                                                class="
                                                    badge-status
                                                    badge-lunas
                                                "
                                            >

                                                <i
                                                    class="
                                                        bi
                                                        bi-check-circle
                                                    "
                                                ></i>

                                                Selesai

                                            </span>

                                        <?php } else { ?>

                                            <div
                                                class="
                                                    d-flex
                                                    gap-2
                                                    flex-wrap
                                                "
                                            >

                                                <!-- BAYAR -->

                                                <form
                                                    method="POST"
                                                    onsubmit="
                                                        return confirm(
                                                            'Pastikan siswa sudah membayar denda secara langsung di perpustakaan. Konfirmasi pembayaran sekarang?'
                                                        );
                                                    "
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="aksi"
                                                        value="bayar"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int)$row['id'] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="
                                                            btn
                                                            btn-success
                                                            btn-action
                                                        "
                                                    >

                                                        <i
                                                            class="
                                                                bi
                                                                bi-cash
                                                            "
                                                        ></i>

                                                        Bayar

                                                    </button>

                                                </form>


                                                <!-- SUSPEND -->

                                                <form
                                                    method="POST"
                                                    onsubmit="
                                                        return confirm(
                                                            'Suspend akun siswa ini? Siswa tidak akan dapat login sampai akun diaktifkan kembali.'
                                                        );
                                                    "
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="aksi"
                                                        value="suspend"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int)$row['id'] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="
                                                            btn
                                                            btn-danger
                                                            btn-action
                                                        "
                                                    >

                                                        <i
                                                            class="
                                                                bi
                                                                bi-person-x
                                                            "
                                                        ></i>

                                                        Suspend

                                                    </button>

                                                </form>

                                            </div>

                                        <?php } ?>

                                    <?php } ?>

                                </td>

                            </tr>

                        <?php } ?>

                        </tbody>

                    </table>

                </div>

            <?php } else { ?>

                <div class="empty-data">

                    <i class="bi bi-cash-stack"></i>

                    <div>
                        Tidak ada data denda.
                    </div>

                </div>

            <?php } ?>

        </div>

    </div>


    <!-- =====================================================
         AKUN SISWA SUSPEND
    ====================================================== -->

    <div class="card-custom">

        <div class="card-header-custom">

            <h5>

                <i class="bi bi-person-x"></i>

                Akun Siswa Suspend

            </h5>


            <span class="badge bg-danger">

                <?= $total_suspend ?>

                Suspend

            </span>

        </div>


        <div class="card-body-custom">

            <?php if (
                $resultSuspend
                &&
                $resultSuspend->num_rows > 0
            ) { ?>

                <div class="table-responsive">

                    <table class="table table-hover">

                        <thead>

                            <tr>

                                <th>No</th>

                                <th>Siswa</th>

                                <th>Alasan Suspend</th>

                                <th>Tanggal Suspend</th>

                                <th>Total Denda</th>

                                <th>Status</th>

                                <th>Aksi</th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php

                        $noSuspend = 1;

                        while (
                            $row =
                            $resultSuspend->fetch_assoc()
                        ) {

                            /*
                             * True jika siswa masih memiliki
                             * minimal satu denda yang belum lunas.
                             */

                            $dendaBelumLunas =
                                (int)$row['jumlah_denda_belum_lunas'] > 0;

                        ?>

                            <tr>

                                <!-- NO -->

                                <td>
                                    <?= $noSuspend++ ?>
                                </td>


                                <!-- SISWA -->

                                <td>

                                    <strong>

                                        <?= e(
                                            $row['nama_lengkap']
                                        ) ?>

                                    </strong>

                                    <br>

                                    <small class="text-muted">

                                        <?= e(
                                            $row['username']
                                        ) ?>

                                    </small>

                                </td>


                                <!-- ALASAN SUSPEND -->

                                <td>

                                    <?= e(
                                        $row['alasan_blokir']
                                        ?:
                                        'Akun ditangguhkan karena belum menyelesaikan pembayaran denda perpustakaan.'
                                    ) ?>

                                </td>


                                <!-- TANGGAL SUSPEND -->

                                <td>

                                    <?php if (
                                        $row['tgl_diblokir']
                                    ) { ?>

                                        <?= date(
                                            'd-m-Y H:i',
                                            strtotime(
                                                $row['tgl_diblokir']
                                            )
                                        ) ?>

                                    <?php } else { ?>

                                        -

                                    <?php } ?>

                                </td>


                                <!-- TOTAL DENDA -->

                                <td>

                                    <?php if (
                                        (float)$row['total_denda'] > 0
                                    ) { ?>

                                        <strong class="text-danger">

                                            <?= rupiah(
                                                $row['total_denda']
                                            ) ?>

                                        </strong>

                                    <?php } else { ?>

                                        <span class="text-success">

                                            Rp0

                                        </span>

                                    <?php } ?>

                                </td>


                                <!-- STATUS AKUN -->

                                <td>

                                    <span
                                        class="
                                            badge-status
                                            badge-suspend
                                        "
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-person-x
                                            "
                                        ></i>

                                        Suspend

                                    </span>

                                </td>


                                <!-- AKSI -->

                                <td>

                                    <div
                                        class="
                                            d-flex
                                            gap-2
                                            flex-wrap
                                        "
                                    >

                                        <?php if (
                                            $dendaBelumLunas
                                        ) { ?>

                                            <!-- BAYAR LUNAS -->

                                            <form
                                                method="POST"
                                                onsubmit="
                                                    return confirm(
                                                        'Pastikan seluruh denda siswa sudah dibayarkan secara langsung di perpustakaan. Tandai denda terkait sebagai Lunas?'
                                                    );
                                                "
                                            >

                                                <?php
                                                /*
                                                 * Karena tabel suspend berisi ID USER,
                                                 * untuk tombol Bayar Lunas kita perlu
                                                 * mencari ID peminjaman yang masih
                                                 * memiliki denda belum lunas.
                                                 */
                                                ?>

                                                <input
                                                    type="hidden"
                                                    name="aksi"
                                                    value="bayar"
                                                >

                                                <?php

                                                $stmtBayarSuspend =
                                                    $koneksi->prepare("
                                                        SELECT id
                                                        FROM peminjaman
                                                        WHERE id_user = ?
                                                          AND denda > 0
                                                          AND status_bayar <> 'lunas'
                                                        ORDER BY id DESC
                                                        LIMIT 1
                                                    ");

                                                $stmtBayarSuspend->bind_param(
                                                    "i",
                                                    $row['id']
                                                );

                                                $stmtBayarSuspend->execute();

                                                $resultBayarSuspend =
                                                    $stmtBayarSuspend->get_result();

                                                $dataBayarSuspend =
                                                    $resultBayarSuspend->fetch_assoc();

                                                $stmtBayarSuspend->close();

                                                ?>

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?=
                                                        (int)(
                                                            $dataBayarSuspend['id']
                                                            ?? 0
                                                        )
                                                    ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="
                                                        btn
                                                        btn-success
                                                        btn-action
                                                    "
                                                    <?= empty($dataBayarSuspend['id'])
                                                        ? 'disabled'
                                                        : '' ?>
                                                >

                                                    <i
                                                        class="
                                                            bi
                                                            bi-cash-check
                                                        "
                                                    ></i>

                                                    Bayar Lunas

                                                </button>

                                            </form>

                                        <?php } else { ?>

                                            <!-- DENDA SUDAH LUNAS -->

                                            <span
                                                class="
                                                    badge-status
                                                    badge-lunas
                                                "
                                            >

                                                <i
                                                    class="
                                                        bi
                                                        bi-check-circle
                                                    "
                                                ></i>

                                                Selesai

                                            </span>

                                        <?php } ?>


                                        <!-- AKTIFKAN AKUN -->

                                        <form
                                            method="POST"
                                            onsubmit="
                                                return confirm(
                                                    'Aktifkan kembali akun siswa ini?'
                                                );
                                            "
                                        >

                                            <input
                                                type="hidden"
                                                name="aksi"
                                                value="aktifkan_akun"
                                            >


                                            <!--
                                                PENTING:
                                                Di sini harus ID USER.
                                            -->

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$row['id'] ?>"
                                            >


                                            <button
                                                type="submit"
                                                class="
                                                    btn
                                                    btn-primary
                                                    btn-action
                                                "
                                            >

                                                <i
                                                    class="
                                                        bi
                                                        bi-person-check
                                                    "
                                                ></i>

                                                Aktifkan Akun

                                            </button>

                                        </form>

                                    </div>


                                    <?php if (
                                        $dendaBelumLunas
                                    ) { ?>

                                        <small
                                            class="
                                                d-block
                                                mt-2
                                                text-danger
                                            "
                                        >

                                            <i
                                                class="
                                                    bi
                                                    bi-info-circle
                                                "
                                            ></i>

                                            Akun baru dapat diaktifkan
                                            setelah seluruh denda lunas.

                                        </small>

                                    <?php } ?>

                                </td>

                            </tr>

                        <?php } ?>

                        </tbody>

                    </table>

                </div>

            <?php } else { ?>

                <div class="empty-data">

                    <i class="bi bi-person-check"></i>

                    <div>
                        Tidak ada siswa yang sedang suspend.
                    </div>

                </div>

            <?php } ?>

        </div>

    </div>


    <!-- =====================================================
         INFORMASI ALUR DENDA
    ====================================================== -->

    <div class="card-custom">

        <div class="card-header-custom">

            <h5>

                <i class="bi bi-info-circle"></i>

                Alur Pembayaran Denda

            </h5>

        </div>


        <div class="card-body-custom">

            <div class="row g-3">


                <!-- LANGKAH 1 -->

                <div class="col-md-3">

                    <div
                        class="
                            p-3
                            bg-light
                            rounded-3
                            h-100
                        "
                    >

                        <strong>
                            1. Denda
                        </strong>


                        <p
                            class="
                                mb-0
                                mt-2
                                small
                                text-muted
                            "
                        >

                            Siswa terlambat mengembalikan buku
                            sehingga sistem mencatat nominal denda.

                        </p>

                    </div>

                </div>


                <!-- LANGKAH 2 -->

                <div class="col-md-3">

                    <div
                        class="
                            p-3
                            bg-light
                            rounded-3
                            h-100
                        "
                    >

                        <strong>
                            2. Belum Bayar
                        </strong>


                        <p
                            class="
                                mb-0
                                mt-2
                                small
                                text-muted
                            "
                        >

                            Denda ditampilkan sebagai
                            <b>Belum Bayar</b>.
                            Petugas dapat memilih
                            <b>Bayar</b> atau
                            <b>Suspend</b>.

                        </p>

                    </div>

                </div>


                <!-- LANGKAH 3 -->

                <div class="col-md-3">

                    <div
                        class="
                            p-3
                            bg-light
                            rounded-3
                            h-100
                        "
                    >

                        <strong>
                            3. Tindakan Petugas
                        </strong>


                        <p
                            class="
                                mb-0
                                mt-2
                                small
                                text-muted
                            "
                        >

                            Jika siswa membayar,
                            petugas memilih
                            <b>Bayar</b>.
                            Jika belum menyelesaikan denda,
                            petugas dapat memilih
                            <b>Suspend</b>.

                        </p>

                    </div>

                </div>


                <!-- LANGKAH 4 -->

                <div class="col-md-3">

                    <div
                        class="
                            p-3
                            bg-light
                            rounded-3
                            h-100
                        "
                    >

                        <strong>
                            4. Lunas & Aktifkan
                        </strong>


                        <p
                            class="
                                mb-0
                                mt-2
                                small
                                text-muted
                            "
                        >

                            Setelah denda dibayar,
                            status menjadi <b>Lunas</b>.
                            Jika akun sebelumnya suspend,
                            petugas dapat
                            <b>Aktifkan Akun</b>.

                        </p>

                    </div>

                </div>


            </div>

        </div>

    </div>


</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>