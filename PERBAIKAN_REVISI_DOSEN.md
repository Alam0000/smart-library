# Perbaikan sesuai catatan revisi dosen

Acuan: catatan spesifikasi Smart Library (AHP + SAW, explainable, what-if,
personalisasi, notifikasi, role-based access, audit trail, laporan, import/export).

## Ditambahkan / diperbaiki
| Poin revisi | Perubahan |
|---|---|
| Role-based access | `lib/auth.php` (`wajibPetugas`, `wajibLogin`). Semua halaman `Admin/` kini mewajibkan role `petugas`. Sebelumnya hanya dashboard yang mengecek, dan `peminjaman/kembali.php` tanpa login. |
| Audit trail | `lib/audit.php` (`catatAudit`, tabel dibuat otomatis). Tercatat: login, logout, tambah/ubah/hapus buku, impor/ekspor CSV, tambah/ubah/hapus kriteria, ubah skala nilai, simpan perbandingan AHP, hitung bobot AHP (beserta CR), generate nilai, hitung SAW, setujui/tolak/kembalikan/tambah peminjaman, suspend/aktifkan akun. |
| Kelola koleksi | `Admin/tambah.php` dan `Admin/edit.php` (via `form_buku.php`) ditambahkan ke folder utama; sebelumnya tombol Tambah/Edit di Kelola Buku menuju file yang hanya ada di `Admin.zip`. Form memuat bahasa, jumlah halaman, tingkat kesulitan; upload cover divalidasi. |
| Detail buku, explainable, alternatif | `public/detail_buku.php` dibuat (tautan "Detail" di koleksi/index/pinjam sebelumnya mati). Menampilkan metadata, ketersediaan + perkiraan kembali, peringkat SAW dan tabel kontribusi tiap kriteria, serta buku alternatif bila stok habis. |
| Halaman rekomendasi (personal, explainable, What-If) | `public/rekomendasi.php` dibuat. Tombol "Rekomendasi" di beranda sebelumnya menuju file yang tidak ada, dan What-If di koleksi hanya berupa CSS tanpa slider. Halaman ini memuat rekomendasi personal/umum, alasan + bar kontribusi tiap kriteria, slider prioritas, preset (rating, terbaru, populer, kategori), dan selisih posisi dibanding bobot AHP. |
| Dashboard: buku tren, statistik koleksi | `public/index.php` mendapat seksi "Sedang Tren" (30 hari) dan ringkasan statistik koleksi. |
| Analisis pola minat baca, visualisasi | `Admin/laporan_analitik.php`: kategori paling dipinjam, kata kunci pencarian, preferensi kategori, sebaran koleksi, tren 30 hari vs 30 hari sebelumnya. |

| Notifikasi (rekomendasi, ketersediaan, pengingat, alternatif) | `lib/notifikasi.php` + `public/notifikasi.php` ditulis ulang. Notifikasi kini disimpan ke tabel `notifikasi` (anti-dobel), ada lencana 🔔 di navbar beranda. |
| Rekomendasi di beranda | Seksi rekomendasi `index.php` kini memakai hasil AHP-SAW (personal bila login) beserta alasan, bukan sekadar buku terpopuler. |
| Kelola data pengguna + histori + pola minat | `Admin/pengguna.php` (daftar siswa, histori pinjam, pola minat, ubah status akun dengan CSRF + audit); tautan di sidebar. |

## Bug yang ikut diperbaiki
- `auth/login.php` mengarah ke `../admin/` (huruf kecil) sehingga gagal di server Linux; kini `../Admin/`.
- `public/kembalikan.php` dan `Admin/peminjaman/kembali.php`: SQL injection, tidak ada cek pemilik, stok bisa bertambah ganda, status `Dikembalikan` tidak seragam, tanggal pengembalian aktual tidak tercatat.
- `Admin/peminjaman/tambah.php`: SQL injection; memakai kolom `dipinjam` yang tidak ada (seharusnya `jumlah_dipinjam`); peminjaman petugas berstatus `menunggu`. Kini transaksi, status `dipinjam`, jatuh tempo 7 hari.

## Belum dikerjakan (saran)
- Penghapusan lewat GET (`hapus.php`, `hapus_kriteria.php`) belum memakai POST + token CSRF.
- `connection/koneksi.php` memakai root tanpa password; `public/index.php` menyalakan `display_errors`.
- Hapus `Admin.zip` dari folder web. Putuskan nasib tabel `hasil_saw` (engine sudah dinamis).
- Grafik interaktif (saat ini bar sederhana), evaluasi/pengujian sistem untuk laporan.

## Cara uji cepat
1. Login sebagai siswa, buka `Admin/ahp/simpan.php` langsung: harus ditolak (403).
2. Login petugas: tambah buku, edit buku, hapus kriteria, hitung AHP, lalu cek Laporan & Analitik > Audit trail.
3. Buka satu buku dengan stok 0 di `detail_buku.php`: harus muncul alternatif.
4. Kembalikan buku dua kali: stok hanya bertambah sekali.

## Perbaikan SAW (tahap berikutnya)
- **Rating terbobot (Bayesian):** skor = (n*R + 3*C) / (n + 3). Sebelumnya buku tanpa rating bernilai 0 dan satu ulasan cukup membuat buku naik ke peringkat 1-2.
- **Data kosong tidak dihukum:** jika kriteria `jumlah_halaman` / `tingkat_kesulitan` dipakai kelak, nilai yang kosong diisi median buku lain (bukan 0).
- **Penjelasan sadar tipe kriteria** (cost/benefit) disiapkan di `frasaKriteria()` / `frasaLemah()`.
- Kriteria cost (Tingkat Kesulitan, Jumlah Halaman) **belum ditambahkan**; ditunda sampai dosen menentukan.
- `lib/saw_engine.php` diubah: `muatDataBuku()`, `hitungSAW()`, `frasaKriteria()`, `frasaLemah()`.

## Perhitungan SAW admin (C1-C8)
- `Admin/saw/hitung.php`: ditambah pilihan **Hitung untuk: Umum / siswa tertentu**. Mode umum memakai kriteria non-personal (C1-C6); memilih siswa yang punya riwayat baca ikut menghitung **C7 Minat Kategori dan C8 Kemiripan Buku** (C1-C8). Hitungan personal tidak menimpa arsip ranking umum.
- Tabel bobot kini menampilkan kolom **Bobot Terpakai** (bobot AHP yang dinormalisasi ke kriteria yang aktif), agar sama dengan angka yang dipakai menghitung skor.
- `Admin/hasil_ranking.php`: logika lama (hanya C1-C5 id 8-12, membaca `nilai_buku` statis, hanya buku yang pernah dipinjam, semua dianggap benefit, query string mentah) diganti memakai mesin dinamis lewat `arsipkanRankingUmum()` di `lib/saw_engine.php`. Sebelumnya halaman ini menimpa `hasil_saw` dengan hasil lama setiap dibuka. Kini `hasil_saw` selalu berisi hasil mesin dinamis lengkap dengan kolom ranking.
