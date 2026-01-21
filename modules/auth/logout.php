<?php
require_once '../../includes/auth.php';

// Proses logout
logout();

// Set pesan sukses
session_start();
$_SESSION['logout_success'] = "Anda telah berhasil logout. Terima kasih!";

// Redirect ke halaman login
header("Location: login.php");
exit;
?>