<?php
require_once 'includes/header.php';
require_admin();

// Fetch all dates for the date filter
$dates_query = "SELECT DISTINCT a.date FROM attendance a ORDER BY a.date DESC";
$all_dates = $pdo->query($dates_query)->fetchAll();

// Filter settings
$class_id = isset($_GET['class_id']) ? $_GET['class_id'] : '';
$room_id = isset($_GET['room_id']) ? $_GET['room_id'] : '';
$table_date = isset($_GET['table_date']) ? $_GET['table_date'] : (!empty($all_dates) ? $all_dates[0]['date'] : '');

// Date range for percentage calculation (defaults to last 30 days)
$date_from = date('Y-m-d', strtotime('-30 days'));
$date_to = date('Y-m-d');

$student_list = [];
$show_data = false;
$total_students_count = 0;

// Only fetch data if a class or room is selected
if (!empty($class_id) || !empty($room_id)) {
    $show_data = true;
    
    // Kala saar shuruudaha ardayda iyo shuruudaha attendance-ka
    $student_conditions = [];
    $table_params = [':date_from' => $date_from, ':date_to' => $date_to, ':table_date' => $table_date];

    if (!empty($class_id)) {
        $student_conditions[] = "s.class_id = :class_id";
        $table_params[':class_id'] = $class_id;
    }
    if (!empty($room_id)) {
        $student_conditions[] = "s.room_id = :room_id";
        $table_params[':room_id'] = $room_id;
    }

    $where_student_sql = "";
    if (count($student_conditions) > 0) {
        $where_student_sql = "WHERE " . implode(" AND ", $student_conditions);
    }

    $list_query = "SELECT 
        s.full_name,
        c.class_name,
        r.room_name,
        COUNT(CASE WHEN a.period_1_status = 'present' THEN 1 END) as total_present,
        COUNT(CASE WHEN a.period_1_status = 'absent' THEN 1 END) as total_absent,
        MAX(CASE WHEN a.date = :table_date THEN a.period_1_status ELSE NULL END) as status_on_date
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN rooms r ON s.room_id = r.id
        LEFT JOIN attendance a ON s.id = a.student_id AND a.date BETWEEN :date_from AND :date_to
        $where_student_sql
        GROUP BY s.id
        ORDER BY s.full_name ASC";

    $stmt = $pdo->prepare($list_query);
    $stmt->execute($table_params);
    $student_list = $stmt->fetchAll();
    $total_students_count = count($student_list);
}

