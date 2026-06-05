<?php
require_once 'includes/header.php';
require_admin();

$error = '';
$success = '';

// ==========================================
// 1. HANDLE STUDENT DELETION (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student_id'])) {
    $id = (int)$_POST['delete_student_id'];
    $stmt = $pdo->prepare("SELECT photo FROM students WHERE id = ?"); 
    $stmt->execute([$id]);
    $student = $stmt->fetch();
    
    if ($student) {
        if ($student['photo'] != 'default_student.png' && file_exists("images/students/" . $student['photo'])) {
            unlink("images/students/" . $student['photo']);
        }
        $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);
        $success = "Ardayga si guul ah ayaa loo tirtiray! (Student deleted successfully!)";
    }
}

// ==========================================
// 2. HANDLE STUDENT UPDATE (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student_id'])) {
    $id = (int)$_POST['update_student_id'];
    $full_name = sanitize($_POST['full_name']);
    $class_id = (int)$_POST['class_id'];
    $room_id = (int)$_POST['room_id'];
    $parent_contact = sanitize($_POST['parent_contact']);
    
    $stmt = $pdo->prepare("SELECT photo FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $student = $stmt->fetch();
    
    if ($student) {
        // Handle Photo Upload
        $photo = $student['photo'];
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
                    $photo = $new_filename;
                }
            }
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE students SET full_name = ?, class_id = ?, room_id = ?, parent_contact = ?, photo = ? WHERE id = ?");
            $stmt->execute([$full_name, $class_id, $room_id, $parent_contact, $photo, $id]);
            $success = "Xogta ardayga si guul ah ayaa loo cusboonaysiiyay! (Student updated successfully!)";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// ==========================================
// 3. HANDLE BULK DISABLE/ENABLE STUDENTS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = sanitize($_POST['bulk_action']);
    $selected_students = isset($_POST['selected_students']) ? $_POST['selected_students'] : [];
    
    if (!empty($selected_students) && is_array($selected_students)) {
        $status = ($action === 'disable') ? 1 : 0;
        $action_text = ($action === 'disable') ? 'disabled' : 'enabled';
        
        foreach ($selected_students as $student_id) {
            $student_id = (int)$student_id;
            $pdo->prepare("UPDATE students SET is_disabled = ? WHERE id = ?")->execute([$status, $student_id]);
        }
        
        $success = count($selected_students) . " student(s) have been " . $action_text . " successfully!";
    } else {
        $error = "Fadlan dooro ardayda ugu yaraan mid. (Please select at least one student.)";
    }
}

// ==========================================
// 4. HANDLE DISABLE/ENABLE STUDENT (Individual)
// ==========================================
if (isset($_GET['toggle_student_status'])) {
    $student_id = (int)$_GET['toggle_student_status'];
    $stmt = $pdo->prepare("SELECT is_disabled FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if ($student) {
        $new_status = $student['is_disabled'] == 1 ? 0 : 1;
        $pdo->prepare("UPDATE students SET is_disabled = ? WHERE id = ?")->execute([$new_status, $student_id]);
        $status_text = $new_status == 1 ? "disabled" : "enabled";
        $success = "Student has been " . $status_text . " successfully!";
    }
}

// ==========================================
// 5. HANDLE DISABLE/ENABLE CLASS
// ==========================================
if (isset($_GET['toggle_class_status'])) {
    $class_id = (int)$_GET['toggle_class_status'];
    $stmt = $pdo->prepare("SELECT is_disabled FROM classes WHERE id = ?");
    $stmt->execute([$class_id]);
    $class = $stmt->fetch();
    
    if ($class) {
        $new_status = $class['is_disabled'] == 1 ? 0 : 1;
        $pdo->prepare("UPDATE classes SET is_disabled = ? WHERE id = ?")->execute([$new_status, $class_id]);
        $status_text = $new_status == 1 ? "disabled" : "enabled";
        $success = "Class has been " . $status_text . " successfully!";
    }
}

