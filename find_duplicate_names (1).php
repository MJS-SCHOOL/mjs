<?php
// Start output buffering to prevent header.php from sending content to the browser early
ob_start();

require_once 'includes/header.php';
require_admin();

$error = '';
$success = '';
$search_query = '';
$duplicates = [];
$statistics = [];

// ==========================================
// CRUD OPERATIONS: HANDLE POST ACTIONS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CREATE ACTION
    if ($_POST['action'] === 'create') {
        try {
            $full_name = trim($_POST['full_name']);
            $class_id = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;
            $room_id = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
            $parent_contact = trim($_POST['parent_contact']);

            if (empty($full_name)) {
                $error = "Full name is required.";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO students (full_name, class_id, room_id, parent_contact, is_disabled, created_at) 
                    VALUES (:full_name, :class_id, :room_id, :parent_contact, 0, NOW())
                ");
                $stmt->execute([
                    ':full_name' => $full_name,
                    ':class_id' => $class_id,
                    ':room_id' => $room_id,
                    ':parent_contact' => $parent_contact
                ]);
                $success = "New student record created successfully!";
            }
        } catch (PDOException $e) {
            $error = "Creation error: " . $e->getMessage();
        }
    }

    // UPDATE ACTION
    if ($_POST['action'] === 'update') {
        try {
            $id = (int)$_POST['student_id'];
            $full_name = trim($_POST['full_name']);
            $class_id = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;
            $room_id = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
            $parent_contact = trim($_POST['parent_contact']);
            $is_disabled = isset($_POST['is_disabled']) ? (int)$_POST['is_disabled'] : 0;

            if (empty($full_name)) {
                $error = "Full name cannot be empty.";
            } else {
                $stmt = $pdo->prepare("
                    UPDATE students 
                    SET full_name = :full_name, class_id = :class_id, room_id = :room_id, parent_contact = :parent_contact, is_disabled = :is_disabled 
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':full_name' => $full_name,
                    ':class_id' => $class_id,
                    ':room_id' => $room_id,
                    ':parent_contact' => $parent_contact,
                    ':is_disabled' => $is_disabled,
                    ':id' => $id
                ]);
                $success = "Student record updated successfully!";
            }
        } catch (PDOException $e) {
            $error = "Update error: " . $e->getMessage();
        }
    }

    // DELETE ACTION
    if ($_POST['action'] === 'delete') {
        try {
            $id = (int)$_POST['student_id'];
            $stmt = $pdo->prepare("DELETE FROM students WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $success = "Student record permanently deleted!";
        } catch (PDOException $e) {
            $error = "Deletion error: " . $e->getMessage();
        }
    }
}

// ==========================================
// FETCH SELECTION OPTIONS FOR DROPDOWNS
// ==========================================
$all_classes = [];
$all_rooms = [];
try {
    $all_classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name ASC")->fetchAll();
    $all_rooms = $pdo->query("SELECT id, room_name FROM rooms ORDER BY room_name ASC")->fetchAll();
} catch (PDOException $e) {
    // Graceful backup if relative tables are unpopulated
}

// ==========================================
// 1. FETCH DUPLICATE NAMES (READ)
// ==========================================
try {
    // Get all students with name counts
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.full_name,
            s.class_id,
            s.room_id,
            s.parent_contact,
            s.photo,
            s.is_disabled,
            s.created_at,
            c.class_name,
            r.room_name,
            COUNT(*) OVER (PARTITION BY LOWER(TRIM(s.full_name))) as name_count
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN rooms r ON s.room_id = r.id
        WHERE s.is_disabled = 0
        ORDER BY LOWER(TRIM(s.full_name)), s.id
    ");
    $stmt->execute();
    $all_students = $stmt->fetchAll();
    
    // Group students by name (case-insensitive, trimmed)
    $grouped = [];
    foreach ($all_students as $student) {
        $normalized_name = strtolower(trim($student['full_name']));
        if (!isset($grouped[$normalized_name])) {
            $grouped[$normalized_name] = [];
        }
        $grouped[$normalized_name][] = $student;
    }
    
    // Filter only duplicates
    foreach ($grouped as $name => $students) {
        if (count($students) > 1) {
            $duplicates[$name] = $students;
        }
    }
    
    // Calculate statistics
    $total_students = count($all_students);
    $duplicate_groups = count($duplicates);
    $students_with_duplicates = 0;
    
    foreach ($duplicates as $group) {
        $students_with_duplicates += count($group);
    }
    
    $statistics = [
        'total_students' => $total_students,
        'duplicate_groups' => $duplicate_groups,
        'students_with_duplicates' => $students_with_duplicates,
        'duplicate_percentage' => $total_students > 0 ? round(($students_with_duplicates / $total_students) * 100, 2) : 0
    ];
    
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
}

// ==========================================
// 2. HANDLE SEARCH FILTER
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $search_query = sanitize($_GET['search']);
    
    // Filter duplicates by search query
    $filtered_duplicates = [];
    foreach ($duplicates as $name => $students) {
        if (stripos($name, strtolower($search_query)) !== false) {
            $filtered_duplicates[$name] = $students;
        }
    }
    $duplicates = $filtered_duplicates;
}
?>

