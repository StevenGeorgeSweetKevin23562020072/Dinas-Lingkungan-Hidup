<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: pending.php");
    exit;
}

// Hanya reviewer dan admin
requireAnyRole(['reviewer', 'admin']);

// Validasi CSRF
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    redirectWithMessage('pending.php', 'error', 'Invalid CSRF token');
}

$user = getCurrentUser();
$user_id = $user['user_id'];

// Ambil input
$doc_code = sanitizeInput($_POST['doc_code'] ?? '');
$version_id = intval($_POST['version_id'] ?? 0);
$review_status = sanitizeInput($_POST['review_status'] ?? '');
$notes = sanitizeInput($_POST['notes'] ?? '');

// Validasi input
$errors = [];

if (empty($doc_code)) {
    $errors[] = "Kode dokumen tidak valid";
}

if ($version_id <= 0) {
    $errors[] = "Version ID tidak valid";
}

if (!in_array($review_status, ['approved', 'needs_revision'])) {
    $errors[] = "Status review tidak valid";
}

// Jika perlu revisi, validasi revision items
$revision_sections = $_POST['revision_section'] ?? [];
$revision_types = $_POST['revision_type'] ?? [];
$revision_priorities = $_POST['revision_priority'] ?? [];
$revision_descriptions = $_POST['revision_description'] ?? [];

if ($review_status === 'needs_revision') {
    // Harus ada minimal 1 item koreksi dengan deskripsi
    $has_valid_item = false;
    
    foreach ($revision_descriptions as $desc) {
        if (!empty(trim($desc))) {
            $has_valid_item = true;
            break;
        }
    }
    
    if (!$has_valid_item) {
        $errors[] = "Minimal harus ada 1 item koreksi dengan deskripsi";
    }
}

if (!empty($errors)) {
    redirectWithMessage("review_form.php?code={$doc_code}", 'error', implode(', ', $errors));
}

// Get document
$document = getDocumentByCode($doc_code);

if (!$document) {
    redirectWithMessage('pending.php', 'error', 'Dokumen tidak ditemukan');
}

// Validasi status dokumen
if ($document['status'] !== 'pending') {
    redirectWithMessage(
        "../documents/detail.php?code={$doc_code}",
        'error',
        'Dokumen ini tidak dalam status pending'
    );
}

// ================================================
// PROSES REVIEW
// ================================================

try {
    // Start transaction
    $conn->begin_transaction();
    
    // 1. Insert review ke tabel reviews
    $stmt = $conn->prepare("
        INSERT INTO reviews (version_id, reviewer_id, status, notes)
        VALUES (?, ?, ?, ?)
    ");
    
    $stmt->bind_param("iiss", $version_id, $user_id, $review_status, $notes);
    
    if (!$stmt->execute()) {
        throw new Exception("Gagal menyimpan review: " . $stmt->error);
    }
    
    $review_id = $conn->insert_id;
    
    // 2. Jika needs_revision, insert review items
    if ($review_status === 'needs_revision') {
        $stmt_item = $conn->prepare("
            INSERT INTO review_items (review_id, section, issue_type, priority, description)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $item_count = count($revision_descriptions);
        
        for ($i = 0; $i < $item_count; $i++) {
            $description = trim($revision_descriptions[$i]);
            
            // Skip jika deskripsi kosong
            if (empty($description)) {
                continue;
            }
            
            $section = trim($revision_sections[$i] ?? '');
            $type = $revision_types[$i] ?? 'other';
            $priority = $revision_priorities[$i] ?? 'medium';
            
            $stmt_item->bind_param("issss", 
                $review_id, 
                $section, 
                $type, 
                $priority, 
                $description
            );
            
            if (!$stmt_item->execute()) {
                throw new Exception("Gagal menyimpan item koreksi: " . $stmt_item->error);
            }
        }
    }
    
    // 3. Update status dokumen
    $new_doc_status = ($review_status === 'approved') ? 'approved' : 'revision';
    
    $stmt = $conn->prepare("
        UPDATE documents 
        SET status = ?, 
            updated_at = NOW()
        WHERE document_id = ?
    ");
    
    $stmt->bind_param("si", $new_doc_status, $document['document_id']);
    
    if (!$stmt->execute()) {
        throw new Exception("Gagal update status dokumen: " . $stmt->error);
    }
    
    // 4. Log aktivitas
    $activity_detail = ($review_status === 'approved') 
        ? "Approved document {$doc_code}"
        : "Requested revision for document {$doc_code}";
    
    logActivity(
        $user_id,
        'review_document',
        $document['document_id'],
        $activity_detail
    );
    
    // Commit transaction
    $conn->commit();
    
    // 5. Redirect dengan pesan sukses
    if ($review_status === 'approved') {
        redirectWithMessage(
            'pending.php',
            'success',
            "Dokumen {$doc_code} berhasil disetujui!"
        );
    } else {
        redirectWithMessage(
            'pending.php',
            'success',
            "Review tersimpan. Dokumen {$doc_code} perlu revisi."
        );
    }
    
} catch (Exception $e) {
    // Rollback
    $conn->rollback();
    
    // Log error
    error_log("Review Error: " . $e->getMessage());
    
    // Redirect dengan error
    redirectWithMessage(
        "review_form.php?code={$doc_code}",
        'error',
        'Terjadi kesalahan saat menyimpan review: ' . $e->getMessage()
    );
}
?>