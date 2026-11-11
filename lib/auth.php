<?php
/**
 * Role-based access control.
 * Pakai di baris awal setiap halaman khusus petugas:
 *     require_once __DIR__ . '/../lib/auth.php';
 *     wajibPetugas('../auth/login.php');
 */
require_once __DIR__ . '/audit.php';

function wajibPetugas(string $urlLogin = '../auth/login.php'): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['login'])) {
        header('Location: ' . $urlLogin);
        exit;
    }
    if (($_SESSION['role'] ?? '') !== 'petugas') {
        http_response_code(403);
        exit('Akses ditolak: halaman ini khusus petugas perpustakaan.');
    }
}

function wajibLogin(string $urlLogin = '../auth/login.php'): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['login'])) {
        header('Location: ' . $urlLogin);
        exit;
    }
}
