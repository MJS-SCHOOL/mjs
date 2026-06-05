<?php
require_once 'includes/header.php';

// Today's date
$today = date('Y-m-d');

// Get filter values from GET parameters
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : $today;
$filter_subject = isset($_GET['filter_subject']) ? $_GET['filter_subject'] : 'all';

// Function to split class and section (e.g., "6A" -> ["class" => "6", "section" => "A"])
function splitClassSection($fullName) {
    if (preg_match('/^(.+)([A-Za-z])$/', $fullName, $matches)) {
        return ['class' => $matches[1], 'section' => $matches[2]];
    }
    return ['class' => $fullName, 'section' => '-'];
}

// Fetch overview statistics based on role
if (is_admin()) {
    // Total counts (Static for overview)
    $total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $total_rooms = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
    $total_classes = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    
    // Build where clause for dynamic stats
    $where_clause = "1=1";
    $params = [];
    
    if ($filter_date && $filter_date != 'all') {
        $where_clause .= " AND a.date = ?";
        $params[] = $filter_date;
    }
    
    $where_clause_with_subject = $where_clause;
    $params_with_subject = $params;
    
    if ($filter_subject && $filter_subject != 'all') {
        $where_clause_with_subject .= " AND (a.period_1_subject = ? OR a.period_2_subject = ?)";
        $params_with_subject[] = $filter_subject;
        $params_with_subject[] = $filter_subject;
    }

    // Dynamic present count based on filters
    $present_stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance a WHERE $where_clause_with_subject AND (a.period_1_status = 'present' OR a.period_2_status = 'present')");
    $present_stmt->execute($params_with_subject);
    $present_count = $present_stmt->fetchColumn();

    // Data for charts (Attendance by Class)
    $chart_stmt = $pdo->prepare("
        SELECT c.class_name, 
               COUNT(DISTINCT a.id) as present_count,
               (SELECT COUNT(*) FROM students s2 WHERE s2.class_id = c.id) as total_students
        FROM classes c
        LEFT JOIN students s ON c.id = s.class_id
        LEFT JOIN attendance a ON s.id = a.student_id AND $where_clause_with_subject AND (a.period_1_status = 'present' OR a.period_2_status = 'present')
        GROUP BY c.id
    ");
    $chart_stmt->execute($params_with_subject);
    $class_attendance_data = $chart_stmt->fetchAll();

    // ==============================================================================
    // DATA PREPARATION FOR THE NEW 5-DAY ATTENDANCE TABLE (ADMIN ONLY)
    // ==============================================================================
    
    // 1. Fetch the last 5 distinct dates where attendance was recorded
    $dates_stmt = $pdo->query("SELECT DISTINCT date FROM attendance ORDER BY date DESC LIMIT 5");
    $last_5_days = $dates_stmt->fetchAll(PDO::FETCH_COLUMN);
    sort($last_5_days); // Sort oldest to newest for chronological display (left to right)

    // 2. Fetch main data: Room, Class, and Total Students
    $query = "
        SELECT 
            r.id AS room_id,
            r.room_name AS room, 
            c.id AS class_id,
            c.class_name AS class, 
            COUNT(DISTINCT s.id) AS total_students
        FROM rooms r
        JOIN students s ON r.id = s.room_id
        JOIN classes c ON s.class_id = c.id
        GROUP BY r.id, c.id
        ORDER BY r.room_name, c.class_name
    ";
    $main_data = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch attendance records for those 5 days securely
    $att_map = [];
    if (!empty($last_5_days)) {
        $placeholders = implode(',', array_fill(0, count($last_5_days), '?'));
        
        $att_query = "
            SELECT 
                s.room_id, 
                s.class_id, 
                a.date,
                SUM(CASE WHEN a.period_1_status = 'present' OR a.period_2_status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN a.period_1_status = 'absent' OR a.period_2_status = 'absent' THEN 1 ELSE 0 END) as absent_count
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            WHERE a.date IN ($placeholders)
            GROUP BY s.room_id, s.class_id, a.date
        ";
        
        $att_stmt = $pdo->prepare($att_query);
        $att_stmt->execute($last_5_days);
        $att_data = $att_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Map data for easy retrieval in the table loop
        foreach ($att_data as $row) {
            $att_map[$row['room_id']][$row['class_id']][$row['date']] = [
                'P' => $row['present_count'],
                'A' => $row['absent_count']
            ];
        }
    }
    
} else {
    // ==============================================================================
    // TEACHER / USER DASHBOARD LOGIC
    // ==============================================================================
    $user_id = $_SESSION['user_id'];
    
    // Total students in assigned rooms
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE room_id IN (SELECT room_id FROM user_rooms WHERE user_id = ?)");
    $stmt->execute([$user_id]);
    $total_students = $stmt->fetchColumn();
    
    // Total rooms assigned to this user
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_rooms WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_rooms = $stmt->fetchColumn();
    
    // Total classes linked to assigned rooms
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT class_id) FROM rooms_classes WHERE room_id IN (SELECT room_id FROM user_rooms WHERE user_id = ?)");
    $stmt->execute([$user_id]);
    $total_classes = $stmt->fetchColumn();
    
    // Present count in assigned rooms
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE a.date = ? 
        AND (a.period_1_status = 'present' OR a.period_2_status = 'present')
        AND s.room_id IN (SELECT room_id FROM user_rooms WHERE user_id = ?)
    ");
    $stmt->execute([$filter_date, $user_id]);
    $present_count = $stmt->fetchColumn();
    
    // Data for charts
    $chart_stmt = $pdo->prepare("
        SELECT c.class_name, 
               COUNT(a.id) as present_count,
               (SELECT COUNT(*) FROM students s2 WHERE s2.class_id = c.id AND s2.room_id IN (SELECT room_id FROM user_rooms WHERE user_id = ?)) as total_students
        FROM classes c
        JOIN students s ON c.id = s.class_id
        LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ? AND (a.period_1_status = 'present' OR a.period_2_status = 'present')
        WHERE s.room_id IN (SELECT room_id FROM user_rooms WHERE user_id = ?)
        GROUP BY c.id
    ");
    $chart_stmt->execute([$user_id, $filter_date, $user_id]);
    $class_attendance_data = $chart_stmt->fetchAll();
}

$attendance_percentage = $total_students > 0 ? round(($present_count / $total_students) * 100, 1) : 0;

// Prepare Chart JS Data
$class_labels = [];
$class_data = [];
foreach ($class_attendance_data as $row) {
    $class_labels[] = $row['class_name'];
    $perc = $row['total_students'] > 0 ? round(($row['present_count'] / $row['total_students']) * 100, 1) : 0;
    $class_data[] = $perc;
}
?>

<style>
/* ============================================
   GLOBAL DASHBOARD STYLES (100% RESPONSIVE)
   ============================================ */
:root {
    --primary: #4e73df;
    --primary-light: #6c8bff;
    --primary-dark: #2e5cb8;
    --success: #1cc88a;
    --danger: #e74a3b;
    --warning: #f6ad55;
    --info: #36b9cc;
    --dark: #2e3338;
    --light: #f8f9fa;
    --border-radius: 16px;
    --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

body {
    font-family: var(--font-family);
    background-color: #f4f6f9;
    color: #333;
}

/* Dashboard Cards Grid */
.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

/* Stat Card Styles */
.stat-card {
    border-radius: var(--border-radius);
    padding: 24px;
    color: #fff;
    position: relative;
    overflow: hidden;
    transition: var(--transition);
    box-shadow: var(--shadow-md);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: -20px;
    right: -20px;
    width: 120px;
    height: 120px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 50%;
    animation: pulse 4s ease-in-out infinite;
}

.stat-icon {
    font-size: 48px;
    opacity: 0.2;
    position: absolute;
    right: 20px;
    bottom: 20px;
    transition: var(--transition);
}

.stat-card:hover .stat-icon {
    transform: scale(1.1) rotate(-5deg);
    opacity: 0.3;
}

.stat-title {
    font-size: 0.85rem;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 1px;
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 8px;
    z-index: 1;
    position: relative;
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 800;
    color: #ffffff;
    line-height: 1;
    z-index: 1;
    position: relative;
}

.stat-subtitle {
    font-size: 0.85rem;
    opacity: 0.9;
    margin-top: 12px;
    font-weight: 500;
    z-index: 1;
    position: relative;
}

/* Color Variants */
.bg-blue { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); }
.bg-green { background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%); }
.bg-orange { background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%); }
.bg-purple { background: linear-gradient(135deg, #6f42c1 0%, #4e2c91 100%); }

/* ============================================
   NEW MODERN TABLE STYLES (5 DAYS ATTENDANCE)
   ============================================ */
.modern-table-wrapper {
    background: #ffffff;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    margin-bottom: 32px;
    border: 1px solid rgba(0,0,0,0.05);
}

.modern-table-header {
    background: #fff;
    color: var(--dark);
    padding: 24px;
    font-weight: 700;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    border-bottom: 1px solid #edf2f7;
}

.modern-table-header i {
    color: var(--primary);
    background: rgba(78, 115, 223, 0.1);
    padding: 12px;
    border-radius: 12px;
}

.table-responsive-custom {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

/* Custom Scrollbar for Table */
.table-responsive-custom::-webkit-scrollbar {
    height: 8px;
}
.table-responsive-custom::-webkit-scrollbar-track {
    background: #f1f5f9;
}
.table-responsive-custom::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.table-responsive-custom::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

.modern-table thead th {
    background-color: #f8fafc;
    color: #64748b;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 16px 24px;
    border-bottom: 2px solid #e2e8f0;
    text-align: center;
}

.modern-table tbody td {
    padding: 16px 24px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    font-size: 0.95rem;
    text-align: center;
    transition: var(--transition);
}

.modern-table tbody tr:hover td {
    background-color: #f8fafc;
}

/* Highlight Columns */
.col-room { color: var(--primary-dark); font-weight: 600 !important; text-align: left !important; }
.col-class { color: #1e293b; font-weight: 600 !important; }
.col-section { color: #64748b; }
.col-total { font-weight: 700 !important; font-size: 1.05rem; color: var(--primary); }

/* Badges for Present and Absent */
.att-badge-container {
    display: flex;
    justify-content: center;
    gap: 6px;
}

.badge-pa {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 700;
    min-width: 40px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.b-present {
    background-color: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}

.b-absent {
    background-color: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.b-empty {
    background-color: #f8fafc;
    color: #94a3b8;
    border: 1px dashed #cbd5e1;
    box-shadow: none;
}

/* ============================================
   WARNING / ALERT CARDS (TEACHER DASHBOARD)
   ============================================ */
.alert-card {
    background: #fff;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-md);
    margin-bottom: 32px;
    overflow: hidden;
    border: 1px solid #fcd34d;
}

.alert-header {
    background: linear-gradient(to right, #f59e0b, #fbbf24);
    color: #fff;
    font-weight: 700;
    font-size: 1.15rem;
    padding: 20px 24px;
    display: flex;
    align-items: center;
}

.alert-header i {
    margin-right: 12px;
    font-size: 1.4rem;
}

.alert-body {
    padding: 24px;
}

.info-section {
    margin-bottom: 24px;
    padding-bottom: 24px;
    border-bottom: 1px solid #f1f5f9;
}

.info-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.info-title {
    color: var(--primary-dark);
    font-weight: 700;
    font-size: 1.1rem;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
}

.info-title i {
    margin-right: 10px;
    color: var(--primary);
}

.info-text {
    color: #475569;
    line-height: 1.6;
    margin-bottom: 12px;
    font-size: 0.95rem;
}

.highlight-box {
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 16px;
    border-left: 4px solid transparent;
}

.box-blue {
    background: #eff6ff;
    border-left-color: #3b82f6;
}

.box-purple {
    background: #faf5ff;
    border-left-color: #a855f7;
}

.box-gray {
    background: #f8fafc;
    border-left-color: #64748b;
}

.box-title {
    font-weight: 700;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
}

.box-blue .box-title { color: #1d4ed8; }
.box-purple .box-title { color: #7e22ce; }
.box-gray .box-title { color: #334155; }

/* ============================================
   CHART CONTAINERS & LAYOUT
   ============================================ */
.row.two-col {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
    margin-top: 24px;
}

.chart-card {
    background: #fff;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
}

.chart-header {
    padding: 20px 24px;
    border-bottom: 1px solid #f1f5f9;
    font-weight: 700;
    color: #1e293b;
    font-size: 1.05rem;
}

.chart-body {
    padding: 24px;
    flex-grow: 1;
}

/* ============================================
   RESPONSIVE DESIGN (MOBILE & TABLET)
   ============================================ */
@media (max-width: 1024px) {
    .row.two-col {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .dashboard-cards {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    
    .stat-card {
        padding: 20px;
    }
    
    .stat-value {
        font-size: 2rem;
    }

    .modern-table-header {
        padding: 16px;
        font-size: 1.1rem;
    }

    .alert-header {
        padding: 16px;
        font-size: 1rem;
    }
    
    .alert-body {
        padding: 16px;
    }
}

@media (max-width: 480px) {
    .dashboard-cards {
        grid-template-columns: 1fr;
    }
    
    .modern-table tbody td, .modern-table thead th {
        padding: 12px 16px;
    }
}

/* Animations */
@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.1; }
    50% { transform: scale(1.05); opacity: 0.15; }
}
</style>

<?php if (!is_admin()): ?>
<div class="alert-card">
    <div class="alert-header">
        <i class="fas fa-exclamation-circle"></i>
        WARGILIN MUHIIM AH 
    </div>
    <div class="alert-body">
        
        <div class="info-section">
            <div class="info-title">
                <i class="fas fa-calendar-check"></i>
                1. XAADIRINТА MAALINLAHA 
            </div>
            <p class="info-text">
                <strong>Taariikhda Xaadirinта:</strong> Xulashada taariikhda waa muhiimada xaadirka maalin kasta si loo xaqiijiyo kala shaandheenta ardayda iyo xaadirka. 
            </p>
            <p class="info-text">
                <strong>Fiiro gaar ah:</strong> Fadlan barre hubi oo xaqiiji in taarikhda kuu xulan in ay tahay taariikhda maanta oo sax ah.
            </p>
            <p class="info-text" style="color: #ef4444; font-weight: 700; margin-top: 12px; background: #fef2f2; padding: 10px; border-radius: 8px; display: inline-block;">
                <i class="fas fa-exclamation-triangle mr-1"></i> ⚠️ TAARIIKH KHADLAN XOG QALDAN!
            </p>
        </div>

        <div class="info-section">
            <div class="info-title">
                <i class="fas fa-clock"></i>
                2. LABADA IMTIXAAN MAALINLAHA
            </div>
            
            <div class="highlight-box box-blue">
                <div class="box-title">
                    <i class="fas fa-hourglass-start mr-2"></i> IMTIXAAN 1AAD (PERIOD 1)
                </div>
                <p class="info-text" style="margin-bottom: 0;">Taariikhda, Room ka iyo Maadadda waa qasab in si sax ah loo xushaa maalin karta iyadoo lagu saleynaaayo hadba roomka lajoogo, xisada 2aad waa mid xayiran mana ahan suuragal in la xaadiriyo ilaa laga xaadiriyo xisada 1aad, sdiaa awgeed fadlan xaadiri xisada 1aad si sax ah.</p>
            </div>
            
            <div class="highlight-box box-purple">
                <div class="box-title">
                    <i class="fas fa-hourglass-end mr-2"></i> IMTIXAAN 2AAD (PERIOD 2)
                </div>
                <p class="info-text" style="margin-bottom: 0;">Xisada 2aad waxa ay kuu furmi doontaa marka xisada 1aad la xaadiriyo, waana muhiim in la raaco talaaboyinkii saxda ahaa ee hubinta taariikhda roomla maadadda iyo keedinta</p>
            </div>
        </div>

        <div class="info-section">
            <div class="highlight-box box-gray">
                <div class="info-title" style="margin-bottom: 8px;">
                    <i class="fas fa-info-circle"></i> GABO GABO
                </div>
                <p class="info-text" style="margin-bottom: 0;">
                    Maado kasta oo la xaadiriyo system ka waxy ka bixidoontaa si otomaticaal ah, waxaana kaliya aad arki doontaa maadooyinka harsan ama dhiman ee aan wali imtixaankooda la galin ama la xaadirin. Macalin waad ku mahadsantahay dadaalka iyo dhiirnatda joogtada ah guuleyso. 
                </p>
            </div>
        </div>

    </div>
</div>
<?php endif; ?>

<?php if (is_admin()): ?>
<div class="dashboard-cards">
    <div class="stat-card bg-blue">
        <i class="fas fa-user-graduate stat-icon"></i>
        <div>
            <div class="stat-title">Total Students</div>
            <div class="stat-value"><?php echo $total_students; ?></div>
        </div>
        <div class="stat-subtitle">Ardayda Guud</div>
    </div>

    <div class="stat-card bg-green">
        <i class="fas fa-door-open stat-icon"></i>
        <div>
            <div class="stat-title">Total Rooms</div>
            <div class="stat-value"><?php echo $total_rooms; ?></div>
        </div>
        <div class="stat-subtitle">Qololka Guud</div>
    </div>

    <div class="stat-card bg-orange">
        <i class="fas fa-school stat-icon"></i>
        <div>
            <div class="stat-title">Total Classes</div>
            <div class="stat-value"><?php echo $total_classes; ?></div>
        </div>
        <div class="stat-subtitle">Fasalada Guud</div>
    </div>

    <div class="stat-card bg-purple">
        <i class="fas fa-chart-pie stat-icon"></i>
        <div>
            <div class="stat-title">Today's Attendance</div>
            <div class="stat-value"><?php echo $attendance_percentage; ?>%</div>
        </div>
        <div class="stat-subtitle">Maanta Joogta</div>
    </div>
</div>

<div class="modern-table-wrapper">
    <div class="modern-table-header">
        <i class="fas fa-calendar-alt mr-3" style="margin-right: 16px;"></i>
        Xogta Xaadirinta 5-tii Maalmood ee Ugu Dambeysay
    </div>
    
    <div class="table-responsive-custom">
        <table class="modern-table">
            <thead>
                <tr>
                    <th style="text-align: left;">Room</th>
                    <th>Class</th>
                    <th>Section</th>
                    <th>Total Students</th>
                    <?php if (empty($last_5_days)): ?>
                        <th>Maalmo Xaadirin Lama Hayo</th>
                    <?php else: ?>
                        <?php foreach ($last_5_days as $date): ?>
                            <th><?php echo date('d M Y', strtotime($date)); ?></th>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($main_data)): ?>
                    <tr>
                        <td colspan="<?php echo 4 + count($last_5_days); ?>" style="padding: 60px 20px; text-align: center; color: #94a3b8;">
                            <i class="fas fa-folder-open mb-3" style="font-size: 3rem; display: block; margin-bottom: 16px; color: #cbd5e1;"></i>
                            <span style="font-size: 1.1rem; font-weight: 500;">Xogta fasalada iyo ardayda wali lama diiwaangelin.</span>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($main_data as $row): ?>
                        <?php 
                            $classInfo = splitClassSection($row['class']);
                        ?>
                        <tr>
                            <td class="col-room">
                                <i class="fas fa-door-open mr-2" style="color: #94a3b8; margin-right: 8px;"></i> 
                                <?php echo htmlspecialchars($row['room']); ?>
                            </td>
                            <td class="col-class"><?php echo htmlspecialchars($classInfo['class']); ?></td>
                            <td class="col-section">
                                <span style="background: #f1f5f9; padding: 4px 12px; border-radius: 20px; font-weight: 700;">
                                    <?php echo htmlspecialchars($classInfo['section']); ?>
                                </span>
                            </td>
                            <td class="col-total"><?php echo $row['total_students']; ?></td>
                            
                            <?php foreach ($last_5_days as $date): ?>
                                <td>
                                    <?php 
                                        $p_count = 0;
                                        $a_count = 0;
                                        $has_data = false;

                                        if (isset($att_map[$row['room_id']][$row['class_id']][$date])) {
                                            $p_count = $att_map[$row['room_id']][$row['class_id']][$date]['P'];
                                            $a_count = $att_map[$row['room_id']][$row['class_id']][$date]['A'];
                                            $has_data = true;
                                        }
                                    ?>
                                    
                                    <div class="att-badge-container">
                                        <?php if ($has_data): ?>
                                            <span class="badge-pa b-present" title="Present (Joogay)">
                                                P: <?php echo $p_count; ?>
                                            </span>
                                            <span class="badge-pa b-absent" title="Absent (Maqan)">
                                                A: <?php echo $a_count; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-pa b-empty">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row two-col">
    <div class="chart-card">
        <div class="chart-header">
            <i class="fas fa-chart-bar mr-2" style="color: #4e73df; margin-right: 8px;"></i> FASALADA (%)
        </div>
        <div class="chart-body">
            <div style="position: relative; height: 320px; width: 100%;">
                <canvas id="classChart"></canvas>
            </div>
        </div>
    </div>
    <div class="chart-card">
        <div class="chart-header">
            <i class="fas fa-chart-pie mr-2" style="color: #1cc88a; margin-right: 8px;"></i> Xaalada Maanta
        </div>
        <div class="chart-body" style="display: flex; justify-content: center; align-items: center;">
            <div style="position: relative; height: 320px; width: 100%; display: flex; justify-content: center;">
                <canvas id="overviewPie" style="max-width: 280px;"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Shared Chart Options
    Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
    Chart.defaults.color = '#64748b';

    // Bar Chart - Attendance by Class
    const ctxBar = document.getElementById('classChart');
    if (ctxBar) {
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($class_labels); ?>,
                datasets: [{
                    label: 'Fasalada %',
                    data: <?php echo json_encode($class_data); ?>,
                    backgroundColor: 'rgba(78, 115, 223, 0.85)',
                    hoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                    borderRadius: 6,
                    borderSkipped: false,
                    maxBarThickness: 40
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        titleFont: { size: 14 },
                        bodyFont: { size: 14 },
                        callbacks: {
                            label: function(context) {
                                return context.raw + '% Joogta';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false, drawBorder: false }
                    },
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            color: '#f1f5f9',
                            drawBorder: false,
                            borderDash: [5, 5]
                        },
                        ticks: {
                            padding: 10,
                            callback: function(value) { return value + '%'; }
                        }
                    }
                }
            }
        });
    }

    // Pie Chart - Today's Overview
    const ctxPie = document.getElementById('overviewPie');
    if (ctxPie) {
        new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: ['Present (Joogay)', 'Absent (Maqan)'],
                datasets: [{
                    data: [<?php echo $present_count; ?>, <?php echo $total_students - $present_count; ?>],
                    backgroundColor: ['#10b981', '#ef4444'],
                    hoverBackgroundColor: ['#059669', '#dc2626'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { 
                        position: 'bottom',
                        labels: { padding: 20, usePointStyle: true, pointStyle: 'circle' }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                return ' ' + context.label + ': ' + context.raw + ' Arday';
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>