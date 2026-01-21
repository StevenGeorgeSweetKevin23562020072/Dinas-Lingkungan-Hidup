<?php
/**
 * Database Configuration
 * Sistem Manajemen Dokumen Laboratorium
 */

// ================================================
// KONFIGURASI DATABASE LARAGON
// ================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Laragon default: password kosong
define('DB_NAME', 'lab_document_management');
define('DB_CHARSET', 'utf8mb4');

// ================================================
// KONEKSI DATABASE
// ================================================
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Cek koneksi
if ($conn->connect_error) {
    // Log error (jangan tampilkan detail ke user)
    error_log("Database connection failed: " . $conn->connect_error);
    die("
        <h2>Koneksi Database Gagal</h2>
        <p>Sistem tidak dapat terhubung ke database.</p>
        <p>Silakan periksa konfigurasi database di <code>config/database.php</code></p>
        <hr>
        <small>Error: " . $conn->connect_errno . "</small>
    ");
}

// Set character set
if (!$conn->set_charset(DB_CHARSET)) {
    error_log("Error loading character set utf8mb4: " . $conn->error);
}

// ================================================
// FUNGSI HELPER DATABASE
// ================================================

/**
 * Generate document code otomatis
 * Format: DOC-2026-001, DOC-2026-002, dst
 */
function generateDocCode() {
    global $conn;
    $current_year = date('Y');
    
    // Ambil counter untuk tahun ini
    $query = "SELECT counter FROM document_counter WHERE year = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $current_year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $counter = $row['counter'];
        
        // Update counter
        $update = "UPDATE document_counter SET counter = counter + 1 WHERE year = ?";
        $stmt_update = $conn->prepare($update);
        $stmt_update->bind_param("i", $current_year);
        $stmt_update->execute();
    } else {
        // Insert tahun baru
        $counter = 1;
        $insert = "INSERT INTO document_counter (year, counter) VALUES (?, 2)";
        $stmt_insert = $conn->prepare($insert);
        $stmt_insert->bind_param("i", $current_year);
        $stmt_insert->execute();
    }
    
    // Format: DOC-2026-001
    return sprintf("DOC-%d-%03d", $current_year, $counter);
}

/**
 * Log aktivitas user
 */
function logActivity($user_id, $action, $document_id = null, $details = null) {
    global $conn;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
    
    $query = "INSERT INTO activity_logs (user_id, action, document_id, details, ip_address, user_agent) 
              VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isisss", $user_id, $action, $document_id, $details, $ip, $user_agent);
    return $stmt->execute();
}

/**
 * Update last login user
 */
function updateLastLogin($user_id) {
    global $conn;
    
    $query = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

/**
 * Escape string untuk keamanan
 */
function escapeString($string) {
    global $conn;
    return $conn->real_escape_string($string);
}

/**
 * Get next version number untuk dokumen
 */
function getNextVersionNumber($document_id) {
    global $conn;
    
    $query = "SELECT MAX(version_number) as max_version 
              FROM document_versions 
              WHERE document_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $current_max = $result['max_version'] ?? 0;
    
    // Increment versi: 1.0 → 1.1 → 1.2, dst
    return number_format($current_max + 0.1, 1);
}

/**
 * Mark semua versi lama sebagai tidak aktif
 */
function markOldVersionsInactive($document_id) {
    global $conn;
    
    $query = "UPDATE document_versions 
              SET is_current = 0 
              WHERE document_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $document_id);
    return $stmt->execute();
}

// ================================================
// TESTING KONEKSI (Hapus setelah development)
// ================================================
// echo "✓ Database connected successfully!<br>";
// echo "Server: " . $conn->host_info . "<br>";
// echo "Database: " . DB_NAME . "<br>";
?>