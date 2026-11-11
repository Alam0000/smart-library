<?php
/**
 * Mesin SAW dinamis (tanpa menyimpan apa pun ke database).
 * Dipakai oleh: halaman admin, rekomendasi user, dan What-If Analysis.
 *
 * Alur:  muatKriteria() + muatBobot() + muatDataBuku()
 *        -> hitungSAW($kriteria, $bobot, $nilaiBuku, $infoBuku)
 */

require_once __DIR__ . '/profil_pengguna.php';

/** Pilihan sumber data kriteria (kolom tabel buku, kecuali 'rating' = rata-rata rating_buku). */
function daftarSumberData(): array {
    return [
        'jumlah_dipinjam'   => 'Frekuensi peminjaman (jumlah_dipinjam)',
        'tahun_terbit'      => 'Tahun terbit',
        'stok'              => 'Stok / ketersediaan',
        'kategori'          => 'Kategori (teks)',
        'penerbit'          => 'Penerbit (teks)',
        'bahasa'            => 'Bahasa (teks)',
        'rating'            => 'Rating rata-rata pengguna',
        'view_buku'         => 'Jumlah dilihat (popularitas)',
        'tingkat_kesulitan' => 'Tingkat kesulitan (1-5)',
        'jumlah_halaman'    => 'Jumlah halaman',
    ] + daftarSumberPersonal();
}

/**
 * Kriteria aktif, urut id. Tidak ada lagi batasan "id 8-12" atau "harus 5".
 * $sertakanPersonal = false -> kriteria personal (minat_kategori, kemiripan_buku)
 * dibuang. Dipakai untuk ranking UMUM (halaman admin, pengunjung, siswa tanpa histori).
 */
function muatKriteria(mysqli $db, bool $sertakanPersonal = true): array {
    $out = [];
    $q = $db->query("SELECT * FROM kriteria WHERE aktif = 1 ORDER BY id");
    while ($r = $q->fetch_assoc()) {
        if (!$sertakanPersonal && isSumberPersonal($r['sumber_data'] ?? '')) continue;
        $out[] = $r;
    }
    return $out;
}

/** Bobot tersimpan hasil AHP: [id_kriteria => bobot]. */
function muatBobot(mysqli $db): array {
    $out = [];
    $q = $db->query("SELECT id_kriteria, bobot FROM bobot_ahp");
    while ($r = $q->fetch_assoc()) $out[(int)$r['id_kriteria']] = (float)$r['bobot'];
    return $out;
}

/** Skala nilai per kriteria: [id_kriteria => [baris...]]. */
function muatSkala(mysqli $db): array {
    $out = [];
    $q = $db->query("SELECT * FROM skala_nilai ORDER BY id_kriteria, batas_min");
    while ($r = $q->fetch_assoc()) $out[(int)$r['id_kriteria']][] = $r;
    return $out;
}

/** Ubah bobot apa pun (misalnya dari slider What-If) jadi total = 1. */
function normalisasiBobot(array $bobot): array {
    $total = array_sum($bobot);
    if ($total <= 0) {
        $n = count($bobot);
        return $n ? array_fill_keys(array_keys($bobot), 1 / $n) : [];
    }
    foreach ($bobot as $k => $v) $bobot[$k] = max(0, $v) / $total;
    return $bobot;
}

/** Konversi nilai mentah (angka/teks) ke skor 1-5 memakai tabel skala_nilai. */
function konversiSkala($mentah, array $skala): float {
    if (!$skala) return is_numeric($mentah) ? (float)$mentah : 1.0;
    $jenis = $skala[0]['jenis'];
    if ($jenis === 'rentang') {
        $hasil = (float)$skala[0]['nilai'];
        $terbaik = -INF;
        foreach ($skala as $s) {
            $b = (float)$s['batas_min'];
            if ((float)$mentah >= $b && $b >= $terbaik) { $terbaik = $b; $hasil = (float)$s['nilai']; }
        }
        return $hasil;
    }
    $teks = strtolower(trim((string)$mentah));
    foreach ($skala as $s) {
        if ($s['kata_kunci'] !== null && $s['kata_kunci'] !== '' &&
            strpos($teks, strtolower($s['kata_kunci'])) !== false) {
            return (float)$s['nilai'];
        }
    }
    return 1.0; // default sama seperti kode lama
}

