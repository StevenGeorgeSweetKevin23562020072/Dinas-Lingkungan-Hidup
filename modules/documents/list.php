<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireLogin();

$user = getCurrentUser();
$user_id = $user['user_id'];
$role = $user['role'];

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = ITEMS_PER_PAGE;
$offset = calculateOffset($page, $per_page);

// Filters
$filter_status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$filter_category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Build query berdasarkan role
$where_conditions = [];
$params = [];
$param_types = '';

if ($role === 'uploader') {
    // Uploader hanya lihat dokumennya sendiri
    $where_conditions[] = "d.uploader_id = ?";
    $params[] = $user_id;
    $param_types .= 'i';
} 
// Reviewer dan admin bisa lihat semua dokumen

// Filter status
if ($filter_status !== '') {
    $where_conditions[] = "d.status = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}

// Filter category
if ($filter_category !== '') {
    $where_conditions[] = "d.category = ?";
    $params[] = $filter_category;
    $param_types .= 's';
}

// Search
if ($search !== '') {
    $where_conditions[] = "(d.doc_code LIKE ? OR d.title LIKE ? OR d.description LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

// Build WHERE clause
$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total records
$count_query = "
    SELECT COUNT(*) as total
    FROM documents d
    {$where_sql}
";

$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// Get documents
$query = "
    SELECT 
        d.*,
        u.full_name as uploader_name,
        dv.version_number,
        dv.file_name,
        dv.file_size
    FROM documents d
    LEFT JOIN users u ON d.uploader_id = u.user_id
    LEFT JOIN document_versions dv ON d.current_version_id = dv.version_id
    {$where_sql}
    ORDER BY d.updated_at DESC
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
$documents = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Dokumen - <?php echo APP_NAME; ?></title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h2 {
            color: #333;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
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
            background: #5568d3;
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #667eea;
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

        .badge-draft { background: #e2e3e5; color: #383d41; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-revision { background: #f8d7da; color: #721c24; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-archived { background: #d6d8db; color: #1b1e21; }

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
            transition: all 0.3s;
        }

        .page-link:hover:not(.disabled) {
            background: #667eea;
            color: white;
        }

        .page-link.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
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

        .empty-state p {
            font-size: 16px;
            margin-bottom: 20px;
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
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📋 Daftar Dokumen</h1>
        <div class="nav-links">
            <a href="../dashboard/index.php">Dashboard</a>
            <?php if ($role === 'uploader' || $role === 'admin'): ?>
            <a href="upload.php">Upload Dokumen</a>
            <?php endif; ?>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>
                <?php 
                if ($role === 'uploader') {
                    echo '📁 Dokumen Saya';
                } else {
                    echo '📁 Semua Dokumen';
                }
                ?>
            </h2>
            <?php if ($role === 'uploader' || $role === 'admin'): ?>
            <a href="upload.php" class="btn">📤 Upload Baru</a>
            <?php endif; ?>
        </div>

        <?php displayFlashMessage(); ?>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Cari Dokumen</label>
                        <input type="text" name="search" placeholder="Kode atau judul..." value="<?php echo e($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">Semua Status</option>
                            <?php foreach (DOC_STATUS as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo $filter_status === $key ? 'selected' : ''; ?>>
                                <?php echo $value; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Kategori</label>
                        <select name="category">
                            <option value="">Semua Kategori</option>
                            <?php foreach (DOC_CATEGORIES as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo $filter_category === $key ? 'selected' : ''; ?>>
                                <?php echo $value; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group" style="justify-content: flex-end;">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn">🔍 Filter</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="card">
            <?php if ($documents->num_rows > 0): ?>
            <p style="margin-bottom: 15px; color: #666;">
                Menampilkan <?php echo (($page-1) * $per_page) + 1; ?> - <?php echo min($page * $per_page, $total_records); ?> 
                dari <?php echo $total_records; ?> dokumen
            </p>
            
            <table>
                <thead>
                    <tr>
                        <th>Kode Dokumen</th>
                        <th>Judul</th>
                        <?php if ($role !== 'uploader'): ?>
                        <th>Pengunggah</th>
                        <?php endif; ?>
                        <th>Kategori</th>
                        <th>Versi</th>
                        <th>Status</th>
                        <th>Terakhir Update</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($doc = $documents->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo e($doc['doc_code']); ?></strong></td>
                        <td><?php echo e(truncate($doc['title'], 60)); ?></td>
                        <?php if ($role !== 'uploader'): ?>
                        <td><?php echo e($doc['uploader_name']); ?></td>
                        <?php endif; ?>
                        <td><?php echo DOC_CATEGORIES[$doc['category']] ?? $doc['category']; ?></td>
                        <td>v<?php echo number_format($doc['version_number'], 1); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $doc['status']; ?>">
                                <?php echo DOC_STATUS[$doc['status']]; ?>
                            </span>
                        </td>
                        <td><?php echo formatTanggal($doc['updated_at']); ?></td>
                        <td>
                            <a href="detail.php?code=<?php echo $doc['doc_code']; ?>" class="btn btn-sm">
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
                $base_url = 'list.php';
                $query_params = [];
                if ($search) $query_params['search'] = $search;
                if ($filter_status) $query_params['status'] = $filter_status;
                if ($filter_category) $query_params['category'] = $filter_category;
                
                // Previous
                if ($page > 1):
                    $prev_url = buildUrl($base_url, array_merge($query_params, ['page' => $page - 1]));
                ?>
                    <a href="<?php echo $prev_url; ?>" class="page-link">← Prev</a>
                <?php else: ?>
                    <span class="page-link disabled">← Prev</span>
                <?php endif; ?>

                <?php
                // Page numbers
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
                // Next
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
                <p>📭 Tidak ada dokumen ditemukan</p>
                <?php if ($role === 'uploader' || $role === 'admin'): ?>
                <a href="upload.php" class="btn">📤 Upload Dokumen Pertama</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="../../assets/js/script.js"></script>

</body>
</html>