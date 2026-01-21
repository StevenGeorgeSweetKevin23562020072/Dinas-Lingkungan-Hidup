<?php
/**
 * Authentication Functions
 * Fungsi-fungsi untuk autentikasi user
 */

// Load dependencies
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/security.php';

// Start secure session
secureSessionStart();

// ================================================
// LOGIN & LOGOUT
// ================================================

/**
 * Proses login user
 */
function login($username, $password) {
    global $conn;
    
    // Cek rate limiting
    $rate_limit = checkLoginRateLimit($username);
    if (!$rate_limit['allowed']) {
        return [
            'success' => false,
            'message' => $rate_limit['message']
        ];
    }
    
    // Sanitasi input
    $username = sanitizeInput($username);
    
    // Query user dari database
    $stmt = $conn->prepare("
        SELECT user_id, username, password, full_name, email, role, is_active 
        FROM users 
        WHERE username = ?
    ");
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Cek apakah user aktif
        if (!$user['is_active']) {
            return [
                'success' => false,
                'message' => 'Akun Anda telah dinonaktifkan. Hubungi administrator.'
            ];
        }
        
        // Verifikasi password
        if (verifyPassword($password, $user['password'])) {
            // Login berhasil
            
            // Clear login attempts
            clearLoginAttempts($username);
            
            // Set session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            
            // Regenerate session ID untuk keamanan
            session_regenerate_id(true);
            
            // Update last login di database
            updateLastLogin($user['user_id']);
            
            // Log aktivitas
            logActivity($user['user_id'], 'login', null, 'User logged in successfully');
            
            return [
                'success' => true,
                'message' => 'Login berhasil!',
                'user' => $user
            ];
        }
    }
    
    // Login gagal - record attempt
    recordLoginAttempt($username);
    
    return [
        'success' => false,
        'message' => 'Username atau password salah!'
    ];
}

/**
 * Logout user
 */
function logout() {
    if (isset($_SESSION['user_id'])) {
        // Log aktivitas
        logActivity($_SESSION['user_id'], 'logout', null, 'User logged out');
    }
    
    // Hapus semua session
    $_SESSION = array();
    
    // Hapus session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy session
    session_destroy();
    
    return true;
}

// ================================================
// SESSION CHECKS
// ================================================

/**
 * Cek apakah user sudah login
 */
function isLoggedIn() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Cek session timeout
    if (!checkSessionTimeout()) {
        return false;
    }
    
    return true;
}

/**
 * Cek role user
 */
function hasRole($required_role) {
    if (!isLoggedIn()) {
        return false;
    }
    
    return isset($_SESSION['role']) && $_SESSION['role'] === $required_role;
}

/**
 * Cek apakah user punya salah satu dari multiple roles
 */
function hasAnyRole($roles = []) {
    if (!isLoggedIn()) {
        return false;
    }
    
    return in_array($_SESSION['role'], $roles);
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'email' => $_SESSION['email'],
        'role' => $_SESSION['role']
    ];
}

// ================================================
// MIDDLEWARE / GUARDS
// ================================================

/**
 * Require user harus login
 * Redirect ke login jika belum login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: " . BASE_URL . "/modules/auth/login.php");
        exit;
    }
}

/**
 * Require role tertentu
 * Die dengan error jika role tidak sesuai
 */