// ==========================================
// 6. HANDLE CSV IMPORT
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = $_FILES['csv_file']['tmp_name'];
        
        if (($handle = fopen($file, "r")) !== FALSE) {
            // Skip the header row
            fgetcsv($handle);
            
            // Assuming CSV format: Full Name, Class Name, Room Name, Parent Contact
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Skip empty rows
                if (empty($data[0])) continue;

                $full_name = sanitize($data[0]);
                $class_name = sanitize($data[1]);
                $room_name = sanitize($data[2]);
                $parent_contact = isset($data[3]) ? sanitize($data[3]) : '';
                $photo = 'default_student.png';

                // Auto-create or fetch Class ID
                $stmt = $pdo->prepare("SELECT id FROM classes WHERE class_name = ?");
                $stmt->execute([$class_name]);
                $class_id = $stmt->fetchColumn();
                
                if (!$class_id && !empty($class_name)) {
                    $pdo->prepare("INSERT INTO classes (class_name, is_disabled) VALUES (?, 0)")->execute([$class_name]);
                    $class_id = $pdo->lastInsertId();
                }

                // Auto-create or fetch Room ID
                $stmt = $pdo->prepare("SELECT id FROM rooms WHERE room_name = ?");
                $stmt->execute([$room_name]);
                $room_id = $stmt->fetchColumn();
                
                if (!$room_id && !empty($room_name)) {
                    $pdo->prepare("INSERT INTO rooms (room_name) VALUES (?)")->execute([$room_name]);
                    $room_id = $pdo->lastInsertId();
                }

                // Insert Student
                try {
                    $stmt = $pdo->prepare("INSERT INTO students (full_name, class_id, room_id, parent_contact, photo, is_disabled) VALUES (?, ?, ?, ?, ?, 0)");
                    $stmt->execute([$full_name, $class_id, $room_id, $parent_contact, $photo]);
                } catch (PDOException $e) {
                    $error = "Error importing some rows: " . $e->getMessage();
                }
            }
            fclose($handle);
            if (empty($error)) {
                $success = "Xogta CSV si guul ah ayaa loo soo dhoofiyay! (CSV Imported successfully!)";
            }
        } else {
            $error = "Kuma guuleysan in la akhriyo feylka CSV. (Failed to read the CSV file.)";
        }
    } else {
        $error = "Fadlan dooro feyl sax ah. (Please select a valid file.)";
    }
}

// ==========================================
// 7. HANDLE STUDENT CREATION (Original)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_student'])) {
    $full_name = sanitize($_POST['full_name']);
    $class_id = (int)$_POST['class_id'];
    $room_id = (int)$_POST['room_id'];
    $parent_contact = sanitize($_POST['parent_contact']);
    $photo = 'default_student.png';
    
    // Handle Photo Upload
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['photo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = "student_" . time() . "." . $ext;
            $upload_path = "images/students/" . $new_filename;
            
            if (!is_dir('images/students')) {
                mkdir('images/students', 0777, true);
            }
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                $photo = $new_filename;
            }
        }
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO students (full_name, class_id, room_id, parent_contact, photo, is_disabled) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->execute([$full_name, $class_id, $room_id, $parent_contact, $photo]);
        $success = "Student added successfully!";
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// ==========================================
// 8. BUILD QUERY WITH FILTERS
// ==========================================
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$filter_class = isset($_GET['class_id']) ? (int)$_GET['class_id'] : '';
$filter_room = isset($_GET['room_id']) ? (int)$_GET['room_id'] : '';
$show_disabled = isset($_GET['show_disabled']) ? (int)$_GET['show_disabled'] : 0;

$query = "SELECT s.*, c.class_name, r.room_name 
          FROM students s 
          LEFT JOIN classes c ON s.class_id = c.id 
          LEFT JOIN rooms r ON s.room_id = r.id 
          WHERE 1=1";

$params = [];

// Hide disabled students by default
if (!$show_disabled) {
    $query .= " AND s.is_disabled = 0 AND (c.is_disabled = 0 OR c.is_disabled IS NULL)";
}

