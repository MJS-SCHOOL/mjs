<?php
require_once 'includes/header.php';
require_admin();

// Saxida Taariikhda iyo Saacadda si ay PDF-ka ugu soo baxdo si sax ah 100%
date_default_timezone_set('Africa/Mogadishu');

// Get all distinct dates from attendance table
$dates_query = $pdo->query("SELECT DISTINCT date FROM attendance ORDER BY date DESC");
$all_dates = $dates_query->fetchAll(PDO::FETCH_COLUMN);

// Get date range from query parameters
$default_date = (count($all_dates) > 0) ? $all_dates[0] : date('Y-m-d');
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : $default_date;
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : $default_date;

// Validate dates
if (!strtotime($from_date) || !strtotime($to_date)) {
    $from_date = date('Y-m-d', strtotime('-30 days'));
    $to_date = date('Y-m-d');
}

// Get all classes
$classes_query = $pdo->query("SELECT * FROM classes WHERE is_disabled = 0 ORDER BY class_name ASC");
$classes = $classes_query->fetchAll(PDO::FETCH_ASSOC);

// Get all rooms
$rooms_query = $pdo->query("SELECT * FROM rooms ORDER BY room_name ASC");
$rooms = $rooms_query->fetchAll(PDO::FETCH_ASSOC);

// Build the main query to get absent students per day for all classrooms and sections
$query = "
    SELECT 
        a.date,
        r.id AS room_id,
        r.room_name,
        c.id AS class_id,
        c.class_name,
        s.id AS student_id,
        s.full_name,
        a.period_1_status,
        a.period_2_status,
        a.period_1_subject, 
        a.period_2_subject  
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN rooms r ON s.room_id = r.id
    JOIN classes c ON s.class_id = c.id
    WHERE a.date BETWEEN ? AND ?
    AND (a.period_1_status = 'absent' OR a.period_2_status = 'absent')
    ORDER BY a.date DESC, r.room_name ASC, c.class_name ASC, s.full_name ASC
";

$stmt = $pdo->prepare($query);
$stmt->execute([$from_date, $to_date]);
$absent_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Xisaabinta Tirada Guud iyo Fasalka ugu maqan
$totals_query = "
    SELECT 
        a.date, s.room_id, s.class_id, c.class_name,
        COUNT(DISTINCT s.id) as total_students_in_class,
        SUM(CASE WHEN a.period_1_status = 'absent' OR a.period_2_status = 'absent' THEN 1 ELSE 0 END) as absent_count_in_class
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN classes c ON s.class_id = c.id
    WHERE a.date BETWEEN ? AND ?
    GROUP BY a.date, s.room_id, s.class_id, c.class_name
";
$totals_stmt = $pdo->prepare($totals_query);
$totals_stmt->execute([$from_date, $to_date]);
$totals_data = $totals_stmt->fetchAll(PDO::FETCH_ASSOC);

$totals_map = [];
$max_absent_in_single_class_all = 0; 
$max_absent_class_name_all = 'Ma jiro'; 
$max_absent_by_date = [];                

foreach ($totals_data as $row) {
    $date = $row['date'];
    $absent_count = (int)$row['absent_count_in_class'];
    $class_name = $row['class_name'];
    
    if ($absent_count > $max_absent_in_single_class_all) {
        $max_absent_in_single_class_all = $absent_count;
        $max_absent_class_name_all = $class_name;
    }
    
    if (!isset($max_absent_by_date[$date]) || $absent_count > $max_absent_by_date[$date]['count']) {
        $max_absent_by_date[$date] = [
            'count' => $absent_count,
            'name' => $class_name
        ];
    }

    $totals_map[$date][$row['room_id']][$row['class_id']] = [
        'total' => $row['total_students_in_class'],
        'absent' => $row['absent_count_in_class']
    ];
}

