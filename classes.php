<?php
require_once 'includes/header.php';
require_admin();

/* ================================
   DATE FILTER
================================ */
$date_filter = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');

/* ================================
   HANDLE ACTIONS (ADD, EDIT, DELETE)
================================ */
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ADD CLASS
    if (isset($_POST['add_class'])) {
        $class_name = sanitize($_POST['class_name']);
        if (!empty($class_name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO classes (class_name, is_disabled) VALUES (?, 0)");
                $stmt->execute([$class_name]);
                $message = "Fasalka '$class_name' si guul leh ayaa loo daray!";
            } catch (PDOException $e) {
                $error = "Khalad: Fasalkani horey ayuu u jiray ama cilad kale ayaa dhacday.";
            }
        } else {
            $error = "Fadlan geli magaca fasalka.";
        }
    }
    
    // EDIT CLASS
    if (isset($_POST['edit_class'])) {
        $class_id = (int)$_POST['class_id'];
        $class_name = sanitize($_POST['class_name']);
        if ($class_id > 0 && !empty($class_name)) {
            try {
                $stmt = $pdo->prepare("UPDATE classes SET class_name = ? WHERE id = ?");
                $stmt->execute([$class_name, $class_id]);
                $message = "Fasalka si guul leh ayaa loo cusboonaysiiyay!";
            } catch (PDOException $e) {
                $error = "Khalad: Ma suurtagelin in fasalka la cusboonaysiiyo.";
            }
        }
    }
    
    // DELETE CLASS
    if (isset($_POST['delete_class'])) {
        $class_id = (int)$_POST['delete_class_id'];
        if ($class_id > 0) {
            try {
                // Check if students are assigned to this class
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class_id = ?");
                $check_stmt->execute([$class_id]);
                if ($check_stmt->fetchColumn() > 0) {
                    $error = "Fasalkan lama tirtiri karo sababtoo ah waxaa ku jira arday. Fadlan marka hore ardayda ka saar.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
                    $stmt->execute([$class_id]);
                    $message = "Fasalka si guul leh ayaa loo tirtiray!";
                }
            } catch (PDOException $e) {
                $error = "Khalad: Ma suurtagelin in fasalka la tirtiro.";
            }
        }
    }
}

/* ================================
   FETCH CLASSES
================================ */
$classes_stmt = $pdo->query("SELECT * FROM classes ORDER BY class_name ASC");
$classes = $classes_stmt->fetchAll();
?>

