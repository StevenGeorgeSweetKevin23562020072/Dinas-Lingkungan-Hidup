<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireLogin();

$user = getCurrentUser();
$user_id = $user['user_id'];
$role = $user['role'];

// Get statistics berdasarkan role
if ($role === 'uploader') {
    $query = "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'revision' THEN 1 ELSE 0 END) as revision,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
              FROM documents
              WHERE uploader_id = ?";
   
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
   
    $query_docs = "SELECT d.doc_code, d.title, d.status, d.updated_at,
                          dv.version_number
                   FROM documents d
                   LEFT JOIN document_versions dv ON d.current_version_id = dv.version_id
                   WHERE d.uploader_id = ?
                   ORDER BY d.updated_at DESC
                   LIMIT 5";
   
    $stmt_docs = $conn->prepare($query_docs);
    $stmt_docs->bind_param("i", $user_id);
    $stmt_docs->execute();
    $recent_docs = $stmt_docs->get_result();
   
} elseif ($role === 'reviewer') {
    $query = "SELECT
                COUNT(DISTINCT d.document_id) as total,
                SUM(CASE WHEN d.status = 'pending' THEN 1 ELSE 0 END) as pending,
                COUNT(DISTINCT r.review_id) as reviewed_today
              FROM documents d
              LEFT JOIN document_versions dv ON d.current_version_id = dv.version_id
              LEFT JOIN reviews r ON dv.version_id = r.version_id
                  AND r.reviewer_id = ?
                  AND DATE(r.review_date) = CURDATE()";
   
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
   
    $query_docs = "SELECT d.doc_code, d.title, d.status, d.updated_at,
                          dv.version_number, u.full_name as uploader_name
                   FROM documents d
                   JOIN document_versions dv ON d.current_version_id = dv.version_id
                   JOIN users u ON d.uploader_id = u.user_id
                   WHERE d.status = 'pending'
                   ORDER BY d.updated_at ASC
                   LIMIT 5";
   
    $recent_docs = $conn->query($query_docs);
   
} else {
    $query = "SELECT
                COUNT(*) as total_docs,
                (SELECT COUNT(*) FROM users WHERE is_active = 1) as total_users,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
              FROM documents";
   
    $stats = $conn->query($query)->fetch_assoc();
   
    $query_logs = "SELECT al.action, al.details, al.created_at, u.full_name
                   FROM activity_logs al
                   JOIN users u ON al.user_id = u.user_id
                   ORDER BY al.created_at DESC
                   LIMIT 10";
   
    $recent_logs = $conn->query($query_logs);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
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
        
        /* Navbar */
        .navbar {
            background: linear-gradient(135deg, #16a085 0%, #27ae60 100%);
            color: white;
            padding: 20px 30px;
            box-shadow: 0 4px 20px rgba(22, 160, 133, 0.3);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .navbar-content {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .navbar h1 {
            font-size: 24px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .navbar h1::before {
            content: '🌿';
            font-size: 28px;
        }
        
        .user-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .user-info span {
            font-size: 14px;
            opacity: 0.95;
        }
        
        .user-info .badge {
            background: rgba(255,255,255,0.25);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            margin: 0 10px;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }
        
        .user-info a {
            color: white;
            text-decoration: none;
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }
        
        .user-info a:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        /* Navigation Menu */
        .nav-menu {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            position: sticky;
            top: 88px;
            z-index: 99;
        }
        
        .nav-menu ul {
            list-style: none;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .nav-menu li {
            margin: 0;
        }
        
        .nav-menu a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 16px 24px;
            color: #2c3e50;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }
        
        .nav-menu a:hover {
            background: #f8f9fa;
            border-bottom-color: #27ae60;
            color: #27ae60;
        }
        
        .nav-menu a.active {
            color: #27ae60;
            border-bottom-color: #27ae60;
            background: rgba(39, 174, 96, 0.05);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px 40px;
        }
        
        /* Welcome Card */
        .welcome {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            padding: 35px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(39, 174, 96, 0.25);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .welcome::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .welcome h2 {
            font-size: 28px;
            margin-bottom: 10px;
            position: relative;
        }
        
        .welcome p {
            font-size: 15px;
            opacity: 0.95;
            position: relative;
        }
        
        /* Stats Grid */
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
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, rgba(39, 174, 96, 0.05), transparent);
            border-radius: 0 15px 0 100%;
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
            letter-spacing: 0.5px;
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
        
        .stat-card.success {
            border-left-color: #27ae60;
        }
        
        .stat-card.success h3 {
            color: #27ae60;
        }
        
        /* Section Cards */
        .section {
            background: white;
            padding: 28px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            margin-bottom: 25px;
        }
        
        .section h2 {
            color: #2c3e50;
            margin-bottom: 24px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e8f5e9;
        }
        
        /* Tables */
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
            letter-spacing: 0.5px;
        }
        
        table td {
            padding: 14px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
            color: #2c3e50;
        }
        
        table tr:hover {
            background: #f8fffe;
        }
        
        table tr:last-child td {
            border-bottom: none;
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-revision {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-draft {
            background: #e2e3e5;
            color: #383d41;
        }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 10px 22px;
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
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
        
        .btn-sm {
            padding: 7px 16px;
            font-size: 13px;
        }
        
        /* Quick Actions */
        .quick-actions {
            margin-top: 30px;
            text-align: center;
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .quick-actions .btn {
            padding: 16px 32px;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px;
            color: #95a5a6;
        }
        
        .empty-state p {
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        /* Admin Quick Links */
        .admin-quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }
        
        .admin-link-card {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 35px 25px;
            border-radius: 15px;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s;
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.3);
        }
        
        .admin-link-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 35px rgba(39, 174, 96, 0.4);
        }
        
        .admin-link-card .icon {
            font-size: 52px;
            margin-bottom: 18px;
        }
        
        .admin-link-card h3 {
            font-size: 18px;
            margin-bottom: 8px;
        }
        
        .admin-link-card p {
            font-size: 13px;
            opacity: 0.9;
        }
        
        @media (max-width: 768px) {
            .navbar h1 {
                font-size: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .nav-menu a {
                padding: 12px 16px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-content">
            <h1>Sistem Manajemen Dokumen Laboratorium</h1>
            <div class="user-info">
                <div>
                    <span>Halo, <strong><?php echo e($user['full_name']); ?></strong></span>
                    <span class="badge"><?php echo e(USER_ROLES[$user['role']]); ?></span>
                </div>
                <a href="../auth/logout.php">🚪 Logout</a>
            </div>
        </div>
    </div>
    
    <div class="nav-menu">
        <ul>
            <li><a href="index.php" class="active">📊 Dashboard</a></li>
            
            <?php if ($role === 'uploader' || $role === 'admin'): ?>
            <li><a href="../documents/upload.php">📤 Upload Dokumen</a></li>
            <li><a href="../documents/list.php">📁 Daftar Dokumen</a></li>
            <?php endif; ?>
            
            <?php if ($role === 'reviewer' || $role === 'admin'): ?>
            <li><a href="../review/pending.php">⏳ Pending Review</a></li>
            <li><a href="../review/history.php">📜 Riwayat Review</a></li>
            <?php endif; ?>
            
            <?php if ($role === 'admin'): ?>
            <li><a href="../admin/users.php">👥 Manajemen User</a></li>
            <li><a href="../admin/reports.php">📊 Laporan Sistem</a></li>
            <?php endif; ?>
            
            <li><a href="statistik.php">📈 Statistik</a></li>
        </ul>
    </div>
    
    <div class="container">
        <div class="welcome">
            <h2>Selamat Datang! 👋</h2>
            <p>Anda login sebagai <strong><?php echo e(USER_ROLES[$role]); ?></strong>. Berikut ringkasan sistem Anda:</p>
        </div>
        
        <?php if ($role === 'uploader'): ?>
            <!-- Dashboard Uploader -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo $stats['total'] ?? 0; ?></h3>
                    <p>Total Dokumen</p>
                </div>
                <div class="stat-card warning">
                    <h3><?php echo $stats['pending'] ?? 0; ?></h3>
                    <p>Menunggu Pemeriksaan</p>
                </div>
                <div class="stat-card danger">
                    <h3><?php echo $stats['revision'] ?? 0; ?></h3>
                    <p>Perlu Revisi</p>
                </div>
                <div class="stat-card success">
                    <h3><?php echo $stats['approved'] ?? 0; ?></h3>
                    <p>Disetujui</p>
                </div>
            </div>
            
            <div class="section">
                <h2>📄 Dokumen Terbaru</h2>
                <?php if ($recent_docs && $recent_docs->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Kode Dokumen</th>
                                <th>Judul</th>
                                <th>Versi</th>
                                <th>Status</th>
                                <th>Terakhir Update</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($doc = $recent_docs->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo e($doc['doc_code']); ?></strong></td>
                                <td><?php echo e($doc['title']); ?></td>
                                <td>v<?php echo number_format($doc['version_number'], 1); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $doc['status']; ?>">
                                        <?php echo DOC_STATUS[$doc['status']]; ?>
                                    </span>
                                </td>
                                <td><?php echo formatTanggal($doc['updated_at']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <p>📭 Belum ada dokumen</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="quick-actions">
                <a href="../documents/upload.php" class="btn">📤 Upload Dokumen Baru</a>
                <a href="../documents/list.php" class="btn">📋 Lihat Semua Dokumen</a>
            </div>
            
        <?php elseif ($role === 'reviewer'): ?>
            <!-- Dashboard Reviewer -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo $stats['total'] ?? 0; ?></h3>
                    <p>Total Dokumen</p>
                </div>
                <div class="stat-card warning">
                    <h3><?php echo $stats['pending'] ?? 0; ?></h3>
                    <p>Menunggu Pemeriksaan</p>
                </div>
                <div class="stat-card success">
                    <h3><?php echo $stats['reviewed_today'] ?? 0; ?></h3>
                    <p>Direview Hari Ini</p>
                </div>
            </div>
            
            <div class="section">
                <h2>⏳ Dokumen Pending Review</h2>
                <?php if ($recent_docs && $recent_docs->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Judul</th>
                                <th>Pengunggah</th>
                                <th>Versi</th>
                                <th>Upload</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($doc = $recent_docs->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo e($doc['doc_code']); ?></strong></td>
                                <td><?php echo e($doc['title']); ?></td>
                                <td><?php echo e($doc['uploader_name']); ?></td>
                                <td>v<?php echo number_format($doc['version_number'], 1); ?></td>
                                <td><?php echo formatTanggal($doc['updated_at']); ?></td>
                                <td>
                                    <a href="../review/review_form.php?code=<?php echo $doc['doc_code']; ?>" class="btn btn-sm">✅ Mulai Review</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <p>✅ Tidak ada dokumen yang menunggu review!</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="quick-actions">
                <a href="../review/pending.php" class="btn">📋 Semua Pending Review</a>
                <a href="../review/history.php" class="btn">📜 Riwayat Review</a>
            </div>
            
        <?php else: ?>
            <!-- Dashboard Admin -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo $stats['total_docs'] ?? 0; ?></h3>
                    <p>Total Dokumen</p>
                </div>
                <div class="stat-card success">
                    <h3><?php echo $stats['total_users'] ?? 0; ?></h3>
                    <p>Total User Aktif</p>
                </div>
                <div class="stat-card warning">
                    <h3><?php echo $stats['pending'] ?? 0; ?></h3>
                    <p>Dokumen Pending</p>
                </div>
                <div class="stat-card success">
                    <h3><?php echo $stats['approved'] ?? 0; ?></h3>
                    <p>Dokumen Disetujui</p>
                </div>
            </div>
            
            <div class="section">
                <h2>🎯 Panel Administrator</h2>
                <p style="margin-bottom: 25px; color: #7f8c8d;">Akses cepat ke semua fitur administrasi sistem</p>
                
                <div class="admin-quick-links">
                    <a href="../admin/users.php" class="admin-link-card">
                        <div class="icon">👥</div>
                        <h3>Manajemen User</h3>
                        <p>Kelola pengguna sistem</p>
                    </a>
                    
                    <a href="../admin/reports.php" class="admin-link-card">
                        <div class="icon">📊</div>
                        <h3>Laporan Sistem</h3>
                        <p>Lihat statistik lengkap</p>
                    </a>
                    
                    <a href="../documents/list.php" class="admin-link-card">
                        <div class="icon">📁</div>
                        <h3>Semua Dokumen</h3>
                        <p>Kelola semua dokumen</p>
                    </a>
                    
                    <a href="../review/pending.php" class="admin-link-card">
                        <div class="icon">⏳</div>
                        <h3>Review Dokumen</h3>
                        <p>Review dokumen pending</p>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script src="../../assets/js/script.js"></script>

</body>
</html>