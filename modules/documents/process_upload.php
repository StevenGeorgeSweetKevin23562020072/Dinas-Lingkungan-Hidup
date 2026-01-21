<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Hanya terima POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: upload.php");
    exit;
}

// Hanya uploader dan admin yang bisa upload
requireAnyRole(['uploader', 'admin']);

// Validasi CSRF token
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    redirectWithMessage('upload.php', 'error', 'Invalid CSRF token');
}

$user = getCurrentUser();
$user_id = $user['user_id'];

// Ambil dan sanitasi input
$title = sanitizeInput($_POST['title'] ?? '');
$category = sanitizeInput($_POST['category'] ?? '');
$description = sanitizeInput($_POST['description'] ?? '');

// Validasi input
$errors = [];

if (empty($title)) {
    $errors[] = "Judul dokumen harus diisi";
}

if (strlen($title) > 200) {
    $errors[] = "Judul terlalu panjang (maksimal 200 karakter)";
}

if (empty($category)) {
    $errors[] = "Kategori harus dipilih";
}

if (!array_key_exists($category, DOC_CATEGORIES)) {
    $errors[] = "Kategori tidak valid";
}

// Validasi file upload
if (!isset($_FILES['document_file'])) {
    $errors[] = "File dokumen harus diupload";
} else {
    $file_errors = validateUploadedFile($_FILES['document_file']);
    $errors = array_merge($errors, $file_errors);
}

// Jika ada error, redirect kembali
if (!empty($errors)) {
    $_SESSION['upload_errors'] = $errors;
    redirectWithMessage('upload.php', 'error', implode(', ', $errors));
}

// ================================================
// PROSES UPLOAD FILE
// ================================================

$uploaded_file = $_FILES['document_file'];

try {
    // Start transaction
    $conn->begin_transaction();
    
    // 1. Generate document code
    $doc_code = generateDocCode();
    
    // 2. Insert ke tabel documents (tanpa current_version_id dulu)
    $stmt = $conn->prepare("
        INSERT INTO documents (doc_code, title, category, description, uploader_id, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    
    $stmt->bind_param("ssssi", $doc_code, $title, $category, $description, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Gagal menyimpan dokumen: " . $stmt->error);
    }
    
    $document_id = $conn->insert_id;
    
    // 3. Buat folder upload jika belum ada
    $upload_dir = createUploadDirectory();
    
    if (!$upload_dir) {
        throw new Exception("Gagal membuat folder upload");
    }
    
    // 4. Generate nama file yang aman
    $version_number = '1.0';
    $original_filename = sanitizeFilename($uploaded_file['name']);
    $secure_filename = generateSecureFilename($original_filename, $doc_code, $version_number);
    
    // 5. Path file lengkap
    $file_path = $upload_dir . $secure_filename;
    $relative_path = str_replace(BASE_PATH . '/', '', $file_path);
    
    // 6. Upload file ke server
    if (!move_uploaded_file($uploaded_file['tmp_name'], $file_path)) {
        throw new Exception("Gagal mengupload file ke server");
    }
    
    // 7. Generate hash file untuk validasi integritas
    $file_hash = generateFileHash($file_path);
    $file_size = filesize($file_path);
    
    // 8. Insert ke tabel document_versions
    $stmt = $conn->prepare("
        INSERT INTO document_versions 
        (document_id, version_number, file_name, file_path, file_size, file_hash, uploaded_by, is_current)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ");
    
    $stmt->bind_param("idssssi", 
        $document_id, 
        $version_number, 
        $original_filename, 
        $relative_path, 
        $file_size, 
        $file_hash, 
        $user_id
    );
    
    if (!$stmt->execute()) {
        // Hapus file jika gagal insert database
        unlink($file_path);
        throw new Exception("Gagal menyimpan versi dokumen: " . $stmt->error);
    }
    
    $version_id = $conn->insert_id;
    
    // 9. Update current_version_id di tabel documents
    $stmt = $conn->prepare("
        UPDATE documents 
        SET current_version_id = ?
        WHERE document_id = ?
    ");
    
    $stmt->bind_param("ii", $version_id, $document_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Gagal update current version: " . $stmt->error);
    }
    
    // 10. Log aktivitas
    logActivity(
        $user_id, 
        'upload_document', 
        $document_id, 
        "Uploaded new document: {$doc_code} - {$title}"
    );
    
    // Commit transaction
    $conn->commit();
    
    // Redirect dengan pesan sukses
    redirectWithMessage(
        'detail.php?code=' . $doc_code, 
        'success', 
        "Dokumen berhasil diupload! Kode Dokumen: {$doc_code}"
    );
    
} catch (Exception $e) {
    // Rollback jika ada error
    $conn->rollback();
    
    // Hapus file jika sudah terupload
    if (isset($file_path) && file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Log error
    error_log("Upload Error: " . $e->getMessage());
    
    // Redirect dengan error
    redirectWithMessage(
        'upload.php', 
        'error', 
        'Terjadi kesalahan saat upload: ' . $e->getMessage()
    );
}
?>