// Fetch classes and rooms for filters
$classes = $pdo->query("SELECT * FROM classes ORDER BY class_name")->fetchAll();
$rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_name")->fetchAll();
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<style>
    :root {
        --primary: #6366f1;
        --primary-hover: #4f46e5;
        --bg-main: #f8fafc;
        --card-bg: #ffffff;
        --text-main: #1e293b;
        --text-muted: #64748b;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --border: #e2e8f0;
        --radius: 16px;
    }

    body {
        background-color: var(--bg-main);
        color: var(--text-main);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    .page-wrapper {
        max-width: 1200px;
        margin: 40px auto;
        padding: 0 20px;
    }

    /* Modern Header */
    .header-card {
        background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
        padding: 40px;
        border-radius: 24px;
        color: white;
        margin-bottom: 30px;
        box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.3);
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .header-card h1 {
        font-size: 2.2rem;
        font-weight: 800;
        margin-bottom: 10px;
        letter-spacing: -0.025em;
        position: relative;
        z-index: 1;
    }

    .header-card p {
        font-size: 1.1rem;
        opacity: 0.9;
        position: relative;
        z-index: 1;
    }

    /* Selection Section */
    .selection-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .selection-card {
        background: var(--card-bg);
        padding: 25px;
        border-radius: var(--radius);
        border: 1px solid var(--border);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .selection-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }

    .selection-card label {
        display: block;
        font-weight: 700;
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-bottom: 12px;
        text-transform: uppercase;
        letter-spacing: 0.1em;
    }

    .modern-select {
        width: 100%;
        padding: 14px 16px;
        border-radius: 12px;
        border: 2px solid var(--border);
        background-color: white;
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-main);
        cursor: pointer;
        transition: all 0.2s;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 16px center;
        background-size: 16px;
    }

    .modern-select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
    }

    /* Student Counter */
    .counter-badge {
        background: rgba(255, 255, 255, 0.2);
        padding: 6px 16px;
        border-radius: 100px;
        font-size: 0.875rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 15px;
    }

    /* Data Table */
    .data-card {
        background: white;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        border: 1px solid var(--border);
    }

    .data-header {
        padding: 30px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
        background: #fcfcfd;
    }

    .data-header h2 {
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0;
        color: var(--text-main);
    }

    .btn-action {
        padding: 12px 24px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.875rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        border: none;
        transition: all 0.2s;
        text-decoration: none;
    }

    .btn-pdf { background: #fee2e2; color: #991b1b; }
    .btn-excel { background: #dcfce7; color: #166534; }
    .btn-pdf:hover { transform: scale(1.05); }
    .btn-excel:hover { transform: scale(1.05); }

    .table-modern {
        width: 100%;
        border-collapse: collapse;
    }

    .table-modern th {
        background: #f8fafc;
        padding: 20px 24px;
        text-align: left;
        font-weight: 700;
        font-size: 0.75rem;
        text-transform: uppercase;
        color: var(--text-muted);
        border-bottom: 1px solid var(--border);
    }

    .table-modern td {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        font-size: 0.9375rem;
        vertical-align: middle;
    }

    /* Status Badges */
    .status-badge {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .status-tick { background: #dcfce7; color: #10b981; }
    .status-cross { background: #fee2e2; color: #ef4444; }
    .status-none { background: #f1f5f9; color: #cbd5e1; }

    /* Animated Attendance Button */
    .attendance-btn {
        padding: 10px 18px;
        border-radius: 100px;
        font-weight: 800;
        font-size: 0.875rem;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: none;
        color: white;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .btn-high { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
    .btn-mid { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
    .btn-low { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }

    .attendance-btn span {
        background: rgba(0, 0, 0, 0.15);
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 100px 40px;
        background: white;
        border-radius: 32px;
        border: 2px dashed var(--border);
    }

    @media (max-width: 768px) {
        .selection-grid { grid-template-columns: 1fr; }
        .data-header { flex-direction: column; align-items: flex-start; }
    }
</style>

<div class="page-wrapper">
    <div class="header-card">
        <h1><i class="fas fa-user-check"></i> Student Attendance Summary</h1>
        <p>Live insights and detailed tracking for your educational institution</p>
        <?php if ($show_data): ?>
            <div class="counter-badge">
                <i class="fas fa-users"></i> Total Students: <?php echo $total_students_count; ?>
            </div>
        <?php endif; ?>
    </div>

    <form method="GET" id="filterForm">
        <div class="selection-grid">
            <div class="selection-card">
                <label><i class="fas fa-calendar-alt"></i> Selected Date</label>
                <select name="table_date" class="modern-select" onchange="this.form.submit()">
                    <?php foreach ($all_dates as $d): ?>
                        <option value="<?php echo $d['date']; ?>" <?php echo $table_date == $d['date'] ? 'selected' : ''; ?>>
                            <?php echo date('D, M d, Y', strtotime($d['date'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="selection-card">
                <label><i class="fas fa-chalkboard-teacher"></i> Filter by Class</label>
                <select name="class_id" class="modern-select" onchange="this.form.submit()">
                    <option value="">-- Choose Class --</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $class_id == $c['id'] ? 'selected' : ''; ?>>
                            <?php echo $c['class_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="selection-card">
                <label><i class="fas fa-school"></i> Filter by Room</label>
                <select name="room_id" class="modern-select" onchange="this.form.submit()">
                    <option value="">-- Choose Room --</option>
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?php echo $r['id']; ?>" <?php echo $room_id == $r['id'] ? 'selected' : ''; ?>>
                            <?php echo $r['room_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>

    <?php if (!$show_data): ?>
        <div class="empty-state">
            <i class="fas fa-layer-group" style="font-size: 5rem; color: var(--primary); opacity: 0.1; margin-bottom: 30px; display: block;"></i>
            <h3>Waiting for Selection</h3>
            <p style="color: var(--text-muted); max-width: 400px; margin: 0 auto;">Please select a <b>Class</b> or <b>Room</b> from the filters above to load the student list.</p>
        </div>
    <?php else: ?>
        <div class="data-card">
            <div class="data-header">
                <div>
                    <h2><?php 
                        if (!empty($class_id)) {
                            foreach($classes as $c) if($c['id'] == $class_id) echo "Class: " . $c['class_name'];
                        } else {
                            foreach($rooms as $r) if($r['id'] == $room_id) echo "Room: " . $r['room_name'];
                        }
                    ?></h2>
                    <span style="color: var(--text-muted); font-size: 0.875rem; font-weight: 500;">Attendance Status for <?php echo date('F d, Y', strtotime($table_date)); ?></span>
                </div>
                <div class="export-btns">
                    <button onclick="exportToPDF()" class="btn-action btn-pdf"><i class="fas fa-file-pdf"></i> PDF</button>
                    <button onclick="exportToExcel()" class="btn-action btn-excel"><i class="fas fa-file-excel"></i> Excel</button>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table class="table-modern" id="attendanceTable">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Information</th>
                            <th style="text-align: center;">Status (Maanta)</th>
                            <th style="text-align: center;">Live Attendance %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($student_list as $row): 
                            $total_days = $row['total_present'] + $row['total_absent'];
                            $perc = $total_days > 0 ? round(($row['total_present'] / $total_days) * 100, 1) : 0;
                            $btn_class = $perc > 80 ? 'btn-high' : ($perc > 50 ? 'btn-mid' : 'btn-low');
                            $icon = $perc > 80 ? 'fa-check-circle' : ($perc > 50 ? 'fa-exclamation-circle' : 'fa-times-circle');
                        ?>
                        <tr>
                            <td style="font-weight: 800; color: #0f172a;"><?php echo $row['full_name']; ?></td>
                            <td>
                                <span style="font-weight: 700; font-size: 0.85rem; color: var(--primary); background: rgba(99, 102, 241, 0.1); padding: 4px 12px; border-radius: 6px;">
                                    <?php echo $row['class_name']; ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($row['status_on_date'] == 'present'): ?>
                                    <div class="status-badge status-tick" title="Present">
                                        <i class="fas fa-check"></i>
                                    </div>
                                <?php elseif ($row['status_on_date'] == 'absent'): ?>
                                    <div class="status-badge status-cross" title="Absent">
                                        <i class="fas fa-times"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="status-badge status-none" title="No Data">
                                        <i class="fas fa-minus"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <div class="attendance-btn <?php echo $btn_class; ?>">
                                    <i class="fas <?php echo $icon; ?>"></i>
                                    <?php echo $perc; ?>%
                                    <span><?php echo $row['total_absent']; ?> Absent</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    function exportToPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.setFont('Helvetica', 'bold');
        doc.text('Attendance Summary Report', 14, 15);
        doc.setFontSize(10);
        doc.setFont('Helvetica', 'normal');
        doc.text('Date: ' + '<?php echo $table_date; ?>', 14, 22);
        doc.autoTable({ html: '#attendanceTable', startY: 30 });
        doc.save('attendance_report_' + '<?php echo $table_date; ?>' + '.pdf');
    }

    function exportToExcel() {
        const table = document.getElementById("attendanceTable");
        const wb = XLSX.utils.table_to_book(table, {sheet: "Attendance"});
        XLSX.writeFile(wb, 'attendance_report_' + '<?php echo $table_date; ?>' + '.xlsx');
    }
</script>

<?php require_once 'includes/footer.php'; ?>