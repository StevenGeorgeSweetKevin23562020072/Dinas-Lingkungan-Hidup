<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Hanya reviewer dan admin
requireAnyRole(['reviewer', 'admin']);

$user = getCurrentUser();
$user_id = $user['user_id'];
$role = $user['role'];

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = ITEMS_PER_PAGE;
$offset = calculateOffset($page, $per_page);

// Filters
$filter_status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Build query
$where_conditions = [];
$params = [];
$param_types = '';

// Filter berdasarkan reviewer (kecuali admin)
if ($role === 'reviewer') {
    $where_conditions[] = "r.reviewer_id = ?";
    $params[] = $user_id;
    $param_types .= 'i';
}

// Filter status
if ($filter_status !== '') {
    $where_conditions[] = "r.status = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}

$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total
$count_query = "
    SELECT COUNT(*) as total
    FROM reviews r
    {$where_sql}
";

$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// Get reviews
$query = "
    SELECT 
        r.*,
        u.full_name as reviewer_name,
        d.doc_code,
        d.title as doc_title,
        d.status as doc_status,
        dv.version_number,
        uploader.full_name as uploader_name
    FROM reviews r
    JOIN users u ON r.reviewer_id = u.user_id
    JOIN document_versions dv ON r.version_id = dv.version_id
    JOIN documents d ON dv.document_id = d.document_id
    JOIN users uploader ON d.uploader_id = uploader.user_id
    {$where_sql}
    ORDER BY r.review_date DESC
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$reviews = $stmt->get_result();

// Statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_reviews,
        SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as total_approved,
        SUM(CASE WHEN r.status = 'needs_revision' THEN 1 ELSE 0 END) as total_rejected
    FROM reviews r
    " . ($role === 'reviewer' ? "WHERE r.reviewer_id = {$user_id}" : "");

$stats = $conn->query($stats_query)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Review - <?php echo APP_NAME; ?></title>
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
            max-width: 1200px;
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
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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

        .stat-card.danger {
            border-left-color: #e74c3c;
        }

        .stat-card.danger h3 {
            color: #e74c3c;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .filters select {
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            margin-right: 10px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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

        table tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-approved {
            background: #d4edda;
            color: #155724;
        }

        .badge-needs_revision {
            background: #f8d7da;
            color: #721c24;
        }

        .btn {
            display: inline-block;
            padding: 6px 12px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 12px;
            transition: all 0.3s;
        }

        .btn:hover {
            background: #5568d3;
        }

        .pagination {
            display: flex;
            gap: 5px;
            justify-content: center;
            margin-top: 20px;
        }

        .page-link {
            padding: 8px 12px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }

        .page-link:hover:not(.disabled) {
            background: #667eea;
            color: white;
        }

        .page-link.active {
            background: #667eea;
            color: white;
        }

        .page-link.disabled {
            color: #ccc;
            cursor: not-allowed;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📜 Riwayat Review</h1>
        <div class="nav-links">
            <a href="../dashboard/index.php">Dashboard</a>
            <a href="pending.php">Pending Review</a>
            <a href="../documents/list.php">Semua Dokumen</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>📜 Riwayat Pemeriksaan Dokumen</h2>
            <p>Daftar semua dokumen yang telah direview</p>
        </div>

        <?php displayFlashMessage(); ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $stats['total_reviews']; ?></h3>
                <p>Total Review</p>
            </div>
            <div class="stat-card success">
                <h3><?php echo $stats['total_approved']; ?></h3>
                <p>Disetujui</p>
            </div>
            <div class="stat-card danger">
                <h3><?php echo $stats['total_rejected']; ?></h3>
                <p>Perlu Revisi</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="">
                <label>Filter Status:</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>
                        Disetujui
                    </option>
                    <option value="needs_revision" <?php echo $filter_status === 'needs_revision' ? 'selected' : ''; ?>>
                        Perlu Revisi
                    </option>
                </select>
            </form>
        </div>

        <!-- Reviews List -->
        <div class="card">
            <?php if ($reviews->num_rows > 0): ?>
            <p style="margin-bottom: 15px; color: #666;">
                Menampilkan <?php echo (($page-1) * $per_page) + 1; ?> - <?php echo min($page * $per_page, $total_records); ?> 
                dari <?php echo $total_records; ?> review
            </p>

            <table>
                <thead>
                    <tr>
                        <th>Tanggal Review</th>
                        <th>Kode Dokumen</th>
                        <th>Judul Dokumen</th>
                        <th>Pengunggah</th>
                        <?php if ($role !== 'reviewer'): ?>
                        <th>Reviewer</th>
                        <?php endif; ?>
                        <th>Versi</th>
                        <th>Hasil Review</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($review = $reviews->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo formatTanggal($review['review_date']); ?></td>
                        <td><strong><?php echo e($review['doc_code']); ?></strong></td>
                        <td><?php echo e(truncate($review['doc_title'], 40)); ?></td>
                        <td><?php echo e($review['uploader_name']); ?></td>
                        <?php if ($role !== 'reviewer'): ?>
                        <td><?php echo e($review['reviewer_name']); ?></td>
                        <?php endif; ?>
                        <td>v<?php echo number_format($review['version_number'], 1); ?></td>
                        <td>
                            <?php 
                            $status_names = [
                                'approved' => 'Disetujui',
                                'needs_revision' => 'Perlu Revisi'
                            ];
                            ?>
                            <span class="badge badge-<?php echo $review['status']; ?>">
                                <?php echo $status_names[$review['status']]; ?>
                            </span>
                        </td>
                        <td>
                            <a href="../documents/detail.php?code=<?php echo $review['doc_code']; ?>" 
                               class="btn">
                                Detail
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php
                $base_url = 'history.php';
                $query_params = [];
                if ($filter_status) $query_params['status'] = $filter_status;
                
                if ($page > 1):
                    $prev_url = buildUrl($base_url, array_merge($query_params, ['page' => $page - 1]));
                ?>
                    <a href="<?php echo $prev_url; ?>" class="page-link">← Prev</a>
                <?php else: ?>
                    <span class="page-link disabled">← Prev</span>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);

                for ($i = $start; $i <= $end; $i++):
                    $page_url = buildUrl($base_url, array_merge($query_params, ['page' => $i]));
                ?>
                    <?php if ($i == $page): ?>
                        <span class="page-link active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo $page_url; ?>" class="page-link"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php
                if ($page < $total_pages):
                    $next_url = buildUrl($base_url, array_merge($query_params, ['page' => $page + 1]));
                ?>
                    <a href="<?php echo $next_url; ?>" class="page-link">Next →</a>
                <?php else: ?>
                    <span class="page-link disabled">Next →</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="empty-state">
                <p>📭 Belum ada riwayat review</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="../../assets/js/script.js"></script>

</body>
</html>