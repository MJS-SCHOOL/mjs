<?php
require_once 'includes/header.php';
require_admin();

$error = '';
$success = '';

if (!isset($_GET['id'])) {
    header("Location: students.php");
    exit();
}

$id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    header("Location: students.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    $full_name = sanitize($_POST['full_name']);
    $class_id = (int)$_POST['class_id'];
    $room_id = (int)$_POST['room_id'];
    $parent_contact = sanitize($_POST['parent_contact']);
    
    // Handle Photo Upload
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['photo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = "student_" . $id . "_" . time() . "." . $ext;
            $upload_path = "images/students/" . $new_filename;
            
            if (!is_dir('images/students')) {
                mkdir('images/students', 0777, true);
            }
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                // Delete old photo
                if ($student['photo'] != 'default_student.png' && file_exists("images/students/" . $student['photo'])) {
                    unlink("images/students/" . $student['photo']);
                }
                
                $stmt = $pdo->prepare("UPDATE students SET photo = ? WHERE id = ?");
                $stmt->execute([$new_filename, $id]);
                $student['photo'] = $new_filename;
            }
        }
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE students SET full_name = ?, class_id = ?, room_id = ?, parent_contact = ? WHERE id = ?");
        $stmt->execute([$full_name, $class_id, $room_id, $parent_contact, $id]);
        $success = "Student updated successfully!";
        // Refresh student data
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $student = $stmt->fetch();
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$classes = $pdo->query("SELECT * FROM classes")->fetchAll();
$rooms = $pdo->query("SELECT * FROM rooms")->fetchAll();
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Wax ka bedel xogta ardayga: <?php echo $student['full_name']; ?></h1>
    <a href="students.php" class="btn btn-secondary">← Dib u laabo</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group">
                        <label>Magaca </label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo $student['full_name']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Fasalka</label>
                        <select name="class_id" class="form-control" required>
                            <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo $student['class_id'] == $class['id'] ? 'selected' : ''; ?>><?php echo $class['class_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Room ka</label>
                        <select name="room_id" class="form-control" required>
                            <?php foreach ($rooms as $room): ?>
                            <option value="<?php echo $room['id']; ?>" <?php echo $student['room_id'] == $room['id'] ? 'selected' : ''; ?>><?php echo $room['room_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Waalid ka</label>
                        <input type="text" name="parent_contact" class="form-control" value="<?php echo $student['parent_contact']; ?>">
                    </div>
                </div>
                <div class="col-md-4" style="text-align: center;">
                    <label>Sawirka Ardayga</label>
                    <div style="margin-bottom: 15px;">
                        <?php 
                        $photo_path = "images/students/" . $student['photo'];
                        if (!file_exists($photo_path) || empty($student['photo'])) {
                            $photo_path = "assets/img/default_student.png";
                        }
                        ?>
                        <img src="<?php echo $photo_path; ?>" width="150" height="150" class="rounded-circle" style="border: 3px solid #4e73df; object-fit: cover;">
                    </div>
                    <input type="file" name="photo" class="form-control" accept="image/*">
                </div>
            </div>
            <div style="margin-top: 20px;">
                <button type="submit" name="update_student" class="btn btn-primary">Cusboneysi</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