// Add search filter
if (!empty($search)) {
    $query .= " AND (s.full_name LIKE ? OR s.parent_contact LIKE ? OR c.class_name LIKE ? OR r.room_name LIKE ?)";
    $search_term = "%{$search}%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

// Add class filter
if (!empty($filter_class)) {
    $query .= " AND s.class_id = ?";
    $params[] = $filter_class;
}

// Add room filter
if (!empty($filter_room)) {
    $query .= " AND s.room_id = ?";
    $params[] = $filter_room;
}

$query .= " ORDER BY s.created_at DESC";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get only enabled classes for dropdown
$enabled_classes = $pdo->query("SELECT * FROM classes WHERE is_disabled = 0 ORDER BY class_name ASC")->fetchAll();
$rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_name ASC")->fetchAll();
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<style>
.disabled-row {
    opacity: 0.6;
    background-color: #f8f9fa;
}

.disabled-badge {
    display: inline-block;
    background-color: #e74a3b;
    color: white;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 0.75rem;
    font-weight: bold;
    margin-left: 5px;
}

.status-toggle-btn {
    padding: 3px 8px;
    font-size: 0.85rem;
    margin: 2px;
}

.class-status-badge {
    display: inline-block;
    background-color: #e74a3b;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.7rem;
    font-weight: bold;
}

.bulk-action-bar {
    display: none;
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
    border-left: 4px solid #2E59D9;
}

.bulk-action-bar.active {
    display: block;
}

.selected-count {
    font-weight: bold;
    color: #2E59D9;
}

/* POPUP MODAL STYLES */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    animation: fadeIn 0.2s ease-out;
    backdrop-filter: blur(4px);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes popupBounce {
    0% { opacity: 0; transform: scale(0.3) translateY(-50px); }
    60% { transform: scale(1.08); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
}

.popup-modal {
    background: linear-gradient(135deg, #2E59D9 0%, #1a3a99 100%);
    border-radius: 20px;
    padding: 30px;
    max-width: 500px;
    width: 95%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    text-align: center;
    animation: popupBounce 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
    overflow-y: auto;
    max-height: 90vh;
}

.popup-icon {
    font-size: 3.5rem;
    margin-bottom: 15px;
}

.popup-title {
    color: white;
    font-size: 1.8rem;
    font-weight: 800;
    margin-bottom: 10px;
}

.popup-message {
    color: rgba(255, 255, 255, 0.95);
    font-size: 1rem;
    margin-bottom: 20px;
}

.popup-form-group {
    text-align: left;
    margin-bottom: 15px;
}

.popup-form-group label {
    color: white;
    font-weight: 600;
    margin-bottom: 5px;
    display: block;
}

.popup-input {
    width: 100%;
    padding: 10px 15px;
    border-radius: 10px;
    border: none;
    background: rgba(255, 255, 255, 0.9);
    font-size: 1rem;
}

.popup-btn-container {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-top: 20px;
}

.popup-btn {
    padding: 10px 25px;
    border-radius: 10px;
    border: none;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.2s ease;
}

.popup-btn-cancel {
    background: rgba(255, 255, 255, 0.2);
    color: white;
}

.popup-btn-confirm {
    background: white;
    color: #2E59D9;
}

.popup-btn-delete {
    background: #e74a3b;
    color: white;
}

.popup-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}
</style>

<script>
var MJS_STUDENTS = <?php
    $js_students = array_map(function($s) {
        return [
            'id'             => (int)$s['id'],
            'full_name'      => $s['full_name'],
            'class_name'     => $s['class_name'] ?? '',
            'room_name'      => $s['room_name']  ?? '',
            'parent_contact' => $s['parent_contact'] ?? '',
            'is_disabled'    => (bool)$s['is_disabled'],
        ];
    }, $students);
    echo json_encode($js_students, JSON_UNESCAPED_UNICODE);
?>;
var MJS_DATE = "<?php echo date('d-m-Y H:i'); ?>";
var MJS_TOTAL = <?php echo count($students); ?>;
</script>

<div class="d-sm-flex align-items-center justify-content-between mb-4 border-bottom pb-3" style="border-color: #2E59D9 !important;">
    <div class="d-flex align-items-center">
        <img src="https://res.cloudinary.com/dsnlshm9k/image/upload/v1734294137/LOGO_LAST_SAX_2024-2025_rkln16.png" alt="MJS Logo" style="height: 50px; margin-right: 15px;">
        <h1 class="h3 mb-0" style="color: #2E59D9; font-weight: bold;">MJS - Ardayda</h1>
    </div>
    <div>
        <button class="btn btn-danger mr-1" onclick="downloadPDF()" title="Save as PDF">
            <i class="fas fa-file-pdf"></i> PDF
        </button>
        <button class="btn btn-primary mr-1" style="background-color: #2E59D9; border-color: #2E59D9;" onclick="downloadDOCX()" title="Save as Word Document">
            <i class="fas fa-file-word"></i> DOCX
        </button>
        <button class="btn btn-success mr-2" onclick="downloadExcel()" title="Save as Excel Sheet">
            <i class="fas fa-file-excel"></i> Excel
        </button>
        <button class="btn btn-info mr-2" onclick="document.getElementById('importCsvModal').style.display='flex'">
            <i class="fas fa-file-csv"></i> Import CSV
        </button>
        <button class="btn btn-primary" style="background-color: #2E59D9; border-color: #2E59D9;" onclick="document.getElementById('createStudentModal').style.display='flex'"> + Arday Cusub</button>
    </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

<div id="bulkActionBar" class="bulk-action-bar">
    <form method="POST" action="" id="bulkActionForm" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <span>Ardayda la xushay: <span id="selectedCount" class="selected-count">0</span></span>
        
        <button type="submit" name="bulk_action" value="disable" class="btn btn-warning btn-sm" onclick="return confirm('Ma hubtaa inaad xanibto ardaydan?')">
            <i class="fas fa-ban"></i> Xanib !
        </button>
        
        <button type="submit" name="bulk_action" value="enable" class="btn btn-success btn-sm" onclick="return confirm('Ma hubtaa inaad fuliso ardaydan?')">
            <i class="fas fa-check"></i> Ka qaad Xanibida
        </button>
        
        <button type="button" class="btn btn-secondary btn-sm" onclick="clearAllCheckboxes()">
            <i class="fas fa-times"></i> Nadiifi
        </button>
    </form>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="form-inline" style="gap: 10px; flex-wrap: wrap;">
            <div class="form-group mb-2" style="flex: 1; min-width: 200px;">
                <input type="text" name="search" class="form-control w-100" placeholder="Magaca, Jinsi, Fasal, Room..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="form-group mb-2">
                <select name="class_id" class="form-control">
                    <option value="">Dhammaan Fasalka</option>
                    <?php foreach ($enabled_classes as $class): ?>
                    <option value="<?php echo $class['id']; ?>" <?php echo ($filter_class == $class['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($class['class_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group mb-2">
                <select name="room_id" class="form-control">
                    <option value="">Dhammaan Rooms</option>
                    <?php foreach ($rooms as $room): ?>
                    <option value="<?php echo $room['id']; ?>" <?php echo ($filter_room == $room['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($room['room_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group mb-2">
                <label style="margin-right: 10px;">
                    <input type="checkbox" name="show_disabled" value="1" <?php echo $show_disabled ? 'checked' : ''; ?>>
                    Xakamee
                </label>
            </div>
            
            <button type="submit" class="btn btn-info mb-2" style="background-color: #2E59D9; border-color: #2E59D9;">
                <i class="fas fa-search"></i> Raadi
            </button>
            
            <a href="?" class="btn btn-secondary mb-2">
                <i class="fas fa-times"></i> Nadiifi
            </a>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm" style="border-top: 4px solid #2E59D9 !important;">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table" id="studentsTable">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleAllCheckboxes(this)">
                        </th>
                        <th>Sawir</th>
                        <th>Magaca</th>
                        <th>Fasal</th>
                        <th>Room</th>
                        <th>Jinsi</th>
                        <th>Maareen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($students) > 0): ?>
                        <?php foreach ($students as $student): ?>
                        <tr <?php echo $student['is_disabled'] ? 'class="disabled-row"' : ''; ?>>
                            <td>
                                <input type="checkbox" class="student-checkbox" name="selected_students[]" form="bulkActionForm" value="<?php echo $student['id']; ?>" onchange="updateBulkActionBar()">
                            </td>
                            <td>
                                <?php 
                                $photo_path = "images/students/" . $student['photo'];
                                if (!file_exists($photo_path) || empty($student['photo'])) {
                                    $photo_path = "assets/img/default_student.png";
                                }
                                ?>
                                <img src="<?php echo $photo_path; ?>" width="40" height="40" class="rounded-circle" style="object-fit: cover;">
                            </td>
                            <td>
                                <?php echo htmlspecialchars($student['full_name']); ?>
                                <?php if ($student['is_disabled']): ?>
                                    <span class="disabled-badge">XANIBAN</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($student['class_name'] ?? ''); ?>
                                <?php 
                                // Check if the class is disabled
                                $class_stmt = $pdo->prepare("SELECT is_disabled FROM classes WHERE id = ?");
                                $class_stmt->execute([$student['class_id']]);
                                $class_info = $class_stmt->fetch();
                                if ($class_info && $class_info['is_disabled']): 
                                ?>
                                    <span class="class-status-badge">XANIBAN</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($student['room_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($student['parent_contact']); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-info" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($student)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <?php if ($student['is_disabled']): ?>
                                    <a href="?toggle_student_status=<?php echo $student['id']; ?>" class="btn btn-sm btn-success status-toggle-btn" title="Enable Student"><i class="fas fa-check"></i> Fulin</a>
                                <?php else: ?>
                                    <a href="?toggle_student_status=<?php echo $student['id']; ?>" class="btn btn-sm btn-warning status-toggle-btn" title="Disable Student"><i class="fas fa-ban"></i> Xanib</a>
                                <?php endif; ?>
                                
                                <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteModal(<?php echo $student['id']; ?>, '<?php echo addslashes($student['full_name']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">Ardayda lama helin. (No students found.)</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="createStudentModal" class="modal-overlay" style="display:none;">
    <div class="popup-modal">
        <div class="popup-icon">👨‍🎓</div>
        <div class="popup-title">Kudar Arday Cusub</div>
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="popup-form-group">
                <label>Magaca buuxa</label>
                <input type="text" name="full_name" class="popup-input" required>
            </div>
            <div class="popup-form-group">
                <label>Fasalka</label>
                <select name="class_id" class="popup-input" required>
                    <option value="">-- Dooro Fasal --</option>
                    <?php foreach ($enabled_classes as $class): ?>
                    <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="popup-form-group">
                <label>Room</label>
                <select name="room_id" class="popup-input" required>
                    <option value="">-- Dooro Room --</option>
                    <?php foreach ($rooms as $room): ?>
                    <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['room_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="popup-form-group">
                <label>Jinsi</label>
                <input type="text" name="parent_contact" class="popup-input">
            </div>
            <div class="popup-form-group">
                <label>Sawir Ardayga</label>
                <input type="file" name="photo" class="popup-input" accept="image/*" style="background: transparent; color: white; padding-left: 0;">
            </div>
            <div class="popup-btn-container">
                <button type="button" class="popup-btn popup-btn-cancel" onclick="document.getElementById('createStudentModal').style.display='none'">Ka laabo</button>
                <button type="submit" name="create_student" class="popup-btn popup-btn-confirm">Keydi Xogta</button>
            </div>
        </form>
    </div>
</div>

<div id="editStudentModal" class="modal-overlay" style="display:none;">
    <div class="popup-modal">
        <div class="popup-icon">✏️</div>
        <div class="popup-title">Wax ka Beddel Ardayga</div>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="update_student_id" id="edit_student_id">
            <div class="popup-form-group">
                <label>Magaca buuxa</label>
                <input type="text" name="full_name" id="edit_full_name" class="popup-input" required>
            </div>
            <div class="popup-form-group">
                <label>Fasalka</label>
                <select name="class_id" id="edit_class_id" class="popup-input" required>
                    <?php foreach ($enabled_classes as $class): ?>
                    <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="popup-form-group">
                <label>Room</label>
                <select name="room_id" id="edit_room_id" class="popup-input" required>
                    <?php foreach ($rooms as $room): ?>
                    <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['room_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="popup-form-group">
                <label>Jinsi</label>
                <input type="text" name="parent_contact" id="edit_parent_contact" class="popup-input">
            </div>
            <div class="popup-form-group">
                <label>Bedel Sawirka (Optional)</label>
                <input type="file" name="photo" class="popup-input" accept="image/*" style="background: transparent; color: white; padding-left: 0;">
            </div>
            <div class="popup-btn-container">
                <button type="button" class="popup-btn popup-btn-cancel" onclick="document.getElementById('editStudentModal').style.display='none'">Ka laabo</button>
                <button type="submit" class="popup-btn popup-btn-confirm">Cusboonaysii</button>
            </div>
        </form>
    </div>
</div>

<div id="deleteStudentModal" class="modal-overlay" style="display:none;">
    <div class="popup-modal">
        <div class="popup-icon">🗑️</div>
        <div class="popup-title">Tirtir Ardayga</div>
        <p class="popup-message">Ma hubtaa inaad rabto inaad tirtirto ardaygan? <br><b id="delete_student_name"></b></p>
        <form method="POST" action="">
            <input type="hidden" name="delete_student_id" id="delete_student_id">
            <div class="popup-btn-container">
                <button type="button" class="popup-btn popup-btn-cancel" onclick="document.getElementById('deleteStudentModal').style.display='none'">Maya, Jooji</button>
                <button type="submit" class="popup-btn popup-btn-delete">Haa, Tirtir</button>
            </div>
        </form>
    </div>
</div>

<div id="importCsvModal" class="modal-overlay" style="display:none;">
    <div class="popup-modal">
        <div class="popup-icon">📥</div>
        <div class="popup-title">Soo Dhoofi CSV</div>
        <p class="popup-message" style="font-size: 0.85rem;">Format-ka CSV: <b>Magaca Buuxa, Magaca Fasalka, Magaca Room-ka, Jinsiga</b>.</p>
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="popup-form-group">
                <label>Dooro Feylka CSV</label>
                <input type="file" name="csv_file" class="popup-input" accept=".csv" required style="background: transparent; color: white; padding-left: 0;">
            </div>
            <div class="popup-btn-container">
                <button type="button" class="popup-btn popup-btn-cancel" onclick="document.getElementById('importCsvModal').style.display='none'">Ka laabo</button>
                <button type="submit" name="import_csv" class="popup-btn popup-btn-confirm">Soo Dhoofi</button>
            </div>
        </form>
    </div>
</div>

<script>
// ==========================================
// CONFIGURATION - UPDATE YOUR LOGO URL HERE
// ==========================================
const MJS_LOGO_URL = 'https://res.cloudinary.com/dsnlshm9k/image/upload/v1734294137/LOGO_LAST_SAX_2024-2025_rkln16.png'; // <-- LOGO UPDATED

// --- Modals Logic ---
function openEditModal(student) {
    document.getElementById('edit_student_id').value = student.id;
    document.getElementById('edit_full_name').value = student.full_name;
    document.getElementById('edit_class_id').value = student.class_id;
    document.getElementById('edit_room_id').value = student.room_id;
    document.getElementById('edit_parent_contact').value = student.parent_contact;
    document.getElementById('editStudentModal').style.display = 'flex';
}

function openDeleteModal(id, name) {
    document.getElementById('delete_student_id').value = id;
    document.getElementById('delete_student_name').innerText = name;
    document.getElementById('deleteStudentModal').style.display = 'flex'; // Fix: Completed the broken tag
}

// --- Bulk Selection Logic ---
function toggleAllCheckboxes(source) {
    let checkboxes = document.querySelectorAll('.student-checkbox');
    for (let i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
    updateBulkActionBar();
}

function clearAllCheckboxes() {
    document.getElementById('selectAllCheckbox').checked = false;
    let checkboxes = document.querySelectorAll('.student-checkbox');
    for (let i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = false;
    }
    updateBulkActionBar();
}

function updateBulkActionBar() {
    let checkboxes = document.querySelectorAll('.student-checkbox:checked');
    let bulkBar = document.getElementById('bulkActionBar');
    let countSpan = document.getElementById('selectedCount');
    
    countSpan.innerText = checkboxes.length;
    if (checkboxes.length > 0) {
        bulkBar.classList.add('active');
    } else {
        bulkBar.classList.remove('active');
    }
}

// --- Report Export Functions ---
function downloadPDF() {
    let exportHTML = `
        <div style="padding: 20px; font-family: Arial, sans-serif;">
            <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #2E59D9; padding-bottom: 15px;">
                <img src="${MJS_LOGO_URL}" style="max-height: 80px; margin-bottom: 10px;">
                <h2 style="color: #2E59D9; margin: 0;">Liiska Ardayda</h2>
                <p style="margin: 5px 0 0 0; color: #555;">Taariikhda: ${MJS_DATE} | Tirada Ardayda: ${MJS_TOTAL}</p>
            </div>
            <table style="width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px;">
                <thead>
                    <tr style="background-color: #2E59D9; color: white;">
                        <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Magaca</th>
                        <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Fasal</th>
                        <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Room</th>
                        <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Jinsi</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    MJS_STUDENTS.forEach(s => {
        let disabledText = s.is_disabled ? ' <span style="color:red; font-size:10px;">(Xaniban)</span>' : '';
        exportHTML += `
            <tr>
                <td style="border: 1px solid #ddd; padding: 8px;">${s.full_name}${disabledText}</td>
                <td style="border: 1px solid #ddd; padding: 8px;">${s.class_name}</td>
                <td style="border: 1px solid #ddd; padding: 8px;">${s.room_name}</td>
                <td style="border: 1px solid #ddd; padding: 8px;">${s.parent_contact}</td>
            </tr>
        `;
    });
    
    exportHTML += `</tbody></table></div>`;
    
    let opt = {
        margin:       [0.5, 0.5, 0.5, 0.5],
        filename:     'MJS_Ardayda.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true },
        jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
    };
    
    html2pdf().set(opt).from(exportHTML).save();
}

function downloadDOCX() {
    let header = "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'><head><meta charset='utf-8'><title>MJS Ardayda Report</title></head><body>";
    let footer = "</body></html>";
    
    let sourceHTML = `
        <div style="text-align: center;">
            <img src="${MJS_LOGO_URL}" width="120" height="120">
            <h2 style="color: #2E59D9;">Liiska Ardayda</h2>
            <p>Taariikhda: ${MJS_DATE} | Tirada Guud: ${MJS_TOTAL}</p>
        </div>
        <table border="1" style="width: 100%; border-collapse: collapse;">
            <tr style="background-color: #2E59D9; color: white;">
                <th>Magaca</th><th>Fasal</th><th>Room</th><th>Jinsi</th><th>Xaalada</th>
            </tr>`;
            
    MJS_STUDENTS.forEach(s => {
        let statusText = s.is_disabled ? "Xaniban" : "Firfircoon";
        sourceHTML += `<tr>
            <td>${s.full_name}</td><td>${s.class_name}</td><td>${s.room_name}</td><td>${s.parent_contact}</td><td>${statusText}</td>
        </tr>`;
    });
    
    sourceHTML += `</table>`;
    
    let source = header + sourceHTML + footer;
    let sourceData = "data:application/vnd.ms-word;charset=utf-8," + encodeURIComponent(source);
    let fileDownload = document.createElement("a");
    document.body.appendChild(fileDownload);
    fileDownload.href = sourceData;
    fileDownload.download = 'MJS_Ardayda_Report.doc';
    fileDownload.click();
    document.body.removeChild(fileDownload);
}

function downloadExcel() {
    let wb = XLSX.utils.book_new();
    let ws_data = [
        ["Magaca Buuxa", "Fasal", "Room", "Jinsi", "Xaalada"]
    ];
    
    MJS_STUDENTS.forEach(s => {
        ws_data.push([
            s.full_name,
            s.class_name,
            s.room_name,
            s.parent_contact,
            s.is_disabled ? "Xaniban" : "Firfircoon"
        ]);
    });
    
    let ws = XLSX.utils.aoa_to_sheet(ws_data);
    XLSX.utils.book_append_sheet(wb, ws, "Ardayda");
    XLSX.writeFile(wb, "MJS_Ardayda_Report.xlsx");
}
</script>