/**
 * Ambil semua buku (TERMASUK buku baru yang belum pernah dipinjam)
 * dan hitung skor 1-5 tiap kriteria.
 * Return: [ $nilaiBuku[id_buku][id_kriteria] => skor, $infoBuku[id_buku] => baris buku ]
 */
function muatDataBuku(mysqli $db, array $kriteria, ?array $profil = null): array {
    $skala = muatSkala($db);
    $rating = []; $jmlRating = []; $totSuara = 0; $totNilai = 0.0;
    $q = $db->query("SELECT id_buku, AVG(rating) a, COUNT(*) n FROM rating_buku GROUP BY id_buku");
    while ($r = $q->fetch_assoc()) {
        $rating[(int)$r['id_buku']]    = (float)$r['a'];
        $jmlRating[(int)$r['id_buku']] = (int)$r['n'];
        $totSuara += (int)$r['n'];
        $totNilai += (float)$r['a'] * (int)$r['n'];
    }
    // Rating terbobot (Bayesian): buku tanpa/dengan sedikit ulasan ditarik ke rata-rata umum,
    // sehingga buku baru tidak otomatis bernilai 0 dan 1 ulasan tidak mengalahkan 50 ulasan.
    //   skor = (n*R + m*C) / (n + m)   n=jumlah ulasan, R=rata-rata buku, C=rata-rata semua, m=3
    $rataUmum  = $totSuara > 0 ? $totNilai / $totSuara : 3.0;
    $minSuara  = 3;
    // data yang boleh kosong: diisi nilai median agar tidak dihukum pada kriteria cost
    $opsional  = ['jumlah_halaman', 'tingkat_kesulitan'];

    $semua = [];
    $q = $db->query("SELECT * FROM buku");
    while ($b = $q->fetch_assoc()) $semua[(int)$b['id']] = $b;

    $nilai = []; $info = [];
    foreach ($semua as $id => $b) {
        $b['rating'] = $rating[$id] ?? 0;       // rata-rata asli (tampilan)
        $b['jumlah_rating'] = $jmlRating[$id] ?? 0;

        // nilai personal (0-100) + keterangannya, hanya jika pengguna punya profil minat
        if ($profil && !empty($profil['ada'])) {
            $p = hitungPersonalBuku($profil, $b, $semua);
            $b['minat_kategori'] = $p['minat_kategori'];
            $b['kemiripan_buku'] = $p['kemiripan_buku'];
            $b['_ket']           = $p['ket'];
            $b['sudah_dipinjam'] = $p['sudah_dipinjam'];
        }

        $info[$id] = $b;
        foreach ($kriteria as $k) {
            $sumber = $k['sumber_data'];
            if ($sumber === 'rating') {
                $n = $jmlRating[$id] ?? 0;
                $mentah = ($n * ($rating[$id] ?? 0) + $minSuara * $rataUmum) / ($n + $minSuara);
            } elseif (in_array($sumber, $opsional, true) && !((float)($b[$sumber] ?? 0) > 0)) {
                $nilai[$id][(int)$k['id']] = null;   // diisi median di bawah
                continue;
            } else {
                $mentah = $b[$sumber] ?? 0;       // kolom di tabel buku / nilai personal
            }
            $nilai[$id][(int)$k['id']] = konversiSkala($mentah, $skala[(int)$k['id']] ?? []);
        }
    }
    // isi data kosong (jumlah halaman / tingkat kesulitan) dengan median skor buku lain
    foreach ($kriteria as $k) {
        $kid = (int)$k['id'];
        if (!in_array($k['sumber_data'] ?? '', $opsional, true)) continue;
        $ada = [];
        foreach ($nilai as $baris) if (isset($baris[$kid])) $ada[] = $baris[$kid];
        if (count($ada) === count($nilai)) continue;
        sort($ada);
        $median = $ada ? $ada[intdiv(count($ada), 2)] : 3.0;
        foreach ($nilai as $idBuku => $baris) {
            if (!isset($baris[$kid])) $nilai[$idBuku][$kid] = $median;
        }
    }
    return [$nilai, $info];
}

