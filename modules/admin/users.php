<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Hanya admin
requireRole('admin');

$user = getCurrentUser();

// Get all users
$query = "
    SELECT 
        user_id,
        username,
        full_name,
        email,
        role,
        is_active,
        created_at,
        last_login
    FROM users
    ORDER BY created_at DESC
";

$users = $conn->query($query);

// Statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN role = 'uploader' THEN 1 ELSE 0 END) as uploaders,
        SUM(CASE WHEN role = 'reviewer' THEN 1 ELSE 0 END) as reviewers,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins
    FROM users
";

$stats = $conn->query($stats_query)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User - <?php echo APP_NAME; ?></title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-header h2 {
            color: #2c3e50;
            font-size: 26px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
        
        .stat-card.success {
            border-left-color: #27ae60;
        }
        
        .stat-card.success h3 {
            color: #27ae60;
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
        
        .badge-uploader {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
        }
        
        .badge-reviewer {
            background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
            color: #856404;
        }
        
        .badge-admin {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }
        
        .badge-active {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }
        
        .badge-inactive {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
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
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-warning {
            background: #f39c12;
        }
        
        .btn-warning:hover {
            background: #e67e22;
            box-shadow: 0 6px 20px rgba(243, 156, 18, 0.3);
        }
        
        .btn-danger {
            background: #e74c3c;
        }
        
        .btn-danger:hover {
            background: #c0392b;
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
        }
        
        .user-actions {
            display: flex;
            gap: 8px;
        }
        
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
            }
            
            .page-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }

    </style>
</head>
<body>
    <div class="navbar">
        <h1>👥 Manajemen User</h1>
        <div class="nav-links">
            <a href="../dashboard/index.php">Dashboard</a>
            <a href="reports.php">Laporan</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>👥 Manajemen Pengguna Sistem</h2>
            <button class="btn" onclick="openAddUserModal()">
                ➕ Tambah User Baru
            </button>
        </div>

        <?php displayFlashMessage(); ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $stats['total_users']; ?></h3>
                <p>Total User</p>
            </div>
            <div class="stat-card success">
                <h3><?php echo $stats['active_users']; ?></h3>
                <p>User Aktif</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['uploaders']; ?></h3>
                <p>Uploader</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['reviewers']; ?></h3>
                <p>Reviewer</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['admins']; ?></h3>
                <p>Admin</p>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <h3 style="margin-bottom: 20px;">Daftar User</h3>

            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Nama Lengkap</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($u = $users->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo e($u['username']); ?></strong></td>
                        <td><?php echo e($u['full_name']); ?></td>
                        <td><?php echo e($u['email']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $u['role']; ?>">
                                <?php echo USER_ROLES[$u['role']]; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['is_active']): ?>
                            <span class="badge badge-active">Aktif</span>
                            <?php else: ?>
                            <span class="badge badge-inactive">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            if ($u['last_login']) {
                                echo timeAgo($u['last_login']);
                            } else {
                                echo '<em style="color: #999;">Belum pernah login</em>';
                            }
                            ?>
                        </td>
                        <td>
                            <div class="user-actions">
                                <button class="btn btn-sm btn-warning" 
                                        onclick='editUser(<?php echo json_encode($u); ?>)'>
                                    ✏️ Edit
                                </button>
                                
                                <?php if ($u['is_active']): ?>
                                <button class="btn btn-sm btn-danger" 
                                        onclick="toggleUserStatus(<?php echo $u['user_id']; ?>, 0)">
                                    🚫 Nonaktifkan
                                </button>
                                <?php else: ?>
                                <button class="btn btn-sm btn-success" 
                                        onclick="toggleUserStatus(<?php echo $u['user_id']; ?>, 1)">
                                    ✅ Aktifkan
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Add User -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-close" onclick="closeAddUserModal()">&times;</span>
                <h3>Tambah User Baru</h3>
            </div>

            <form action="process_user.php" method="POST" id="addUserForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label for="add_username">Username *</label>
                    <input type="text" id="add_username" name="username" required 
                           pattern="[a-zA-Z0-9_]{3,50}"
                           placeholder="3-50 karakter, hanya huruf, angka, underscore">
                    <div class="help-text">Username untuk login</div>
                </div>

                <div class="form-group">
                    <label for="add_password">Password *</label>
                    <input type="password" id="add_password" name="password" required 
                           minlength="8"
                           placeholder="Minimal 8 karakter">
                    <div class="help-text">Minimal 8 karakter, ada huruf besar, kecil, dan angka</div>
                </div>

                <div class="form-group">
                    <label for="add_full_name">Nama Lengkap *</label>
                    <input type="text" id="add_full_name" name="full_name" required
                           placeholder="Nama lengkap user">
                </div>

                <div class="form-group">
                    <label for="add_email">Email *</label>
                    <input type="email" id="add_email" name="email" required
                           placeholder="user@example.com">
                </div>

                <div class="form-group">
                    <label for="add_role">Role *</label>
                    <select id="add_role" name="role" required>
                        <option value="">-- Pilih Role --</option>
                        <option value="uploader">Pengunggah Dokumen</option>
                        <option value="reviewer">Pemeriksa Dokumen</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-success">
                        ✅ Tambah User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit User -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-close" onclick="closeEditUserModal()">&times;</span>
                <h3>Edit User</h3>
            </div>

            <form action="process_user.php" method="POST" id="editUserForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_user_id" name="user_id">

                <div class="form-group">
                    <label for="edit_username">Username</label>
                    <input type="text" id="edit_username" disabled>
                    <div class="help-text">Username tidak dapat diubah</div>
                </div>

                <div class="form-group">
                    <label for="edit_full_name">Nama Lengkap *</label>
                    <input type="text" id="edit_full_name" name="full_name" required>
                </div>

                <div class="form-group">
                    <label for="edit_email">Email *</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="edit_role">Role *</label>
                    <select id="edit_role" name="role" required>
                        <option value="uploader">Pengunggah Dokumen</option>
                        <option value="reviewer">Pemeriksa Dokumen</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit_password">Password Baru</label>
                    <input type="password" id="edit_password" name="password" 
                           minlength="8"
                           placeholder="Kosongkan jika tidak ingin mengubah password">
                    <div class="help-text">Isi hanya jika ingin mengubah password</div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-success">
                        💾 Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
 <script src="../../assets/js/script.js"></script>
    <script>
        function openAddUserModal() {
            document.getElementById('addUserModal').classList.add('active');
        }

        function closeAddUserModal() {
            document.getElementById('addUserModal').classList.remove('active');
            document.getElementById('addUserForm').reset();
        }

        function editUser(user) {
            document.getElementById('edit_user_id').value = user.user_id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_password').value = '';
            
            document.getElementById('editUserModal').classList.add('active');
        }

        function closeEditUserModal() {
            document.getElementById('editUserModal').classList.remove('active');
            document.getElementById('editUserForm').reset();
        }

        function toggleUserStatus(userId, status) {
            const action = status === 1 ? 'mengaktifkan' : 'menonaktifkan';
            
            if (confirm(`Apakah Anda yakin ingin ${action} user ini?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'process_user.php';
                
                form.innerHTML = `
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="user_id" value="${userId}">
                    <input type="hidden" name="is_active" value="${status}">
                `;
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addUserModal');
            const editModal = document.getElementById('editUserModal');
            
            if (event.target === addModal) {
                closeAddUserModal();
            }
            if (event.target === editModal) {
                closeEditUserModal();
            }
        }
    </script>
</body>
</html>