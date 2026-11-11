-- =====================================================================
-- MIGRASI: dua kriteria bertipe COST untuk SAW
--   C9  Tingkat Kesulitan (cost, sumber: buku.tingkat_kesulitan, skala 1-5)
--   C10 Jumlah Halaman    (cost, sumber: buku.jumlah_halaman, dikonversi skala 1-5)
-- Cost = makin kecil makin baik  ->  r = min(x) / x
-- Aman dijalankan ulang (idempotent). Jalankan di phpMyAdmin pada database perpustakaan_db.
-- Nilai perbandingan AHP di bawah adalah USULAN awal (kriteria baru dibuat paling
-- tidak penting). Ubah lewat Admin > AHP bila dosen/pengelola punya penilaian lain,
-- lalu tekan Hitung agar bobot dan CR dihitung ulang.
-- =====================================================================

INSERT IGNORE INTO `kriteria` (`id`, `kode_kriteria`, `nama_kriteria`, `tipe`, `sumber_data`, `aktif`) VALUES
(16, 'C9',  'Tingkat Kesulitan', 'cost', 'tingkat_kesulitan', 1),
(17, 'C10', 'Jumlah Halaman',    'cost', 'jumlah_halaman',    1);
ALTER TABLE `kriteria` AUTO_INCREMENT = 18;

-- skala jumlah halaman -> nilai 1 (tipis) .. 5 (tebal). Tingkat kesulitan sudah 1-5, tanpa skala.
DELETE FROM `skala_nilai` WHERE `id_kriteria` = 17;
INSERT INTO `skala_nilai` (`id_kriteria`, `jenis`, `batas_min`, `kata_kunci`, `nilai`) VALUES
(17, 'rentang',   0.00, NULL, 1.00),
(17, 'rentang', 150.00, NULL, 2.00),
(17, 'rentang', 250.00, NULL, 3.00),
(17, 'rentang', 350.00, NULL, 4.00),
(17, 'rentang', 500.00, NULL, 5.00);

-- perbandingan berpasangan AHP untuk kriteria baru (skala Saaty)
DELETE FROM `perbandingan_ahp` WHERE `kriteria1` IN (16, 17) OR `kriteria2` IN (16, 17);
INSERT INTO `perbandingan_ahp` (`kriteria1`, `kriteria2`, `nilai`) VALUES
(8, 16, 3.000000),
(9, 16, 2.000000),
(10, 16, 1.000000),
(11, 16, 1.000000),
(12, 16, 1.000000),
(13, 16, 3.000000),
(14, 16, 4.000000),
(15, 16, 3.000000),
(8, 17, 4.000000),
(9, 17, 3.000000),
(10, 17, 2.000000),
(11, 17, 1.000000),
(12, 17, 1.000000),
(13, 17, 4.000000),
(14, 17, 5.000000),
(15, 17, 4.000000),
(16, 17, 1.000000);

-- bobot AHP hasil perhitungan 10 kriteria (CR = 0.0083, konsisten karena < 0.1)
DELETE FROM `bobot_ahp`;
INSERT INTO `bobot_ahp` (`id_kriteria`, `bobot`) VALUES
(8, 0.157181),
(9, 0.103749),
(10, 0.063238),
(11, 0.052618),
(12, 0.050208),
(13, 0.157181),
(14, 0.182742),
(15, 0.142240),
(16, 0.050208),
(17, 0.040634);
