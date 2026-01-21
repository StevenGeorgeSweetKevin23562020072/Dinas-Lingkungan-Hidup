<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Hanya uploader yang bisa upload revisi
requireAnyRole(['uploader', 'admin']);

$user = getCurrentUser();
$user_id = $user['user_id'];

// Get document code
$doc_code = isset($_GET['code']) ? sanitizeInput($_GET['code']) : '';

if (empty($doc_code)) {
    redirectWithMessage('list.php', 'error', 'Kode dokumen tidak valid');
}

// Get document data
$document = getDocumentByCode($doc_code);

if (!$document) {
    redirectWithMessage('list.php', 'error', 'Dokumen tidak ditemukan');
}

// Validasi: hanya uploader dokumen yang bisa upload revisi
if ($document['uploader_id'] != $user_id && $user['role'] !== 'admin') {
    redirectWithMessage('list.php', 'error', 'Anda tidak memiliki izin untuk merevisi dokumen ini');
}

// Validasi: dokumen harus berstatus 'revision'
if ($document['status'] !== 'revision') {
    redirectWithMessage(
        "detail.php?code={$doc_code}", 
        'error', 
        'Dokumen ini tidak dalam status perlu revisi'
    );
}

// Get review notes (catatan pemeriksa)
$review_notes = null;
$review_items = null;

