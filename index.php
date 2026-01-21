<?php
/**
 * Entry Point
 * Redirect ke login atau dashboard
 */

require_once 'includes/auth.php';

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    header("Location: modules/dashboard/index.php");
} else {
    // Jika belum login, redirect ke login
    header("Location: modules/auth/login.php");
}

exit;
?>