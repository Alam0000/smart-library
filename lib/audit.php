<?php
/**
 * Pencatat audit trail. Aman dipanggil di mana saja: tidak pernah melempar error.
 * Tabel audit_log dibuat otomatis jika belum ada (tidak perlu migrasi manual).
 */
function catatAudit(mysqli $db, string $aksi, string $entitas, $entitasId = null, string $detail = '', ?int $idUser = null): void {
    try {
        static $siap = false;
        if (!$siap) {
            $db->query("CREATE TABLE IF NOT EXISTS audit_log (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $siap = true;
        }
        $uid  = $idUser ?? (int)($_SESSION['id_user'] ?? $_SESSION['id'] ?? 0);
        $nama = (string)($_SESSION['username'] ?? 'sistem');
        $eid  = $entitasId === null ? null : (string)$entitasId;
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
        $st = $db->prepare("INSERT INTO audit_log (id_user,nama_pengguna,aksi,entitas,entitas_id,detail,ip_address) VALUES (?,?,?,?,?,?,?)");
        if ($st) {
            $st->bind_param('issssss', $uid, $nama, $aksi, $entitas, $eid, $detail, $ip);
            $st->execute();
            $st->close();
        }
    } catch (Throwable $e) {
        // audit tidak boleh menghentikan proses utama
    }
}
