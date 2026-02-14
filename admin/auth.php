<?php
// admin/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Jika role bukan admin/receptionist
$role = $_SESSION['role'] ?? '';

if (!in_array($role, ['admin', 'receptionist'])) {
    // bisa kamu arahkan ke halaman utama user
    header("Location: ../index.php");
    exit;
}