<style>
    /* ===== MODERN RESPONSIVE DESIGN ===== */
    
    * {
        box-sizing: border-box;
    }
    
    /* Header Section */
    .classes-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 40px;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .classes-header h1 {
        margin: 0;
        font-size: 2rem;
        font-weight: 800;
        color: #2e3338;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .header-actions {
        display: flex;
        gap: 15px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .btn-add-class {
        background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 1rem;
        box-shadow: 0 4px 12px rgba(78, 115, 223, 0.3);
    }
    
    .btn-add-class:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(78, 115, 223, 0.4);
    }
    
    .date-filter-input {
        padding: 12px 16px;
        border: 2px solid #e3e6f0;
        border-radius: 10px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: white;
    }
    
    .date-filter-input:focus {
        outline: none;
        border-color: #4e73df;
        box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.1);
    }
    
    /* Alert Messages */
    .alert-message {
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideInDown 0.4s ease-out;
        font-weight: 500;
    }
    
    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .alert-danger {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    /* Classes Container */
    .classes-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 24px;
        margin-bottom: 40px;
    }
    
    @media (max-width: 768px) {
        .classes-container {
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
        }
    }
    
    @media (max-width: 480px) {
        .classes-container {
            grid-template-columns: 1fr;
            gap: 12px;
        }
    }
    
    /* Class Card */
    .class-card-wrapper {
        position: relative;
        animation: fadeInUp 0.5s ease-out;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .class-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fc 100%);
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        border: 2px solid transparent;
        cursor: pointer;
        text-decoration: none !important;
        color: inherit !important;
        display: block;
        position: relative;
        overflow: hidden;
    }
    
    .class-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
        transition: left 0.5s ease;
    }
    
    .class-card:hover::before {
        left: 100%;
    }
    
    .class-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 30px rgba(78, 115, 223, 0.2);
        border-color: #4e73df;
    }
    
    /* Card Header */
    .class-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 2px solid #e3e6f0;
    }
    
    .class-title {
        font-size: 1.3rem;
        font-weight: 800;
        color: #4e73df;
        margin: 0;
        flex: 1;
    }
    
    .class-icon {
        font-size: 1.8rem;
        margin-left: 8px;
    }
    
    /* Card Stats */
    .class-stats {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .stat-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        font-size: 0.95rem;
        color: #5a5c69;
    }
    
    .stat-label {
        font-weight: 600;
        color: #2e3338;
    }
    
    .stat-value {
        font-weight: 700;
        color: #4e73df;
        font-size: 1.1rem;
    }
    
    .badge {
        display: inline-block;
        padding: 6px 14px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.85rem;
    }
    
    .badge-present {
        background: rgba(28, 200, 138, 0.15);
        color: #1cc88a;
    }
    
    .badge-absent {
        background: rgba(231, 74, 59, 0.15);
        color: #e74a3b;
    }
    
    /* Progress Bar */
    .progress-section {
        margin-top: 18px;
        padding-top: 18px;
        border-top: 2px solid #e3e6f0;
    }
    
    .progress-bar-custom {
        height: 10px;
        background: #e3e6f0;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 8px;
    }
    
    .progress-fill {
        height: 100%;
        border-radius: 10px;
        transition: width 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        background: linear-gradient(90deg, #4e73df 0%, #224abe 100%);
    }
    
    .attendance-percentage {
        text-align: right;
        font-size: 0.9rem;
        font-weight: 700;
        color: #2e3338;
    }
    
    /* Card Actions */
    .class-actions {
        position: absolute;
        top: 16px;
        right: 16px;
        display: flex;
        gap: 8px;
        z-index: 10;
    }
    
    .btn-action {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        background: white;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .btn-edit {
        color: #4e73df;
    }
    
    .btn-edit:hover {
        background: #4e73df;
        color: white;
        transform: scale(1.1);
    }
    
    .btn-delete {
        color: #e74a3b;
    }
    
    .btn-delete:hover {
        background: #e74a3b;
        color: white;
        transform: scale(1.1);
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #858796;
    }
    
    .empty-state-icon {
        font-size: 4rem;
        margin-bottom: 16px;
        opacity: 0.5;
    }
    
    .empty-state-text {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 8px;
    }
    
    /* Modal Styles */
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
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 20px;
        padding: 40px;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        text-align: center;
        animation: popupBounce 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        position: relative;
        overflow: hidden;
    }
    
    .popup-icon {
        font-size: 4rem;
        margin-bottom: 20px;
        animation: float 1.5s ease-in-out infinite;
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
    
    .popup-title {
        color: white;
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 15px;
    }
    
    .popup-message {
        color: rgba(255, 255, 255, 0.95);
        font-size: 1.1rem;
        margin-bottom: 10px;
        font-weight: 500;
    }
    
    .popup-button {
        background: white;
        color: #667eea;
        border: none;
        padding: 12px 30px;
        border-radius: 25px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
        margin-top: 20px;
    }
    
    .popup-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
    }
    
    .pulse-dot {
        display: inline-block;
        width: 12px;
        height: 12px;
        background: #1cc88a;
        border-radius: 50%;
        margin-right: 8px;
        animation: pulse 1.5s ease-in-out infinite;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.3); opacity: 0.7; }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .classes-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .classes-header h1 {
            font-size: 1.5rem;
        }
        
        .header-actions {
            width: 100%;
            flex-direction: column;
        }
        
        .btn-add-class,
        .date-filter-input {
            width: 100%;
            justify-content: center;
        }
        
        .class-card {
            padding: 18px;
        }
        
        .class-title {
            font-size: 1.1rem;
        }
        
        .class-actions {
            top: 12px;
            right: 12px;
        }
        
        .btn-action {
            width: 32px;
            height: 32px;
            font-size: 0.8rem;
        }
    }
    
    @media (max-width: 480px) {
        .classes-header h1 {
            font-size: 1.3rem;
        }
        
        .class-card {
            padding: 16px;
        }
        
        .class-title {
            font-size: 1rem;
        }
        
        .stat-item {
            font-size: 0.85rem;
        }
        
        .popup-modal {
            padding: 30px 20px;
        }
        
        .popup-title {
            font-size: 1.5rem;
        }
    }
