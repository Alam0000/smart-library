# Catatan revisi Smart Library

## Penambahan pada paket ini
- `Admin/laporan_analitik.php`: ringkasan jumlah buku, pengguna, transaksi, peminjaman aktif/terlambat, buku populer, serta audit trail terbaru.
- `Admin/export_buku.php`: ekspor koleksi buku ke CSV.
- `Admin/impor_buku.php`: impor CSV dengan pemeriksaan login petugas, ukuran file, dan pencocokan header terhadap kolom tabel `buku`.
- `public/notifikasi.php`: pengingat pengembalian buku yang mendekati tenggat/terlambat serta pintasan mencari buku tersedia sebagai alternatif.
- `database/migrasi_fitur_lanjutan.sql`: menambahkan tabel `audit_log` dan `notifikasi`.
- tautan fitur analitik dan impor CSV ditambahkan ke sidebar admin.

## Yang sudah ada pada kode sumber yang dikirim
`public/koleksi.php` sudah memiliki pencarian multi-kata dan filter kategori, penulis, tahun terbit, rating, ketersediaan, bahasa, tingkat kesulitan, serta opsi urut termasuk rekomendasi/populer/rating/terbaru. `lib/profil_pengguna.php` dan `lib/saw_engine.php` sudah memuat fondasi rekomendasi personal dan skor rekomendasi.

## Langkah pemasangan
1. Backup database terlebih dahulu.
2. Import `database/migrasi_fitur_lanjutan.sql` ke database `perpustakaan_db`.
3. Salin folder proyek ke lingkungan PHP/MySQL.
4. Pastikan koneksi di `connection/koneksi.php` sesuai konfigurasi server.
5. Login menggunakan akun dengan role `petugas`, lalu buka menu Laporan & Analitik dan Impor Buku CSV.
6. Uji coba impor menggunakan backup atau lingkungan lokal dahulu.

## Batasan yang perlu diketahui
ZIP yang dikirim tidak menyertakan dump database asli (`.sql`), sehingga perubahan struktur database hanya berupa migrasi tambahan yang aman dan tidak mengubah tabel yang sudah ada. Fitur impor mengasumsikan file CSV menggunakan nama kolom tabel `buku`; ekspor aplikasi bisa digunakan sebagai template. Audit trail yang ditambahkan di paket ini mencatat aktivitas impor/ekspor; agar semua aksi admin (ubah kriteria, persetujuan peminjaman, edit/hapus buku, dan login/logout) tercatat, titik aksi tersebut perlu dihubungkan juga ke `audit_log`. Tabel `notifikasi` disiapkan untuk notifikasi persisten, sedangkan halaman notifikasi saat ini menampilkan pengingat berdasarkan tanggal pengembalian aktual.
