<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require login
requireLogin();

// Get user data
$user = getCurrentUser();
$user_id = $user['user_id'];
$role = $user['role'];

// ================================================
// STATISTIK BERDASARKAN ROLE
// ================================================

if ($role === 'uploader') {
    // STATISTIK UPLOADER
    
    // Total dokumen per status
    $query_status = "
        SELECT 
            status,
            COUNT(*) as jumlah
        FROM documents 
        WHERE uploader_id = ?
        GROUP BY status
    ";
    $stmt = $conn->prepare($query_status);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $status_stats = $stmt->get_result();
    
    // Dokumen per kategori
    $query_category = "
        SELECT 
            category,
            COUNT(*) as jumlah
        FROM documents 
        WHERE uploader_id = ?
        GROUP BY category
        ORDER BY jumlah DESC
    ";
    $stmt = $conn->prepare($query_category);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $category_stats = $stmt->get_result();
    
    // Dokumen per bulan (6 bulan terakhir)
    $query_monthly = "
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as bulan,
            COUNT(*) as jumlah
        FROM documents 
        WHERE uploader_id = ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY bulan
        ORDER BY bulan ASC
    ";
    $stmt = $conn->prepare($query_monthly);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $monthly_stats = $stmt->get_result();
    
    // Rata-rata waktu review
    $query_avg_review = "
        SELECT 
            AVG(TIMESTAMPDIFF(HOUR, dv.upload_date, r.review_date)) as avg_hours
        FROM documents d
        JOIN document_versions dv ON d.current_version_id = dv.version_id
        JOIN reviews r ON dv.version_id = r.version_id
        WHERE d.uploader_id = ?
        AND r.review_date IS NOT NULL
    ";
    $stmt = $conn->prepare($query_avg_review);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $avg_review = $stmt->get_result()->fetch_assoc();
    
    // Dokumen dengan revisi terbanyak
    $query_most_revised = "
        SELECT 
            d.doc_code,
            d.title,
            COUNT(dv.version_id) as total_versions
        FROM documents d
        JOIN document_versions dv ON d.document_id = dv.document_id
        WHERE d.uploader_id = ?
        GROUP BY d.document_id
        HAVING total_versions > 1
        ORDER BY total_versions DESC
        LIMIT 5
    ";
    $stmt = $conn->prepare($query_most_revised);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $most_revised = $stmt->get_result();
    
} elseif ($role === 'reviewer') {
    // STATISTIK REVIEWER
    
    // Total review per status
    $query_review_status = "
        SELECT 
            r.status,
            COUNT(*) as jumlah
        FROM reviews r
        WHERE r.reviewer_id = ?
        GROUP BY r.status
    ";
    $stmt = $conn->prepare($query_review_status);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $review_status_stats = $stmt->get_result();
    
    // Review per bulan
    $query_monthly_review = "
        SELECT 
            DATE_FORMAT(review_date, '%Y-%m') as bulan,
            COUNT(*) as jumlah
        FROM reviews
        WHERE reviewer_id = ?
        AND review_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY bulan
        ORDER BY bulan ASC
    ";
    $stmt = $conn->prepare($query_monthly_review);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $monthly_review_stats = $stmt->get_result();
    
    // Dokumen pending saat ini
    $query_pending = "
        SELECT COUNT(*) as total
        FROM documents
        WHERE status = 'pending'
    ";
    $pending_count = $conn->query($query_pending)->fetch_assoc();
    
    // Rata-rata waktu review
    $query_avg_review_time = "
        SELECT 
            AVG(TIMESTAMPDIFF(HOUR, dv.upload_date, r.review_date)) as avg_hours
        FROM reviews r
        JOIN document_versions dv ON r.version_id = dv.version_id
        WHERE r.reviewer_id = ?
        AND r.review_date IS NOT NULL
    ";
    $stmt = $conn->prepare($query_avg_review_time);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $avg_review_time = $stmt->get_result()->fetch_assoc();
    
    // Top issue types
    $query_issues = "
        SELECT 
            ri.issue_type,
            COUNT(*) as jumlah
        FROM review_items ri
        JOIN reviews r ON ri.review_id = r.review_id
        WHERE r.reviewer_id = ?
        GROUP BY ri.issue_type
        ORDER BY jumlah DESC
    ";
    $stmt = $conn->prepare($query_issues);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $issue_stats = $stmt->get_result();
    
} else {
    // STATISTIK ADMIN
    
    // Overview statistik
    $query_overview = "
        SELECT 
            (SELECT COUNT(*) FROM documents) as total_docs,
            (SELECT COUNT(*) FROM users WHERE is_active = 1) as total_users,
            (SELECT COUNT(*) FROM documents WHERE status = 'pending') as pending_docs,
            (SELECT COUNT(*) FROM documents WHERE status = 'approved') as approved_docs,
            (SELECT COUNT(*) FROM document_versions) as total_versions,
            (SELECT COUNT(*) FROM reviews) as total_reviews
    ";
    $overview = $conn->query($query_overview)->fetch_assoc();
    
    // Dokumen per status
    $query_status_all = "
        SELECT 
            status,
            COUNT(*) as jumlah
        FROM documents
        GROUP BY status
    ";
    $status_all_stats = $conn->query($query_status_all);
    
    // User paling aktif (uploader)
    $query_active_uploaders = "
        SELECT 
            u.full_name,
            COUNT(d.document_id) as total_docs
        FROM users u
        LEFT JOIN documents d ON u.user_id = d.uploader_id
        WHERE u.role = 'uploader'
        GROUP BY u.user_id
        ORDER BY total_docs DESC
        LIMIT 5
    ";
    $active_uploaders = $conn->query($query_active_uploaders);
    
    // User paling aktif (reviewer)
    $query_active_reviewers = "
        SELECT 
            u.full_name,
            COUNT(r.review_id) as total_reviews
        FROM users u
        LEFT JOIN reviews r ON u.user_id = r.reviewer_id
        WHERE u.role = 'reviewer'
        GROUP BY u.user_id
        ORDER BY total_reviews DESC
        LIMIT 5
    ";
    $active_reviewers = $conn->query($query_active_reviewers);
    
    // Dokumen per bulan (sistem keseluruhan)
    $query_monthly_system = "
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as bulan,
            COUNT(*) as jumlah
        FROM documents
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY bulan
        ORDER BY bulan ASC
    ";
    $monthly_system_stats = $conn->query($query_monthly_system);
    
    // Kategori dokumen terpopuler
    $query_popular_categories = "
        SELECT 
            category,
            COUNT(*) as jumlah
        FROM documents
        GROUP BY category
        ORDER BY jumlah DESC
        LIMIT 5
    ";
    $popular_categories = $conn->query($query_popular_categories);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistik - <?php echo APP_NAME; ?></title>
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

        .navbar .nav-links a:hover,
        .navbar .nav-links a.active {
            background: rgba(255,255,255,0.2);
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .page-header h2 {
            color: #333;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #666;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

        .stat-card.success { border-left-color: #27ae60; }
        .stat-card.success h3 { color: #27ae60; }
        
        .stat-card.warning { border-left-color: #f39c12; }
        .stat-card.warning h3 { color: #f39c12; }
        
        .stat-card.danger { border-left-color: #e74c3c; }
        .stat-card.danger h3 { color: #e74c3c; }

        .chart-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .chart-section h3 {
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
            padding: 12px;
            text-align: left;
            font-size: 13px;
            color: #666;
            border-bottom: 2px solid #e0e0e0;
        }

        table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }

        .progress-fill {
            height: 100%;
            background: #667eea;
            transition: width 0.3s;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-needs_revision { background: #f8d7da; color: #721c24; }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
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
        <h1>📊 Statistik Sistem</h1>
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="statistik.php" class="active">Statistik</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>📈 Statistik & Laporan</h2>
            <p>Data statistik untuk <strong><?php echo e($user['full_name']); ?></strong> - <?php echo USER_ROLES[$role]; ?></p>
        </div>

        <?php if ($role === 'uploader'): ?>
            <!-- STATISTIK UPLOADER -->
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php 
                        $total = 0;
                        mysqli_data_seek($status_stats, 0);
                        while($s = $status_stats->fetch_assoc()) {
                            $total += $s['jumlah'];
                        }
                        echo $total;
                    ?></h3>
                    <p>Total Dokumen Saya</p>
                </div>
                <div class="stat-card warning">
                    <h3><?php echo round($avg_review['avg_hours'] ?? 0, 1); ?> jam</h3>
                    <p>Rata-rata Waktu Review</p>
                </div>
                <div class="stat-card success">
                    <h3><?php echo $category_stats->num_rows; ?></h3>
                    <p>Kategori Dokumen</p>
                </div>
            </div>

            <div class="grid-2">
                <div class="chart-section">
                    <h3>📊 Dokumen Per Status</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Jumlah</th>
                                <th>Persentase</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($status_stats, 0);
                            if ($status_stats->num_rows > 0):
                                while($stat = $status_stats->fetch_assoc()): 
                                    $percentage = $total > 0 ? ($stat['jumlah'] / $total * 100) : 0;
                            ?>
                            <tr>
                                <td><strong><?php echo DOC_STATUS[$stat['status']]; ?></strong></td>
                                <td><?php echo $stat['jumlah']; ?></td>
                                <td>
                                    <?php echo round($percentage, 1); ?>%
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="3" class="empty-state">Belum ada data</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="chart-section">
                    <h3>📁 Dokumen Per Kategori</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($category_stats->num_rows > 0):
                                while($cat = $category_stats->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo DOC_CATEGORIES[$cat['category']] ?? $cat['category']; ?></td>
                                <td><strong><?php echo $cat['jumlah']; ?></strong></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="2" class="empty-state">Belum ada data</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="chart-section">
                <h3>🔄 Dokumen dengan Revisi Terbanyak</h3>
                <?php if ($most_revised->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Kode Dokumen</th>
                            <th>Judul</th>
                            <th>Total Versi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($doc = $most_revised->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo e($doc['doc_code']); ?></strong></td>
                            <td><?php echo e($doc['title']); ?></td>
                            <td><?php echo $doc['total_versions']; ?> versi</td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <p>Tidak ada dokumen dengan revisi</p>
                </div>
                <?php endif; ?>
            </div>

        <?php elseif ($role === 'reviewer'): ?>
            <!-- STATISTIK REVIEWER -->
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php 
                        $total_reviews = 0;
                        mysqli_data_seek($review_status_stats, 0);
                        while($s = $review_status_stats->fetch_assoc()) {
                            $total_reviews += $s['jumlah'];
                        }
                        echo $total_reviews;
                    ?></h3>
                    <p>Total Review Saya</p>
                </div>
                <div class="stat-card warning">
                    <h3><?php echo $pending_count['total']; ?></h3>
                    <p>Dokumen Pending Saat Ini</p>
                </div>
                <div class="stat-card success">
                    <h3><?php echo round($avg_review_time['avg_hours'] ?? 0, 1); ?> jam</h3>
                    <p>Rata-rata Waktu Review</p>
                </div>
            </div>

            <div class="grid-2">
                <div class="chart-section">
                    <h3>✅ Review Per Status</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($review_status_stats, 0);
                            if ($review_status_stats->num_rows > 0):
                                while($stat = $review_status_stats->fetch_assoc()): 
                            ?>
                            <tr>
                                <td>
                                    <span class="badge badge-<?php echo $stat['status']; ?>">
                                        <?php 
                                        $status_names = [
                                            'pending' => 'Menunggu',
                                            'approved' => 'Disetujui',
                                            'needs_revision' => 'Perlu Revisi'
                                        ];
                                        echo $status_names[$stat['status']] ?? $stat['status']; 
                                        ?>
                                    </span>
                                </td>
                                <td><strong><?php echo $stat['jumlah']; ?></strong></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="2" class="empty-state">Belum ada data</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="chart-section">
                    <h3>⚠️ Jenis Issue Terbanyak</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Jenis Issue</th>
                                <th>Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($issue_stats->num_rows > 0):
                                while($issue = $issue_stats->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo ISSUE_TYPES[$issue['issue_type']] ?? $issue['issue_type']; ?></td>
                                <td><strong><?php echo $issue['jumlah']; ?></strong></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="2" class="empty-state">Belum ada data</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <!-- STATISTIK ADMIN -->
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo $overview['total_docs']; ?></h3>
                    <p>Total Dokumen</p>
                </div>
                <div class="stat-card success">
                    <h3><?php echo $overview['total_users']; ?></h3>
                    <p>Total User Aktif</p>
                </div>
                <div class="stat-card warning">
                    <h3><?php echo $overview['pending_docs']; ?></h3>
                    <p>Dokumen Pending</p>
                </div>
                <div class="stat-card success">
                    <h3><?php echo $overview['total_reviews']; ?></h3>
                    <p>Total Review</p>
                </div>
            </div>

            <div class="grid-2">
                <div class="chart-section">
                    <h3>👥 Uploader Paling Aktif</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Total Dokumen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($active_uploaders->num_rows > 0):
                                while($uploader = $active_uploaders->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo e($uploader['full_name']); ?></td>
                                <td><strong><?php echo $uploader['total_docs']; ?></strong></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="2" class="empty-state">Belum ada data</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="chart-section">
                    <h3>👨‍⚖️ Reviewer Paling Aktif</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Total Review</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($active_reviewers->num_rows > 0):
                                while($reviewer = $active_reviewers->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo e($reviewer['full_name']); ?></td>
                                <td><strong><?php echo $reviewer['total_reviews']; ?></strong></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="2" class="empty-state">Belum ada data</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="chart-section">
                <h3>📁 Kategori Dokumen Terpopuler</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th>Jumlah Dokumen</th>
                            <th>Persentase</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($popular_categories->num_rows > 0):
                            $total_docs = $overview['total_docs'];
                            while($cat = $popular_categories->fetch_assoc()): 
                                $percentage = $total_docs > 0 ? ($cat['jumlah'] / $total_docs * 100) : 0;
                        ?>
                        <tr>
                            <td><?php echo DOC_CATEGORIES[$cat['category']] ?? $cat['category']; ?></td>
                            <td><strong><?php echo $cat['jumlah']; ?></strong></td>
                            <td>
                                <?php echo round($percentage, 1); ?>%
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="3" class="empty-state">Belum ada data</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>