function requireRole($role) {
    requireLogin();
    
    if (!hasRole($role)) {
        http_response_code(403);
        die("
            <h1>403 - Akses Ditolak</h1>
            <p>Anda tidak memiliki izin untuk mengakses halaman ini.</p>
            <p>Role yang diperlukan: <strong>{$role}</strong></p>
            <p>Role Anda: <strong>" . getCurrentUserRole() . "</strong></p>
            <hr>
            <a href='" . BASE_URL . "/modules/dashboard/index.php'>← Kembali ke Dashboard</a>
        ");
    }
}

/**
 * Require salah satu dari multiple roles
 */
function requireAnyRole($roles = []) {
    requireLogin();
    
    if (!hasAnyRole($roles)) {
        $roles_str = implode(', ', $roles);
        http_response_code(403);
        die("
            <h1>403 - Akses Ditolak</h1>
            <p>Anda tidak memiliki izin untuk mengakses halaman ini.</p>
            <p>Role yang diperlukan: <strong>{$roles_str}</strong></p>
            <p>Role Anda: <strong>" . getCurrentUserRole() . "</strong></p>
            <hr>
            <a href='" . BASE_URL . "/modules/dashboard/index.php'>← Kembali ke Dashboard</a>
        ");
    }
}

/**
 * Redirect jika sudah login
 * Untuk halaman login/register
 */
function redirectIfLoggedIn($redirect_to = null) {
    if (isLoggedIn()) {
        if ($redirect_to === null) {
            $redirect_to = BASE_URL . "/modules/dashboard/index.php";
        }
        header("Location: " . $redirect_to);
        exit;
    }
}

// ================================================
// USER MANAGEMENT
// ================================================

/**
 * Get user by ID
 */
function getUserById($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT user_id, username, full_name, email, role, is_active, created_at, last_login
        FROM users 
        WHERE user_id = ?
    ");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Get user by username
 */
function getUserByUsername($username) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT user_id, username, full_name, email, role, is_active, created_at, last_login
        FROM users 
        WHERE username = ?
    ");
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Create new user
 */
function createUser($username, $password, $full_name, $email, $role) {
    global $conn;
    
    // Validasi input
    if (!isValidUsername($username)) {
        return [
            'success' => false,
            'message' => 'Username tidak valid (3-50 karakter, hanya huruf, angka, underscore)'
        ];
    }
    
    if (!isValidEmail($email)) {
        return [
            'success' => false,
            'message' => 'Email tidak valid'
        ];
    }
    
    if (!isStrongPassword($password)) {
        return [
            'success' => false,
            'message' => 'Password harus minimal 8 karakter, mengandung huruf besar, kecil, dan angka'
        ];
    }
    
    // Cek apakah username sudah ada
    if (getUserByUsername($username)) {
        return [
            'success' => false,
            'message' => 'Username sudah digunakan'
        ];
    }
    
    // Hash password
    $password_hash = hashPassword($password);
    
    // Insert user
    $stmt = $conn->prepare("
        INSERT INTO users (username, password, full_name, email, role, is_active)
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    
    $stmt->bind_param("sssss", $username, $password_hash, $full_name, $email, $role);
    
    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        
        // Log aktivitas
        if (isLoggedIn()) {
            logActivity(getCurrentUserId(), 'create_user', null, "Created user: {$username}");
        }
        
        return [
            'success' => true,
            'message' => 'User berhasil dibuat',
            'user_id' => $user_id
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Gagal membuat user: ' . $conn->error
    ];
}

/**
 * Update user password
 */
function updateUserPassword($user_id, $new_password) {
    global $conn;
    
    if (!isStrongPassword($new_password)) {
        return [
            'success' => false,
            'message' => 'Password harus minimal 8 karakter, mengandung huruf besar, kecil, dan angka'
        ];
    }
    
    $password_hash = hashPassword($new_password);
    
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $stmt->bind_param("si", $password_hash, $user_id);
    
    if ($stmt->execute()) {
        logActivity(getCurrentUserId(), 'change_password', null, "Changed password for user_id: {$user_id}");
        
        return [
            'success' => true,
            'message' => 'Password berhasil diubah'
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Gagal mengubah password'
    ];
}

// ================================================
// AUTO-CHECK SESSION (Optional)
// ================================================

// Cek session timeout otomatis di setiap request
if (isLoggedIn()) {
    if (!checkSessionTimeout()) {
        logout();
        header("Location: " . BASE_URL . "/modules/auth/login.php?timeout=1");
        exit;
    }
}
?>