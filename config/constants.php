<?php
/**
 * System Constants
 * Sistem Manajemen Dokumen Laboratorium
 */

// ================================================
// PATH CONFIGURATION
// ================================================
define('BASE_PATH', dirname(__DIR__));  // Root folder project
define('UPLOAD_PATH', BASE_PATH . '/uploads/documents/');
define('BASE_URL', 'http://lab-docs');  

// ================================================
// FILE UPLOAD SETTINGS
// ================================================
define('MAX_FILE_SIZE', 10 * 1024 * 1024);  // 10MB dalam bytes
define('ALLOWED_EXTENSIONS', ['pdf']);
define('ALLOWED_MIME_TYPES', ['application/pdf']);

// ================================================
// DOCUMENT CATEGORIES
// ================================================
define('DOC_CATEGORIES', [
    'analisis_air' => 'Analisis Air',
    'analisis_udara' => 'Analisis Udara',
    'analisis_tanah' => 'Analisis Tanah',
    'analisis_limbah' => 'Analisis Limbah',
    'kalibrasi' => 'Kalibrasi Alat',
    'sop' => 'SOP Laboratorium',
    'laporan_kegiatan' => 'Laporan Kegiatan',
    'lainnya' => 'Lainnya'
]);

// ================================================
// STATUS DOKUMEN
// ================================================
define('DOC_STATUS', [
    'draft' => 'Draft',
    'pending' => 'Menunggu Pemeriksaan',
    'revision' => 'Perlu Revisi',
    'approved' => 'Disetujui',
    'archived' => 'Diarsipkan'
]);

// ================================================
// USER ROLES
// ================================================
define('USER_ROLES', [
    'uploader' => 'Pengunggah Dokumen',
    'reviewer' => 'Pemeriksa Dokumen',
    'admin' => 'Administrator'
]);

// ================================================
// REVIEW ISSUE TYPES
// ================================================
define('ISSUE_TYPES', [
    'typo' => 'Kesalahan Ketik',
    'data' => 'Kesalahan Data',
    'format' => 'Format Tidak Sesuai',
    'content' => 'Konten Kurang Lengkap',
    'other' => 'Lainnya'
]);

// ================================================
// PRIORITY LEVELS
// ================================================
define('PRIORITY_LEVELS', [
    'low' => 'Rendah',
    'medium' => 'Sedang',
    'high' => 'Tinggi'
]);

// ================================================
// PAGINATION SETTINGS
// ================================================
define('ITEMS_PER_PAGE', 10);

// ================================================
// SESSION SETTINGS
// ================================================
define('SESSION_TIMEOUT', 3600);  // 1 jam dalam detik

// ================================================
// APPLICATION INFO
// ================================================
define('APP_NAME', 'Sistem Manajemen Dokumen Laboratorium');
define('APP_SHORT_NAME', 'SMDL');
define('APP_VERSION', '1.0.0');
define('APP_AUTHOR', 'Dinas Lingkungan Hidup');
define('APP_YEAR', '2026');

// ================================================
// HELPER FUNCTIONS
// ================================================

/**
 * Format ukuran file menjadi readable
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        $bytes = $bytes . ' bytes';
    } elseif ($bytes == 1) {
        $bytes = $bytes . ' byte';
    } else {
        $bytes = '0 bytes';
    }
    return $bytes;
}

/**
 * Format tanggal Indonesia
 */
function formatTanggal($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

/**
 * Get status badge class
 */
function getStatusBadgeClass($status) {
    $classes = [
        'draft' => 'secondary',
        'pending' => 'warning',
        'revision' => 'danger',
        'approved' => 'success',
        'archived' => 'secondary'
    ];
    return $classes[$status] ?? 'secondary';
}

/**
 * Get priority badge class
 */
function getPriorityBadgeClass($priority) {
    $classes = [
        'low' => 'info',
        'medium' => 'warning',
        'high' => 'danger'
    ];
    return $classes[$priority] ?? 'secondary';
}
?>