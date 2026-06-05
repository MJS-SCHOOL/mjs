<?php
require_once 'includes/header.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = sanitize($_POST['full_name']);
    $user_id = $_SESSION['user_id'];
    
    // Handle Profile Image Upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION)); 
        
        if (in_array($ext, $allowed)) {
            $new_filename = "profile_" . $user_id . "_" . time() . "." . $ext;
            $upload_path = "images/profile_pics/" . $new_filename;
            
            // Create directory if it doesn't exist
            if (!is_dir("images/profile_pics/")) {
                mkdir("images/profile_pics/", 0777, true);
            }

            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                // Delete old profile image if it exists and is not default
                if (!empty($_SESSION['profile_image']) && $_SESSION['profile_image'] != 'default_profile.png' && file_exists("images/profile_pics/" . $_SESSION['profile_image'])) {
                    @unlink("images/profile_pics/" . $_SESSION['profile_image']);
                }
                
	                $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
	                $stmt->execute([$new_filename, $user_id]);
	                $_SESSION['profile_image'] = $new_filename;
                    // Force refresh from DB to be sure
                    $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user_data = $stmt->fetch();
                    if ($user_data) {
                        $_SESSION['profile_image'] = $user_data['profile_image'];
                    }
	            } else {
                $error = "Wuu ku guuldareystay inuu upload gareeyo sawirka profile-ka.";
            }
        }
    }
    
    // Update Full Name
    $stmt = $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?");
    $stmt->execute([$full_name, $user_id]);
    $_SESSION['full_name'] = $full_name;
    $success = "Profile updated successfully!";
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $user_id = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            $success = "Password changed successfully!";
        } else {
            $error = "New passwords do not match!";
        }
    } else {
        $error = "Current password is incorrect!";
    }
}
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><?php echo __('Koonto'); ?> Shaqsiga</h1>
</div>

<?php if ($error): ?><div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;"><?php echo $success; ?></div><?php endif; ?>

<div class="row" style="display: flex; gap: 20px;">
    <div class="card" style="flex: 1;">
        <div class="card-header">Cusboonaysii Koontada</div>
        <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data">
                <div style="text-align: center; margin-bottom: 20px;">
		                    <?php 
		                    $profile_image = isset($_SESSION['profile_image']) ? $_SESSION['profile_image'] : 'default_profile.png';
		                    $profile_path = "assets/img/default_profile.png"; // Default Fallback
		                    
		                    if (!empty($profile_image) && $profile_image !== 'default_profile.png') {
		                        $saved_profile = "images/profile_pics/" . $profile_image;
		                        // Check if file exists, if not, try to use it directly as it might be in the database
		                        if (file_exists($saved_profile)) {
		                            $profile_path = $saved_profile;
		                        }
		                    }
		                    ?>
                    <img src="<?php echo htmlspecialchars($profile_path); ?>" width="100" height="100" class="rounded-circle" style="border: 3px solid #4e73df; object-fit: cover;">
                </div>
                <div class="form-group">
                    <label>Magaca</label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo $_SESSION['full_name']; ?>" required>
                </div>
                <div class="form-group">
                    <label>Sawirka</label>
                    <input type="file" name="profile_image" class="form-control" accept="image/*">
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" name="update_profile" class="btn btn-primary">Keedi</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card" style="flex: 1;">
        <div class="card-header">Bedel Furaha Sirta</div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label>Sirtii Hore</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Sirta Cusub</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Kuceli Sirta Cusub</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" name="change_password" class="btn btn-warning">Bedel Furaha </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
