<?php
require_once 'includes/header.php';
require_admin();

$error = '';
$success = '';

if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit();
}

$id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: users.php");
    exit();
}

// Get all available teacher pages
$available_pages = get_available_teacher_pages();

// Fetch currently assigned pages for this user
$assigned_pages_stmt = $pdo->prepare("SELECT page_name FROM teacher_pages WHERE user_id = ?");
$assigned_pages_stmt->execute([$id]);
$assigned_pages = $assigned_pages_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch all rooms and currently assigned rooms for this user
$all_rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_name ASC")->fetchAll();
$user_rooms_stmt = $pdo->prepare("SELECT room_id FROM user_rooms WHERE user_id = ?");
$user_rooms_stmt->execute([$id]);
$assigned_rooms = $user_rooms_stmt->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $full_name = sanitize($_POST['full_name']);
    $username = sanitize($_POST['username']);
    $role = sanitize($_POST['role']);
    $status = (int)$_POST['status'];
    
    try {
        $pdo->beginTransaction();
        
        // Update basic info
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, role = ?, status = ? WHERE id = ?");
        $stmt->execute([$full_name, $username, $role, $status, $id]);
        
        // Update Room Assignments
        $pdo->prepare("DELETE FROM user_rooms WHERE user_id = ?")->execute([$id]);
        if (isset($_POST['rooms']) && is_array($_POST['rooms'])) {
            $room_stmt = $pdo->prepare("INSERT INTO user_rooms (user_id, room_id) VALUES (?, ?)");
            foreach ($_POST['rooms'] as $room_id) {
                $room_stmt->execute([$id, (int)$room_id]);
            }
        }

        // Update Page Access Control
        $pdo->prepare("DELETE FROM teacher_pages WHERE user_id = ?")->execute([$id]);
        if (isset($_POST['pages']) && is_array($_POST['pages'])) {
            $page_stmt = $pdo->prepare("INSERT INTO teacher_pages (user_id, page_name) VALUES (?, ?)");
            foreach ($_POST['pages'] as $page_name) {
                if (array_key_exists($page_name, $available_pages)) {
                    $page_stmt->execute([$id, $page_name]);
                }
            }
        }

        // Update password if provided
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$password, $id]);
        }
        
        // Handle Profile Image Upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_filename = "profile_" . $id . "_" . time() . "." . $ext;
                $upload_path = "images/profile_pics/" . $new_filename;
                
                // Create directory if it doesn't exist
                if (!is_dir("images/profile_pics/")) {
                    mkdir("images/profile_pics/", 0777, true);
                }

                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                    // Delete old image
                    if (!empty($user['profile_image']) && $user['profile_image'] != 'default_profile.png' && file_exists("images/profile_pics/" . $user['profile_image'])) {
                        @unlink("images/profile_pics/" . $user['profile_image']);
                    }
                    
                    $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                    $stmt->execute([$new_filename, $id]);
                    $user['profile_image'] = $new_filename;
                    
                    if ($id == $_SESSION['user_id']) {
                        $_SESSION['profile_image'] = $new_filename;
                    }
                }
            }
        }
        
        if ($id == $_SESSION['user_id']) {
            $_SESSION['full_name'] = $full_name;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
        }
        
        $pdo->commit();
        $success = "User updated successfully!";
        
        // Refresh data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        $user_rooms_stmt->execute([$id]);
        $assigned_rooms = $user_rooms_stmt->fetchAll(PDO::FETCH_COLUMN);

        $assigned_pages_stmt->execute([$id]);
        $assigned_pages = $assigned_pages_stmt->fetchAll(PDO::FETCH_COLUMN);
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Edit User: <?php echo htmlspecialchars($user['full_name']); ?></h1>
    <a href="users.php" class="btn btn-secondary shadow-sm"><i class="fas fa-arrow-left mr-1"></i> Back to Users</a>
</div>

