<?php
require_once '../../includes/auth.php';

// Redirect jika sudah login
redirectIfLoggedIn();

// Ambil pesan error atau sukses
$error = '';
$success = '';
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
if (isset($_SESSION['logout_success'])) {
    $success = $_SESSION['logout_success'];
    unset($_SESSION['logout_success']);
}
if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $error = 'Sesi Anda telah berakhir. Silakan login kembali.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1abc9c 0%, #16a085 50%, #27ae60 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        /* Animated background circles */
        body::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -200px;
            right: -200px;
            animation: float 8s ease-in-out infinite;
        }
        
        body::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            bottom: -150px;
            left: -150px;
            animation: float 10s ease-in-out infinite reverse;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(20px); }
        }
        
        .login-container {
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 1;
        }
        
        .login-box {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 45px;
            animation: slideUp 0.6s ease-out;
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
        
        .logo-container {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .logo {
            width: 90px;
            height: 90px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 42px;
            font-weight: bold;
            box-shadow: 0 10px 30px rgba(39, 174, 96, 0.4);
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .logo-text {
            font-size: 28px;
            font-weight: 700;
            color: #27ae60;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .logo-subtext {
            color: #7f8c8d;
            font-size: 15px;
            font-weight: 500;
        }
        
        .divider {
            height: 3px;
            background: linear-gradient(90deg, transparent, #27ae60, transparent);
            margin: 25px 0;
            border-radius: 2px;
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
            line-height: 1.5;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.4s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .alert-danger {
            background: #fee;
            color: #c33;
            border-left: 4px solid #e74c3c;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #27ae60;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="password"] {
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
        input[type="password"]:focus {
            outline: none;
            border-color: #27ae60;
            box-shadow: 0 0 0 4px rgba(39, 174, 96, 0.1);
            background: white;
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle input {
            padding-right: 50px;
        }
        
        .toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            transition: all 0.3s;
        }
        
        .toggle-btn:hover {
            transform: translateY(-50%) scale(1.1);
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 17px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(39, 174, 96, 0.4);
            background: linear-gradient(135deg, #229954 0%, #27ae60 100%);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .info-box {
            background: linear-gradient(135deg, #e8f5e9 0%, #f1f8f4 100%);
            border-left: 4px solid #27ae60;
            padding: 18px;
            margin-top: 25px;
            border-radius: 10px;
            font-size: 13px;
            line-height: 1.7;
        }
        
        .info-box strong {
            display: block;
            margin-bottom: 10px;
            color: #27ae60;
            font-size: 14px;
        }
        
        .info-box ul {
            list-style: none;
            padding-left: 0;
        }
        
        .info-box li {
            padding: 6px 0;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-box li::before {
            content: '🔑';
            font-size: 16px;
        }
        
        .footer-text {
            text-align: center;
            color: rgba(255, 255, 255, 0.9);
            font-size: 13px;
            margin-top: 25px;
            padding-top: 20px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .footer-text a {
            color: white;
            text-decoration: none;
            font-weight: 600;
        }
        
        /* Icon decorations */
        .icon-deco {
            font-size: 18px;
            margin-right: 5px;
        }
        
        @media (max-width: 480px) {
            .login-box {
                padding: 35px 25px;
            }
            
            .logo {
                width: 80px;
                height: 80px;
                font-size: 38px;
            }
            
            .logo-text {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="logo-container">
                <div class="logo">🌿</div>
                <div class="logo-text">SMDL</div>
                <div class="logo-subtext">Dinas Lingkungan Hidup</div>
            </div>
            
            <div class="divider"></div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <span>⚠️</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <span>✓</span>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>
            
            <form action="process_login.php" method="POST" id="loginForm">
                <div class="form-group">
                    <label for="username">
                        <span class="icon-deco">👤</span>Username
                    </label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="Masukkan username Anda"
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <span class="icon-deco">🔒</span>Password
                    </label>
                    <div class="password-toggle">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="Masukkan password Anda"
                        >
                        <button type="button" class="toggle-btn" onclick="togglePassword()">
                            👁️
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    Masuk ke Sistem
                </button>
            </form>
            
            <!-- <div class="info-box">
                <strong>📋 Akun Demo:</strong>
                <ul>
                    <li><strong>Admin:</strong> admin / admin123</li>
                    <li><strong>Uploader:</strong> uploader1 / admin123</li>
                    <li><strong>Reviewer:</strong> reviewer1 / admin123</li>
                </ul>
            </div> -->
        </div>
        
        <div class="footer-text">
            © <?php echo APP_YEAR; ?> <?php echo APP_AUTHOR; ?><br>
            <small>Sistem Manajemen Dokumen Laboratorium v<?php echo APP_VERSION; ?></small>
        </div>
    </div>

    <script src="../../assets/js/script.js"></script>
    
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.toggle-btn');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (username === '' || password === '') {
                e.preventDefault();
                alert('Username dan password harus diisi!');
                return false;
            }
            
            // Disable submit button untuk mencegah double submit
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Memproses...';
        });
        
        // Auto-focus ke password jika username sudah terisi
        window.addEventListener('load', function() {
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            
            if (usernameInput.value.trim() !== '') {
                passwordInput.focus();
            }
        });
    </script>
</body>
</html>