/**
 * INTI SAW (fungsi murni, tidak menyentuh database, mudah diuji).
 *  benefit : r = x / max(x)
 *  cost    : r = min(x) / x
 * Return daftar buku terurut skor tertinggi, lengkap dengan kontribusi
 * tiap kriteria (bahan Explainable Recommendation).
 */
function hitungSAW(array $kriteria, array $bobot, array $nilaiBuku, array $infoBuku = []): array {
    $idAktif = array_map('intval', array_column($kriteria, 'id'));
    $bobot = normalisasiBobot(array_intersect_key($bobot, array_flip($idAktif)));
    $maks = []; $min = [];
    foreach ($kriteria as $k) {
        $kolom = array_column($nilaiBuku, (int)$k['id']);
        $maks[$k['id']] = $kolom ? max($kolom) : 1;
        $min[$k['id']]  = $kolom ? min($kolom) : 1;
    }

    $hasil = [];
    foreach ($nilaiBuku as $idBuku => $baris) {
        $skor = 0.0; $kontribusi = [];
        foreach ($kriteria as $k) {
            $id = (int)$k['id'];
            $x = (float)($baris[$id] ?? 0);
            if ($k['tipe'] === 'cost') {
                $r = $x > 0 ? $min[$id] / $x : 0;
            } else {
                $r = $maks[$id] > 0 ? $x / $maks[$id] : 0;
            }
            $w = $bobot[$id] ?? 0;
            $kontribusi[$id] = [
                'kode' => $k['kode_kriteria'], 'nama' => $k['nama_kriteria'], 'tipe' => $k['tipe'], 'nilai' => $x,
                'sumber' => $k['sumber_data'] ?? '',
                'mentah' => $infoBuku[$idBuku][$k['sumber_data'] ?? ''] ?? null,
                'ket' => $infoBuku[$idBuku]['_ket'][$k['sumber_data'] ?? ''] ?? '',
                'normal' => $r, 'bobot' => $w, 'kontribusi' => $r * $w,
            ];
            $skor += $r * $w;
        }
        $hasil[] = [
            'id_buku' => $idBuku,
            'nama_buku' => $infoBuku[$idBuku]['nama_buku'] ?? '',
            'skor' => $skor,
            'kontribusi' => $kontribusi,
        ];
    }
    usort($hasil, fn($a, $b) => $b['skor'] <=> $a['skor']);
    foreach ($hasil as $i => &$h) $h['ranking'] = $i + 1;
    return $hasil;
}

