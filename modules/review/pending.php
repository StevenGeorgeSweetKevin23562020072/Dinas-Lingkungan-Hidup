<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Hanya reviewer dan admin yang bisa akses
requireAnyRole(['reviewer', 'admin']);

$user = getCurrentUser();

// Get pending documents
$query = "
    SELECT 
        d.document_id,
        d.doc_code,
        d.title,
        d.category,
        d.status,
        d.created_at,
        d.updated_at,
        u.full_name as uploader_name,
        dv.version_number,
        dv.version_id,
        dv.file_name,
        dv.file_size,
        dv.upload_date
    FROM documents d
    JOIN users u ON d.uploader_id = u.user_id
    JOIN document_versions dv ON d.current_version_id = dv.version_id
    WHERE d.status = 'pending'
    ORDER BY dv.upload_date ASC
";

$pending_docs = $conn->query($query);

// Statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_pending,
        COUNT(CASE WHEN DATE(d.updated_at) = CURDATE() THEN 1 END) as pending_today,
        COUNT(CASE WHEN DATEDIFF(NOW(), d.updated_at) > 3 THEN 1 END) as pending_long
    FROM documents d
    WHERE d.status = 'pending'
";

$stats = $conn->query($stats_query)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokumen Pending Review - <?php echo APP_NAME; ?></title>
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
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
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
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .page-header h2 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 26px;
        }
        
        .page-header p {
            color: #7f8c8d;
            font-size: 15px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 28px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border-left: 5px solid #27ae60;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(39, 174, 96, 0.15);
        }
        
        .stat-card h3 {
            font-size: 42px;
            color: #27ae60;
            margin-bottom: 12px;
            font-weight: 700;
        }
        
        .stat-card p {
            color: #7f8c8d;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .stat-card.warning {
            border-left-color: #f39c12;
        }
        
        .stat-card.warning h3 {
            color: #f39c12;
        }
        
        .stat-card.danger {
            border-left-color: #e74c3c;
        }
        
        .stat-card.danger h3 {
            color: #e74c3c;
        }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .card h3 {
            color: #2c3e50;
            margin-bottom: 25px;
            font-size: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e8f5e9;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e8f5e9 100%);
            padding: 14px;
            text-align: left;
            font-size: 13px;
            color: #27ae60;
            border-bottom: 2px solid #27ae60;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        table td {
            padding: 14px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        
        table tr:hover {
            background: linear-gradient(135deg, #f8fffe 0%, #f1f8f4 100%);
        }
        
        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-new {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
        }
        
        .badge-urgent {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #229954 0%, #27ae60 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .doc-actions {
            display: flex;
            gap: 8px;
        }
        
        .waiting-time {
            font-size: 12px;
            color: #95a5a6;
        }
        
        .waiting-time.urgent {
            color: #e74c3c;
            font-weight: 700;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #95a5a6;
        }
        
        .empty-state p {
            font-size: 18px;
            margin-bottom: 15px;
        }
        
        .empty-state .icon {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

    </style>
</head>
<body>
    <div class="navbar">
        <h1>⏳ Dokumen Pending Review</h1>
        <div class="nav-links">
            <a href="../dashboard/index.php">Dashboard</a>
            <a href="history.php">Riwayat Review</a>
            <a href="../documents/list.php">Semua Dokumen</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>📋 Dokumen Menunggu Pemeriksaan</h2>
            <p>Daftar dokumen yang perlu direview dan disetujui</p>
        </div>

        <?php displayFlashMessage(); ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $stats['total_pending']; ?></h3>
                <p>Total Pending</p>
            </div>
            <div class="stat-card warning">
                <h3><?php echo $stats['pending_today']; ?></h3>
                <p>Masuk Hari Ini</p>
            </div>
            <div class="stat-card danger">
                <h3><?php echo $stats['pending_long']; ?></h3>
                <p>Menunggu > 3 Hari</p>
            </div>
        </div>

        <!-- Pending Documents List -->
        <div class="card">
            <h3>Daftar Dokumen</h3>

            <?php if ($pending_docs->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Kode Dokumen</th>
                        <th>Judul</th>
                        <th>Pengunggah</th>
                        <th>Kategori</th>
                        <th>Versi</th>
                        <th>Waktu Tunggu</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($doc = $pending_docs->fetch_assoc()): 
                        $days_waiting = floor((time() - strtotime($doc['upload_date'])) / 86400);
                        $is_urgent = $days_waiting > 3;
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo e($doc['doc_code']); ?></strong>
                            <?php if ($days_waiting == 0): ?>
                            <span class="badge badge-new">Baru</span>
                            <?php elseif ($is_urgent): ?>
                            <span class="badge badge-urgent">Mendesak</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e(truncate($doc['title'], 50)); ?></td>
                        <td><?php echo e($doc['uploader_name']); ?></td>
                        <td><?php echo DOC_CATEGORIES[$doc['category']] ?? $doc['category']; ?></td>
                        <td>v<?php echo number_format($doc['version_number'], 1); ?></td>
                        <td>
                            <span class="waiting-time <?php echo $is_urgent ? 'urgent' : ''; ?>">
                                <?php 
                                if ($days_waiting == 0) {
                                    echo 'Hari ini';
                                } elseif ($days_waiting == 1) {
                                    echo '1 hari';
                                } else {
                                    echo $days_waiting . ' hari';
                                }
                                ?>
                            </span>
                            <br>
                            <small style="color: #999;">
                                <?php echo formatTanggal($doc['upload_date'], 'd/m/Y H:i'); ?>
                            </small>
                        </td>
                        <td>
                            <div class="doc-actions">
                                <a href="../documents/detail.php?code=<?php echo $doc['doc_code']; ?>" 
                                   class="btn btn-sm" 
                                   title="Lihat Detail">
                                    👁️ Detail
                                </a>
                                <a href="review_form.php?code=<?php echo $doc['doc_code']; ?>" 
                                   class="btn btn-success btn-sm"
                                   title="Mulai Review">
                                    ✅ Review
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <p>🎉 Tidak ada dokumen yang menunggu review!</p>
                <p style="font-size: 14px; margin-top: 10px;">Semua dokumen sudah direview.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>