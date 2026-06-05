<?php
ob_start(); // FIX: Start output buffering to prevent "headers already sent" errors from header.php
require_once 'includes/header.php';
require_admin();

/* ==============================
   FETCH TODAY'S CLASS SUMMARY
============================== */
$today = date('Y-m-d');
$today_summary_query = "
    SELECT 
        c.id as class_id,
        c.class_name,
        COUNT(DISTINCT s.id) as total_students,
        SUM(CASE WHEN a.period_1_status = 'present' OR a.period_2_status = 'present' THEN 1 ELSE 0 END) as present_count
    FROM classes c
    LEFT JOIN students s ON c.id = s.class_id
    LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ?
    GROUP BY c.id, c.class_name
    ORDER BY c.class_name
";
$stmt_summary = $pdo->prepare($today_summary_query);
$stmt_summary->execute([$today]);
$today_classes = $stmt_summary->fetchAll();

/* ==============================
   FETCH FILTER DATA FOR DETAILED REPORT
============================== */
$classes = $pdo->query("SELECT * FROM classes ORDER BY class_name")->fetchAll();
$rooms   = $pdo->query("SELECT * FROM rooms ORDER BY room_name")->fetchAll();

$selected_class = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selected_room  = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$from_date      = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date        = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

// SUPER FILTER VARIABLES
$student_name      = isset($_GET['student_name']) ? trim($_GET['student_name']) : '';
$attendance_status = isset($_GET['attendance_status']) ? $_GET['attendance_status'] : '';

$report_data = [];
$present_total = 0;
$absent_total  = 0;
$total_students = 0;

/* ==============================
   FETCH DETAILED REPORT DATA & APPLY SUPER FILTERS
============================== */
if ($selected_class > 0) {
    $query = "
        SELECT s.id, s.full_name,
               SUM(CASE WHEN a.period_1_status = 'present' THEN 1 ELSE 0 END) as p1_present,
               SUM(CASE WHEN a.period_2_status = 'present' THEN 1 ELSE 0 END) as p2_present,
               COUNT(DISTINCT a.date) as total_days
        FROM students s
        LEFT JOIN attendance a 
            ON s.id = a.student_id 
            AND a.date BETWEEN ? AND ?
        WHERE s.class_id = ?
    ";

    $params = [$from_date, $to_date, $selected_class];

    if ($selected_room > 0) {
        $query .= " AND s.room_id = ?";
        $params[] = $selected_room;
    }

    // SUPER FILTER: SQL Student Name Search
    if (!empty($student_name)) {
        $query .= " AND s.full_name LIKE ?";
        $params[] = "%" . $student_name . "%";
    }

    $query .= " GROUP BY s.id, s.full_name ORDER BY s.full_name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $raw_data = $stmt->fetchAll();

    // SUPER FILTER: Array-level Status Filtering
    foreach ($raw_data as $row) {
        $total_possible = $row['total_days'] * 2;
        $student_present = $row['p1_present'] + $row['p2_present'];
        $perc = $total_possible > 0 ? round(($student_present / $total_possible) * 100) : 0;

        // Apply Threshold Filters
        if ($attendance_status === 'perfect' && $perc < 100) continue;
        if ($attendance_status === 'average' && ($perc < 50 || $perc == 100)) continue;
        if ($attendance_status === 'at_risk' && $perc >= 50) continue;

        $row['perc'] = $perc; // Cache for CSV and HTML
        $report_data[] = $row;
    }
    
    $total_students = count($report_data);
}

/* ==============================
   CSV EXPORT FIX (Now runs safely)
============================== */
// Make sure to extract cleanly so we don't duplicate the "export" parameter
$query_string = preg_replace('/&?export=[^&]*/', '', $_SERVER['QUERY_STRING']);

