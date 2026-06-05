<?php
require_once 'includes/header.php';
require_admin();

$error = '';
$success = '';

// Get all teachers (non-admin users)
$teachers_query = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'teacher' ORDER BY full_name ASC");
$teachers = $teachers_query->fetchAll();

// Define available pages for teachers
$available_pages = [
    'dashboard.php' => 'Dashboard',
    'dashboard_periods.php' => 'Imtixan La baabashay',
    'mark_attendance.php' => 'Mark Attendance',
    'update_attendance.php' => 'Update Attendance',
    'reports.php' => 'Reports',
    'attendance_report.php' => 'Attendance Report',
    'profile.php' => 'Profile',
    'rooms.php' => 'Rooms',
    'classes.php' => 'Classes',
    'students.php' => 'Students',
    'rooms_attendance_status.php' => 'Rooms Status (Red/Green)',
    'room_summary.php' => 'Room Summary'
];

// Handle page assignment update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pages'])) {
    $user_id = (int)$_POST['user_id'];
    $selected_pages = isset($_POST['pages']) ? $_POST['pages'] : [];

    try {
        $pdo->beginTransaction();
        
        // Delete existing page assignments for this user
        $pdo->prepare("DELETE FROM teacher_pages WHERE user_id = ?")->execute([$user_id]);
        
        // Insert new page assignments
        $stmt = $pdo->prepare("INSERT INTO teacher_pages (user_id, page_name) VALUES (?, ?)");
        foreach ($selected_pages as $page) {
            if (array_key_exists($page, $available_pages)) {
                $stmt->execute([$user_id, $page]);
            }
        }
        
        $pdo->commit();
        $success = "Bogagga macallinka waa la cusboonaysiiyey!";
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Khalad: " . $e->getMessage();
    }
}

// Get currently selected teacher (if any)
$selected_teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : null;
$selected_teacher = null;
$teacher_pages = [];

