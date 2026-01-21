<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Hanya reviewer dan admin
requireAnyRole(['reviewer', 'admin']);

$user = getCurrentUser();

// Get document code
$doc_code = isset($_GET['code']) ? sanitizeInput($_GET['code']) : '';

if (empty($doc_code)) {
    redirectWithMessage('pending.php', 'error', 'Kode dokumen tidak valid');
}

// Get document data
$document = getDocumentByCode($doc_code);

if (!$document) {
    redirectWithMessage('pending.php', 'error', 'Dokumen tidak ditemukan');
}

// Validasi status dokumen harus pending
if ($document['status'] !== 'pending') {
    redirectWithMessage(
        '../documents/detail.php?code=' . $doc_code,
        'error',
        'Dokumen ini tidak dalam status pending review'
    );
}

// Get current version info
$query_version = "
    SELECT dv.*, u.full_name as uploaded_by_name
    FROM document_versions dv
    JOIN users u ON dv.uploaded_by = u.user_id
    WHERE dv.version_id = ?
";

$stmt = $conn->prepare($query_version);
$stmt->bind_param("i", $document['current_version_id']);
$stmt->execute();
$current_version = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Dokumen - <?php echo e($document['title']); ?></title>
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
            max-width: 1000px;
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
            margin-bottom: 15px;
        }

        .card h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .doc-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .doc-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .doc-info-item {
            display: flex;
            flex-direction: column;
        }

        .doc-info-label {
            font-size: 12px;
            color: #999;
            margin-bottom: 5px;
        }

        .doc-info-value {
            font-size: 14px;
            color: #333;
            font-weight: 600;
        }

        .download-section {
            background: #e8f4ff;
            border: 2px solid #667eea;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
        }

        .download-section p {
            margin-bottom: 15px;
            color: #333;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 15px;
        }

        .radio-group {
            display: flex;
            gap: 30px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .radio-option input[type="radio"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .radio-option label {
            margin: 0;
            cursor: pointer;
            font-weight: normal;
            font-size: 15px;
        }

        .radio-option.approve {
            color: #27ae60;
        }

        .radio-option.reject {
            color: #e74c3c;
        }

        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
        }

        textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .revision-section {
            display: none;
            border: 2px solid #f39c12;
            padding: 20px;
            border-radius: 8px;
            background: #fffbf0;
            margin-top: 20px;
        }

        .revision-section.active {
            display: block;
        }

        .revision-items {
            margin-top: 15px;
        }

        .revision-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 3px solid #e74c3c;
        }

        .revision-item-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .revision-item input,
        .revision-item select,
        .revision-item textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
            font-size: 13px;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #27ae60;
        }

        .btn-success:hover {
            background: #229954;
        }

        .btn-danger {
            background: #e74c3c;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 13px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
        }

        .help-text {
            font-size: 13px;
            color: #999;
            margin-top: 5px;
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
    </style>
</head>
<body>
    <div class="navbar">
        <h1>✅ Review Dokumen</h1>
        <div class="nav-links">
            <a href="pending.php">Pending Review</a>
            <a href="history.php">Riwayat Review</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="breadcrumb">
            <a href="../dashboard/index.php">Dashboard</a>
            <span>›</span>
            <a href="pending.php">Pending Review</a>
            <span>›</span>
            <strong>Review: <?php echo e($doc_code); ?></strong>
        </div>

        <!-- Document Info -->
        <div class="card">
            <h2><?php echo e($document['title']); ?></h2>
            
            <div class="doc-info">
                <div class="doc-info-grid">
                    <div class="doc-info-item">
                        <div class="doc-info-label">Kode Dokumen</div>
                        <div class="doc-info-value"><?php echo e($doc_code); ?></div>
                    </div>
                    <div class="doc-info-item">
                        <div class="doc-info-label">Kategori</div>
                        <div class="doc-info-value">
                            <?php echo DOC_CATEGORIES[$document['category']] ?? $document['category']; ?>
                        </div>
                    </div>
                    <div class="doc-info-item">
                        <div class="doc-info-label">Pengunggah</div>
                        <div class="doc-info-value"><?php echo e($document['uploader_name']); ?></div>
                    </div>
                    <div class="doc-info-item">
                        <div class="doc-info-label">Versi</div>
                        <div class="doc-info-value">
                            v<?php echo number_format($current_version['version_number'], 1); ?>
                        </div>
                    </div>
                    <div class="doc-info-item">
                        <div class="doc-info-label">Tanggal Upload</div>
                        <div class="doc-info-value">
                            <?php echo formatTanggalLengkap($current_version['upload_date']); ?>
                        </div>
                    </div>
                    <div class="doc-info-item">
                        <div class="doc-info-label">Ukuran File</div>
                        <div class="doc-info-value">
                            <?php echo formatFileSize($current_version['file_size']); ?>
                        </div>
                    </div>
                </div>

                <?php if ($document['description']): ?>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0;">
                    <div class="doc-info-label">Deskripsi</div>
                    <p style="color: #666; font-size: 14px; margin-top: 5px;">
                        <?php echo nl2br(e($document['description'])); ?>
                    </p>
                </div>
                <?php endif; ?>

                <?php if ($current_version['notes']): ?>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0;">
                    <div class="doc-info-label">Catatan dari Pengunggah</div>
                    <p style="color: #666; font-size: 14px; margin-top: 5px;">
                        <?php echo nl2br(e($current_version['notes'])); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <div class="download-section">
                <p><strong>📄 Silakan download dan periksa dokumen terlebih dahulu</strong></p>
                <a href="../documents/download.php?vid=<?php echo $current_version['version_id']; ?>" 
                   class="btn" 
                   target="_blank">
                    📥 Download Dokumen (<?php echo e($current_version['file_name']); ?>)
                </a>
            </div>
        </div>

        <!-- Review Form -->
        <div class="card">
            <h3>Form Pemeriksaan Dokumen</h3>

            <form action="process_review.php" method="POST" id="reviewForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="doc_code" value="<?php echo e($doc_code); ?>">
                <input type="hidden" name="version_id" value="<?php echo $current_version['version_id']; ?>">

                <!-- Keputusan Review -->
                <div class="form-group">
                    <label>Keputusan Review *</label>
                    <div class="radio-group">
                        <div class="radio-option approve">
                            <input type="radio" 
                                   id="approve" 
                                   name="review_status" 
                                   value="approved" 
                                   required>
                            <label for="approve">✅ Setujui Dokumen</label>
                        </div>
                        <div class="radio-option reject">
                            <input type="radio" 
                                   id="reject" 
                                   name="review_status" 
                                   value="needs_revision" 
                                   required>
                            <label for="reject">❌ Perlu Revisi</label>
                        </div>
                    </div>
                </div>

                <!-- Catatan Umum -->
                <div class="form-group">
                    <label for="notes">Catatan Umum</label>
                    <textarea 
                        id="notes" 
                        name="notes" 
                        placeholder="Berikan catatan atau komentar mengenai dokumen ini (opsional)"
                    ></textarea>
                    <div class="help-text">
                        Catatan ini akan dilihat oleh pengunggah dokumen
                    </div>
                </div>

                <!-- Revision Items (hanya muncul jika perlu revisi) -->
                <div class="revision-section" id="revisionSection">
                    <h4 style="margin-bottom: 15px; color: #856404;">
                        📝 Detail Koreksi yang Diperlukan
                    </h4>
                    <p style="margin-bottom: 15px; color: #856404; font-size: 14px;">
                        Tambahkan item-item yang perlu diperbaiki oleh pengunggah
                    </p>

                    <div id="revisionItems">
                        <!-- Item pertama -->
                        <div class="revision-item" data-item="1">
                            <div class="revision-item-header">
                                <strong>Item Koreksi #1</strong>
                            </div>
                            
                            <label style="font-size: 13px; margin-bottom: 5px;">Bagian Dokumen</label>
                            <input type="text" 
                                   name="revision_section[]" 
                                   placeholder="Contoh: Bab 2, Halaman 5, Tabel 3.1">

                            <label style="font-size: 13px; margin-bottom: 5px;">Jenis Masalah</label>
                            <select name="revision_type[]">
                                <?php foreach (ISSUE_TYPES as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>

                            <label style="font-size: 13px; margin-bottom: 5px;">Prioritas</label>
                            <select name="revision_priority[]">
                                <option value="medium">Sedang</option>
                                <option value="high">Tinggi</option>
                                <option value="low">Rendah</option>
                            </select>

                            <label style="font-size: 13px; margin-bottom: 5px;">Deskripsi Masalah *</label>
                            <textarea 
                                name="revision_description[]" 
                                placeholder="Jelaskan secara detail masalah yang ditemukan dan bagaimana seharusnya diperbaiki"
                                rows="3"
                            ></textarea>
                        </div>
                    </div>

                    <button type="button" class="btn btn-secondary btn-small" onclick="addRevisionItem()">
                        ➕ Tambah Item Koreksi
                    </button>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="pending.php" class="btn btn-secondary">
                        ← Batal
                    </a>
                    <button type="submit" class="btn btn-success" id="submitBtn">
                        💾 Simpan Review
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const approveRadio = document.getElementById('approve');
        const rejectRadio = document.getElementById('reject');
        const revisionSection = document.getElementById('revisionSection');
        const form = document.getElementById('reviewForm');
        let itemCount = 1;

        // Toggle revision section
        approveRadio.addEventListener('change', function() {
            if (this.checked) {
                revisionSection.classList.remove('active');
            }
        });

        rejectRadio.addEventListener('change', function() {
            if (this.checked) {
                revisionSection.classList.add('active');
            }
        });

        // Add revision item
        function addRevisionItem() {
            itemCount++;
            const itemsContainer = document.getElementById('revisionItems');
            
            const newItem = document.createElement('div');
            newItem.className = 'revision-item';
            newItem.setAttribute('data-item', itemCount);
            
            newItem.innerHTML = `
                <div class="revision-item-header">
                    <strong>Item Koreksi #${itemCount}</strong>
                    <button type="button" class="btn btn-danger btn-small" onclick="removeRevisionItem(this)">
                        🗑️ Hapus
                    </button>
                </div>
                
                <label style="font-size: 13px; margin-bottom: 5px;">Bagian Dokumen</label>
                <input type="text" 
                       name="revision_section[]" 
                       placeholder="Contoh: Bab 2, Halaman 5, Tabel 3.1">

                <label style="font-size: 13px; margin-bottom: 5px;">Jenis Masalah</label>
                <select name="revision_type[]">
                    <?php foreach (ISSUE_TYPES as $key => $value): ?>
                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                    <?php endforeach; ?>
                </select>

                <label style="font-size: 13px; margin-bottom: 5px;">Prioritas</label>
                <select name="revision_priority[]">
                    <option value="medium">Sedang</option>
                    <option value="high">Tinggi</option>
                    <option value="low">Rendah</option>
                </select>

                <label style="font-size: 13px; margin-bottom: 5px;">Deskripsi Masalah *</label>
                <textarea 
                    name="revision_description[]" 
                    placeholder="Jelaskan secara detail masalah yang ditemukan"
                    rows="3"
                ></textarea>
            `;
            
            itemsContainer.appendChild(newItem);
        }

        // Remove revision item
        function removeRevisionItem(button) {
            const item = button.closest('.revision-item');
            item.remove();
        }

        // Form validation
        form.addEventListener('submit', function(e) {
            const status = document.querySelector('input[name="review_status"]:checked');
            
            if (!status) {
                e.preventDefault();
                alert('Silakan pilih keputusan review!');
                return false;
            }

            if (status.value === 'needs_revision') {
                const descriptions = document.querySelectorAll('textarea[name="revision_description[]"]');
                let hasEmptyDesc = false;
                
                descriptions.forEach(desc => {
                    if (desc.value.trim() === '') {
                        hasEmptyDesc = true;
                    }
                });

                if (hasEmptyDesc) {
                    e.preventDefault();
                    alert('Deskripsi masalah harus diisi untuk semua item koreksi!');
                    return false;
                }
            }

            // Disable submit button
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Menyimpan...';
        });
    </script>
</body>
</html>