if (isset($_GET['export']) && $_GET['export'] == 'csv' && !empty($report_data)) {
    ob_end_clean(); // FIX: Clears all HTML output from header.php so the file downloads pure CSV
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Attendance_Report_' . $from_date . '_to_' . $to_date . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // FIX: Add UTF-8 BOM so Excel opens the file properly and reads special characters
    fputs($output, "\xEF\xBB\xBF");
    
    fputcsv($output, ['Student Name', 'P1 Present', 'P2 Present', 'Attendance %']);

    foreach ($report_data as $row) {
        fputcsv($output, [$row['full_name'], $row['p1_present'], $row['p2_present'], $row['perc'] . '%']);
    }
    fclose($output);
    exit(); // Stop script execution here so footer doesn't print
}
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
/* Today's Summary Cards */
.today-title { font-size: 1.2rem; font-weight: 700; color: #4e73df; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.class-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-bottom: 40px; }
.class-card { background: #fff; border-radius: 15px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 5px solid #4e73df; transition: all 0.3s ease; position: relative; overflow: hidden; }
.class-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
.class-card.complete { border-left-color: #1cc88a; }
.class-card.partial { border-left-color: #f6c23e; }
.class-card.empty { border-left-color: #e74a3b; }
.class-card .class-name { font-size: 1.1rem; font-weight: 700; color: #333; margin-bottom: 15px; display: block; }
.class-card .stats-row { display: flex; justify-content: space-between; align-items: center; }
.class-card .stat-box { text-align: center; }
.class-card .stat-value { font-size: 1.4rem; font-weight: 700; display: block; }
.class-card .stat-label { font-size: 0.8rem; color: #888; text-transform: uppercase; }
.class-card .percentage-badge { position: absolute; top: 15px; right: 15px; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; color: #fff; }
.bg-present { background: #1cc88a; } .bg-absent { background: #e74a3b; } .bg-warning { background: #f6c23e; }

/* General Styles */
.stat-cards{display:flex;gap:20px;margin-bottom:25px;flex-wrap:wrap;}
.stat-card{flex:1;min-width:220px;padding:25px;border-radius:12px;color:#fff;position:relative;transition:.4s;box-shadow:0 10px 25px rgba(0,0,0,.08);}
.stat-card:hover{transform:translateY(-6px);}
.stat-card i{font-size:30px;position:absolute;right:20px;top:20px;opacity:.3;}
.card-present{background:linear-gradient(45deg,#1cc88a,#17a673);}
.card-absent{background:linear-gradient(45deg,#e74a3b,#c0392b);}
.card-total{background:linear-gradient(45deg,#4e73df,#2e59d9);}
.stat-card h4{font-size:28px;margin:0;}
.stat-card p{margin:0;font-weight:600;}
.progress{background:#eaecf4;border-radius:6px;height:10px;}
.progress-bar{height:100%;border-radius:6px;transition:width .6s ease;}

/* Super Filter Styling Enhancements */
.super-filter-box { background: #f8f9fc; border: 1px solid #e3e6f0; padding: 15px; border-radius: 8px; margin-bottom: 15px; }

/* PDF Export Specific Adjustments */
.pdf-header-title { display: none; }
</style>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Attendance Reports & Analytics</h1>
</div>

<div class="today-title">
    <i class="fas fa-filter"></i> SUPER FILTER (WARBIXIN FAAHFAAHSAN)
</div>

<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="GET" class="row" style="display:flex;gap:15px;flex-wrap:wrap;align-items:flex-end;">
            
            <div class="super-filter-box w-100 mb-0 d-flex flex-wrap" style="gap:15px;">
                <div class="form-group mb-0" style="flex: 1; min-width: 150px;">
                    <label class="font-weight-bold">Class *</label>
                    <select name="class_id" class="form-control" required>
                        <option value="">Select Class</option>
                        <?php foreach($classes as $class): ?>
                            <option value="<?= $class['id']; ?>" <?= $selected_class==$class['id']?'selected':''; ?>>
                                <?= htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group mb-0" style="flex: 1; min-width: 150px;">
                    <label class="font-weight-bold">Room</label>
                    <select name="room_id" class="form-control">
                        <option value="">All Rooms</option>
                        <?php foreach($rooms as $room): ?>
                            <option value="<?= $room['id']; ?>" <?= $selected_room==$room['id']?'selected':''; ?>>
                                <?= htmlspecialchars($room['room_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group mb-0" style="flex: 1; min-width: 150px;">
                    <label class="font-weight-bold text-primary">Student Name</label>
                    <input type="text" name="student_name" class="form-control" placeholder="Search by name..." value="<?= htmlspecialchars($student_name); ?>">
                </div>

                <div class="form-group mb-0" style="flex: 1; min-width: 150px;">
                    <label class="font-weight-bold text-primary">Attendance Status</label>
                    <select name="attendance_status" class="form-control">
                        <option value="">All Students</option>
                        <option value="perfect" <?= $attendance_status=='perfect'?'selected':''; ?>>100% (Perfect)</option>
                        <option value="average" <?= $attendance_status=='average'?'selected':''; ?>>50% - 99% (Average)</option>
                        <option value="at_risk" <?= $attendance_status=='at_risk'?'selected':''; ?>>< 50% (At Risk)</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>From</label>
                <input type="date" name="from_date" class="form-control" value="<?= $from_date; ?>">
            </div>

            <div class="form-group">
                <label>To</label>
                <input type="date" name="to_date" class="form-control" value="<?= $to_date; ?>">
            </div>

            <div class="form-group" style="display:flex; gap:10px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Generate</button>
                <?php if(!empty($report_data)): ?>
                    <a href="?<?= $query_string; ?>&export=csv" class="btn btn-success"><i class="fas fa-file-csv"></i> CSV</a>
                    <button type="button" onclick="downloadPDF()" class="btn btn-danger"><i class="fas fa-file-pdf"></i> PDF</button>
                <?php endif; ?>
            </div>

        </form>
    </div>
</div>

<?php if(!empty($report_data)): ?>

    <?php
    foreach ($report_data as $row) {
        if ($row['perc'] >= 50) $present_total++;
        else $absent_total++;
    }
    ?>

    <div id="pdf-report-container">
        
        <h3 class="pdf-header-title text-center mb-4" style="color:#333; border-bottom: 2px solid #4e73df; padding-bottom: 10px;">
            Attendance Report (<?= $from_date; ?> to <?= $to_date; ?>)
        </h3>

        <div class="stat-cards">
            <div class="stat-card card-present">
                <i class="fas fa-user-check"></i>
                <p>Overall Present (>=50%)</p>
                <h4><?= $present_total; ?></h4>
            </div>

            <div class="stat-card card-absent">
                <i class="fas fa-user-times"></i>
                <p>Overall Absent (<50%)</p>
                <h4><?= $absent_total; ?></h4>
            </div>

            <div class="stat-card card-total">
                <i class="fas fa-users"></i>
                <p>Filtered Students</p>
                <h4><?= $total_students; ?></h4>
            </div>
        </div>

        <div class="card mb-5 shadow-sm">
            <div class="card-header bg-white font-weight-bold">
                Data Table Summary
            </div>

            <div class="card-body p-0">
                <table class="table table-bordered table-striped m-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Student Name</th>
                            <th>P1 Present</th>
                            <th>P2 Present</th>
                            <th>Attendance %</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($report_data as $row): 
                        $perc = $row['perc'];
                        $color = $perc==100 ? '#1cc88a' : ($perc>=50 ? '#f6c23e' : '#e74a3b');
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['full_name']); ?></strong></td>
                            <td><?= $row['p1_present']; ?></td>
                            <td><?= $row['p2_present']; ?></td>
                            <td style="min-width: 150px;">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="progress" style="flex:1;">
                                        <div class="progress-bar" style="width:<?= $perc; ?>%;background:<?= $color; ?>;"></div>
                                    </div>
                                    <strong><?= $perc; ?>%</strong>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div> <?php elseif($selected_class > 0): ?>
    <div class="alert alert-info border-left-info shadow-sm mb-5">
        <i class="fas fa-info-circle"></i> No attendance data found matching your super filter criteria.
    </div>
<?php endif; ?>

<hr class="mb-4">

<div class="today-title">
    <i class="fas fa-calendar-day"></i> XAALADDA FASALADA MAANTA (<?= date('d-M-Y') ?>)
</div>

<div class="class-grid">
    <?php foreach($today_classes as $tc): 
        $perc = $tc['total_students'] > 0 ? round(($tc['present_count'] / $tc['total_students']) * 100) : 0;
        $status_class = $perc == 100 ? 'complete' : ($perc > 0 ? 'partial' : 'empty');
        $badge_color = $perc == 100 ? 'bg-present' : ($perc > 0 ? 'bg-warning' : 'bg-absent');
    ?>
    <div class="class-card <?= $status_class ?>">
        <span class="percentage-badge <?= $badge_color ?>"><?= $perc ?>%</span>
        <span class="class-name"><?= htmlspecialchars($tc['class_name']) ?></span>
        <div class="stats-row">
            <div class="stat-box">
                <span class="stat-value" style="color: #1cc88a;"><?= $tc['present_count'] ?></span>
                <span class="stat-label">Joogta</span>
            </div>
            <div class="stat-box">
                <span class="stat-value" style="color: #e74a3b;"><?= $tc['total_students'] - $tc['present_count'] ?></span>
                <span class="stat-label">Maqan</span>
            </div>
            <div class="stat-box">
                <span class="stat-value" style="color: #4e73df;"><?= $tc['total_students'] ?></span>
                <span class="stat-label">Wadar</span>
            </div>
        </div>
        <div class="progress mt-3">
            <div class="progress-bar <?= $badge_color ?>" style="width: <?= $perc ?>%"></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
function downloadPDF() {
    // Show hidden PDF header temporarily
    document.querySelector('.pdf-header-title').style.display = 'block';

    const element = document.getElementById('pdf-report-container');
    const opt = {
        margin:       0.3,
        filename:     'Attendance_Report_<?= $from_date; ?>_to_<?= $to_date; ?>.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true },
        jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
    };

    // Generate PDF then hide the header again
    html2pdf().set(opt).from(element).save().then(function() {
        document.querySelector('.pdf-header-title').style.display = 'none';
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>