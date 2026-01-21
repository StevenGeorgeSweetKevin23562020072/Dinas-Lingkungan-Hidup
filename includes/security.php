<?php
/**
 * Security Functions
 * Fungsi-fungsi keamanan untuk sistem
 */

// Prevent direct access
if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

// ================================================
// INPUT SANITIZATION
// ================================================

/**
 * Sanitasi input text biasa
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Sanitasi untuk SQL query (gunakan dengan prepared statement)
 */
function sanitizeSQL($data) {
    global $conn;
    return $conn->real_escape_string($data);
}

/**
 * Validasi email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validasi username (hanya huruf, angka, underscore)
 */
function isValidUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username);
}

// ================================================
// FILE UPLOAD SECURITY
// ================================================

/**
 * Validasi file yang diupload
 */
function validateUploadedFile($file) {
    $errors = [];
    
    // 1. Cek apakah file ada
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = "Tidak ada file yang diunggah";
        return $errors;
    }
    
    // 2. Cek error upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = "Ukuran file terlalu besar";
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = "File hanya terupload sebagian";
                break;
            default:
                $errors[] = "Terjadi error saat upload file";
        }
        return $errors;
    }
    
    // 3. Validasi ukuran file
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = "Ukuran file terlalu besar (maksimal " . formatFileSize(MAX_FILE_SIZE) . ")";
    }
    
    if ($file['size'] == 0) {
        $errors[] = "File kosong atau corrupt";
    }
    
    // 4. Validasi tipe MIME menggunakan finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, ALLOWED_MIME_TYPES)) {
        $errors[] = "Tipe file tidak diperbolehkan. Hanya file PDF yang diperbolehkan";
    }
    
    // 5. Validasi ekstensi file
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, ALLOWED_EXTENSIONS)) {
        $errors[] = "Ekstensi file tidak valid. Hanya file .pdf yang diperbolehkan";
    }
    
    // 6. Cek apakah benar-benar file PDF dengan membaca magic number
    $handle = fopen($file['tmp_name'], 'rb');
    $header = fread($handle, 4);
    fclose($handle);
    
    // PDF magic number: %PDF
    if (substr($header, 0, 4) !== '%PDF') {
        $errors[] = "File bukan PDF yang valid";
    }
    
    return $errors;
}

/**
 * Sanitasi nama file
 */
function sanitizeFilename($filename) {
    // Hapus karakter berbahaya
    $filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename);
    // Hapus multiple dots
    $filename = preg_replace('/\.+/', '.', $filename);
    return $filename;
}

/**
 * Generate nama file yang aman dan unik
 */
function generateSecureFilename($original_name, $doc_code, $version) {
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $timestamp = time();
    $random = bin2hex(random_bytes(8));
    
    // Format: DOC-2026-001_v1.0_1704067200_a1b2c3d4.pdf
    return "{$doc_code}_v{$version}_{$timestamp}_{$random}.{$extension}";
}

/**
 * Generate SHA256 hash untuk file
 */
function generateFileHash($file_path) {
    return hash_file('sha256', $file_path);
}

/**
 * Buat struktur folder upload berdasarkan tahun/bulan
 */
function createUploadDirectory() {
    $year = date('Y');
    $month = date('m');
    
    $upload_dir = UPLOAD_PATH . $year . '/' . $month . '/';
    
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            return false;
        }
        
        // Buat file .htaccess untuk keamanan
        createHtaccessFile($upload_dir);
    }
    
    return $upload_dir;
}

/**
 * Buat file .htaccess untuk blokir akses langsung
 */
function createHtaccessFile($directory) {
    $htaccess_content = "# Blokir akses langsung ke file upload
Order Deny,Allow
Deny from all

# Hanya PHP yang bisa akses
<FilesMatch \"\\.(pdf|doc|docx)$\">
    Deny from all
</FilesMatch>";

    file_put_contents($directory . '.htaccess', $htaccess_content);
}

// ================================================
// CSRF PROTECTION
// ================================================

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validasi CSRF token
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate CSRF input field
 */
function csrfField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

// ================================================
// XSS PROTECTION
// ================================================

/**
 * Escape output untuk mencegah XSS
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// ================================================
// SESSION SECURITY
// ================================================

/**
 * Secure session start
 */
function secureSessionStart() {
    // Cegah session fixation
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure session configuration
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', 0); // Set 1 jika menggunakan HTTPS
        
        session_start();
        
        // Regenerate session ID untuk keamanan
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }
    }
}

/**
 * Cek session timeout
 */
function checkSessionTimeout() {
    if (isset($_SESSION['last_activity'])) {
        $elapsed = time() - $_SESSION['last_activity'];
        
        if ($elapsed > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            return false;
        }
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

// ================================================
// PASSWORD SECURITY
// ================================================

/**
 * Hash password menggunakan bcrypt
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verifikasi password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Validasi kekuatan password
 */
function isStrongPassword($password) {
    // Minimal 8 karakter, ada huruf besar, kecil, dan angka
    if (strlen($password) < 8) {
        return false;
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        return false; // Tidak ada huruf besar
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        return false; // Tidak ada huruf kecil
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        return false; // Tidak ada angka
    }
    
    return true;
}

// ================================================
// ACCESS CONTROL
// ================================================

/**
 * Cek apakah user punya akses ke dokumen
 */
function hasDocumentAccess($user_id, $user_role, $document_uploader_id) {
    // Admin bisa akses semua
    if ($user_role === 'admin') {
        return true;
    }
    
    // Reviewer bisa akses semua
    if ($user_role === 'reviewer') {
        return true;
    }
    
    // Uploader hanya bisa akses dokumennya sendiri
    if ($user_role === 'uploader' && $user_id == $document_uploader_id) {
        return true;
    }
    
    return false;
}

// ================================================
// RATE LIMITING (Sederhana)
// ================================================

/**
 * Check rate limit untuk login attempts
 */
function checkLoginRateLimit($username) {
    $max_attempts = 5;
    $lockout_time = 900; // 15 menit
    
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    $attempts = &$_SESSION['login_attempts'];
    
    // Hapus attempts yang sudah expired
    foreach ($attempts as $user => $data) {
        if (time() - $data['time'] > $lockout_time) {
            unset($attempts[$user]);
        }
    }
    
    // Cek apakah user sudah di-lockout
    if (isset($attempts[$username])) {
        if ($attempts[$username]['count'] >= $max_attempts) {
            $remaining = $lockout_time - (time() - $attempts[$username]['time']);
            if ($remaining > 0) {
                return [
                    'allowed' => false,
                    'message' => "Terlalu banyak percobaan login. Coba lagi dalam " . ceil($remaining / 60) . " menit."
                ];
            } else {
                unset($attempts[$username]);
            }
        }
    }
    
    return ['allowed' => true];
}

/**
 * Record failed login attempt
 */
function recordLoginAttempt($username) {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    if (!isset($_SESSION['login_attempts'][$username])) {
        $_SESSION['login_attempts'][$username] = [
            'count' => 0,
            'time' => time()
        ];
    }
    
    $_SESSION['login_attempts'][$username]['count']++;
    $_SESSION['login_attempts'][$username]['time'] = time();
}

/**
 * Clear login attempts setelah login sukses
 */
function clearLoginAttempts($username) {
    if (isset($_SESSION['login_attempts'][$username])) {
        unset($_SESSION['login_attempts'][$username]);
    }
}
?>