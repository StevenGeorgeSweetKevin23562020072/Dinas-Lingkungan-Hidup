<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Hanya uploader dan admin yang bisa upload
requireAnyRole(['uploader', 'admin']);

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Dokumen - <?php echo APP_NAME; ?></title>
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
        
        /* .navbar h1::before {
            content: '📤';
            font-size: 26px;
        } */
        
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
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .card h2 {
            color: #27ae60;
            margin-bottom: 10px;
            font-size: 26px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .card h2::before {
            content: '📄';
            font-size: 30px;
        }
        
        .card > p {
            color: #7f8c8d;
            margin-bottom: 30px;
            font-size: 15px;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            line-height: 1.6;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        
        .form-group {
            margin-bottom: 28px;
        }
        
        label {
            display: block;
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 15px;
        }
        
        .required {
            color: #e74c3c;
            font-weight: 700;
        }
        
        input[type="text"],
        select,
        textarea {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-family: inherit;
            background: #f8f9fa;
        }
        
        input[type="text"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #27ae60;
            box-shadow: 0 0 0 4px rgba(39, 174, 96, 0.1);
            background: white;
        }
        
        textarea {
            resize: vertical;
            min-height: 110px;
        }
        
        .help-text {
            font-size: 13px;
            color: #95a5a6;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .help-text::before {
            content: 'ℹ️';
            font-size: 14px;
        }
        
        .file-upload {
            border: 3px dashed #bdc3c7;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }
        
        .file-upload:hover {
            border-color: #27ae60;
            background: linear-gradient(135deg, #e8f5e9 0%, #f1f8f4 100%);
            transform: scale(1.01);
        }
        
        .file-upload.dragover {
            border-color: #27ae60;
            background: #e8f5e9;
            transform: scale(1.02);
        }
        
        .file-upload input[type="file"] {
            display: none;
        }
        
        .file-upload-label {
            cursor: pointer;
        }
        
        .file-upload-icon {
            font-size: 56px;
            margin-bottom: 18px;
            animation: bounce 2s ease-in-out infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .file-upload-text {
            color: #7f8c8d;
            font-size: 15px;
            line-height: 1.6;
        }
        
        .file-upload-text strong {
            color: #27ae60;
            font-size: 16px;
        }
        
        .file-info {
            background: linear-gradient(135deg, #e8f5e9 0%, #f1f8f4 100%);
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            display: none;
            border-left: 4px solid #27ae60;
        }
        
        .file-info.active {
            display: block;
            animation: slideDown 0.4s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                max-height: 0;
            }
            to {
                opacity: 1;
                max-height: 200px;
            }
        }
        
        .file-info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #d4edda;
        }
        
        .file-info-item:last-child {
            border-bottom: none;
        }
        
        .file-info-label {
            color: #27ae60;
            font-size: 14px;
            font-weight: 600;
        }
        
        .file-info-value {
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
        }
        
        .btn {
            padding: 14px 32px;
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #229954 0%, #27ae60 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(39, 174, 96, 0.4);
        }
        
        .btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 35px;
            padding-top: 25px;
            border-top: 2px solid #e8f5e9;
        }
        
        .form-actions .btn {
            flex: 1;
        }
        
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
            }
            
            .card {
                padding: 25px 20px;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📤 Upload Dokumen Baru</h1>
        <div class="nav-links">
            <a href="../dashboard/index.php">Dashboard</a>
            <a href="list.php">Daftar Dokumen</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h2>Upload Dokumen Baru</h2>
            <p>Lengkapi form di bawah ini untuk mengunggah dokumen laboratorium</p>

            <?php displayFlashMessage(); ?>

            <div class="alert alert-info">
                <strong>ℹ️ Informasi:</strong><br>
                • Hanya file PDF yang diperbolehkan<br>
                • Ukuran maksimal: <?php echo formatFileSize(MAX_FILE_SIZE); ?><br>
                • Dokumen akan direview oleh pemeriksa sebelum disetujui
            </div>

            <form action="process_upload.php" method="POST" enctype="multipart/form-data" id="uploadForm">
                <?php echo csrfField(); ?>

                <div class="form-group">
                    <label for="title">
                        Judul Dokumen <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="title" 
                        name="title" 
                        required
                        placeholder="Contoh: Laporan Analisis Kualitas Air Limbah Januari 2026"
                        maxlength="200"
                    >
                    <div class="help-text">Berikan judul yang jelas dan deskriptif</div>
                </div>

                <div class="form-group">
                    <label for="category">
                        Kategori Dokumen <span class="required">*</span>
                    </label>
                    <select id="category" name="category" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach (DOC_CATEGORIES as $key => $value): ?>
                        <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">
                        Deskripsi Dokumen
                    </label>
                    <textarea 
                        id="description" 
                        name="description" 
                        placeholder="Berikan deskripsi singkat tentang dokumen ini (opsional)"
                    ></textarea>
                    <div class="help-text">Jelaskan secara singkat isi atau tujuan dokumen</div>
                </div>

                <div class="form-group">
                    <label>
                        File Dokumen (PDF) <span class="required">*</span>
                    </label>
                    <div class="file-upload" id="fileUploadArea">
                        <input 
                            type="file" 
                            id="document_file" 
                            name="document_file" 
                            accept=".pdf,application/pdf"
                            required
                        >
                        <label for="document_file" class="file-upload-label">
                            <div class="file-upload-icon">📄</div>
                            <div class="file-upload-text">
                                <strong>Klik untuk memilih file</strong> atau drag & drop file PDF di sini<br>
                                <small>Maksimal <?php echo formatFileSize(MAX_FILE_SIZE); ?></small>
                            </div>
                        </label>
                    </div>

                    <div class="file-info" id="fileInfo">
                        <div class="file-info-item">
                            <span class="file-info-label">Nama File:</span>
                            <span class="file-info-value" id="fileName">-</span>
                        </div>
                        <div class="file-info-item">
                            <span class="file-info-label">Ukuran:</span>
                            <span class="file-info-value" id="fileSize">-</span>
                        </div>
                        <div class="file-info-item">
                            <span class="file-info-label">Tipe:</span>
                            <span class="file-info-value" id="fileType">-</span>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='../dashboard/index.php'">
                        ← Batal
                    </button>
                    <button type="submit" class="btn" id="submitBtn">
                        📤 Upload Dokumen
                    </button>
                </div>
            </form>
        </div>
    </div>
<script src="../../assets/js/script.js"></script>

    <script>
        const fileInput = document.getElementById('document_file');
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInfo = document.getElementById('fileInfo');
        const submitBtn = document.getElementById('submitBtn');
        const form = document.getElementById('uploadForm');
        const maxFileSize = <?php echo MAX_FILE_SIZE; ?>;

        // File input change
        fileInput.addEventListener('change', function(e) {
            handleFile(this.files[0]);
        });

        // Drag and drop
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        fileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });

        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFile(files[0]);
            }
        });

        function handleFile(file) {
            if (!file) return;

            // Validasi tipe file
            if (file.type !== 'application/pdf') {
                alert('Hanya file PDF yang diperbolehkan!');
                fileInput.value = '';
                fileInfo.classList.remove('active');
                return;
            }

            // Validasi ukuran file
            if (file.size > maxFileSize) {
                alert('Ukuran file terlalu besar! Maksimal ' + formatFileSize(maxFileSize));
                fileInput.value = '';
                fileInfo.classList.remove('active');
                return;
            }

            // Tampilkan info file
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileSize').textContent = formatFileSize(file.size);
            document.getElementById('fileType').textContent = file.type;
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

        // Form submission
        form.addEventListener('submit', function(e) {
            // Validasi form
            if (!fileInput.files || fileInput.files.length === 0) {
                e.preventDefault();
                alert('Silakan pilih file dokumen!');
                return false;
            }

            // Disable button untuk mencegah double submit
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Mengupload...';
        });

        // Character counter untuk title
        const titleInput = document.getElementById('title');
        titleInput.addEventListener('input', function() {
            const remaining = 200 - this.value.length;
            // Bisa ditambahkan counter display jika diperlukan
        });
    </script>
</body>
</html>