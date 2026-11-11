PETUNJUK PEMASANGAN REVISI SMART LIBRARY
========================================

Database yang diperiksa: perpustakaan_db
Server pada dump: MariaDB 10.4.32
PHP pada dump: 8.2.12
Nama tabel yang sudah ada: bobot_ahp, buku, hasil_saw, kriteria, nilai_buku,
peminjaman, perbandingan_ahp, preferensi_kategori, rating_buku,
riwayat_pencarian, simpan_buku, skala_nilai, users.

SEBELUM MEMASANG
1. Backup database dan folder proyek.
2. Ekstrak ZIP ke folder web server.
3. Buka phpMyAdmin, pilih database perpustakaan_db.
4. Import database/migrasi_fitur_lanjutan.sql. File ini hanya membuat tabel
   tambahan audit_log dan notifikasi menggunakan CREATE TABLE IF NOT EXISTS;
   tidak menghapus atau menimpa tabel data lama.
5. Buka halaman Admin/dashboard.php, lalu uji menu Laporan & Analitik dan
   Impor Buku CSV.
6. Sesudah perubahan kriteria/bobot, jalankan ulang perhitungan AHP dan SAW
   dari menu aplikasi agar hasil_saw dan ranking mengikuti konfigurasi terbaru.

CATATAN BERDASARKAN DUMP DATABASE
- Tabel buku sudah memiliki bahasa, jumlah_halaman, tingkat_kesulitan,
  jumlah_dipinjam, dan view_buku.
- Tabel kriteria sudah mendukung tipe benefit/cost, sumber_data, dan aktif.
- Tabel preferensi_kategori dan riwayat_pencarian sudah tersedia untuk
  personalisasi.
- Pada dump yang diberikan, kolom ranking di hasil_saw masih NULL. Hitung
  ulang SAW dari aplikasi setelah memasang.
- nilai_buku yang ada pada dump tidak berisi semua pasangan buku-kriteria
  untuk kriteria C7/C8. Gunakan fitur generate nilai dari aplikasi dan cek
  kembali data sebelum menyimpulkan hasil rekomendasi.
- Tabel notifikasi disiapkan untuk penyimpanan notifikasi di tahap integrasi.
  Halaman public/notifikasi.php saat ini menghitung pengingat langsung dari
  transaksi peminjaman; belum menyimpan semua pengingat ke tabel notifikasi.
- Audit trail sudah dicatat untuk aksi impor/ekspor CSV. Belum semua aktivitas
  (login, edit/hapus buku, perubahan kriteria, persetujuan peminjaman) otomatis
  dicatat, sehingga integrasi audit trail masih perlu dilanjutkan.

KONFIGURASI
File koneksi: connection/koneksi.php
Dump menunjukkan MariaDB di port 3311; file koneksi proyek saat ini juga
menggunakan port 3311. Sesuaikan host, user, password, database, dan port
jika lingkungan lokal Anda berbeda.

JANGAN unggah database berisi data pengguna ke repositori publik. Dump SQL
dapat berisi informasi akun dan data transaksi.
