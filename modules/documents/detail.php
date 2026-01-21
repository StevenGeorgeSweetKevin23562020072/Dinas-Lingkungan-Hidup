<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireLogin();

$user = getCurrentUser();
$user_id = $user['user_id'];
$role = $user['role'];

// Get document code dari URL
$doc_code = isset($_GET['code']) ? sanitizeInput($_GET['code']) : '';

if (empty($doc_code)) {
    redirectWithMessage('list.php', 'error', 'Kode dokumen tidak valid');
}

// Get document data
$document = getDocumentByCode($doc_code);

if (!$document) {
    redirectWithMessage('list.php', 'error', 'Dokumen tidak ditemukan');
}

// Check access permission
if (!hasDocumentAccess($user_id, $role, $document['uploader_id'])) {
    http_response_code(403);
    die("
        <h1>403 - Akses Ditolak</h1>
        <p>Anda tidak memiliki izin untuk melihat dokumen ini.</p>
        <a href='list.php'>← Kembali ke Daftar Dokumen</a>
    ");
}

// Get all versions
$versions = getDocumentVersions($document['document_id']);

// Get latest review (jika ada)
$latest_review = null;
$review_items = null;

if ($document['current_version_id']) {
    $query_review = "
        SELECT r.*, u.full_name as reviewer_name, ri.item_id
        FROM reviews r
        LEFT JOIN users u ON r.reviewer_id = u.user_id
        LEFT JOIN review_items ri ON r.review_id = ri.review_id
        WHERE r.version_id = ?
        ORDER BY r.review_date DESC
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($query_review);
    $stmt->bind_param("i", $document['current_version_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $latest_review = $result->fetch_assoc();
        
        // Get review items jika ada
        if ($latest_review['review_id']) {
            $query_items = "
                SELECT * FROM review_items
                WHERE review_id = ?
                ORDER BY priority DESC, created_at ASC
            ";
            
            $stmt = $conn->prepare($query_items);
            $stmt->bind_param("i", $latest_review['review_id']);
            $stmt->execute();
            $review_items = $stmt->get_result();
        }
    }
}

// Check if user can upload revision (uploader only, dan status revision)
$can_upload_revision = ($role === 'uploader' || $role === 'admin') && 
                       $document['uploader_id'] == $user_id && 
                       $document['status'] === 'revision';

// Check if reviewer can review (reviewer only, dan status pending)
$can_review = ($role === 'reviewer' || $role === 'admin') && 
              $document['status'] === 'pending';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($document['title']); ?> - <?php echo APP_NAME; ?></title>
     <link rel="stylesheet" href="../../assets/css/style.css"> 

    <style>
         * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #e8f5e9 0%, #f1f8f4 100%);
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(135deg, #16a085 0%, #27ae60 100%);
            color: white;
            padding: 20px 30px;
            box-shadow: 0 4px 20px rgba(22, 160, 133, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar h1 {
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .nav-links {
            display: flex;
            gap: 15px;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 600;
            font-size: 14px;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .breadcrumb {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .breadcrumb a {
            color: #27ae60;
            text-decoration: none;
            font-weight: 600;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb span {
            color: #95a5a6;
            margin: 0 8px;
        }
        
        .doc-header {
            background: white;
            padding: 35px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .doc-code {
            display: inline-block;
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 18px;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }
        
        .doc-header h2 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 28px;
            line-height: 1.4;
        }
        
        .doc-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 2px solid #e8f5e9;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-size: 12px;
            color: #95a5a6;
            margin-bottom: 6px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .meta-value {
            font-size: 15px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        
        .badge-draft { background: #e2e3e5; color: #383d41; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-revision { background: #f8d7da; color: #721c24; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-low { background: #d1ecf1; color: #0c5460; }
        .badge-medium { background: #fff3cd; color: #856404; }
        .badge-high { background: #f8d7da; color: #721c24; }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .card h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e8f5e9;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #229954 0%, #27ae60 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
        }
        
        .btn-danger {
            background: #e74c3c;
        }
        
        .btn-danger:hover {
            background: #c0392b;
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.3);
        }
        
        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 25px;
        }
        
        .timeline {
            position: relative;
            padding-left: 35px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 12px;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(180deg, #27ae60 0%, #e8f5e9 100%);
            border-radius: 2px;
        }
        
        .timeline-item {
            position: relative;
            padding: 20px 0;
            padding-left: 25px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -28px;
            top: 25px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #27ae60;
            border: 4px solid white;
            box-shadow: 0 0 0 3px #e8f5e9;
        }
        
        .timeline-item.current::before {
            background: #2ecc71;
            box-shadow: 0 0 0 3px #2ecc71, 0 0 15px rgba(46, 204, 113, 0.5);
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .version-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e8f5e9 100%);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 12px;
            border-left: 4px solid #27ae60;
        }
        
        .version-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .version-number {
            font-size: 18px;
            font-weight: 700;
            color: #27ae60;
        }
        
        .version-meta {
            font-size: 13px;
            color: #7f8c8d;
            line-height: 1.6;
        }
        
        .review-alert {
            background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
            border-left: 4px solid #f39c12;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        .review-alert h4 {
            color: #856404;
            margin-bottom: 12px;
            font-size: 18px;
        }
        
        .review-items {
            list-style: none;
        }
        
        .review-items li {
            padding: 15px;
            background: white;
            margin-bottom: 12px;
            border-radius: 10px;
            border-left: 4px solid #e74c3c;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .review-items .item-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
        }
        
        .review-items .item-section {
            font-weight: 700;
            color: #2c3e50;
        }
        
        .review-items .item-desc {
            color: #7f8c8d;
            font-size: 14px;
            line-height: 1.6;
        }
        
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
            }
            
            .doc-meta {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📄 Detail Dokumen</h1>
        <div class="nav-links">
            <a href="../dashboard/index.php">Dashboard</a>
            <a href="list.php">Daftar Dokumen</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="breadcrumb">
            <a href="../dashboard/index.php">Dashboard</a>
            <span>›</span>
            <a href="list.php">Daftar Dokumen</a>
            <span>›</span>
            <strong><?php echo e($document['doc_code']); ?></strong>
        </div>

        <?php displayFlashMessage(); ?>

        <!-- Document Header -->
        <div class="doc-header">
            <div class="doc-code"><?php echo e($document['doc_code']); ?></div>
            <h2><?php echo e($document['title']); ?></h2>
            
            <?php if ($document['description']): ?>
            <p style="color: #666; margin-top: 10px;">
                <?php echo nl2br(e($document['description'])); ?>
            </p>
            <?php endif; ?>

            <div class="doc-meta">
                <div class="meta-item">
                    <div class="meta-label">Status</div>
                    <div class="meta-value">
                        <?php echo getStatusBadge($document['status']); ?>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Kategori</div>
                    <div class="meta-value">
                        <?php echo DOC_CATEGORIES[$document['category']] ?? $document['category']; ?>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Pengunggah</div>
                    <div class="meta-value">
                        <?php echo e($document['uploader_name']); ?>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Versi Saat Ini</div>
                    <div class="meta-value">
                        v<?php echo number_format($document['version_number'], 1); ?>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Dibuat</div>
                    <div class="meta-value">
                        <?php echo formatTanggalLengkap($document['created_at']); ?>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Terakhir Update</div>
                    <div class="meta-value">
                        <?php echo timeAgo($document['updated_at']); ?>
                    </div>
                </div>
            </div>

            <div class="actions" style="margin-top: 20px;">
                <a href="download.php?vid=<?php echo $document['current_version_id']; ?>" class="btn" target="_blank">
                    📥 Download Versi Terbaru
                </a>
                
                <?php if ($can_upload_revision): ?>
                <a href="revisi.php?code=<?php echo $document['doc_code']; ?>" class="btn btn-success">
                    🔄 Upload Revisi
                </a>
                <?php endif; ?>

                <?php if ($can_review): ?>
                <a href="../review/review_form.php?code=<?php echo $document['doc_code']; ?>" class="btn btn-success">
                    ✅ Mulai Review
                </a>
                <?php endif; ?>

                <a href="list.php" class="btn btn-secondary">
                    ← Kembali
                </a>
            </div>
        </div>

        <!-- Review Notes (jika ada) -->
        <?php if ($latest_review && $latest_review['status'] === 'needs_revision' && $review_items && $review_items->num_rows > 0): ?>
        <div class="review-alert">
            <h4>⚠️ Catatan Pemeriksa - Perlu Perbaikan</h4>
            <p style="margin-bottom: 15px; color: #856404;">
                <strong>Pemeriksa:</strong> <?php echo e($latest_review['reviewer_name']); ?> • 
                <strong>Tanggal:</strong> <?php echo formatTanggal($latest_review['review_date']); ?>
            </p>
            
            <?php if ($latest_review['notes']): ?>
            <p style="margin-bottom: 15px; color: #856404;">
                <strong>Catatan Umum:</strong> <?php echo nl2br(e($latest_review['notes'])); ?>
            </p>
            <?php endif; ?>

            <ul class="review-items">
                <?php while($item = $review_items->fetch_assoc()): ?>
                <li>
                    <div class="item-header">
                        <span class="item-section">
                            📍 <?php echo e($item['section'] ?: 'Umum'); ?>
                        </span>
                        <span>
                            <?php echo getPriorityBadge($item['priority']); ?>
                            <span style="margin-left: 10px; color: #999;">
                                <?php echo ISSUE_TYPES[$item['issue_type']]; ?>
                            </span>
                        </span>
                    </div>
                    <div class="item-desc">
                        <?php echo nl2br(e($item['description'])); ?>
                    </div>
                </li>
                <?php endwhile; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Version History -->
        <div class="card">
            <h3>📜 Riwayat Versi Dokumen</h3>
            
            <?php if ($versions->num_rows > 0): ?>
            <div class="timeline">
                <?php 
                $version_count = 0;
                while($version = $versions->fetch_assoc()): 
                    $version_count++;
                    $is_current = $version['is_current'] == 1;
                ?>
                <div class="timeline-item <?php echo $is_current ? 'current' : ''; ?>">
                    <div class="version-info">
                        <div class="version-header">
                            <div>
                                <span class="version-number">
                                    v<?php echo number_format($version['version_number'], 1); ?>
                                    <?php if ($is_current): ?>
                                    <span class="badge badge-approved">Versi Aktif</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <a href="download.php?vid=<?php echo $version['version_id']; ?>" class="btn" style="padding: 6px 12px; font-size: 12px;" target="_blank">
                                📥 Download
                            </a>
                        </div>
                        
                        <div class="version-meta">
                            <p>
                                <strong>File:</strong> <?php echo e($version['file_name']); ?> 
                                (<?php echo formatFileSize($version['file_size']); ?>)
                            </p>
                            <p>
                                <strong>Diunggah:</strong> <?php echo formatTanggalLengkap($version['upload_date']); ?>
                                oleh <?php echo e($version['uploaded_by_name']); ?>
                            </p>
                            
                            <?php if ($version['notes']): ?>
                            <p style="margin-top: 10px;">
                                <strong>Catatan Perubahan:</strong><br>
                                <?php echo nl2br(e($version['notes'])); ?>
                            </p>
                            <?php endif; ?>

                            <?php if ($version['review_status']): ?>
                            <p style="margin-top: 10px;">
                                <strong>Status Review:</strong> 
                                <?php 
                                $status_names = [
                                    'pending' => 'Menunggu Review',
                                    'approved' => 'Disetujui',
                                    'needs_revision' => 'Perlu Revisi'
                                ];
                                echo $status_names[$version['review_status']] ?? $version['review_status'];
                                ?>
                                <?php if ($version['reviewer_name']): ?>
                                oleh <?php echo e($version['reviewer_name']); ?>
                                <?php endif; ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p>Belum ada riwayat versi</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="../../assets/js/script.js"></script>

</body>
</html>