<?php if ($error): ?><div class="alert alert-danger shadow-sm"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success shadow-sm"><?php echo $success; ?></div><?php endif; ?>

<div class="card shadow border-0">
    <div class="card-body p-4">
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-8">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Full Name</label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold">Username</label>
                                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold">Password <small class="text-muted">(leave blank to keep current)</small></label>
                                <input type="password" name="password" class="form-control">
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold">Role</label>
                                <select name="role" class="form-control">
                                    <option value="teacher" <?php echo $user['role'] == 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                    <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold">Status</label>
                                <select name="status" class="form-control">
                                    <option value="1" <?php echo $user['status'] == 1 ? 'selected' : ''; ?>>Active</option>
                                    <option value="0" <?php echo $user['status'] == 0 ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold text-primary">Page Access Control</label>
                                <div style="max-height: 350px; overflow-y: auto; border: 1px solid #e3e6f0; padding: 15px; border-radius: 10px; background: #f8f9fc;">
                                    <?php foreach ($available_pages as $page_name => $page_label): ?>
                                        <div class="custom-control custom-checkbox mb-3">
                                            <input type="checkbox" class="custom-control-input" name="pages[]" value="<?php echo htmlspecialchars($page_name); ?>" id="page_<?php echo md5($page_name); ?>" <?php echo in_array($page_name, $assigned_pages) ? 'checked' : ''; ?>>
                                            <label class="custom-control-label font-weight-bold" for="page_<?php echo md5($page_name); ?>" style="cursor: pointer; color: #4e73df;">
                                                Show "<?php echo htmlspecialchars($page_label); ?>" Page
                                            </label>
                                            <div class="small text-muted ml-1">Access to <?php echo htmlspecialchars($page_name); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-3">
                        <label class="font-weight-bold text-info">Assigned Rooms</label>
                        <div style="max-height: 200px; overflow-y: auto; border: 1px solid #e3e6f0; padding: 15px; border-radius: 10px; background: #f8f9fc;">
                            <div class="row">
                                <?php foreach ($all_rooms as $room): ?>
                                    <div class="col-md-6">
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input class="custom-control-input" type="checkbox" name="rooms[]" value="<?php echo $room['id']; ?>" id="room_<?php echo $room['id']; ?>" <?php echo in_array($room['id'], $assigned_rooms) ? 'checked' : ''; ?>>
                                            <label class="custom-control-label" for="room_<?php echo $room['id']; ?>" style="cursor: pointer;">
                                                <?php echo htmlspecialchars($room['room_name']); ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="sticky-top" style="top: 20px;">
                        <label class="font-weight-bold">Profile Image</label>
	                        <div class="mb-3">
	                            <?php 
	                            $profile_path = "assets/img/default_profile.png";
	                            if (!empty($user['profile_image'])) {
	                                $saved_profile = "images/profile_pics/" . $user['profile_image'];
	                                if (file_exists($saved_profile)) {
	                                    $profile_path = $saved_profile;
	                                }
	                            }
	                            ?>
	                            <img src="<?php echo htmlspecialchars($profile_path); ?>" width="180" height="180" class="rounded-circle shadow" style="border: 5px solid #fff; object-fit: cover;">
	                        </div>
                        <div class="custom-file text-left">
                            <input type="file" name="profile_image" class="custom-file-input" id="profileImage" accept="image/*">
                            <label class="custom-file-label" for="profileImage">Choose image</label>
                        </div>
                        
                        <div class="mt-5">
                            <button type="submit" name="update_user" class="btn btn-primary btn-lg btn-block shadow"><i class="fas fa-save mr-2"></i> Update User</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Update file input label
document.getElementById('profileImage')?.addEventListener('change', function(e){
    var fileName = e.target.files[0].name;
    var nextSibling = e.target.nextElementSibling;
    nextSibling.innerText = fileName;
});
</script>

<?php require_once 'includes/footer.php'; ?>