// Group data by date, room, and class
$grouped_data = [];
foreach ($absent_data as $row) {
    $date = $row['date'];
    $room_id = $row['room_id'];
    $class_id = $row['class_id'];
    
    if (!isset($grouped_data[$date])) {
        $grouped_data[$date] = [];
    }
    if (!isset($grouped_data[$date][$room_id])) {
        $grouped_data[$date][$room_id] = [];
    }
    if (!isset($grouped_data[$date][$room_id][$class_id])) {
        $class_totals = $totals_map[$date][$room_id][$class_id] ?? ['total' => 0, 'absent' => 0];
        $grouped_data[$date][$room_id][$class_id] = [
            'room_name' => $row['room_name'],
            'class_name' => $row['class_name'],
            'total_students' => $class_totals['total'],
            'absent_count' => $class_totals['absent'],
            'students' => []
        ];
    }
    
    $grouped_data[$date][$room_id][$class_id]['students'][] = [
        'id' => $row['student_id'],
        'name' => $row['full_name'],
        'period_1_status' => $row['period_1_status'],
        'period_2_status' => $row['period_2_status'],
        'period_1_subject' => 'Xisaab', // Halkan magaca Xisaab ayaa lagu go'aamiyay
        'period_2_subject' => 'Technology' // Halkan magaca Technology ayaa lagu go'aamiyay
    ];
}

// Summary Statistics
$summary_query = "
    SELECT 
        a.date,
        COUNT(DISTINCT CONCAT(s.room_id, '-', s.class_id)) as total_class_sections,
        COUNT(DISTINCT s.id) as total_students_present_on_date,
        SUM(CASE WHEN a.period_1_status = 'absent' OR a.period_2_status = 'absent' THEN 1 ELSE 0 END) as total_absent_on_date
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    WHERE a.date BETWEEN ? AND ?
    GROUP BY a.date
    ORDER BY a.date DESC
";

$summary_stmt = $pdo->prepare($summary_query);
$summary_stmt->execute([$from_date, $to_date]);
$summary_stats = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);

$summary_by_date = [];
foreach ($summary_stats as $stat) {
    $summary_by_date[$stat['date']] = $stat;
}
?>