/** Frasa manusiawi untuk satu kriteria, mis. "sering dipinjam (3x)". */
function frasaKriteria(array $c): string {
    $v = $c['mentah'];
    $ada = ($v !== null && $v !== '');
    switch ($c['sumber']) {
        case 'jumlah_dipinjam':   return $ada ? "sering dipinjam ({$v}x)" : 'sering dipinjam';
        case 'tahun_terbit':      return $ada ? "terbitannya baru ($v)" : 'terbitannya baru';
        case 'kategori':          return $ada ? "kategorinya sesuai ($v)" : 'kategorinya sesuai';
        case 'stok':              return $ada ? "stoknya tersedia ($v eksemplar)" : 'stoknya tersedia';
        case 'penerbit':          return $ada ? "diterbitkan oleh $v" : 'penerbitnya terpercaya';
        case 'bahasa':            return $ada ? "berbahasa $v" : 'bahasanya sesuai';
        case 'rating':            return ($ada && $v > 0) ? 'ratingnya tinggi (' . number_format((float)$v, 1) . '/5)' : '';  // belum ada rating: tidak dipuji
        case 'view_buku':         return $ada ? "banyak dilihat ({$v}x)" : 'banyak dilihat';
        case 'tingkat_kesulitan':
            if (($c['tipe'] ?? 'benefit') === 'cost') return $ada ? "mudah dipahami ($v/5)" : 'mudah dipahami';
            return $ada ? "tingkat kesulitannya sesuai ($v/5)" : 'tingkat kesulitannya sesuai';
        case 'jumlah_halaman':
            if (($c['tipe'] ?? 'benefit') === 'cost') return $ada ? "ringkas ($v halaman)" : 'ringkas';
            return $ada ? "tebalnya sesuai ($v halaman)" : 'tebalnya sesuai';
        case 'minat_kategori':    return $c['ket'] !== '' ? $c['ket'] : 'sesuai minat Anda';
        case 'kemiripan_buku':    return $c['ket'] !== '' ? $c['ket'] : '';
    }
    return strtolower($c['nama']);
}

/** Frasa untuk kriteria yang LEMAH, mis. "jarang dipinjam (1x)". */
function frasaLemah(array $c): string {
    $v = $c['mentah'];
    $ada = ($v !== null && $v !== '');
    switch ($c['sumber']) {
        case 'jumlah_dipinjam':   return $ada ? "lebih jarang dipinjam dibanding buku lain ({$v}x)" : 'lebih jarang dipinjam dibanding buku lain';
        case 'tahun_terbit':      return $ada ? "terbitannya lebih lama dibanding buku lain ($v)" : 'terbitannya lebih lama dibanding buku lain';
        case 'kategori':          return $ada ? "kategorinya kurang diprioritaskan ($v)" : 'kategorinya kurang diprioritaskan';
        case 'stok':              return $ada ? "stoknya lebih sedikit dibanding buku lain ($v eksemplar)" : 'stoknya lebih sedikit dibanding buku lain';
        case 'penerbit':          return 'penerbitnya belum masuk daftar prioritas';
        case 'rating':            return ($ada && $v > 0) ? 'ratingnya belum tinggi (' . number_format((float)$v, 1) . '/5)' : 'belum memiliki rating';
        case 'minat_kategori':    return 'kategorinya belum termasuk minat Anda';
        case 'kemiripan_buku':    return 'belum mirip dengan buku yang pernah Anda baca';
        case 'tingkat_kesulitan':
            if (($c['tipe'] ?? 'benefit') === 'cost') return $ada ? "tingkat kesulitannya lebih tinggi dibanding buku lain ($v/5)" : 'tingkat kesulitannya lebih tinggi dibanding buku lain';
            return $ada ? "tingkat kesulitannya kurang sesuai ($v/5)" : 'tingkat kesulitannya kurang sesuai';
        case 'jumlah_halaman':
            if (($c['tipe'] ?? 'benefit') === 'cost') return $ada ? "lebih tebal dibanding buku lain ($v halaman)" : 'lebih tebal dibanding buku lain';
            return 'jumlah halamannya kurang sesuai';
    }
    return 'nilai ' . strtolower($c['nama']) . ' masih rendah';
}

/**
 * Alasan rekomendasi (Explainable Recommendation).
 * Return: ['kalimat' => ..., 'positif' => [...], 'lemah' => ...]
 *  - positif : kriteria penyumbang skor terbesar yang nilainya memang tinggi (>= 0,6)
 *  - lemah   : kriteria yang paling banyak "menghilangkan" skor
 */
