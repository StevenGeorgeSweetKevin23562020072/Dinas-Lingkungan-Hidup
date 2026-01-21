<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Hanya terima POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: list.php");
    exit;
}

// Hanya uploader dan admin
requireAnyRole(['uploader', 'admin']);

// Validasi CSRF token
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    redirectWithMessage('list.php', 'error', 'Invalid CSRF token');
}

$user = getCurrentUser();
$user_id = $user['user_id'];

// Ambil input
$doc_code = sanitizeInput($_POST['doc_code'] ?? '');
$notes = sanitizeInput($_POST['notes'] ?? '');

// Validasi input
$errors = [];

if (empty($doc_code)) {
    $errors[] = "Kode dokumen tidak valid";
}

if (empty($notes)) {
    $errors[] = "Catatan perubahan harus diisi";
}

// Validasi file
if (!isset($_FILES['document_file'])) {
    $errors[] = "File dokumen harus diupload";
} else {
    $file_errors = validateUploadedFile($_FILES['document_file']);
    $errors = array_merge($errors, $file_errors);
}

if (!empty($errors)) {
    $_SESSION['upload_errors'] = $errors;
    redirectWithMessage("revisi.php?code={$doc_code}", 'error', implode(', ', $errors));
}

// Get document data
$document = getDocumentByCode($doc_code);

if (!$document) {
    redirectWithMessage('list.php', 'error', 'Dokumen tidak ditemukan');
}

// Validasi ownership
if ($document['uploader_id'] != $user_id && $user['role'] !== 'admin') {
    redirectWithMessage('list.php', 'error', 'Anda tidak memiliki izin untuk merevisi dokumen ini');
}

// Validasi status dokumen
if ($document['status'] !== 'revision') {
    redirectWithMessage(
        "detail.php?code={$doc_code}", 
        'error', 
        'Dokumen ini tidak dalam status perlu revisi'
    );
}

// ================================================
// PROSES UPLOAD REVISI
// ================================================

$uploaded_file = $_FILES['document_file'];

try {
    // Start transaction
    $conn->begin_transaction();
    
    // 1. Get next version number
    $next_version = getNextVersionNumber($document['document_id']);
    
    // 2. Mark all old versions as not current
    markOldVersionsInactive($document['document_id']);
    
    // 3. Buat folder upload
    $upload_dir = createUploadDirectory();
    
    if (!$upload_dir) {
        throw new Exception("Gagal membuat folder upload");
    }
    
    // 4. Generate nama file yang aman
    $original_filename = sanitizeFilename($uploaded_file['name']);
    $secure_filename = generateSecureFilename($original_filename, $doc_code, $next_version);
    
    // 5. Path file lengkap
    $file_path = $upload_dir . $secure_filename;
    $relative_path = str_replace(BASE_PATH . '/', '', $file_path);
    
    // 6. Upload file
    if (!move_uploaded_file($uploaded_file['tmp_name'], $file_path)) {
        throw new Exception("Gagal mengupload file ke server");
    }
    
    // 7. Generate hash file
    $file_hash = generateFileHash($file_path);
    $file_size = filesize($file_path);
    
    // 8. Insert new version ke database
    $stmt = $conn->prepare("
        INSERT INTO document_versions 
        (document_id, version_number, file_name, file_path, file_size, file_hash, uploaded_by, is_current, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
    ");
    
    $stmt->bind_param("idssisis", 
        $document['document_id'], 
        $next_version, 
        $original_filename, 
        $relative_path, 
        $file_size, 
        $file_hash, 
        $user_id,
        $notes
    );
    
    if (!$stmt->execute()) {
        unlink($file_path);
        throw new Exception("Gagal menyimpan versi dokumen: " . $stmt->error);
    }
    
    $new_version_id = $conn->insert_id;
    
    // 9. Update document: current_version_id dan status kembali ke 'pending'
    $stmt = $conn->prepare("
        UPDATE documents 
        SET current_version_id = ?,
            status = 'pending',
            updated_at = NOW()
        WHERE document_id = ?
    ");
    
    $stmt->bind_param("ii", $new_version_id, $document['document_id']);
    
    if (!$stmt->execute()) {
        throw new Exception("Gagal update dokumen: " . $stmt->error);
    }
    
    // 10. Log aktivitas
    logActivity(
        $user_id, 
        'upload_revision', 
        $document['document_id'], 
        "Uploaded revision v{$next_version} for {$doc_code}"
    );
    
    // Commit transaction
    $conn->commit();
    
    // Redirect dengan pesan sukses
    redirectWithMessage(
        "detail.php?code={$doc_code}", 
        'success', 
        "Revisi berhasil diupload! Versi baru: v{$next_version}. Dokumen kembali menunggu pemeriksaan."
    );
    
} catch (Exception $e) {
    // Rollback
    $conn->rollback();
    
    // Hapus file jika sudah terupload
    if (isset($file_path) && file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Log error
    error_log("Revision Upload Error: " . $e->getMessage());
    
    // Redirect dengan error
    redirectWithMessage(
        "revisi.php?code={$doc_code}", 
        'error', 
        'Terjadi kesalahan saat upload revisi: ' . $e->getMessage()
    );
}
?>