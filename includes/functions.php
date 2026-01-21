<?php
/**
 * Helper Functions
 * Fungsi-fungsi pembantu umum untuk sistem
 */

// Prevent direct access
if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

// ================================================
// ALERT & NOTIFICATION FUNCTIONS
// ================================================

/**
 * Set flash message (alert)
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type, // success, error, warning, info
        'message' => $message
    ];
}

/**
 * Get dan hapus flash message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

/**
 * Display flash message HTML
 */
function displayFlashMessage() {
    $flash = getFlashMessage();
    
    if ($flash) {
        $icons = [
            'success' => '✓',
            'error' => '⚠️',
            'warning' => '⚠',
            'info' => 'ℹ'
        ];
        
        $icon = $icons[$flash['type']] ?? 'ℹ';
        
        echo "<div class='alert alert-{$flash['type']}' style='animation: slideDown 0.3s ease-out;'>";
        echo "{$icon} " . htmlspecialchars($flash['message']);
        echo "</div>";
    }
}

// ================================================
// PAGINATION FUNCTIONS
// ================================================

/**
 * Generate pagination links
 */
function generatePagination($total_records, $per_page, $current_page, $base_url) {
    $total_pages = ceil($total_records / $per_page);
    
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<div class="pagination">';
    
    // Previous button
    if ($current_page > 1) {
        $prev_page = $current_page - 1;
        $html .= "<a href='{$base_url}?page={$prev_page}' class='page-link'>← Prev</a>";
    } else {
        $html .= "<span class='page-link disabled'>← Prev</span>";
    }
    
    // Page numbers
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    if ($start > 1) {
        $html .= "<a href='{$base_url}?page=1' class='page-link'>1</a>";
        if ($start > 2) {
            $html .= "<span class='page-link disabled'>...</span>";
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $current_page) {
            $html .= "<span class='page-link active'>{$i}</span>";
        } else {
            $html .= "<a href='{$base_url}?page={$i}' class='page-link'>{$i}</a>";
        }
    }
    
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $html .= "<span class='page-link disabled'>...</span>";
        }
        $html .= "<a href='{$base_url}?page={$total_pages}' class='page-link'>{$total_pages}</a>";
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $next_page = $current_page + 1;
        $html .= "<a href='{$base_url}?page={$next_page}' class='page-link'>Next →</a>";
    } else {
        $html .= "<span class='page-link disabled'>Next →</span>";
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Calculate offset for SQL query
 */
function calculateOffset($page, $per_page) {
    return ($page - 1) * $per_page;
}

// ================================================
// URL & REDIRECT FUNCTIONS
// ================================================

/**
 * Redirect with message
 */
function redirectWithMessage($url, $type, $message) {
    setFlashMessage($type, $message);
    header("Location: " . $url);
    exit;
}

/**
 * Redirect back (referer)
 */
function redirectBack($default_url = null) {
    if (isset($_SERVER['HTTP_REFERER'])) {
        header("Location: " . $_SERVER['HTTP_REFERER']);
    } elseif ($default_url) {
        header("Location: " . $default_url);
    } else {
        header("Location: " . BASE_URL);
    }
    exit;
}

/**
 * Build URL with query parameters
 */
function buildUrl($base_url, $params = []) {
    if (empty($params)) {
        return $base_url;
    }
    
    return $base_url . '?' . http_build_query($params);
}

/**
 * Get current URL
 */
function getCurrentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    return $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

// ================================================
// DATE & TIME FUNCTIONS
// ================================================

/**
 * Format tanggal Indonesia lengkap
 */
function formatTanggalLengkap($date) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $timestamp = strtotime($date);
    $hari = date('d', $timestamp);
    $bulan_num = date('n', $timestamp);
    $tahun = date('Y', $timestamp);
    
    return $hari . ' ' . $bulan[$bulan_num] . ' ' . $tahun;
}

/**
 * Time ago format (2 jam yang lalu, 3 hari yang lalu)
 */
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Baru saja';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' menit yang lalu';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' jam yang lalu';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' hari yang lalu';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' minggu yang lalu';
    } else {
        return formatTanggalLengkap($datetime);
    }
}

/**
 * Check if date is today
 */
function isToday($date) {
    return date('Y-m-d', strtotime($date)) === date('Y-m-d');
}

// ================================================
// ARRAY & DATA FUNCTIONS
// ================================================

/**
 * Get value dari array dengan default
 */
function arrayGet($array, $key, $default = null) {
    return isset($array[$key]) ? $array[$key] : $default;
}

/**
 * Check if array is associative
 */
function isAssocArray($array) {
    if (!is_array($array) || empty($array)) {
        return false;
    }
    return array_keys($array) !== range(0, count($array) - 1);
}

// ================================================
// STRING FUNCTIONS
// ================================================

/**
 * Truncate string dengan ellipsis
 */
function truncate($string, $length = 100, $ellipsis = '...') {
    if (strlen($string) <= $length) {
        return $string;
    }
    
    return substr($string, 0, $length - strlen($ellipsis)) . $ellipsis;
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $string = '';
    
    for ($i = 0; $i < $length; $i++) {
        $string .= $characters[random_int(0, strlen($characters) - 1)];
    }
    
    return $string;
}

/**
 * Slug generator (untuk URL friendly string)
 */
function slugify($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    $string = trim($string, '-');
    return $string;
}