function alasanRekomendasi(array $item): array {
    $k = $item['kontribusi'];

    $urut = $k;
    uasort($urut, fn($a, $b) => $b['kontribusi'] <=> $a['kontribusi']);
    $positif = [];
    foreach ($urut as $c) {
        if ($c['normal'] >= 0.6 && $c['kontribusi'] > 0) {
            $f = frasaKriteria($c);
            if ($f !== '') $positif[] = $f;
        }
        if (count($positif) == 2) break;
    }

    $lemah = null; $rugi = 0.0;
    foreach ($k as $c) {
        $hilang = $c['bobot'] * (1 - $c['normal']);   // poin yang tidak terambil
        if ($c['normal'] < 0.8 && $hilang > $rugi) { $rugi = $hilang; $lemah = $c; }
    }

    if ($positif) {
        $kalimat = 'Direkomendasikan karena ' . implode(' dan ', $positif) . '.';
    } else {
        $kalimat = 'Skor buku ini relatif rendah di sebagian besar kriteria.';
    }
    $fraseLemah = null;
    if ($lemah && $rugi >= 0.01) {
        $fraseLemah = frasaLemah($lemah);
        $kalimat .= ' Skornya tertahan karena ' . $fraseLemah . '.';
    }
    return ['kalimat' => $kalimat, 'positif' => $positif, 'lemah' => $fraseLemah];
}

/**
 * Paket lengkap: baca DB, hitung, kembalikan ranking.
 *  $bobotOverride : bobot simulasi (What-If), null = bobot AHP
 *  $idUser        : jika diisi DAN pengguna punya profil minat, kriteria personal
 *                   ikut dihitung (Personalized Recommendation). Selain itu ranking umum.
 */
function rekomendasi(mysqli $db, ?array $bobotOverride = null, ?int $idUser = null): array {
    $profil = $idUser ? muatProfil($db, $idUser) : null;
    $personal = $profil && !empty($profil['ada']);

    $kriteria = muatKriteria($db, $personal);
    $bobot = $bobotOverride ?? muatBobot($db);
    [$nilai, $info] = muatDataBuku($db, $kriteria, $profil);
    $hasil = hitungSAW($kriteria, $bobot, $nilai, $info);
    foreach ($hasil as &$h) {
        $h['buku']   = $info[$h['id_buku']] ?? [];
        $h['alasan'] = alasanRekomendasi($h);
    }
    unset($h);
    return $hasil;
}

/**
 * Hitung ranking UMUM dengan mesin dinamis (kriteria non-personal yang aktif, semua buku,
 * benefit & cost) lalu arsipkan ke tabel hasil_saw lengkap dengan kolom ranking.
 * Dipakai bersama oleh Admin/saw/hitung.php dan Admin/hasil_ranking.php agar hasilnya sama.
 * Mengembalikan [] (tanpa menyentuh arsip) bila ada kriteria aktif yang belum punya bobot AHP.
 */
function arsipkanRankingUmum(mysqli $db): array {
    $kriteria = muatKriteria($db, false);
    $bobot = muatBobot($db);
    if (!$kriteria) return [];
    foreach ($kriteria as $k) {
        if (!isset($bobot[(int)$k['id']])) return [];
    }
    [$nilai, $info] = muatDataBuku($db, $kriteria);
    $hasil = hitungSAW($kriteria, $bobot, $nilai, $info);
    usort($hasil, function ($a, $b) {
        $c = $b['skor'] <=> $a['skor'];
        return $c !== 0 ? $c : strcmp($a['nama_buku'], $b['nama_buku']);
    });
    $db->query("TRUNCATE TABLE hasil_saw");
    $st = $db->prepare("INSERT INTO hasil_saw (id_buku, nilai, ranking) VALUES (?, ?, ?)");
    foreach ($hasil as $i => $h) {
        $idBuku = (int)$h['id_buku']; $skor = (float)$h['skor']; $urut = $i + 1;
        $st->bind_param('idi', $idBuku, $skor, $urut);
        $st->execute();
    }
    $st->close();
    return $hasil;
}
