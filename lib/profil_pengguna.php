<?php
/**
 * PROFIL MINAT PENGGUNA (dasar Personalized Recommendation)
 *
 * Profil dibangun dari lima sinyal:
 *   1. preferensi kategori yang dipilih sendiri  (tabel preferensi_kategori)
 *   2. peminjaman                                 (tabel peminjaman)
 *   3. buku yang disimpan                         (tabel simpan_buku)
 *   4. rating yang diberikan                      (tabel rating_buku, rating >= 3)
 *   5. pencarian                                  (tabel riwayat_pencarian)
 *
 * Dua kriteria personal yang dihasilkan (nilai 0-100, lalu diubah ke skor 1-5
 * oleh tabel skala_nilai seperti kriteria lain):
 *   - minat_kategori : seberapa sesuai kategori buku dengan minat pengguna
 *   - kemiripan_buku : seberapa mirip buku dengan buku yang pernah dibaca/diminati
 */

/** Seberapa besar tiap sinyal memengaruhi profil (boleh diubah). */
const BOBOT_SINYAL = [
    'preferensi'      => 4,   // kategori favorit yang dipilih langsung oleh pengguna
    'pinjam'          => 3,   // per kali meminjam
    'simpan'          => 2,   // buku disimpan
    'rating_per_poin' => 1,   // rating 3 => 1, rating 4 => 2, rating 5 => 3
    'cari'            => 1,   // per pencarian yang cocok
];

/** Bobot kemiripan dua buku (total 100). */
const BOBOT_KEMIRIPAN = ['kategori' => 50, 'penulis' => 30, 'penerbit' => 20];

function daftarSumberPersonal(): array {
    return [
        'minat_kategori' => 'Kesesuaian minat kategori (personal)',
        'kemiripan_buku' => 'Kemiripan dengan buku yang pernah dibaca (personal)',
    ];
}

function isSumberPersonal($sumber): bool {
    return isset(daftarSumberPersonal()[(string)$sumber]);
}

/** ['a','b','c'] -> "a, b, dan c" ; ['a','b'] -> "a dan b" */
function gabungKata(array $kata): string {
    $kata = array_values($kata);
    $n = count($kata);
    if ($n <= 1) return $kata[0] ?? '';
    if ($n == 2) return $kata[0] . ' dan ' . $kata[1];
    return implode(', ', array_slice($kata, 0, -1)) . ', dan ' . $kata[$n - 1];
}

function profilKosong(int $idUser = 0): array {
    return [
        'id_user' => $idUser, 'ada' => false,
        'kategori' => [],      // kunci huruf kecil => ['label'=>..., 'bobot'=>...]
        'penulis' => [],       // kunci huruf kecil => bobot
        'sumber' => [],        // id_buku => bobot (buku acuan untuk kemiripan)
        'pernah_pinjam' => [], // id_buku => true
        'preferensi' => [],    // daftar kategori favorit
    ];
}

