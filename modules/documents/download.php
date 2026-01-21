<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require login
requireLogin();

$user = getCurrentUser();
$user_id = $user['user_id'];
$role = $user['role'];

// Get version ID dari URL
$version_id = isset($_GET['vid']) ? intval($_GET['vid']) : 0;

if ($version_id <= 0) {
    die('Version ID tidak valid');
}

// Ambil informasi file dari database
$query = "
    SELECT 
        dv.version_id,
        dv.file_path,
        dv.file_name,
        dv.file_size,
        dv.file_hash,
        d.document_id,
        d.doc_code,
        d.title,
        d.uploader_id
    FROM document_versions dv
    JOIN documents d ON dv.document_id = d.document_id
    WHERE dv.version_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $version_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('File tidak ditemukan di database');
}

$file_data = $result->fetch_assoc();

// ================================================
// VALIDASI AKSES
// ================================================

// Cek apakah user punya akses ke dokumen ini
if (!hasDocumentAccess($user_id, $role, $file_data['uploader_id'])) {
    http_response_code(403);
    die("
        <h1>403 - Akses Ditolak</h1>
        <p>Anda tidak memiliki izin untuk mengakses file ini.</p>
        <p><strong>Role Anda:</strong> {$role}</p>
        <p><strong>Dokumen:</strong> {$file_data['doc_code']} - {$file_data['title']}</p>
        <hr>
        <a href='list.php'>← Kembali ke Daftar Dokumen</a>
    ");
}

// ================================================
// VALIDASI FILE
// ================================================

// Path file lengkap
$file_path = BASE_PATH . '/' . $file_data['file_path'];

// Cek keberadaan file di server
if (!file_exists($file_path)) {
    error_log("File not found: {$file_path}");
    die("
        <h1>404 - File Tidak Ditemukan</h1>
        <p>File tidak ditemukan di server.</p>
        <p><strong>File:</strong> {$file_data['file_name']}</p>
        <p>Silakan hubungi administrator.</p>
        <hr>
        <a href='detail.php?code={$file_data['doc_code']}'>← Kembali ke Detail Dokumen</a>
    ");
}

// Validasi hash file (integritas)
$current_hash = hash_file('sha256', $file_path);
if ($current_hash !== $file_data['file_hash']) {
    error_log("File hash mismatch for version_id: {$version_id}");
    die("
        <h1>⚠️ Peringatan Keamanan</h1>
        <p>File telah termodifikasi atau corrupt.</p>
        <p>Hash tidak sesuai dengan database.</p>
        <p>Silakan hubungi administrator.</p>
        <hr>
        <a href='detail.php?code={$file_data['doc_code']}'>← Kembali ke Detail Dokumen</a>
    ");
}

// ================================================
// LOG AKTIVITAS DOWNLOAD
// ================================================

logActivity(
    $user_id, 
    'download_document', 
    $file_data['document_id'], 
    "Downloaded version {$version_id} of {$file_data['doc_code']}"
);

// ================================================
// FORCE DOWNLOAD FILE
// ================================================

// Clear output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Set headers untuk download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($file_data['file_name']) . '"');
header('Content-Length: ' . $file_data['file_size']);
header('Content-Transfer-Encoding: binary');
header('Cache-Control: private, no-transform, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Baca dan output file
readfile($file_path);

exit;
?>