<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Hanya admin
requireRole('admin');

$user = getCurrentUser();

// ================================================
// STATISTIK SISTEM
// ================================================

// Overview
$overview_query = "
    SELECT 
        (SELECT COUNT(*) FROM documents) as total_docs,
        (SELECT COUNT(*) FROM users WHERE is_active = 1) as total_users,
        (SELECT COUNT(*) FROM document_versions) as total_versions,
        (SELECT COUNT(*) FROM reviews) as total_reviews,
        (SELECT COUNT(*) FROM documents WHERE status = 'approved') as approved_docs,
        (SELECT COUNT(*) FROM documents WHERE status = 'pending') as pending_docs,
        (SELECT COUNT(*) FROM documents WHERE status = 'revision') as revision_docs
";
$overview = $conn->query($overview_query)->fetch_assoc();

// Dokumen per kategori
$category_query = "
    SELECT 
        category,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
    FROM documents
    GROUP BY category
    ORDER BY total DESC
";
$category_stats = $conn->query($category_query);

// User paling produktif (uploader)
$productive_uploader_query = "
    SELECT 
        u.username,
        u.full_name,
        COUNT(d.document_id) as total_docs,
        SUM(CASE WHEN d.status = 'approved' THEN 1 ELSE 0 END) as approved_docs
    FROM users u
    LEFT JOIN documents d ON u.user_id = d.uploader_id
    WHERE u.role = 'uploader' AND u.is_active = 1
    GROUP BY u.user_id
    ORDER BY total_docs DESC
    LIMIT 10
";
$productive_uploaders = $conn->query($productive_uploader_query);

// Reviewer paling aktif
$active_reviewer_query = "
    SELECT 
        u.username,
        u.full_name,
        COUNT(r.review_id) as total_reviews,
        SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN r.status = 'needs_revision' THEN 1 ELSE 0 END) as rejected
    FROM users u
    LEFT JOIN reviews r ON u.user_id = r.reviewer_id
    WHERE u.role = 'reviewer' AND u.is_active = 1
    GROUP BY u.user_id
    ORDER BY total_reviews DESC
    LIMIT 10
";
$active_reviewers = $conn->query($active_reviewer_query);

// Dokumen dengan revisi terbanyak
$most_revised_query = "
    SELECT 
        d.doc_code,
        d.title,
        u.full_name as uploader,
        COUNT(dv.version_id) as total_versions,
        d.status
    FROM documents d
    JOIN users u ON d.uploader_id = u.user_id
    JOIN document_versions dv ON d.document_id = dv.document_id
    GROUP BY d.document_id
    HAVING total_versions > 1
    ORDER BY total_versions DESC
    LIMIT 10
";
$most_revised = $conn->query($most_revised_query);

// Aktivitas terakhir
$recent_activity_query = "
    SELECT 
        al.action,
        al.details,
        al.created_at,
        u.full_name,
        d.doc_code
    FROM activity_logs al
    JOIN users u ON al.user_id = u.user_id
    LEFT JOIN documents d ON al.document_id = d.document_id
    ORDER BY al.created_at DESC
    LIMIT 20
";
$recent_activities = $conn->query($recent_activity_query);

