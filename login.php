<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        $error = $_SESSION['lang'] === 'so' ? "Fadlan buuxi dhammaan meelaha bannaan." : ($_SESSION['lang'] === 'ar' ? "يرجى ملء جميع الحقول." : "Please fill in all fields.");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['profile_image'] = $user['profile_image'];

            if ($remember) {
                setcookie('remember_user', $user['username'], time() + (86400 * 30), "/");
            }

            header("Location: dashboard.php");
            exit();
        } else {
            $error = $_SESSION['lang'] === 'so' ? "Magaca isticmaalaha ama sirta ayaa khaldan." : ($_SESSION['lang'] === 'ar' ? "اسم المستخدم أو كلمة المرور غير صحيحة." : "Invalid username or password.");
        }
    }
}

$stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
$settings = $stmt->fetch();
$logo_path = "assets/img/logo.png";
if ($settings && !empty($settings['logo'])) {
    $saved_logo = "images/logo/" . $settings['logo'];
    if (file_exists($saved_logo)) {
        $logo_path = $saved_logo;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" data-theme="<?php echo $_SESSION['theme']; ?>" <?php echo ($_SESSION['lang'] ?? 'so') === 'ar' ? 'dir="rtl"' : ''; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('login'); ?> - <?php echo $settings['site_name'] ?? 'MJS'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --primary-dark: #2e59d9;
            --success-color: #1cc88a;
            --light-bg: #f8f9fc;
            --text-color: #5a5c69;
        }
        [data-theme="dark"] {
            --light-bg: #1a1c23;
            --text-color: #d1d5db;
            --card-bg: #24262d;
            --border-color: #374151;
        }
        body {
            background: var(--light-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            font-family: 'Nunito', sans-serif;
            transition: all 0.3s ease;
        }
        .login-container {
            background: #fff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            position: relative;
        }
        [data-theme="dark"] .login-container {
            background: var(--card-bg);
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h2 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        [data-theme="dark"] .login-header h2 { color: #fff; }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-color);
            font-weight: 600;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
        [data-theme="dark"] .form-control {
            background: #2d2f39;
            border-color: var(--border-color);
            color: #fff;
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background 0.3s;
        }
        .btn-login:hover { background: var(--primary-dark); }
        .error-msg {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo" style="max-width: 80px; margin: 0 auto 20px; display: block;">
            <h2><?php echo $settings['site_name'] ?? 'Attendance System'; ?></h2>
            <p style="color: var(--text-color);"><?php echo __('login'); ?></p>
        </div>

        <?php if ($error): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label><?php echo $_SESSION['lang'] === 'so' ? 'User name' : ($_SESSION['lang'] === 'ar' ? 'اسم المستخدم' : 'Username'); ?></label>
                <input type="text" name="username" class="form-control" required value="<?php echo isset($_COOKIE['remember_user']) ? sanitize($_COOKIE['remember_user']) : ''; ?>">
            </div>
            <div class="form-group">
                <label><?php echo $_SESSION['lang'] === 'so' ? 'Password' : ($_SESSION['lang'] === 'ar' ? 'كلمة المرور' : 'Password'); ?></label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn-login"><?php echo __('login'); ?></button>
        </form>
    </div>
</body>
</html>