<style>
    .absent-report-container { max-width: 1400px; margin: 0 auto; }
    .filter-card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); padding: 25px; margin-bottom: 30px; border-left: 5px solid #4e73df; }
    .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: flex-end; }
    .form-group-inline { margin-bottom: 0; }
    .form-group-inline label { display: block; font-weight: 600; color: #2d3748; margin-bottom: 8px; font-size: 13px; }
    .form-group-inline input, .form-group-inline select { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
    .btn-filter { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); color: white; padding: 10px 25px; border: none; border-radius: 5px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; width: 100%; }
    .btn-export-pdf { background: linear-gradient(135deg, #dc3545 0%, #a02830 100%); color: white; padding: 12px 25px; border: none; border-radius: 5px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; margin-left: 10px; }
    
    /* Summary Cards Styling */
    .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .summary-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); border-top: 4px solid #4e73df; }
    .summary-card.warning { border-top-color: #ffc107; }
    .summary-card.danger { border-top-color: #dc3545; }
    .summary-card-title { font-size: 13px; color: #718096; font-weight: 600; text-transform: uppercase; margin-bottom: 10px; }
    .summary-card-value { font-size: 28px; font-weight: 700; color: #2d3748; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .summary-card-subtitle { font-size: 12px; color: #a0aec0; margin-top: 8px; }

    /* Web Report Styling */
    .date-section { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); margin-bottom: 30px; overflow: hidden; }
    .date-header { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); color: white; padding: 20px; font-size: 18px; font-weight: 700; }
    .date-header-stats { display: flex; gap: 30px; margin-top: 10px; font-size: 14px; font-weight: 500; }
    .room-section { border-bottom: 1px solid #e2e8f0; padding: 20px; }
    .room-title { font-size: 16px; font-weight: 700; color: #2d3748; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
    .class-subsection { background: #f7fafc; border-left: 4px solid #4e73df; padding: 15px; margin-bottom: 15px; border-radius: 5px; }
    .student-item { padding: 10px 0; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; font-size: 14px; }
    .status-badge { padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }
    .status-absent { background: #f8d7da; color: #721c24; }
    .status-present { background: #d4edda; color: #155724; }

    /* =========================================
       PDF SPECIFIC STYLES (Heer Caalami ah)
       ========================================= */
    #pdf-content { display: none; background: white; position: relative; overflow: hidden; }
    .pdf-page-wrapper { width: 100%; background: transparent; padding: 30px; position: relative; z-index: 10; }
    
    .pdf-header-new { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 3px solid #4e73df; position: relative; z-index: 10; }
    .header-left { display: flex; align-items: center; gap: 20px; }
    .header-logo-new { max-width: 80px; height: auto; }
    .header-text-new h1 { margin: 0; font-size: 22px; color: #2d3748; }
    .header-text-new p { margin: 5px 0 0 0; color: #718096; font-size: 13px; }
    .header-right-new { text-align: right; }
    .doc-badge { background: #4e73df; color: white; padding: 6px 12px; border-radius: 4px; font-weight: bold; font-size: 11px; margin-bottom: 8px; display: inline-block; }
    .header-right-new p { margin: 4px 0; font-size: 11px; color: #718096; }
    
    .meta-grid-new { display: flex; justify-content: space-between; margin-bottom: 25px; padding: 15px; background: rgba(248, 249, 252, 0.85); border-radius: 6px; border: 1px solid #e3e6f0; position: relative; z-index: 10; }
    .meta-box-new { display: flex; flex-direction: column; }
    .meta-box-new label { font-size: 10px; color: #4e73df; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; }
    .meta-box-new span { font-size: 13px; color: #3a3b45; font-weight: bold; }

    /* PDF Tables */
    .pdf-table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 25px; font-size: 12px; position: relative; z-index: 10; background: rgba(255, 255, 255, 0.7); }
    .pdf-table th { background-color: #4e73df; color: white; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #c2c9d6; }
    .pdf-table td { padding: 8px 10px; border: 1px solid #e3e6f0; color: #3a3b45; }
    .pdf-table tr:nth-child(even) { background-color: rgba(248, 249, 252, 0.8); }
    .date-title-pdf { font-size: 15px; color: #2d3748; margin-top: 20px; margin-bottom: 10px; font-weight: 700; border-left: 4px solid #4e73df; padding-left: 10px; position: relative; z-index: 10; }

    .status-badge-pdf { font-weight: bold; font-size: 11px; }
    .status-absent-pdf { color: #e74a3b; }
    .status-present-pdf { color: #1cc88a; }

    /* WATERMARK QURXAN OO LA HABEEYAY */
    .watermark-overlay {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        opacity: 0.08; /* Aad u khafiif ah si uusan qoraalka u qarin */
        z-index: 1; /* Wuxuu ka hooseeyaa qoraalka */
        pointer-events: none;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .watermark-overlay img { 
        width: 450px; 
        height: auto; 
    }
</style>

<div class="absent-report-container">
    <div class="d-sm-flex align-items-center justify-content-between mb-4 no-print">
        <h1 class="h3 text-gray-800 font-weight-bold">
            <i class="fas fa-user-clock mr-2 text-primary"></i>Warbixinta Ardayda Maqan
        </h1>
        <div>
            <button id="btn-generate-pdf" class="btn-export-pdf" onclick="generatePDF()">
                <i class="fas fa-file-pdf mr-2"></i>Download PDF
            </button>
        </div>
    </div>

    <div class="filter-card no-print">
        <form method="GET" class="filter-row">
            <div class="form-group-inline">
                <label>Laga Bilaabo</label>
                <input type="date" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>" required>
            </div>
            <div class="form-group-inline">
                <label>Ilaa</label>
                <input type="date" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>" required>
            </div>
            <div class="form-group-inline">
                <button type="submit" class="btn-filter">
                    <i class="fas fa-search mr-2"></i>Raadi
                </button>
            </div>
        </form>
    </div>

    <?php
    $total_dates = count($summary_by_date);
    $total_absent_all = 0;
    foreach ($summary_by_date as $stat) { $total_absent_all += $stat['total_absent_on_date']; }
    $avg_absent_per_day = $total_dates > 0 ? round($total_absent_all / $total_dates, 1) : 0;
    ?>
    <div class="summary-cards no-print">
        <div class="summary-card">
            <div class="summary-card-title">Guud Ahaan Maqan</div>
            <div class="summary-card-value"><?php echo $total_absent_all; ?></div>
            <div class="summary-card-subtitle">Ardayda maqan oo dhan</div>
        </div>
        <div class="summary-card warning">
            <div class="summary-card-title">Celceliska Maalin Kasta</div>
            <div class="summary-card-value"><?php echo $avg_absent_per_day; ?></div>
            <div class="summary-card-subtitle">Celcelis caadi ah</div>
        </div>
        <div class="summary-card danger">
            <div class="summary-card-title">Maalmaha la Xisaabay</div>
            <div class="summary-card-value"><?php echo $total_dates; ?></div>
            <div class="summary-card-subtitle">Maalmaha leh xogta</div>
        </div>
        <div class="summary-card">
            <div class="summary-card-title">Fasalka Ugu Maqnaanshaha Badan</div>
            <div class="summary-card-value" title="<?php echo htmlspecialchars($max_absent_class_name_all); ?>">
                <?php echo htmlspecialchars($max_absent_class_name_all); ?>
            </div>
            <div class="summary-card-subtitle">Wuxuu waayay: <?php echo $max_absent_in_single_class_all; ?> Arday</div>
        </div>
    </div>

    <div id="pdf-content">
        <?php 
        $logo_path = "assets/img/logo.png";
        if (!empty($settings['logo'])) {
            $saved_logo = "images/logo/" . $settings['logo'];
            if (file_exists($saved_logo)) { $logo_path = $saved_logo; }
        }
        ?>
        <!-- Watermark Qurxan -->
        <div class="watermark-overlay">
            <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Watermark">
        </div>
        
        <div class="pdf-page-wrapper">
            <div class="pdf-header-new">
                <div class="header-left">
                    <img src="<?php echo htmlspecialchars($logo_path); ?>" class="header-logo-new">
                    <div class="header-text-new">
                        <h1><?php echo isset($settings['site_name']) ? $settings['site_name'] : 'School Management System'; ?></h1>
                        <p>Diiwaanka Ardayda Maqan - Warbixin Rasmi ah</p>
                    </div>
                </div>
                <div class="header-right-new">
                    <div class="doc-badge">WARBIXIN</div>
                    <p>SYSTEM ID: MJS-<?= date('Ymd') ?></p>
                </div>
            </div>

            <div class="meta-grid-new">
                <div class="meta-box-new"><label>Xilliga Warbixinta</label><span><?= htmlspecialchars($from_date) ?></span></div>
                <div class="meta-box-new"><label>Guud Ahaan Maqan</label><span><?= $total_absent_all ?> Arday</span></div>
                <div class="meta-box-new"><label>Fasalka Ugu Maqan</label><span><?= htmlspecialchars($max_absent_class_name_all) ?></span></div>
            </div>

            <?php if (empty($grouped_data)): ?>
                <div style="text-align:center; padding: 50px; color: #718096; position: relative; z-index: 10;">Wax xog ah ma jiraan muddadan la doortay.</div>
            <?php else: ?>
                <?php foreach ($grouped_data as $date => $rooms_data): ?>
                    <div class="date-title-pdf">Taariikhda: <?php echo date('l, F d, Y', strtotime($date)); ?></div>
                    <table class="pdf-table">
                        <thead>
                            <tr>
                                <!-- Tirada ama Numberka (Serial #) -->
                                <th width="5%">#</th>
                                <th width="15%">Qolka</th>
                                <th width="15%">Fasalka</th>
                                <th width="30%">Magaca Ardayga</th>
                                <th width="17.5%">Xilliga 1aad</th>
                                <th width="17.5%">Xilliga 2aad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $serial_num = 1; // Tirada halkaan ayaa laga bilaabayaa ?>
                            <?php foreach ($rooms_data as $room_id => $classes_data): ?>
                                <?php foreach ($classes_data as $class_id => $class_info): ?>
                                    <?php foreach ($class_info['students'] as $student): ?>
                                        <tr>
                                            <td><?php echo $serial_num++; // Tirada ayaa si toos ah isku kordhinaysa ?></td>
                                            <td><?php echo htmlspecialchars($class_info['room_name']); ?></td>
                                            <td><?php echo htmlspecialchars($class_info['class_name']); ?></td>
                                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                                            <td>
                                                <span class="status-badge-pdf <?php echo $student['period_1_status'] === 'absent' ? 'status-absent-pdf' : 'status-present-pdf'; ?>">
                                                    Xisaab 1 <?php echo $student['period_1_status'] === 'absent' ? 'Maqan' : 'Xaadir'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge-pdf <?php echo $student['period_2_status'] === 'absent' ? 'status-absent-pdf' : 'status-present-pdf'; ?>">
                                                    Technology 2 <?php echo $student['period_2_status'] === 'absent' ? 'Maqan' : 'Xaadir'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($grouped_data)): ?>
        <div class="no-data">
            <div class="no-data-icon"><i class="fas fa-inbox"></i></div>
            <div class="no-data-text">Xogta la raadinayo lama helin</div>
            <div class="no-data-subtext">Fadlan badal taariikhda ama raadi taariikhda kale</div>
        </div>
    <?php else: ?>
        <?php foreach ($grouped_data as $date => $rooms_data): ?>
            <div class="date-section no-print">
                <div class="date-header">
                    <div><i class="fas fa-calendar-alt mr-2"></i><?php echo date('l, F d, Y', strtotime($date)); ?></div>
                    <?php if (isset($summary_by_date[$date])): ?>
                        <div class="date-header-stats">
                            <div class="date-header-stat"><i class="fas fa-users"></i><span>Guud Ahaan Maqan: <?php echo $summary_by_date[$date]['total_absent_on_date']; ?></span></div>
                            <div class="date-header-stat">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Fasalka Ugu Maqan: <strong><?php echo isset($max_absent_by_date[$date]) ? htmlspecialchars($max_absent_by_date[$date]['name']) . " (" . $max_absent_by_date[$date]['count'] . " Arday)" : 'Ma jiro'; ?></strong></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php foreach ($rooms_data as $room_id => $classes_data): ?>
                    <div class="room-section">
                        <div class="room-title">
                            <div class="room-icon"><i class="fas fa-door-open"></i></div>
                            <span><?php echo htmlspecialchars($classes_data[array_key_first($classes_data)]['room_name']); ?></span>
                        </div>
                        <?php foreach ($classes_data as $class_id => $class_info): ?>
                            <div class="class-subsection">
                                <div class="class-subsection-title"><span><?php echo htmlspecialchars($class_info['class_name']); ?></span></div>
                                <ul class="student-list">
                                    <?php foreach ($class_info['students'] as $student): ?>
                                        <li class="student-item">
                                            <span class="student-name"><i class="fas fa-user-circle mr-2" style="color: #4e73df;"></i><?php echo htmlspecialchars($student['name']); ?></span>
                                            <div class="student-status">
                                                <?php if ($student['period_1_status'] === 'absent'): ?>
                                                    <span class="status-badge status-absent">Xisaab 1 Maqan</span>
                                                <?php else: ?>
                                                    <span class="status-badge status-present">Xisaab 1 Xaadir</span>
                                                <?php endif; ?>
                                                
                                                <?php if ($student['period_2_status'] === 'absent'): ?>
                                                    <span class="status-badge status-absent">Technology 2 Maqan</span>
                                                <?php else: ?>
                                                    <span class="status-badge status-present">Technology 2 Xaadir</span>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
function generatePDF() {
    const pdfElement = document.getElementById('pdf-content');
    
    // Si kumeel gaar ah u muuji (Display:block) si uu html2pdf u arko
    pdfElement.style.display = 'block';
    
    const opt = {
        margin:       [10, 10, 15, 10], // Top, Left, Bottom, Right
        filename:     'Warbixinta_Ardayda_Maqan_<?= date('Y-m-d') ?>.pdf',
        image:        { type: 'jpeg', quality: 1 },
        html2canvas:  { scale: 2, useCORS: true },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    // Soo Saarida PDF-ka iyo ku darista Page Numbers-ka
    html2pdf().set(opt).from(pdfElement).toPdf().get('pdf').then(function (pdf) {
        var totalPages = pdf.internal.getNumberOfPages();
        for (let i = 1; i <= totalPages; i++) {
            pdf.setPage(i);
            pdf.setFontSize(10);
            pdf.setTextColor(100);
            // Qeybta hoose (Footer) xaga midig
            pdf.text('Page ' + i + ' of ' + totalPages, pdf.internal.pageSize.getWidth() - 30, pdf.internal.pageSize.getHeight() - 8);
        }
    }).save().then(() => {
        // Marka uu soo dejiyo dib u qari
        pdfElement.style.display = 'none';
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>