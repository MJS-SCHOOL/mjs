<?php
require_once 'includes/header.php';
require_admin();

// Get all classes for the dropdown
$classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name ASC")->fetchAll();

// Handle class selection
$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : (count($classes) > 0 ? $classes[0]['id'] : 0);

// Fetch selected class data
$selected_class_name = '';
$total_students_in_class = 0;
$rooms_data = [];

if ($selected_class_id > 0) {
    // Get class name and total students
    $stmt = $pdo->prepare("SELECT class_name FROM classes WHERE id = ?");
    $stmt->execute([$selected_class_id]);
    $class = $stmt->fetch();
    if ($class) {
        $selected_class_name = $class['class_name'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class_id = ? AND is_disabled = 0");
        $stmt->execute([$selected_class_id]);
        $total_students_in_class = $stmt->fetchColumn();

        // Get rooms and their student counts for this class
        $stmt = $pdo->prepare("
            SELECT r.room_name, COUNT(s.id) as students_in_room
            FROM rooms r
            JOIN students s ON r.id = s.room_id
            WHERE s.class_id = ? AND s.is_disabled = 0
            GROUP BY r.id, r.room_name
            ORDER BY r.room_name ASC
        ");
        $stmt->execute([$selected_class_id]);
        $rooms_data = $stmt->fetchAll();
    }
}
?>

<style>
    .admin-dashboard-header {
        background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
        padding: 30px;
        border-radius: 15px;
        color: white;
        margin-bottom: 30px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }
    .filter-section {
        background: #fff;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    [data-theme="dark"] .filter-section {
        background: #24262d;
    }
    .data-card {
        background: #fff;
        padding: 25px;
        border-radius: 15px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }
    [data-theme="dark"] .data-card {
        background: #24262d;
    }
    .stat-row {
        display: flex;
        justify-content: space-between;
        padding: 15px 0;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    [data-theme="dark"] .stat-row {
        border-bottom-color: rgba(255,255,255,0.05);
    }
    .stat-label {
        font-weight: 700;
        color: #4e73df;
    }
    .stat-value {
        font-weight: 800;
        font-size: 1.1rem;
    }
    .room-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    .room-card {
        background: #f8f9fc;
        padding: 20px;
        border-radius: 12px;
        border-left: 5px solid #1cc88a;
    }
    [data-theme="dark"] .room-card {
        background: #2d2f39;
    }
    .room-name {
        font-weight: 800;
        font-size: 1.2rem;
        margin-bottom: 10px;
        color: #1cc88a;
    }
    .room-count {
        font-size: 0.9rem;
        color: #858796;
    }
    .room-count strong {
        font-size: 1.2rem;
        color: var(--text-color);
    }
    .select-style {
        padding: 10px 15px;
        border-radius: 8px;
        border: 1px solid #ddd;
        min-width: 200px;
        font-size: 1rem;
    }
</style>

<div class="admin-dashboard-header">
    <h1><i class="fas fa-chart-bar mr-2"></i> <?php echo __('admin_dashboard'); ?></h1>
    <p><?php echo $_SESSION['lang'] === 'so' ? 'Maamulka Fasalada iyo Qololka' : ($_SESSION['lang'] === 'ar' ? 'إدارة الفصول والغرف' : 'Class and Room Management'); ?></p>
</div>

<div class="filter-section">
    <label for="classSelect" style="font-weight: 700;"><?php echo __('class'); ?>:</label>
    <select id="classSelect" class="select-style" onchange="location.href='admin_dashboard.php?class_id=' + this.value">
        <?php foreach ($classes as $class): ?>
            <option value="<?php echo $class['id']; ?>" <?php echo $selected_class_id == $class['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($class['class_name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<?php if ($selected_class_id > 0): ?>
    <div class="data-card">
        <div class="stat-row">
            <span class="stat-label"><?php echo $_SESSION['lang'] === 'so' ? 'Magaca Fasalka' : ($_SESSION['lang'] === 'ar' ? 'اسم الفصل' : 'Class Name'); ?>:</span>
            <span class="stat-value"><?php echo htmlspecialchars($selected_class_name); ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label"><?php echo $_SESSION['lang'] === 'so' ? 'Wadarta Ardayda Fasalka' : ($_SESSION['lang'] === 'ar' ? 'إجمالي طلاب الفصل' : 'Total Students in Class'); ?>:</span>
            <span class="stat-value"><?php echo $total_students_in_class; ?></span>
        </div>
    </div>

    <h3 style="margin: 30px 0 20px; font-weight: 800; color: #4e73df;">
        <i class="fas fa-door-open mr-2"></i> <?php echo $_SESSION['lang'] === 'so' ? 'Qololka Fasalka' : ($_SESSION['lang'] === 'ar' ? 'غرف الفصل' : 'Class Rooms'); ?>
    </h3>

    <div class="room-grid">
        <?php if (count($rooms_data) > 0): ?>
            <?php foreach ($rooms_data as $room): ?>
                <div class="room-card">
                    <div class="room-name"><?php echo htmlspecialchars($room['room_name']); ?></div>
                    <div class="room-count">
                        <?php echo $_SESSION['lang'] === 'so' ? 'Ardayda Qolka' : ($_SESSION['lang'] === 'ar' ? 'طلاب الغرفة' : 'Students in Room'); ?>: 
                        <strong><?php echo $room['students_in_room']; ?></strong>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="data-card" style="grid-column: 1 / -1; text-align: center; color: #999;">
                <?php echo $_SESSION['lang'] === 'so' ? 'Wax xog ah lagama helin fasalkan.' : ($_SESSION['lang'] === 'ar' ? 'لم يتم العثور على بيانات لهذا الفصل.' : 'No data found for this class.'); ?>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="data-card" style="text-align: center; color: #999; padding: 50px;">
        <i class="fas fa-info-circle fa-3x mb-3"></i>
        <p><?php echo $_SESSION['lang'] === 'so' ? 'Fadlan dooro fasal si aad u aragto xogta.' : ($_SESSION['lang'] === 'ar' ? 'يرجى اختيار فصل لعرض البيانات.' : 'Please select a class to view data.'); ?></p>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