/** Bangun profil seorang pengguna. Tabel yang belum ada diabaikan (tidak error). */
function muatProfil(mysqli $db, ?int $idUser): array {

    if (!$idUser) return profilKosong();

    $profil = profilKosong($idUser);

    $buku = [];
    $q = $db->query("SELECT id, nama_buku, penulis, penerbit, kategori FROM buku");
    while ($r = $q->fetch_assoc()) $buku[(int)$r['id']] = $r;
    if (!$buku) return $profil;

    $tambahKategori = function ($label, $bobot) use (&$profil) {
        $label = trim((string)$label);
        if ($label === '' || $bobot <= 0) return;
        $k = strtolower($label);
        if (!isset($profil['kategori'][$k])) $profil['kategori'][$k] = ['label' => $label, 'bobot' => 0];
        $profil['kategori'][$k]['bobot'] += $bobot;
    };
    $tambahPenulis = function ($nama, $bobot) use (&$profil) {
        $k = strtolower(trim((string)$nama));
        if ($k === '' || $bobot <= 0) return;
        $profil['penulis'][$k] = ($profil['penulis'][$k] ?? 0) + $bobot;
    };
    $tambahBuku = function ($idBuku, $bobot) use (&$profil, $buku, $tambahKategori, $tambahPenulis) {
        $idBuku = (int)$idBuku;
        if (!isset($buku[$idBuku]) || $bobot <= 0) return;
        $tambahKategori($buku[$idBuku]['kategori'], $bobot);
        $tambahPenulis($buku[$idBuku]['penulis'], $bobot);
        $profil['sumber'][$idBuku] = ($profil['sumber'][$idBuku] ?? 0) + $bobot;
    };

    $jalankan = function (string $sql, callable $untukBaris) use ($db, $idUser) {
        try {
            $st = $db->prepare($sql);
            $st->bind_param('i', $idUser);
            $st->execute();
            $res = $st->get_result();
            while ($r = $res->fetch_assoc()) $untukBaris($r);
            $st->close();
        } catch (Throwable $e) { /* tabel belum ada: lewati */ }
    };

    // 1. preferensi kategori
    $jalankan("SELECT kategori FROM preferensi_kategori WHERE id_user = ?", function ($r) use (&$profil, $tambahKategori) {
        $profil['preferensi'][] = $r['kategori'];
        $tambahKategori($r['kategori'], BOBOT_SINYAL['preferensi']);
    });

    // 2. peminjaman
    $jalankan("SELECT id_buku, COUNT(*) n FROM peminjaman WHERE id_user = ? GROUP BY id_buku",
        function ($r) use (&$profil, $tambahBuku) {
            $profil['pernah_pinjam'][(int)$r['id_buku']] = true;
            $tambahBuku($r['id_buku'], BOBOT_SINYAL['pinjam'] * min(3, (int)$r['n']));
        });

    // 3. buku disimpan
    $jalankan("SELECT DISTINCT id_buku FROM simpan_buku WHERE id_user = ?", function ($r) use ($tambahBuku) {
        $tambahBuku($r['id_buku'], BOBOT_SINYAL['simpan']);
    });

    // 4. rating (hanya yang menyukai: >= 3)
    $jalankan("SELECT id_buku, rating FROM rating_buku WHERE id_user = ?", function ($r) use ($tambahBuku) {
        $poin = (int)$r['rating'] - 2;
        if ($poin > 0) $tambahBuku($r['id_buku'], $poin * BOBOT_SINYAL['rating_per_poin']);
    });

    // 5. pencarian (30 terakhir): kata kunci dicocokkan ke nama/penulis/kategori buku
    $jalankan("SELECT kata_kunci FROM riwayat_pencarian WHERE id_user = ? ORDER BY id DESC LIMIT 30",
        function ($r) use ($buku, $tambahKategori, $tambahPenulis, &$profil) {
            $kw = strtolower(trim((string)$r['kata_kunci']));
            if (strlen($kw) < 3) return;
            $kat = []; $pen = []; $cocok = [];
            foreach ($buku as $id => $b) {
                $hay = strtolower($b['nama_buku'] . ' ' . $b['penulis'] . ' ' . $b['kategori']);
                if (strpos($hay, $kw) !== false) {
                    $kat[strtolower($b['kategori'])] = $b['kategori'];
                    $pen[strtolower($b['penulis'])] = $b['penulis'];
                    $cocok[] = $id;
                }
            }
            foreach ($kat as $label) $tambahKategori($label, BOBOT_SINYAL['cari']);
            foreach ($pen as $nama)  $tambahPenulis($nama, BOBOT_SINYAL['cari']);
            foreach ($cocok as $id)  $profil['sumber'][$id] = ($profil['sumber'][$id] ?? 0) + BOBOT_SINYAL['cari'];
        });

    $profil['ada'] = !empty($profil['kategori']);
    return $profil;
}

/**
 * Hitung dua nilai personal (0-100) untuk satu buku + keterangannya.
 * $semuaBuku: id => baris buku (dipakai untuk membandingkan dengan buku acuan).
 */
function hitungPersonalBuku(array $profil, array $b, array $semuaBuku): array {

    // --- kesesuaian minat kategori: dibanding kategori terfavorit pengguna
    $maks = 0;
    foreach ($profil['kategori'] as $k) $maks = max($maks, $k['bobot']);
    $bobotKat = $profil['kategori'][strtolower((string)$b['kategori'])]['bobot'] ?? 0;
    $minat = $maks > 0 ? round($bobotKat / $maks * 100, 1) : 0.0;
    $ketMinat = $bobotKat > 0 ? 'kategori ' . $b['kategori'] . ' sesuai minat Anda' : '';

    // --- kemiripan dengan buku acuan (dipinjam/disimpan/dirating/dicari)
    $terbaik = 0; $acuan = null; $alasan = [];
    foreach ($profil['sumber'] as $idAcuan => $bobotAcuan) {
        if ($idAcuan == $b['id'] || !isset($semuaBuku[$idAcuan])) continue;
        $s = $semuaBuku[$idAcuan];
        $skor = 0; $al = [];
        if (strtolower((string)$s['kategori']) === strtolower((string)$b['kategori']) && $b['kategori'] !== '') { $skor += BOBOT_KEMIRIPAN['kategori']; $al[] = 'kategori'; }
        if (strtolower((string)$s['penulis'])  === strtolower((string)$b['penulis'])  && $b['penulis']  !== '') { $skor += BOBOT_KEMIRIPAN['penulis'];  $al[] = 'penulis'; }
        if (strtolower((string)$s['penerbit']) === strtolower((string)$b['penerbit']) && $b['penerbit'] !== '') { $skor += BOBOT_KEMIRIPAN['penerbit']; $al[] = 'penerbit'; }
        if ($skor > $terbaik) { $terbaik = $skor; $acuan = $s; $alasan = $al; }
    }
    $ketMirip = '';
    if ($acuan) {
        $pernah = !empty($profil['pernah_pinjam'][(int)$acuan['id']]);
        $ketMirip = 'mirip dengan "' . $acuan['nama_buku'] . '" yang ' . ($pernah ? 'pernah Anda pinjam' : 'Anda minati')
                  . ' (' . gabungKata($alasan) . ' sama)';
    }

    return [
        'minat_kategori' => (float)$minat,
        'kemiripan_buku' => (float)$terbaik,
        'ket' => ['minat_kategori' => $ketMinat, 'kemiripan_buku' => $ketMirip],
        'sudah_dipinjam' => !empty($profil['pernah_pinjam'][(int)$b['id']]),
    ];
}