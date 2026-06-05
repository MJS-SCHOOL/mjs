<?php
require_once 'includes/header.php';
require_admin();

$error = '';
$success = '';

// Fetch current settings
$stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
$settings = $stmt->fetch();

if (!$settings) {
    // Initialize settings if not exists
    $pdo->query("INSERT INTO settings (id, site_name, logo) VALUES (1, 'School Attendance System', 'logo.png')");
    $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
    $settings = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $site_name = sanitize($_POST['site_name']);
    
    // Handle Logo Upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
        $filename = $_FILES['logo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = "logo_" . time() . "." . $ext;
            $upload_path = "images/logo/" . $new_filename;
            
            // Create directory if it doesn't exist
            if (!is_dir("images/logo/")) {
                mkdir("images/logo/", 0777, true);
            }

            if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                // Delete old logo if it exists and is not default
                if (!empty($settings['logo']) && $settings['logo'] != 'logo.png' && file_exists("images/logo/" . $settings['logo'])) {
                    @unlink("images/logo/" . $settings['logo']);
                }
                
                $stmt = $pdo->prepare("UPDATE settings SET logo = ? WHERE id = 1");
                $stmt->execute([$new_filename]);
                $settings['logo'] = $new_filename;
            } else {
                $error = "Wuu ku guuldareystay inuu upload gareeyo sawirka. Fadlan hubi ogolaanshaha galka (folder permissions).";
            }
        } else {
            $error = "Invalid file type for logo.";
        }
    }
    
    // Update Site Name
    $stmt = $pdo->prepare("UPDATE settings SET site_name = ? WHERE id = 1");
    $stmt->execute([$site_name]);
    $settings['site_name'] = $site_name;
    
    if (!$error) {
        $success = "Waa lagu guuleystay!";
    }
}
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Admin Profile</h1>
</div>

<?php if ($error): ?><div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;"><?php echo $success; ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">Hagida Guud</div>
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Magaca</label>
                        <input type="text" name="site_name" class="form-control" value="<?php echo $settings['site_name']; ?>" required>
                    </div>
                    <div class="form-group" style="margin-top: 20px;">
                        <label>Astaanta </label>
                        <input type="file" name="logo" class="form-control" accept="image/*">
                        <small class="text-muted">Latalin waxaad isticmaasha cabirada: Square image, PNG or SVG format.</small>
                    </div>
                </div>
                <div class="col-md-6" style="text-align: center;">
                    <label>Astaanta</label>
                    <div style="margin-top: 10px; padding: 20px; background: #f8f9fc; border: 1px dashed #ddd; border-radius: 10px;">
	                        <?php 
	                        $logo_path = "assets/img/logo.png"; // Default Fallback
	                        if (!empty($settings['logo']) && $settings['logo'] !== 'logo.png') {
	                            $saved_path = "images/logo/" . $settings['logo'];
	                            if (file_exists($saved_path)) {
	                                $logo_path = $saved_path;
	                            }
	                        }
	                        ?>
                        <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo" style="max-width: 150px; max-height: 150px; display: block;">
                    </div>
                </div>
            </div>
            <div style="margin-top: 30px;">
                <button type="submit" name="update_settings" class="btn btn-primary">Keedi Xogta</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
