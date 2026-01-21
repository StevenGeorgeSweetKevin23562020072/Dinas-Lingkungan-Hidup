<?php
require_once '../../includes/auth.php';

// Hanya terima POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

// Ambil dan sanitasi input
$username = sanitizeInput($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validasi input tidak boleh kosong
if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = "Username dan password harus diisi!";
    header("Location: login.php");
    exit;
}

// Proses login
$result = login($username, $password);

if ($result['success']) {
    // Login berhasil
    
    // Cek apakah ada redirect URL sebelumnya
    if (isset($_SESSION['redirect_after_login'])) {
        $redirect_url = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
        header("Location: " . $redirect_url);
    } else {
        // Redirect ke dashboard sesuai role
        header("Location: ../dashboard/index.php");
    }
    exit;
    
} else {
    // Login gagal
    $_SESSION['login_error'] = $result['message'];
    header("Location: login.php");
    exit;
}
?>