<!DOCTYPE html>
<html lang="so">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Duplicate Names - School System</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .stats-card h3 {
            margin: 0 0 10px 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .stats-card .value {
            font-size: 2rem;
            font-weight: bold;
        }
        
        .stats-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .stats-card.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .stats-card.info {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        
        .duplicate-group {
            background: white;
            border-left: 4px solid #f5576c;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .duplicate-group-title {
            font-size: 1.1rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .duplicate-count {
            display: inline-block;
            background: #f5576c;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.85rem;
            margin-left: 10px;
        }
        
        .student-row {
            display: grid;
            grid-template-columns: 50px 1.5fr 1fr 1fr 1.2fr 80px 140px;
            gap: 10px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
            margin-bottom: 8px;
            align-items: center;
            font-size: 0.9rem;
        }
        
        .student-row:hover {
            background: #f0f0f0;
        }
        
        .student-id {
            font-weight: bold;
            color: #667eea;
        }
        
        .student-name {
            color: #333;
            font-weight: 500;
        }
        
        .student-class, .student-room, .student-contact {
            color: #666;
            word-break: break-word;
        }
        
        .student-status {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
            text-align: center;
            display: inline-block;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-disabled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .search-box {
            margin-bottom: 20px;
        }
        
        .search-box input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .search-box button, .btn-primary {
            margin-top: 10px;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .search-box button:hover, .btn-primary:hover {
            background: #5568d3;
        }
        
        .export-btn {
            padding: 10px 20px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-bottom: 20px;
            text-decoration: none;
            display: inline-block;
        }
        
        .export-btn:hover {
            background: #bd2130;
        }

        .add-btn {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-bottom: 20px;
            margin-left: 10px;
            text-decoration: none;
            display: inline-block;
        }

        .add-btn:hover {
            background: #0056b3;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-action {
            padding: 4px 8px;
            font-size: 0.8rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            color: white;
            text-decoration: none;
            text-align: center;
        }

        .btn-edit { background-color: #ffc107; color: #212529; }
        .btn-edit:hover { background-color: #e0a800; }
        .btn-delete { background-color: #dc3545; }
        .btn-delete:hover { background-color: #bd2130; }
        
        .no-duplicates {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            text-align: center;
            margin: 20px 0;
        }
        
        .header-row {
            display: grid;
            grid-template-columns: 50px 1.5fr 1fr 1fr 1.2fr 80px 140px;
            gap: 10px;
            padding: 10px;
            background: #667eea;
            color: white;
            border-radius: 4px;
            margin-bottom: 10px;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        /* CRUD Modal Styles */
        .crud-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }

        .crud-modal-content {
            background-color: #fff;
            padding: 25px;
            border-radius: 6px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .student-row,
            .header-row {
                grid-template-columns: 1fr;
            }
            
            .stats-card {
                margin-bottom: 10px;
            }
            .action-buttons {
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <div style="margin-bottom: 30px;">
                <h1 style="color: #333; margin-bottom: 10px;">
                    <i class="fas fa-search-plus"></i>Magaca Soolaabtay
                </h1>
                <p style="color: #666;">Hubi magacyada soo laalaabtay in ka badan 1 jeer</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
                <div class="stats-card">
                    <h3>Total Students</h3>
                    <div class="value"><?php echo $statistics['total_students'] ?? 0; ?></div>
                </div>
                
                <div class="stats-card warning">
                    <h3>Duplicate Groups</h3>
                    <div class="value"><?php echo $statistics['duplicate_groups'] ?? 0; ?></div>
                </div>
                
                <div class="stats-card success">
                    <h3>Students with Duplicates</h3>
                    <div class="value"><?php echo $statistics['students_with_duplicates'] ?? 0; ?></div>
                </div>
                
                <div class="stats-card info">
                    <h3>Duplicate Percentage</h3>
                    <div class="value"><?php echo $statistics['duplicate_percentage'] ?? 0; ?>%</div>
                </div>
            </div>

            <div class="search-box">
                <form method="GET" style="display: flex; gap: 10px;">
                    <input 
                        type="text" 
                        name="search" 
                        placeholder="Halkan karaadi..." 
                        value="<?php echo htmlspecialchars($search_query); ?>"
                        style="flex: 1;"
                    >
                    <button type="submit" style="width: auto; margin-top: 0;">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if ($search_query): ?>
                        <a href="find_duplicate_names.php" style="padding: 10px 20px; background: #6c757d; color: white; border-radius: 4px; text-decoration: none; display: flex; align-items: center;">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <div>
                <?php if (!empty($duplicates)): ?>
                    <button type="button" class="export-btn" onclick="downloadPDF()">
                        <i class="fas fa-file-pdf"></i> Export to PDF
                    </button>
                <?php endif; ?>
                <button type="button" class="add-btn" onclick="openCreateModal()">
                    <i class="fas fa-plus"></i> Add New Student
                </button>
            </div>

            <div id="pdf-content-area" style="background: white; padding: 5px;">
                <?php if (empty($duplicates)): ?>
                    <div class="no-duplicates">
                        <i class="fas fa-check-circle"></i> No duplicate names found in the system!
                    </div>
                <?php else: ?>
                    <?php foreach ($duplicates as $name => $students): ?>
                        <div class="duplicate-group">
                            <div class="duplicate-group-title">
                                <?php echo htmlspecialchars($name); ?>
                                <span class="duplicate-count"><?php echo count($students); ?> students</span>
                            </div>
                            
                            <div class="header-row">
                                <div>ID</div>
                                <div>Full Name</div>
                                <div>Class</div>
                                <div>Room</div>
                                <div>Contact</div>
                                <div>Status</div>
                                <div class="action-column">Actions</div>
                            </div>
                            
                            <?php foreach ($students as $student): ?>
                                <div class="student-row">
                                    <div class="student-id"><?php echo htmlspecialchars($student['id']); ?></div>
                                    <div class="student-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                    <div class="student-class"><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></div>
                                    <div class="student-room"><?php echo htmlspecialchars($student['room_name'] ?? 'N/A'); ?></div>
                                    <div class="student-contact"><?php echo htmlspecialchars($student['parent_contact'] ?? 'N/A'); ?></div>
                                    <div>
                                        <span class="student-status <?php echo $student['is_disabled'] ? 'status-disabled' : 'status-active'; ?>">
                                            <?php echo $student['is_disabled'] ? 'Disabled' : 'Active'; ?>
                                        </span>
                                    </div>
                                    <div class="action-buttons">
                                        <button type="button" class="btn-action btn-edit" 
                                                onclick='openEditModal(<?php echo json_encode($student); ?>)'>
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to permanently delete this student record?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <button type="submit" class="btn-action btn-delete">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            </div>
    </div>

    <div id="createModal" class="crud-modal">
        <div class="crud-modal-content">
            <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;"><i class="fas fa-user-plus"></i> Add New Student</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" required>
                </div>
                <div class="form-group">
                    <label>Class ID</label>
                    <select name="class_id">
                        <option value="">-- Select Class --</option>
                        <?php foreach($all_classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Room ID</label>
                    <select name="room_id">
                        <option value="">-- Select Room --</option>
                        <?php foreach($all_rooms as $r): ?>
                            <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['room_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Parent Contact</label>
                    <input type="text" name="parent_contact">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-primary" style="background:#6c757d;" onclick="closeModal('createModal')">Cancel</button>
                    <button type="submit" class="btn-primary" style="background:#007bff;">Create Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="crud-modal">
        <div class="crud-modal-content">
            <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;"><i class="fas fa-user-edit"></i> Edit Student Record</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" id="edit_student_id" name="student_id">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" id="edit_full_name" name="full_name" required>
                </div>
                <div class="form-group">
                    <label>Class ID</label>
                    <select id="edit_class_id" name="class_id">
                        <option value="">-- Select Class --</option>
                        <?php foreach($all_classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Room ID</label>
                    <select id="edit_room_id" name="room_id">
                        <option value="">-- Select Room --</option>
                        <?php foreach($all_rooms as $r): ?>
                            <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['room_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Parent Contact</label>
                    <input type="text" id="edit_parent_contact" name="parent_contact">
                </div>
                <div class="form-group">
                    <label>Account Status</label>
                    <select id="edit_is_disabled" name="is_disabled">
                        <option value="0">Active</option>
                        <option value="1">Disabled</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-primary" style="background:#6c757d;" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn-primary" style="background:#ffc107; color:#212529;">Update Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCreateModal() {
            document.getElementById('createModal').style.display = 'flex';
        }

        function openEditModal(student) {
            document.getElementById('edit_student_id').value = student.id;
            document.getElementById('edit_full_name').value = student.full_name;
            document.getElementById('edit_class_id').value = student.class_id || '';
            document.getElementById('edit_room_id').value = student.room_id || '';
            document.getElementById('edit_parent_contact').value = student.parent_contact || '';
            document.getElementById('edit_is_disabled').value = student.is_disabled;
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close window clicks on background mask
        window.onclick = function(event) {
            if (event.target.classList.contains('crud-modal')) {
                event.target.style.display = 'none';
            }
        }

        // --- NEW PDF EXPORT FUNCTION ---
        function downloadPDF() {
            // Temporarily hide the 'Actions' headers and buttons before making the PDF
            const actionButtons = document.querySelectorAll('.action-buttons');
            const actionColumns = document.querySelectorAll('.action-column');
            
            actionButtons.forEach(el => el.style.display = 'none');
            actionColumns.forEach(el => el.style.display = 'none');

            // Select the content area to export
            const element = document.getElementById('pdf-content-area');
            
            // Configure html2pdf settings
            const opt = {
                margin:       10,
                filename:     'duplicate_names_<?php echo date('Y-m-d_H-i-s'); ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };

            // Generate and save the PDF, then restore the buttons
            html2pdf().set(opt).from(element).save().then(() => {
                actionButtons.forEach(el => el.style.display = 'flex');
                actionColumns.forEach(el => el.style.display = 'block');
            });
        }
    </script>
</body>
</html>

<?php
require_once 'includes/footer.php';
?>