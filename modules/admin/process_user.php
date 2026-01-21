    <?php
    require_once '../../includes/auth.php';
    require_once '../../includes/functions.php';

    // Hanya terima POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: users.php");
        exit;
    }

    // Hanya admin
    requireRole('admin');

    // Validasi CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('users.php', 'error', 'Invalid CSRF token');
    }

    $admin_user = getCurrentUser();
    $action = sanitizeInput($_POST['action'] ?? '');

    // ================================================
    // ADD USER
    // ================================================
    if ($action === 'add') {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $full_name = sanitizeInput($_POST['full_name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $role = sanitizeInput($_POST['role'] ?? '');

        // Validasi
        $errors = [];

        if (empty($username)) {
            $errors[] = "Username harus diisi";
        } elseif (!isValidUsername($username)) {
            $errors[] = "Username tidak valid (3-50 karakter, hanya huruf, angka, underscore)";
        }

        if (empty($password)) {
            $errors[] = "Password harus diisi";
        } elseif (!isStrongPassword($password)) {
            $errors[] = "Password harus minimal 8 karakter, mengandung huruf besar, kecil, dan angka";
        }

        if (empty($full_name)) {
            $errors[] = "Nama lengkap harus diisi";
        }

        if (empty($email)) {
            $errors[] = "Email harus diisi";
        } elseif (!isValidEmail($email)) {
            $errors[] = "Email tidak valid";
        }

        if (!in_array($role, ['uploader', 'reviewer', 'admin'])) {
            $errors[] = "Role tidak valid";
        }

        // Cek username sudah ada
        $check_username = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $check_username->bind_param("s", $username);
        $check_username->execute();
        if ($check_username->get_result()->num_rows > 0) {
            $errors[] = "Username sudah digunakan";
        }

        // Cek email sudah ada
        $check_email = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        if ($check_email->get_result()->num_rows > 0) {
            $errors[] = "Email sudah digunakan";
        }

        if (!empty($errors)) {
            redirectWithMessage('users.php', 'error', implode(', ', $errors));
        }

        try {
            // Hash password
            $password_hash = hashPassword($password);

            // Insert user
            $stmt = $conn->prepare("
                INSERT INTO users (username, password, full_name, email, role, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");

            $stmt->bind_param("sssss", $username, $password_hash, $full_name, $email, $role);

            if ($stmt->execute()) {
                $new_user_id = $conn->insert_id;

                // Log aktivitas
                logActivity(
                    $admin_user['user_id'],
                    'create_user',
                    null,
                    "Created user: {$username} (ID: {$new_user_id})"
                );

                redirectWithMessage(
                    'users.php',
                    'success',
                    "User {$username} berhasil ditambahkan!"
                );
            } else {
                throw new Exception($stmt->error);
            }

        } catch (Exception $e) {
            error_log("Add User Error: " . $e->getMessage());
            redirectWithMessage('users.php', 'error', 'Gagal menambahkan user: ' . $e->getMessage());
        }
    }

    // ================================================
    // EDIT USER
    // ================================================
    elseif ($action === 'edit') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $full_name = sanitizeInput($_POST['full_name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $role = sanitizeInput($_POST['role'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validasi
        $errors = [];

        if ($user_id <= 0) {
            $errors[] = "User ID tidak valid";
        }

        if (empty($full_name)) {
            $errors[] = "Nama lengkap harus diisi";
        }

        if (empty($email)) {
            $errors[] = "Email harus diisi";
        } elseif (!isValidEmail($email)) {
            $errors[] = "Email tidak valid";
        }

        if (!in_array($role, ['uploader', 'reviewer', 'admin'])) {
            $errors[] = "Role tidak valid";
        }

        // Jika password diisi, validasi
        if (!empty($password) && !isStrongPassword($password)) {
            $errors[] = "Password harus minimal 8 karakter, mengandung huruf besar, kecil, dan angka";
        }

        // Cek email sudah digunakan user lain
        $check_email = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check_email->bind_param("si", $email, $user_id);
        $check_email->execute();
        if ($check_email->get_result()->num_rows > 0) {
            $errors[] = "Email sudah digunakan oleh user lain";
        }

        if (!empty($errors)) {
            redirectWithMessage('users.php', 'error', implode(', ', $errors));
        }

        try {
            // Update user
            if (!empty($password)) {
                // Update dengan password baru
                $password_hash = hashPassword($password);
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET full_name = ?, email = ?, role = ?, password = ?
                    WHERE user_id = ?
                ");
                $stmt->bind_param("ssssi", $full_name, $email, $role, $password_hash, $user_id);
            } else {
                // Update tanpa password
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET full_name = ?, email = ?, role = ?
                    WHERE user_id = ?
                ");
                $stmt->bind_param("sssi", $full_name, $email, $role, $user_id);
            }

            if ($stmt->execute()) {
                // Log aktivitas
                logActivity(
                    $admin_user['user_id'],
                    'update_user',
                    null,
                    "Updated user ID: {$user_id}"
                );

                redirectWithMessage(
                    'users.php',
                    'success',
                    "Data user berhasil diperbarui!"
                );
            } else {
                throw new Exception($stmt->error);
            }

        } catch (Exception $e) {
            error_log("Edit User Error: " . $e->getMessage());
            redirectWithMessage('users.php', 'error', 'Gagal mengupdate user: ' . $e->getMessage());
        }
    }

    // ================================================
    // TOGGLE STATUS (Aktif/Nonaktif)
    // ================================================
    elseif ($action === 'toggle_status') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $is_active = intval($_POST['is_active'] ?? 0);

        if ($user_id <= 0) {
            redirectWithMessage('users.php', 'error', 'User ID tidak valid');
        }

        // Tidak boleh menonaktifkan diri sendiri
        if ($user_id == $admin_user['user_id']) {
            redirectWithMessage('users.php', 'error', 'Anda tidak dapat menonaktifkan akun Anda sendiri');
        }

        try {
            $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
            $stmt->bind_param("ii", $is_active, $user_id);

            if ($stmt->execute()) {
                $status_text = $is_active ? 'diaktifkan' : 'dinonaktifkan';

                // Log aktivitas
                logActivity(
                    $admin_user['user_id'],
                    'toggle_user_status',
                    null,
                    "User ID {$user_id} {$status_text}"
                );

                redirectWithMessage(
                    'users.php',
                    'success',
                    "User berhasil {$status_text}!"
                );
            } else {
                throw new Exception($stmt->error);
            }

        } catch (Exception $e) {
            error_log("Toggle Status Error: " . $e->getMessage());
            redirectWithMessage('users.php', 'error', 'Gagal mengubah status user');
        }
    }

    // ================================================
    // ACTION TIDAK VALID
    // ================================================
    else {
        redirectWithMessage('users.php', 'error', 'Action tidak valid');
    }
    ?>