// ================================================
// VALIDATION FUNCTIONS
// ================================================

/**
 * Validate required fields
 */
function validateRequired($data, $required_fields) {
    $errors = [];
    
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' tidak boleh kosong';
        }
    }
    
    return $errors;
}

/**
 * Validate file extension
 */
function validateFileExtension($filename, $allowed_extensions) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, $allowed_extensions);
}

// ================================================
// DEBUG FUNCTIONS
// ================================================

/**
 * Pretty print array (untuk debugging)
 */
function dd($data, $die = true) {
    echo '<pre style="background: #1e1e1e; color: #dcdcdc; padding: 15px; border-radius: 5px; font-size: 12px; overflow-x: auto;">';
    print_r($data);
    echo '</pre>';
    
    if ($die) {
        die();
    }
}

/**
 * Dump and die
 */
function dump($data) {
    dd($data, false);
}

/**
 * Log debug info ke file
 */
function logDebug($message, $data = null) {
    $log_file = BASE_PATH . '/logs/debug.log';
    $log_dir = dirname($log_file);
    
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}";
    
    if ($data !== null) {
        $log_message .= "\n" . print_r($data, true);
    }
    
    $log_message .= "\n" . str_repeat('-', 80) . "\n";
    
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// ================================================
// DOCUMENT HELPER FUNCTIONS
// ================================================

/**
 * Get document by code
 */
function getDocumentByCode($doc_code) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT d.*, u.full_name as uploader_name,
               dv.version_number, dv.file_name, dv.file_path
        FROM documents d
        LEFT JOIN users u ON d.uploader_id = u.user_id
        LEFT JOIN document_versions dv ON d.current_version_id = dv.version_id
        WHERE d.doc_code = ?
    ");
    
    $stmt->bind_param("s", $doc_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Get all versions of a document
 */
function getDocumentVersions($document_id) {
    global $conn;
    
    $query = "
        SELECT dv.*, u.full_name as uploaded_by_name,
               r.status as review_status, r.notes as review_notes,
               rv.full_name as reviewer_name
        FROM document_versions dv
        LEFT JOIN users u ON dv.uploaded_by = u.user_id
        LEFT JOIN reviews r ON dv.version_id = r.version_id
        LEFT JOIN users rv ON r.reviewer_id = rv.user_id
        WHERE dv.document_id = ?
        ORDER BY dv.version_number DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    
    return $stmt->get_result();
}

/**
 * Check if user can edit document
 */
function canEditDocument($document_id, $user_id, $user_role) {
    global $conn;
    
    // Admin bisa edit semua
    if ($user_role === 'admin') {
        return true;
    }
    
    // Cek apakah user adalah uploader dokumen
    $stmt = $conn->prepare("SELECT uploader_id FROM documents WHERE document_id = ?");
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $doc = $result->fetch_assoc();
        return $doc['uploader_id'] == $user_id;
    }
    
    return false;
}

/**
 * Get status badge HTML
 */
function getStatusBadge($status) {
    $badge_class = getStatusBadgeClass($status);
    $status_text = DOC_STATUS[$status] ?? $status;
    
    return "<span class='badge badge-{$badge_class}'>{$status_text}</span>";
}

/**
 * Get priority badge HTML
 */
function getPriorityBadge($priority) {
    $badge_class = getPriorityBadgeClass($priority);
    $priority_text = PRIORITY_LEVELS[$priority] ?? $priority;
    
    return "<span class='badge badge-{$badge_class}'>{$priority_text}</span>";
}

// ================================================
// STATISTICS FUNCTIONS
// ================================================

/**
 * Get user statistics
 */
function getUserStatistics($user_id, $role) {
    global $conn;
    
    if ($role === 'uploader') {
        $query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'revision' THEN 1 ELSE 0 END) as revision,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft
            FROM documents 
            WHERE uploader_id = ?
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        
    } elseif ($role === 'reviewer') {
        $query = "
            SELECT 
                COUNT(DISTINCT d.document_id) as total,
                SUM(CASE WHEN d.status = 'pending' THEN 1 ELSE 0 END) as pending,
                COUNT(DISTINCT r.review_id) as total_reviewed,
                COUNT(DISTINCT CASE WHEN DATE(r.review_date) = CURDATE() THEN r.review_id END) as reviewed_today
            FROM documents d
            LEFT JOIN document_versions dv ON d.current_version_id = dv.version_id
            LEFT JOIN reviews r ON dv.version_id = r.version_id AND r.reviewer_id = ?
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        
    } else {
        // Admin
        $query = "
            SELECT 
                COUNT(*) as total_docs,
                (SELECT COUNT(*) FROM users WHERE is_active = 1) as total_users,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'revision' THEN 1 ELSE 0 END) as revision
            FROM documents
        ";
        
        $stmt = $conn->prepare($query);
    }
    
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// ================================================
// NOTIFICATION FUNCTIONS
// ================================================

/**
 * Get unread notifications count (placeholder untuk fitur future)
 */
function getUnreadNotificationsCount($user_id) {
    // TODO: Implement notification system
    return 0;
}

/**
 * Send email notification (placeholder untuk fitur future)
 */
function sendEmailNotification($to, $subject, $message) {
    // TODO: Implement email sending
    // Untuk sekarang hanya log saja
    logDebug("Email would be sent to: {$to}", ['subject' => $subject, 'message' => $message]);
    return true;
}
?>