// Dokumen per bulan (6 bulan terakhir)
$monthly_docs_query = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as total
    FROM documents
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month ASC
";
$monthly_docs = $conn->query($monthly_docs_query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Sistem - <?php echo APP_NAME; ?></title>
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
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .page-header h2 {
            color: #333;
            margin-bottom: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #667eea;
        }

        .stat-card h3 {
            font-size: 36px;
            color: #667eea;
            margin-bottom: 10px;
        }

        .stat-card p {
            color: #666;
            font-size: 14px;
        }

        .stat-card.success {
            border-left-color: #27ae60;
        }

        .stat-card.success h3 {
            color: #27ae60;
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

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .card h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            background: #f8f9fa;
            padding: 10px;
            text-align: left;
            font-size: 12px;
            color: #666;
            border-bottom: 2px solid #e0e0e0;
        }

        table td {
            padding: 10px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-approved {
            background: #d4edda;
            color: #155724;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-revision {
            background: #f8d7da;
            color: #721c24;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: #f0f0f0;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 5px;
        }

        .progress-fill {
            height: 100%;
            background: #667eea;
        }

        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-time {
            font-size: 11px;
            color: #999;
        }

        .activity-action {
            font-weight: 600;
            color: #333;
            font-size: 13px;
        }

        .activity-details {
            font-size: 12px;
            color: #666;
            margin-top: 3px;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📊 Laporan & Statistik</h1>
        <div class="nav-links">
            <a href="../dashboard/index.php">Dashboard</a>
            <a href="users.php">Manajemen User</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>📊 Laporan & Analisis Sistem</h2>
            <p>Overview lengkap aktivitas dan performa sistem</p>
        </div>

        <!-- Overview Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $overview['total_docs']; ?></h3>
                <p>Total Dokumen</p>
            </div>
            <div class="stat-card success">
                <h3><?php echo $overview['approved_docs']; ?></h3>
                <p>Dokumen Disetujui</p>
            </div>
            <div class="stat-card warning">
                <h3><?php echo $overview['pending_docs']; ?></h3>
                <p>Pending Review</p>
            </div>
            <div class="stat-card danger">
                <h3><?php echo $overview['revision_docs']; ?></h3>
                <p>Perlu Revisi</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $overview['total_versions']; ?></h3>
                <p>Total Versi</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $overview['total_reviews']; ?></h3>
                <p>Total Review</p>
            </div>
            <div class="stat-card success">
                <h3><?php echo $overview['total_users']; ?></h3>
                <p>User Aktif</p>
            </div>
        </div>

        <!-- Dokumen per Kategori -->
        <div class="card">
            <h3>📁 Dokumen per Kategori</h3>
            <table>
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th>Total Dokumen</th>
                        <th>Disetujui</th>
                        <th>Persentase Approval</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($category_stats->num_rows > 0):
                        while ($cat = $category_stats->fetch_assoc()): 
                            $approval_rate = $cat['total'] > 0 ? ($cat['approved'] / $cat['total'] * 100) : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo DOC_CATEGORIES[$cat['category']] ?? $cat['category']; ?></strong></td>
                        <td><?php echo $cat['total']; ?></td>
                        <td><?php echo $cat['approved']; ?></td>
                        <td>
                            <?php echo round($approval_rate, 1); ?>%
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $approval_rate; ?>%"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="4" class="empty-state">Belum ada data</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Grid 2 Columns -->
        <div class="grid-2">
            <!-- Uploader Produktif -->
            <div class="card">
                <h3>🏆 Uploader Paling Produktif</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Total Docs</th>
                            <th>Approved</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($productive_uploaders->num_rows > 0):
                            while ($uploader = $productive_uploaders->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo e($uploader['full_name']); ?></td>
                            <td><strong><?php echo $uploader['total_docs']; ?></strong></td>
                            <td><?php echo $uploader['approved_docs']; ?></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="3" class="empty-state">Belum ada data</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Reviewer Aktif -->
            <div class="card">
                <h3>⭐ Reviewer Paling Aktif</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Total Review</th>
                            <th>Approved</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($active_reviewers->num_rows > 0):
                            while ($reviewer = $active_reviewers->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo e($reviewer['full_name']); ?></td>
                            <td><strong><?php echo $reviewer['total_reviews']; ?></strong></td>
                            <td><?php echo $reviewer['approved']; ?></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="3" class="empty-state">Belum ada data</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Dokumen dengan Revisi Terbanyak -->
        <div class="card">
            <h3>🔄 Dokumen dengan Revisi Terbanyak</h3>
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Judul</th>
                        <th>Uploader</th>
                        <th>Total Versi</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($most_revised->num_rows > 0):
                        while ($doc = $most_revised->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo e($doc['doc_code']); ?></strong></td>
                        <td><?php echo e(truncate($doc['title'], 50)); ?></td>
                        <td><?php echo e($doc['uploader']); ?></td>
                        <td><?php echo $doc['total_versions']; ?> versi</td>
                        <td>
                            <span class="badge badge-<?php echo $doc['status']; ?>">
                                <?php echo DOC_STATUS[$doc['status']]; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="5" class="empty-state">Tidak ada dokumen dengan revisi</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Aktivitas Terakhir -->
        <div class="card">
            <h3>📜 Aktivitas Terakhir</h3>
            <?php if ($recent_activities->num_rows > 0): ?>
                <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                <div class="activity-item">
                    <div class="activity-time"><?php echo timeAgo($activity['created_at']); ?></div>
                    <div class="activity-action">
                        <?php echo e($activity['full_name']); ?> - <?php echo e($activity['action']); ?>
                    </div>
                    <?php if ($activity['details']): ?>
                    <div class="activity-details"><?php echo e($activity['details']); ?></div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">Belum ada aktivitas</div>
            <?php endif; ?>
        </div>
    </div>
    <script src="../../assets/js/script.js"></script>

</body>
</html>