if ($selected_teacher_id) {
    // Get teacher info
    $stmt = $pdo->prepare("SELECT id, full_name, username FROM users WHERE id = ? AND role = 'teacher'");
    $stmt->execute([$selected_teacher_id]);
    $selected_teacher = $stmt->fetch();
    
    // Get assigned pages
    if ($selected_teacher) {
        $stmt = $pdo->prepare("SELECT page_name FROM teacher_pages WHERE user_id = ?");
        $stmt->execute([$selected_teacher_id]);
        $teacher_pages = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>

<style>
    .page-management-container {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 20px;
        margin-top: 20px;
    }

    @media (max-width: 1024px) {
        .page-management-container {
            grid-template-columns: 1fr;
        }
    }

    .teacher-list {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .teacher-list-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        font-weight: 600;
        font-size: 16px;
    }

    .teacher-list-body {
        max-height: 600px;
        overflow-y: auto;
    }

    .teacher-item {
        padding: 15px 20px;
        border-bottom: 1px solid #e2e8f0;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .teacher-item:hover {
        background: #f7fafc;
        padding-left: 25px;
    }

    .teacher-item.active {
        background: #edf2f7;
        border-left: 4px solid #667eea;
        padding-left: 16px;
    }

    .teacher-info {
        flex: 1;
    }

    .teacher-name {
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 3px;
    }

    .teacher-username {
        font-size: 12px;
        color: #718096;
    }

    .page-editor {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        padding: 30px;
    }

    .page-editor-header {
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid #e2e8f0;
    }

    .page-editor-title {
        font-size: 24px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 5px;
    }

    .page-editor-subtitle {
        color: #718096;
        font-size: 14px;
    }

    .pages-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }

    .page-checkbox-wrapper {
        display: flex;
        align-items: center;
        padding: 15px;
        background: #f7fafc;
        border-radius: 8px;
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .page-checkbox-wrapper:hover {
        border-color: #667eea;
        background: #f0f4ff;
    }

    .page-checkbox-wrapper input[type="checkbox"] {
        margin-right: 12px;
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    .page-checkbox-wrapper input[type="checkbox"]:checked + label {
        color: #667eea;
        font-weight: 600;
    }

    .page-checkbox-wrapper label {
        margin: 0;
        cursor: pointer;
        flex: 1;
        color: #2d3748;
    }

    .page-checkbox-wrapper input[type="checkbox"]:checked {
        accent-color: #667eea;
    }

    .empty-state {
        text-align: center;
        padding: 60px 30px;
        color: #718096;
    }

    .empty-state-icon {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.5;
    }

    .empty-state-text {
        font-size: 16px;
        margin-bottom: 10px;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 2px solid #e2e8f0;
    }

    .btn-save {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
    }

    .btn-save:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    .btn-cancel {
        background: #e2e8f0;
        color: #2d3748;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-cancel:hover {
        background: #cbd5e0;
    }

    .select-all-wrapper {
        margin-bottom: 20px;
        padding: 15px;
        background: #f0f4ff;
        border-radius: 8px;
        border: 1px solid #667eea;
    }

    .select-all-wrapper label {
        display: flex;
        align-items: center;
        cursor: pointer;
        margin: 0;
        font-weight: 600;
        color: #667eea;
    }

    .select-all-wrapper input[type="checkbox"] {
        margin-right: 10px;
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: #667eea;
    }
</style>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Bogagga Macallimiinta</h1>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
    </div>
<?php endif; ?>

<div class="page-management-container">
    <!-- Teacher List -->
    <div class="teacher-list">
        <div class="teacher-list-header">
            <i class="fas fa-users"></i> Macallimiinta
        </div>
        <div class="teacher-list-body">
            <?php if (empty($teachers)): ?>
                <div style="padding: 20px; text-align: center; color: #718096;">
                    <i class="fas fa-inbox"></i> Macallim la'aan
                </div>
            <?php else: ?>
                <?php foreach ($teachers as $teacher): ?>
                    <a href="?teacher_id=<?php echo $teacher['id']; ?>" style="text-decoration: none; color: inherit;">
                        <div class="teacher-item <?php echo ($selected_teacher_id === $teacher['id']) ? 'active' : ''; ?>">
                            <div class="teacher-info">
                                <div class="teacher-name"><?php echo htmlspecialchars($teacher['full_name']); ?></div>
                                <div class="teacher-username">@<?php echo htmlspecialchars($teacher['username']); ?></div>
                            </div>
                            <div style="color: #cbd5e0;">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Page Editor -->
    <div class="page-editor">
        <?php if ($selected_teacher): ?>
            <form method="POST" action="">
                <div class="page-editor-header">
                    <div class="page-editor-title">
                        <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($selected_teacher['full_name']); ?>
                    </div>
                    <div class="page-editor-subtitle">
                        Dooro bogagga uu macallimku ku gali karo
                    </div>
                </div>

                <input type="hidden" name="user_id" value="<?php echo $selected_teacher['id']; ?>">

                <!-- Select All / Deselect All -->
                <div class="select-all-wrapper">
                    <label>
                        <input type="checkbox" id="selectAllPages" onchange="toggleAllPages()">
                        Xulo Ama Xir Dhamaan Bogagga
                    </label>
                </div>

                <!-- Pages Grid -->
                <div class="pages-grid">
                    <?php foreach ($available_pages as $page_name => $page_label): ?>
                        <div class="page-checkbox-wrapper">
                            <input 
                                type="checkbox" 
                                name="pages[]" 
                                value="<?php echo htmlspecialchars($page_name); ?>"
                                id="page_<?php echo htmlspecialchars($page_name); ?>"
                                class="page-checkbox"
                                <?php echo in_array($page_name, $teacher_pages) ? 'checked' : ''; ?>
                            >
                            <label for="page_<?php echo htmlspecialchars($page_name); ?>">
                                <?php echo htmlspecialchars($page_label); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="manage_teacher_pages.php" class="btn-cancel">
                        <i class="fas fa-times"></i> Jooji
                    </a>
                    <button type="submit" name="update_pages" class="btn-save">
                        <i class="fas fa-save"></i> Keedi Xogta
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-hand-pointer"></i>
                </div>
                <div class="empty-state-text">Macallim dooro si aad u xakamaynto bogaggiisa</div>
                <div style="font-size: 14px; color: #a0aec0;">
                    Macallim ku click garee liiska bidix
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function toggleAllPages() {
        const selectAllCheckbox = document.getElementById('selectAllPages');
        const pageCheckboxes = document.querySelectorAll('.page-checkbox');
        
        pageCheckboxes.forEach(checkbox => {
            checkbox.checked = selectAllCheckbox.checked;
        });
    }

    // Update select all checkbox state when individual checkboxes change
    document.querySelectorAll('.page-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const selectAllCheckbox = document.getElementById('selectAllPages');
            const pageCheckboxes = document.querySelectorAll('.page-checkbox');
            const allChecked = Array.from(pageCheckboxes).every(cb => cb.checked);
            const someChecked = Array.from(pageCheckboxes).some(cb => cb.checked);
            
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = someChecked && !allChecked;
        });
    });

    // Initialize select all checkbox state on page load
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllCheckbox = document.getElementById('selectAllPages');
        const pageCheckboxes = document.querySelectorAll('.page-checkbox');
        const allChecked = Array.from(pageCheckboxes).every(cb => cb.checked);
        const someChecked = Array.from(pageCheckboxes).some(cb => cb.checked);
        
        selectAllCheckbox.checked = allChecked;
        selectAllCheckbox.indeterminate = someChecked && !allChecked;
    });
</script>

<?php require_once 'includes/footer.php'; ?>