</style>

<div class="container-fluid">
    <!-- Header Section -->
    <div class="classes-header">
        <h1>📚 Fasalada Dugsiga</h1>
        <div class="header-actions">
            <button type="button" class="btn-add-class" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Fasal Cusub
            </button>
            <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                <label style="font-weight: 600; color: #2e3338; white-space: nowrap;">📅 Taariikhda:</label>
                <input type="date" name="date" value="<?php echo $date_filter; ?>" onchange="this.form.submit()" class="date-filter-input">
            </form>
        </div>
    </div>
    
    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="alert-message alert-success">
            <i class="fas fa-check-circle" style="font-size: 1.2rem;"></i>
            <span><?php echo $message; ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert-message alert-danger">
            <i class="fas fa-exclamation-circle" style="font-size: 1.2rem;"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>
    
    <!-- Classes Grid -->
    <?php if (empty($classes)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📭</div>
            <div class="empty-state-text">Fasalal la'aan jira</div>
            <p style="color: #858796;">Fadlan fasal cusub soo dar si aad u bilaabto.</p>
        </div>
    <?php else: ?>
        <div class="classes-container">
            <?php foreach ($classes as $class):
                
                /* ================================
                   TOTAL STUDENTS
                ================================= */
                $total_stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class_id = ?");
                $total_stmt->execute([$class['id']]);
                $total_students = (int)$total_stmt->fetchColumn();

                /* ================================
                   CHECK IF ATTENDANCE TAKEN TODAY
                ================================= */
                $attendance_check_stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM attendance a
                    JOIN students s ON a.student_id = s.id
                    WHERE s.class_id = ?
                    AND a.date = ?
                ");
                $attendance_check_stmt->execute([$class['id'], $date_filter]);
                $attendance_taken = (int)$attendance_check_stmt->fetchColumn();

                if ($attendance_taken == 0) {
                    $present_today = 0;
                    $absent_today = 0;
                    $percentage = 0;
                } else {
                    /* ================================
                       COUNT PRESENT TODAY
                    ================================= */
                    $present_stmt = $pdo->prepare("
                        SELECT COUNT(*) 
                        FROM attendance a
                        JOIN students s ON a.student_id = s.id
                        WHERE s.class_id = ?
                        AND a.date = ?
                        AND (a.period_1_status = 'present'
                             OR a.period_2_status = 'present')
                    ");
                    $present_stmt->execute([$class['id'], $date_filter]);
                    $present_today = (int)$present_stmt->fetchColumn();

                    $absent_today = max($total_students - $present_today, 0);

                    $percentage = $total_students > 0
                        ? round(($present_today / $total_students) * 100, 1)
                        : 0;
                }

                // Color logic
                if ($percentage >= 80) {
                    $color = "#1cc88a";
                    $color_light = "rgba(28, 200, 138, 0.1)";
                } elseif ($percentage >= 50) {
                    $color = "#f6c23e";
                    $color_light = "rgba(246, 194, 62, 0.1)";
                } else {
                    $color = "#e74a3b";
                    $color_light = "rgba(231, 74, 59, 0.1)";
                }
            ?>
            
            <div class="class-card-wrapper">
                <div class="class-actions">
                    <button type="button" class="btn-action btn-edit" 
                            onclick="openEditModal(<?= $class['id'] ?>, '<?= htmlspecialchars($class['class_name']) ?>')" 
                            title="Wax ka beddel">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn-action btn-delete" 
                            onclick="openDeleteModal(<?= $class['id'] ?>, '<?= htmlspecialchars($class['class_name']) ?>')" 
                            title="Tirtir">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                
                <a href="class_detail.php?id=<?php echo $class['id']; ?>" class="class-card">
                    <div class="class-card-header">
                        <h3 class="class-title">
                            <?php echo htmlspecialchars($class['class_name']); ?>
                            <span class="class-icon">📖</span>
                        </h3>
                    </div>
                    
                    <div class="class-stats">
                        <div class="stat-item">
                            <span class="stat-label">👥 Ardayda Guud</span>
                            <span class="stat-value"><?php echo $total_students; ?></span>
                        </div>
                        
                        <div class="stat-item">
                            <span class="stat-label">✓ Joogay Maanta</span>
                            <span class="badge badge-present"><?php echo $present_today; ?></span>
                        </div>
                        
                        <div class="stat-item">
                            <span class="stat-label">✗ Maqnaa Maanta</span>
                            <span class="badge badge-absent"><?php echo $absent_today; ?></span>
                        </div>
                    </div>
                    
                    <div class="progress-section">
                        <div class="progress-bar-custom">
                            <div class="progress-fill" style="width: <?php echo $percentage; ?>%; background: <?php echo $color; ?>;"></div>
                        </div>
                        <div class="attendance-percentage" style="color: <?php echo $color; ?>;">
                            <?php echo $percentage; ?>% Heerka Xaadiridda
                        </div>
                    </div>
                </a>
            </div>
            
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Add Class Modal -->
<div id="addClassModal" class="modal-overlay" style="display: none;">
    <div class="popup-modal" style="max-width: 450px;">
        <div class="popup-content">
            <div class="popup-icon">➕</div>
            <div class="popup-title">Fasal Cusub</div>
            <form method="POST" style="margin-top: 25px;">
                <div style="margin-bottom: 20px;">
                    <input type="text" name="class_name" placeholder="Tusaale: Form 1" 
                           style="width: 100%; padding: 12px 16px; border: 2px solid #e3e6f0; border-radius: 10px; font-size: 1rem; font-family: inherit;"
                           required autofocus>
                </div>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button type="button" onclick="closeAddModal()" 
                            style="background: #858796; color: white; border: none; padding: 10px 24px; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        Jooji
                    </button>
                    <button type="submit" name="add_class" 
                            style="background: #1cc88a; color: white; border: none; padding: 10px 24px; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        Keydi Fasalka
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Class Modal -->
<div id="editClassModal" class="modal-overlay" style="display: none;">
    <div class="popup-modal" style="max-width: 450px;">
        <div class="popup-content">
            <div class="popup-icon">✏️</div>
            <div class="popup-title">Wax ka Beddel Fasalka</div>
            <form method="POST" style="margin-top: 25px;">
                <input type="hidden" name="class_id" id="edit_class_id">
                <div style="margin-bottom: 20px;">
                    <input type="text" name="class_name" id="edit_class_name" placeholder="Magaca Fasalka" 
                           style="width: 100%; padding: 12px 16px; border: 2px solid #e3e6f0; border-radius: 10px; font-size: 1rem; font-family: inherit;"
                           required autofocus>
                </div>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button type="button" onclick="closeEditModal()" 
                            style="background: #858796; color: white; border: none; padding: 10px 24px; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        Jooji
                    </button>
                    <button type="submit" name="edit_class" 
                            style="background: #4e73df; color: white; border: none; padding: 10px 24px; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        Cusboonaysii
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Class Modal -->
<div id="deleteClassModal" class="modal-overlay" style="display: none;">
    <div class="popup-modal" style="max-width: 450px;">
        <div class="popup-content">
            <div class="popup-icon">🗑️</div>
            <div class="popup-title">Tirtir Fasalka</div>
            <p style="color: rgba(255, 255, 255, 0.9); margin: 15px 0; font-size: 1rem;">
                Ma hubtaa inaad rabto inaad tirtirto fasalkan?
            </p>
            <p id="delete_class_name_display" style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem; margin-bottom: 20px;"></p>
            <form method="POST" style="margin-top: 25px;">
                <input type="hidden" name="delete_class_id" id="delete_class_id">
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button type="button" onclick="closeDeleteModal()" 
                            style="background: #858796; color: white; border: none; padding: 10px 24px; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        Jooji
                    </button>
                    <button type="submit" name="delete_class" 
                            style="background: #e74a3b; color: white; border: none; padding: 10px 24px; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        Haa, Tirtir
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Modal Functions
    function openAddModal() {
        document.getElementById('addClassModal').style.display = 'flex';
    }
    
    function closeAddModal() {
        document.getElementById('addClassModal').style.display = 'none';
    }
    
    function openEditModal(id, name) {
        document.getElementById('edit_class_id').value = id;
        document.getElementById('edit_class_name').value = name;
        document.getElementById('editClassModal').style.display = 'flex';
    }
    
    function closeEditModal() {
        document.getElementById('editClassModal').style.display = 'none';
    }
    
    function openDeleteModal(id, name) {
        document.getElementById('delete_class_id').value = id;
        document.getElementById('delete_class_name_display').innerText = "Fasalka: " + name;
        document.getElementById('deleteClassModal').style.display = 'flex';
    }
    
    function closeDeleteModal() {
        document.getElementById('deleteClassModal').style.display = 'none';
    }
    
    // Close modal when clicking overlay
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });
    
    // ============================================
    // REAL-TIME MONITORING SYSTEM
    // ============================================
    
    function playNotificationSound() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const now = audioContext.currentTime;
            
            const osc1 = audioContext.createOscillator();
            const osc2 = audioContext.createOscillator();
            const osc3 = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            osc1.connect(gainNode);
            osc2.connect(gainNode);
            osc3.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            osc1.frequency.setValueAtTime(659.25, now);
            osc2.frequency.setValueAtTime(783.99, now + 0.05);
            osc3.frequency.setValueAtTime(1046.50, now + 0.1);
            
            gainNode.gain.setValueAtTime(0.2, now);
            gainNode.gain.exponentialRampToValueAtTime(0.01, now + 0.2);
            
            osc1.start(now); osc1.stop(now + 0.2);
            osc2.start(now + 0.05); osc2.stop(now + 0.2);
            osc3.start(now + 0.1); osc3.stop(now + 0.2);
        } catch (e) { console.log('Audio context error'); }
    }
    
    function showUpdatePopup() {
        if (document.querySelector('.modal-overlay[style*="display: flex"]')) return;
        
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.style.display = 'flex';
        
        const now = new Date();
        const timeString = now.toLocaleTimeString('so-SO', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        
        overlay.innerHTML = `
            <div class="popup-modal">
                <div class="popup-content">
                    <div class="popup-icon">✨</div>
                    <div class="popup-title">Xog Cusub!</div>
                    <div class="popup-message">
                        <span class="pulse-dot"></span>
                        Xogta fasalada ayaa isbeddeshay
                    </div>
                    <div style="color: rgba(255, 255, 255, 0.8); font-size: 0.9rem; margin-bottom: 20px;">⏰ ${timeString}</div>
                    <button class="popup-button" onclick="this.closest('.modal-overlay').remove(); window.scrollTo({ top: 0, behavior: 'smooth' });">
                        Okey
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(overlay);
        playNotificationSound();
        
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) overlay.remove();
        });
    }
    
    let lastContentHash = null;
    
    function startMonitoring() {
        setInterval(() => {
            if (document.querySelector('.modal-overlay[style*="display: flex"]')) return;

            fetch(window.location.href, { cache: 'no-store' })
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const newDoc = parser.parseFromString(html, 'text/html');
                    const newContent = newDoc.querySelector('.classes-container');
                    const currentContent = document.querySelector('.classes-container');
                    
                    if (newContent && currentContent) {
                        const newHash = newContent.innerHTML;
                        if (lastContentHash && newHash !== lastContentHash) {
                            currentContent.innerHTML = newContent.innerHTML;
                            showUpdatePopup();
                        }
                        lastContentHash = newHash;
                    }
                })
                .catch(error => console.error('Monitoring error:', error));
        }, 3000);
    }
    
    document.addEventListener('DOMContentLoaded', () => {
        const container = document.querySelector('.classes-container');
        if (container) {
            lastContentHash = container.innerHTML;
            startMonitoring();
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>
