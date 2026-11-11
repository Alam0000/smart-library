<?php
/** Mencatat kata kunci pencarian siswa yang login (bahan profil minat). */
function catatPencarian(mysqli $db, $kata): void {
    $kata = trim((string)$kata);
    if ($kata === '' || strlen($kata) < 3 || empty($_SESSION['id_user'])) return;
    $kata = mb_substr($kata, 0, 150);
    $idUser = (int)$_SESSION['id_user'];
    try {
        // jangan dobel jika kata yang sama baru saja dicari
        $cek = $db->prepare("SELECT kata_kunci FROM riwayat_pencarian WHERE id_user = ? ORDER BY id DESC LIMIT 1");
        $cek->bind_param('i', $idUser);
        $cek->execute();
        $terakhir = $cek->get_result()->fetch_assoc();
        $cek->close();
        if ($terakhir && strcasecmp($terakhir['kata_kunci'], $kata) === 0) return;

        $st = $db->prepare("INSERT INTO riwayat_pencarian (id_user, kata_kunci) VALUES (?, ?)");
        $st->bind_param('is', $idUser, $kata);
        $st->execute();
        $st->close();
    } catch (Throwable $e) { /* tabel belum dibuat: abaikan, jangan merusak halaman */ }
}