if ($document['current_version_id']) {
    $query_review = "
        SELECT r.*, u.full_name as reviewer_name
        FROM reviews r
        LEFT JOIN users u ON r.reviewer_id = u.user_id
        WHERE r.version_id = ? AND r.status = 'needs_revision'
        ORDER BY r.review_date DESC
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($query_review);
    $stmt->bind_param("i", $document['current_version_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $review_notes = $result->fetch_assoc();
        
        // Get review items
        $query_items = "
            SELECT * FROM review_items
            WHERE review_id = ?
            ORDER BY priority DESC, created_at ASC
        ";
        
        $stmt = $conn->prepare($query_items);
        $stmt->bind_param("i", $review_notes['review_id']);
        $stmt->execute();
        $review_items = $stmt->get_result();
    }
}

// Get next version number
$next_version = getNextVersionNumber($document['document_id']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Revisi - <?php echo e($document['title']); ?></title>
     <link rel="stylesheet" href="../../assets/css/style.css"> 

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
        }

        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar h1 {
            font-size: 20px;
        }

        .navbar .nav-links {
            display: flex;
            gap: 15px;
        }

        .navbar .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .navbar .nav-links a:hover {
            background: rgba(255,255,255,0.2);
        }

        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .breadcrumb {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }

        .breadcrumb span {
            color: #999;
            margin: 0 8px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .card h2 {
            color: #333;
            margin-bottom: 10px;
        }

        .card h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-warning {
            background: #fff3cd;
            border-left: 4px solid #f39c12;
            color: #856404;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .review-items {
            list-style: none;
            margin-top: 15px;
        }

        .review-items li {
            padding: 12px;
            background: #f8f9fa;
            margin-bottom: 10px;
            border-radius: 5px;
            border-left: 3px solid #e74c3c;
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 12px;
        }

        .item-section {
            font-weight: 600;
            color: #333;
        }

        .item-desc {
            color: #666;
            font-size: 14px;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-low { background: #d1ecf1; color: #0c5460; }
        .badge-medium { background: #fff3cd; color: #856404; }
        .badge-high { background: #f8d7da; color: #721c24; }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        label .required {
            color: #e74c3c;
        }

        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
        }

        textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .file-upload {
            border: 2px dashed #e0e0e0;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }

        .file-upload:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .file-upload input[type="file"] {
            display: none;
        }

        .file-upload-icon {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 15px;
        }

        .file-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            display: none;
        }

        .file-info.active {
            display: block;
        }

        .btn {
            padding: 12px 30px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .help-text {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .doc-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .doc-info p {
            margin: 5px 0;
            font-size: 14px;
            color: #666;
        }

        .doc-info strong {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🔄 Upload Revisi Dokumen</h1>
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
            <a href="detail.php?code=<?php echo $doc_code; ?>"><?php echo e($doc_code); ?></a>
            <span>›</span>
            <strong>Upload Revisi</strong>
        </div>

        <?php displayFlashMessage(); ?>

        <div class="card">
            <h2>Upload Revisi Dokumen</h2>
            <p style="color: #666; margin-bottom: 20px;">
                Upload file yang sudah diperbaiki sesuai catatan pemeriksa
            </p>

            <div class="doc-info">
                <p><strong>Dokumen:</strong> <?php echo e($document['title']); ?></p>
                <p><strong>Kode:</strong> <?php echo e($document['doc_code']); ?></p>
                <p><strong>Versi Saat Ini:</strong> v<?php echo number_format($document['version_number'], 1); ?></p>
                <p><strong>Versi Baru:</strong> v<?php echo $next_version; ?></p>
            </div>

            <?php if ($review_notes): ?>
            <div class="alert alert-warning">
                <h4>📋 Catatan Pemeriksa</h4>
                <p style="margin-top: 10px;">
                    <strong>Pemeriksa:</strong> <?php echo e($review_notes['reviewer_name']); ?><br>
                    <strong>Tanggal Review:</strong> <?php echo formatTanggalLengkap($review_notes['review_date']); ?>
                </p>

                <?php if ($review_notes['notes']): ?>
                <p style="margin-top: 15px;">
                    <strong>Catatan Umum:</strong><br>
                    <?php echo nl2br(e($review_notes['notes'])); ?>
                </p>
                <?php endif; ?>

                <?php if ($review_items && $review_items->num_rows > 0): ?>
                <h4 style="margin-top: 20px; margin-bottom: 10px;">Daftar Perbaikan yang Diperlukan:</h4>
                <ul class="review-items">
                    <?php while($item = $review_items->fetch_assoc()): ?>
                    <li>
                        <div class="item-header">
                            <span class="item-section">
                                📍 <?php echo e($item['section'] ?: 'Umum'); ?>
                            </span>
                            <span>
                                <?php 
                                $badges = [
                                    'low' => 'badge-low',
                                    'medium' => 'badge-medium',
                                    'high' => 'badge-high'
                                ];
                                $priority_text = PRIORITY_LEVELS[$item['priority']];
                                ?>
                                <span class="badge <?php echo $badges[$item['priority']]; ?>">
                                    <?php echo $priority_text; ?>
                                </span>
                                <span style="margin-left: 10px; color: #999; font-size: 11px;">
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
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Upload File Revisi</h3>

            <div class="alert alert-info">
                <strong>ℹ️ Informasi:</strong><br>
                • File akan disimpan sebagai versi baru (v<?php echo $next_version; ?>)<br>
                • Versi lama tetap tersimpan untuk arsip<br>
                • Dokumen akan kembali berstatus "Menunggu Pemeriksaan"
            </div>

            <form action="process_revisi.php" method="POST" enctype="multipart/form-data" id="revisiForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="doc_code" value="<?php echo e($doc_code); ?>">

                <div class="form-group">
                    <label for="notes">
                        Catatan Perubahan <span class="required">*</span>
                    </label>
                    <textarea 
                        id="notes" 
                        name="notes" 
                        required
                        placeholder="Jelaskan perubahan apa saja yang telah dilakukan pada revisi ini..."
                    ></textarea>
                    <div class="help-text">
                        Jelaskan secara singkat perubahan yang dilakukan untuk memudahkan pemeriksa
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        File Dokumen Revisi (PDF) <span class="required">*</span>
                    </label>
                    <div class="file-upload" id="fileUploadArea">
                        <input 
                            type="file" 
                            id="document_file" 
                            name="document_file" 
                            accept=".pdf,application/pdf"
                            required
                        >
                        <label for="document_file" style="cursor: pointer;">
                            <div class="file-upload-icon">📄</div>
                            <div>
                                <strong>Klik untuk memilih file</strong> atau drag & drop<br>
                                <small>File PDF, maksimal <?php echo formatFileSize(MAX_FILE_SIZE); ?></small>
                            </div>
                        </label>
                    </div>

                    <div class="file-info" id="fileInfo">
                        <div style="display: flex; justify-content: space-between; padding: 8px 0;">
                            <span style="color: #666;">Nama File:</span>
                            <span id="fileName" style="font-weight: 600;">-</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 8px 0; border-top: 1px solid #e0e0e0;">
                            <span style="color: #666;">Ukuran:</span>
                            <span id="fileSize" style="font-weight: 600;">-</span>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="detail.php?code=<?php echo $doc_code; ?>" class="btn btn-secondary">
                        ← Batal
                    </a>
                    <button type="submit" class="btn" id="submitBtn">
                        🔄 Upload Revisi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const fileInput = document.getElementById('document_file');
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInfo = document.getElementById('fileInfo');
        const form = document.getElementById('revisiForm');
        const submitBtn = document.getElementById('submitBtn');
        const maxFileSize = <?php echo MAX_FILE_SIZE; ?>;

        fileInput.addEventListener('change', function(e) {
            handleFile(this.files[0]);
        });

        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = '#667eea';
            this.style.background = '#f0f2ff';
        });

        fileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.borderColor = '#e0e0e0';
            this.style.background = 'transparent';
        });

        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = '#e0e0e0';
            this.style.background = 'transparent';
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFile(files[0]);
            }
        });

        function handleFile(file) {
            if (!file) return;

            if (file.type !== 'application/pdf') {
                alert('Hanya file PDF yang diperbolehkan!');
                fileInput.value = '';
                fileInfo.classList.remove('active');
                return;
            }

            if (file.size > maxFileSize) {
                alert('Ukuran file terlalu besar! Maksimal ' + formatFileSize(maxFileSize));
                fileInput.value = '';
                fileInfo.classList.remove('active');
                return;
            }

            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileSize').textContent = formatFileSize(file.size);
            fileInfo.classList.add('active');
        }

        function formatFileSize(bytes) {
            if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            } else {
                return bytes + ' bytes';
            }
        }

        form.addEventListener('submit', function(e) {
            if (!fileInput.files || fileInput.files.length === 0) {
                e.preventDefault();
                alert('Silakan pilih file dokumen revisi!');
                return false;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Mengupload Revisi...';
        });
    </script>
    <script src="../../assets/js/script.js"></script>

</body>
</html>