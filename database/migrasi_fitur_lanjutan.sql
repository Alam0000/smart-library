-- Migrasi tambahan Smart Library (MySQL/MariaDB)
-- Backup database terlebih dahulu. Jalankan pada database perpustakaan_db.
CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_user INT NULL,
  nama_pengguna VARCHAR(150) NULL,
  aksi VARCHAR(100) NOT NULL,
  entitas VARCHAR(100) NOT NULL,
  entitas_id VARCHAR(100) NULL,
  detail TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_created (created_at),
  INDEX idx_audit_user (id_user),
  INDEX idx_audit_aksi (aksi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifikasi (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_user INT NOT NULL,
  judul VARCHAR(180) NOT NULL,
  pesan TEXT NOT NULL,
  tautan VARCHAR(255) NULL,
  dibaca TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notifikasi_user_baca (id_user, dibaca, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
