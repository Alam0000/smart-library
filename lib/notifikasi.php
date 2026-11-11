<?php
/**
 * Notifikasi persisten (tabel notifikasi).
 * Jenis: pengingat, terlambat, ketersediaan, alternatif, rekomendasi.
 * Dibuat saat siswa membuka beranda/halaman notifikasi (maks. sekali per 10 menit),
 * dan tidak pernah dobel karena memakai kunci unik (id_user, kunci).
 */
require_once __DIR__ . '/saw_engine.php';

function siapkanNotifikasi(mysqli $db): void {
    if (!empty($_SESSION['notif_siap'])) return;
    try {
        $db->query("CREATE TABLE IF NOT EXISTS notifikasi (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            id_user INT NOT NULL,
            jenis VARCHAR(30) NULL,
            judul VARCHAR(180) NOT NULL,
            pesan TEXT NOT NULL,
            tautan VARCHAR(255) NULL,
            kunci VARCHAR(120) NULL,
            dibaca TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notifikasi_user_baca (id_user, dibaca, created_at),
            UNIQUE KEY uq_notif_kunci (id_user, kunci)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}
    // tabel lama dari migrasi belum punya kolom jenis & kunci
    foreach ([
        "ALTER TABLE notifikasi ADD COLUMN jenis VARCHAR(30) NULL AFTER id_user",
        "ALTER TABLE notifikasi ADD COLUMN kunci VARCHAR(120) NULL",
        "ALTER TABLE notifikasi ADD UNIQUE INDEX uq_notif_kunci (id_user, kunci)",
    ] as $sql) {
        try { $db->query($sql); } catch (Throwable $e) {}
    }
    if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['notif_siap'] = true;
}

function tambahNotifikasi(mysqli $db, int $idUser, string $jenis, string $judul, string $pesan, string $tautan, string $kunci): void {
    try {
        $st = $db->prepare("INSERT IGNORE INTO notifikasi (id_user, jenis, judul, pesan, tautan, kunci) VALUES (?,?,?,?,?,?)");
        $st->bind_param('isssss', $idUser, $jenis, $judul, $pesan, $tautan, $kunci);
        $st->execute();
        $st->close();
    } catch (Throwable $e) {}
}

function jumlahNotifBelumDibaca(mysqli $db, int $idUser): int {
    try {
        siapkanNotifikasi($db);
        $st = $db->prepare("SELECT COUNT(*) n FROM notifikasi WHERE id_user=? AND dibaca=0");
        $st->bind_param('i', $idUser);
        $st->execute();
        return (int)($st->get_result()->fetch_assoc()['n'] ?? 0);
    } catch (Throwable $e) { return 0; }
}

function sinkronNotifikasi(mysqli $db, int $idUser, bool $paksa = false): void {
    if ($idUser <= 0) return;
    if (session_status() === PHP_SESSION_ACTIVE) {
        $t = (int)($_SESSION['notif_sync'][$idUser] ?? 0);
        if (!$paksa && time() - $t < 600) return;
        $_SESSION['notif_sync'][$idUser] = time();
    }
    siapkanNotifikasi($db);

    $minggu = date('oW');
    $hariIni = date('Y-m-d');
    $batas = date('Y-m-d', strtotime('+2 days'));

    // ranking SAW dihitung sekali dan hanya jika dibutuhkan
    $semua = null;
    $ambilSemua = function () use ($db, $idUser, &$semua) {
        if ($semua === null) {
            try { $semua = rekomendasi($db, null, $idUser); } catch (Throwable $e) { $semua = []; }
        }
        return $semua;
    };

    // 1. pengingat pengembalian & keterlambatan
    try {
        $st = $db->prepare("SELECT p.id, p.tanggal_kembali, b.id AS id_buku, b.nama_buku FROM peminjaman p JOIN buku b ON b.id=p.id_buku WHERE p.id_user=? AND p.status IN ('dipinjam','terlambat')");
        $st->bind_param('i', $idUser);
        $st->execute();
        $res = $st->get_result();
        while ($x = $res->fetch_assoc()) {
            $due = (string)($x['tanggal_kembali'] ?? '');
            if ($due === '') continue;
            if ($due < $hariIni) {
                tambahNotifikasi($db, $idUser, 'terlambat', 'Peminjaman terlambat',
                    'Buku “' . $x['nama_buku'] . '” melewati batas pengembalian (' . date('d-m-Y', strtotime($due)) . '). Segera kembalikan ke petugas.',
                    'buku_saya.php', 'telat:' . $x['id']);
            } elseif ($due <= $batas) {
                tambahNotifikasi($db, $idUser, 'pengingat', 'Pengingat pengembalian',
                    'Buku “' . $x['nama_buku'] . '” harus dikembalikan paling lambat ' . date('d-m-Y', strtotime($due)) . '.',
                    'buku_saya.php', 'tempo:' . $x['id']);
            }
        }
        $st->close();
    } catch (Throwable $e) {}

    // 2 & 3. ketersediaan buku tersimpan + alternatif bila sedang dipinjam
    try {
        $st = $db->prepare("SELECT b.id, b.nama_buku, b.kategori, b.stok,
            (SELECT MIN(p.tanggal_kembali) FROM peminjaman p WHERE p.id_buku=b.id AND p.status IN ('dipinjam','terlambat')) AS perkiraan,
            (SELECT MAX(p.tgl_kembali_aktual) FROM peminjaman p WHERE p.id_buku=b.id) AS terakhir
            FROM simpan_buku s JOIN buku b ON b.id=s.id_buku WHERE s.id_user=?");
        $st->bind_param('i', $idUser);
        $st->execute();
        $res = $st->get_result();
        while ($b = $res->fetch_assoc()) {
            $idB = (int)$b['id'];
            $tautan = 'detail_buku.php?id=' . $idB;
            if ((int)$b['stok'] > 0) {
                tambahNotifikasi($db, $idUser, 'ketersediaan', 'Buku tersimpan tersedia',
                    'Buku “' . $b['nama_buku'] . '” kini tersedia (' . (int)$b['stok'] . ' eksemplar). Segera ajukan peminjaman.',
                    $tautan, 'tersedia:' . $idB . ':' . ($b['terakhir'] ?? '0'));
            } else {
                $ket = $b['perkiraan'] ? ' Perkiraan tersedia kembali ' . date('d-m-Y', strtotime($b['perkiraan'])) . '.' : '';
                tambahNotifikasi($db, $idUser, 'ketersediaan', 'Buku tersimpan sedang dipinjam',
                    'Buku “' . $b['nama_buku'] . '” sedang dipinjam.' . $ket, $tautan,
                    'habis:' . $idB . ':' . ($b['perkiraan'] ?? $minggu));
                $alt = null;
                foreach ($ambilSemua() as $r) {
                    $bb = $r['buku'];
                    if ((int)$r['id_buku'] === $idB || (int)($bb['stok'] ?? 0) <= 0) continue;
                    if (($bb['kategori'] ?? '') === $b['kategori']) { $alt = $r; break; }
                    if ($alt === null) $alt = $r;   // cadangan: peringkat tertinggi yang tersedia
                }
                if ($alt) {
                    tambahNotifikasi($db, $idUser, 'alternatif', 'Alternatif buku yang tersedia',
                        'Karena “' . $b['nama_buku'] . '” sedang dipinjam, coba “' . ($alt['buku']['nama_buku'] ?? '') . '”. ' . $alt['alasan']['kalimat'],
                        'detail_buku.php?id=' . (int)$alt['id_buku'], 'alt:' . $idB . ':' . (int)$alt['id_buku']);
                }
            }
        }
        $st->close();
    } catch (Throwable $e) {}

    // 4. rekomendasi personal mingguan (hanya jika siswa punya profil minat)
    try {
        $profil = muatProfil($db, $idUser);
        if (!empty($profil['ada'])) {
            foreach ($ambilSemua() as $r) {
                $b = $r['buku'];
                if ((int)($b['stok'] ?? 0) > 0 && empty($b['sudah_dipinjam'])) {
                    tambahNotifikasi($db, $idUser, 'rekomendasi', 'Rekomendasi untuk Anda',
                        'Coba baca “' . ($b['nama_buku'] ?? '') . '”. ' . $r['alasan']['kalimat'],
                        'detail_buku.php?id=' . (int)$r['id_buku'], 'rek:' . $minggu);
                    break;
                }
            }
        }
    } catch (Throwable $e) {}
}
