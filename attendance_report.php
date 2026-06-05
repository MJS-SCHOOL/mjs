<?php
require_once 'includes/header.php';
require_admin();

$classes = $pdo->query("SELECT * FROM classes ORDER BY class_name")->fetchAll();
$rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_name")->fetchAll();

$selected_class = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selected_room = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$from_date = isset($_GET['from_date']) ? sanitize($_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? sanitize($_GET['to_date']) : '';

$report_data = [];
$class_name = '';
if ($selected_class > 0) {
    $class_stmt = $pdo->prepare("SELECT class_name FROM classes WHERE id = ?");
    $class_stmt->execute([$selected_class]);
    $class_info = $class_stmt->fetch();
    $class_name = $class_info ? $class_info['class_name'] : '';
    
    $query = "SELECT s.id, s.full_name,  
               GROUP_CONCAT(CONCAT(a.date, '|', IFNULL(a.period_1_status,''), '|', IFNULL(a.period_1_subject,''), '|', IFNULL(a.period_2_status,''), '|', IFNULL(a.period_2_subject,'')) SEPARATOR ';;') as attendance_details
        FROM students s
        LEFT JOIN attendance a ON s.id = a.student_id ";
    
    $params = [];
    if (!empty($from_date) && !empty($to_date)) {
        $query .= " AND a.date BETWEEN ? AND ? ";
        $params[] = $from_date;
        $params[] = $to_date;
    }
    
    $query .= " WHERE s.class_id = ? ";
    $params[] = $selected_class;
    
    if ($selected_room > 0) {
        $query .= " AND s.room_id = ? ";
        $params[] = $selected_room;
    }
    $query .= " GROUP BY s.id, s.full_name ORDER BY s.full_name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $report_data = $stmt->fetchAll();
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
:root {
    --primary-gradient: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    --success-gradient: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
    --danger-gradient: linear-gradient(135deg, #e74a3b 0%, #be2617 100%);
    --warning-gradient: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%);
    --info-gradient: linear-gradient(135deg, #36b9cc 0%, #258391 100%);
    --dark-gradient: linear-gradient(135deg, #5a5c69 0%, #373840 100%);
}

/* Web Interface Styles */
.dash-card { 
    border-radius: 12px; color: white; padding: 12px; position: relative; overflow: hidden;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1); margin-bottom: 10px; transition: all 0.3s ease;
    border: none; min-height: 90px; cursor: pointer;
}
.dash-card:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.15); }
.dash-card::after { content: ''; position: absolute; top: -50%; right: -20%; width: 100px; height: 100px; background: rgba(255,255,255,0.1); border-radius: 50%; }
.bg-custom-present { background: var(--success-gradient) !important; }
.bg-custom-absent { background: var(--danger-gradient) !important; }
.bg-custom-total { background: var(--primary-gradient) !important; }
.bg-custom-process { background: var(--warning-gradient) !important; }
.bg-custom-dark { background: var(--dark-gradient) !important; }
.bg-custom-info { background: var(--info-gradient) !important; }
.stat-label { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
.stat-number { font-size: 1.5rem; font-weight: 800; line-height: 1; }
.dash-icon { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 1.8rem; opacity: 0.2; }

/* PDF Professional Redesign - High Precision (0.5cm) */
#pdf-content { background: white; width: 100%; position: relative; overflow: visible; }
.pdf-page-wrapper { padding: 0.5cm; box-sizing: border-box; position: relative; min-height: 100%; }

.watermark-overlay {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
    z-index: 0; pointer-events: none; opacity: 0.05;
}
.watermark-overlay img { width: 50%; transform: rotate(-30deg); }

.pdf-header-new {
    display: flex; justify-content: space-between; align-items: center;
    border-bottom: 3px solid #2e59d9; padding-bottom: 10px; margin-bottom: 15px;
    position: relative; z-index: 1;
}
.header-left { display: flex; align-items: center; }
.header-logo-new { width: 80px; height: 80px; object-fit: contain; margin-right: 15px; }
.header-text-new h1 { font-size: 26px; font-weight: 900; color: #2e59d9; margin: 0; letter-spacing: 1px; }
.header-text-new p { font-size: 12px; color: #5a5c69; margin: 2px 0 0 0; font-weight: 700; text-transform: uppercase; }

.header-right-new { text-align: right; }
.doc-badge { background: #2e59d9; color: white; padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: 800; -webkit-print-color-adjust: exact; }
.header-right-new p { font-size: 10px; margin: 4px 0 0 0; font-weight: 700; color: #3a3b45; }

.meta-grid-new {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;
    background: #f8f9fc; padding: 12px; border-radius: 6px; margin-bottom: 15px;
    border: 1px solid #e3e6f0; position: relative; z-index: 1;
}
.meta-box-new { font-size: 11px; }
.meta-box-new label { display: block; color: #4e73df; font-weight: 800; margin-bottom: 2px; text-transform: uppercase; font-size: 9px; }
.meta-box-new span { color: #3a3b45; font-weight: 700; font-size: 12px; }

.report-table-new { 
    width: 100%; border-collapse: collapse; margin-bottom: 20px; 
    position: relative; z-index: 1; background: white; table-layout: fixed;
}
.report-table-new thead th { 
    background: #4e73df !important; color: white !important; font-size: 11px; 
    font-weight: 800; text-transform: uppercase; padding: 10px 8px; 
    border: 1px solid #2e59d9; text-align: left; -webkit-print-color-adjust: exact;
}
.report-table-new tbody td { 
    padding: 8px; border: 1px solid #e3e6f0; font-size: 11px; 
    vertical-align: middle; word-wrap: break-word;
}
.report-table-new tr:nth-child(even) { background-color: #fcfdfe; }

.pill-new {
    display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 10px;
    font-weight: 700; margin: 1px; border: 1px solid transparent; -webkit-print-color-adjust: exact;
}
.pill-p { background: #e6fcf5 !important; color: #0ca678 !important; border-color: #b2f2bb !important; }
.pill-a { background: #fff5f5 !important; color: #fa5252 !important; border-color: #ffc9c9 !important; }
.pill-i { background: #fff9db !important; color: #f08c00 !important; border-color: #ffec99 !important; }

.absent-tag {
    padding: 4px 10px; border-radius: 15px; font-weight: 900; font-size: 11px;
    display: inline-block; -webkit-print-color-adjust: exact;
}
.tag-danger { background: #e74a3b !important; color: white !important; }
.tag-success { background: #1cc88a !important; color: white !important; }

.footer-new {
    display: flex; justify-content: space-between; align-items: flex-end;
    margin-top: 20px; padding-top: 15px; border-top: 2px solid #e3e6f0;
    position: relative; z-index: 1; page-break-inside: avoid;
}
.footer-note { font-size: 9px; color: #858796; font-style: italic; max-width: 40%; }
.sig-section { display: flex; gap: 40px; }
.sig-box { text-align: center; width: 160px; }
.sig-line { border-top: 1.5px solid #3a3b45; margin-top: 35px; padding-top: 5px; font-weight: 800; font-size: 10px; color: #3a3b45; text-transform: uppercase; }

.horizontal-cards { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin-bottom: 20px; position: relative; z-index: 1; }
.hidden-row { display: none !important; }

@media print {
    .no-print { display: none !important; }
    body { margin: 0 !important; padding: 0 !important; }
}
</style>

<div class="container-fluid py-3 px-md-4">
    <div class="d-sm-flex align-items-center justify-content-between mb-4 no-print animate__animated animate__fadeInDown">
        <h1 class="h3 text-gray-800 font-weight-bold"><i class="fas fa-chart-line mr-2 text-primary"></i>Warbixinta Xaadirinta Fasal</h1>
        <div class="btn-group">
            <?php if (!empty($report_data)): ?>
                <button id="btn-generate-pdf" class="btn btn-danger btn-lg shadow-sm rounded-pill px-4 mr-2"><i class="fas fa-file-pdf mr-2"></i> Download Professional PDF</button>
                <button id="btn-reset-filter" class="btn btn-secondary btn-lg shadow-sm rounded-pill px-4 d-none"><i class="fas fa-sync-alt mr-2"></i> Show All</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm mb-4 no-print border-0 animate__animated animate__fadeIn">
        <div class="card-body p-4 bg-light rounded-lg">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="small font-weight-bold text-primary">Fasalka</label>
                    <select name="class_id" class="form-control border-0 shadow-sm" required>
                        <option value="">Dooro Fasal</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id']; ?>" <?= $selected_class == $class['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($class['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="small font-weight-bold text-primary">Qolka (Ikhtiyaari)</label>
                    <select name="room_id" class="form-control border-0 shadow-sm">
                        <option value="">Dhammaan Qolalka</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?= $room['id']; ?>" <?= $selected_room == $room['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($room['room_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small font-weight-bold text-primary">Laga bilaabo</label>
                    <input type="date" name="from_date" class="form-control border-0 shadow-sm" value="<?= $from_date; ?>">
                </div>
                <div class="col-md-2">
                    <label class="small font-weight-bold text-primary">Ilaa</label>
                    <input type="date" name="to_date" class="form-control border-0 shadow-sm" value="<?= $to_date; ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-block shadow-sm font-weight-bold py-2"><i class="fas fa-search mr-2"></i>RAADI</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($report_data)): ?>
    <div class="animate__animated animate__fadeInUp">
        <div id="pdf-content">
            <?php 
            $logo_path = "assets/img/logo.png"; // Default Fallback
            if (!empty($settings['logo'])) {
                $saved_logo = "images/logo/" . $settings['logo'];
                if (file_exists($saved_logo)) {
                    $logo_path = $saved_logo;
                }
            }
            ?>
            <div class="watermark-overlay"><img src="<?php echo htmlspecialchars($logo_path); ?>"></div>
            
            <div class="pdf-page-wrapper">
                <div class="pdf-header-new">
                    <div class="header-left">
                        <img src="<?php echo htmlspecialchars($logo_path); ?>" class="header-logo-new">
                        <div class="header-text-new">
                            <h1><?php echo $settings['site_name']; ?></h1>
                            <p>Official Attendance & Academic Performance Report</p>
                        </div>
                    </div>
                    <div class="header-right-new">
                        <div class="doc-badge">OFFICIAL DOCUMENT</div>
                        <p>PRINTED: <?= date('d/m/Y H:i') ?></p>
                        <p>SYSTEM ID: MJS-<?= date('Ymd') ?></p>
                    </div>
                </div>

                <div class="meta-grid-new">
                    <div class="meta-box-new"><label>Class Name</label><span><?= htmlspecialchars($class_name) ?></span></div>
                    <div class="meta-box-new"><label>Report Period</label><span><?= (!empty($from_date) ? "$from_date - $to_date" : "All Academic Year") ?></span></div>
                    <div class="meta-box-new"><label>Report Type</label><span id="pdf-cat-label">General Attendance</span></div>
                    <div class="meta-box-new"><label>Student Count</label><span><?= count($report_data) ?> Students</span></div>
                </div>

                <div class="horizontal-cards no-print">
                    <?php
                    $count_all_green = 0; $count_1_missed = 0; $count_2_missed = 0; $count_3_missed = 0; $count_4plus_missed = 0;
                    $processed = [];
                    foreach ($report_data as $row) {
                        $s = ['name' => $row['full_name'], 'items' => [], 'missed' => 0];
                        $total_missed = 0; $daily_count = 0;
                        if (!empty($row['attendance_details'])) {
                            foreach (explode(';;', $row['attendance_details']) as $rec) {
                                $p = explode('|', $rec); if (count($p) < 5) continue;
                                $daily_count++;
                                if ($p[1] === 'absent') $total_missed++;
                                if ($p[3] === 'absent') $total_missed++;
                                $s['items'][] = ['n' => !empty($p[2]) ? $p[2] : "Xisaab", 's' => $p[1]==='present'?'p':($p[1]==='absent'?'a':'i'), 'd' => $p[0]];
                                $s['items'][] = ['n' => !empty($p[4]) ? $p[4] : "Technology", 's' => $p[3]==='present'?'p':($p[3]==='absent'?'a':'i'), 'd' => $p[0]];
                            }
                        }
                        $cat = '';
                        if ($total_missed == 0 && $daily_count > 0) { $count_all_green++; $cat = 'perfect'; }
                        elseif ($total_missed == 1) { $count_1_missed++; $cat = 'missed-1'; }
                        elseif ($total_missed == 2) { $count_2_missed++; $cat = 'missed-2'; }
                        elseif ($total_missed == 3) { $count_3_missed++; $cat = 'missed-3'; }
                        elseif ($total_missed >= 4) { $count_4plus_missed++; $cat = 'missed-4plus'; }
                        $s['missed'] = $total_missed; $s['cat'] = $cat; $processed[] = $s;
                    }
                    ?>
                    <div class="dash-card bg-custom-total" onclick="filterCategory('all', 'GENERAL REPORT')">
                        <div class="stat-label">TOTAL</div><div class="stat-number"><?= count($report_data) ?></div><i class="fas fa-users dash-icon"></i>
                    </div>
                    <div class="dash-card bg-custom-present" onclick="filterCategory('perfect', 'PERFECT ATTENDANCE')">
                        <div class="stat-label">PERFECT</div><div class="stat-number"><?= $count_all_green ?></div><i class="fas fa-user-check dash-icon"></i>
                    </div>
                    <div class="dash-card bg-custom-info" onclick="filterCategory('missed-1', 'MISSED 1 SUBJECT')">
                        <div class="stat-label">MISSED 1</div><div class="stat-number"><?= $count_1_missed ?></div><i class="fas fa-exclamation-circle dash-icon"></i>
                    </div>
                    <div class="dash-card bg-custom-process" onclick="filterCategory('missed-2', 'MISSED 2 SUBJECTS')">
                        <div class="stat-label">MISSED 2</div><div class="stat-number"><?= $count_2_missed ?></div><i class="fas fa-exclamation-triangle dash-icon"></i>
                    </div>
                    <div class="dash-card bg-custom-absent" onclick="filterCategory('missed-3', 'MISSED 3 SUBJECTS')">
                        <div class="stat-label">MISSED 3</div><div class="stat-number"><?= $count_3_missed ?></div><i class="fas fa-times-circle dash-icon"></i>
                    </div>
                    <div class="dash-card bg-custom-dark" onclick="filterCategory('missed-4plus', 'MISSED 4+ SUBJECTS')">
                        <div class="stat-label">MISSED 4+</div><div class="stat-number"><?= $count_4plus_missed ?></div><i class="fas fa-ban dash-icon"></i>
                    </div>
                </div>

                <table class="report-table-new" id="report-table">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">#</th>
                            <th style="width: 220px;">Student Full Name</th>
                            <th>Attendance Log & Subject Performance</th>
                            <th style="width: 100px; text-align: center;">Absences</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($processed as $index => $student): ?>
                        <tr class="report-row" data-cat="<?= $student['cat'] ?>">
                            <td style="text-align: center; font-weight: 800; color: #4e73df;"><?= $index + 1 ?></td>
                            <td style="font-weight: 700; color: #2e3748;"><?= htmlspecialchars($student['name']) ?></td>
                            <td>
                                <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                    <?php foreach ($student['items'] as $item): ?>
                                        <div class="pill-new pill-<?= $item['s'] ?>">
                                            <?= htmlspecialchars($item['n']) ?>
                                            <span style="display: block; font-size: 8px; opacity: 0.7; font-weight: normal;"><?= $item['d'] ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <div class="absent-tag <?= $student['missed']>0?'tag-danger':'tag-success' ?>">
                                    <?= $student['missed'] ?> Missed
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="footer-new">
                    <div class="footer-note">
                        * This is an automated academic record from Mo'alim Jama School Management System. Generated on <?= date('Y-m-d H:i:s') ?>.
                    </div>
                    <div class="sig-section">
                        <div class="sig-box"><div class="sig-line">Office Stamp</div></div>
                        <div class="sig-box"><div class="sig-line">Principal Signature</div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php elseif ($selected_class > 0): ?>
        <div class="alert alert-info shadow-sm no-print">No records found for the selected criteria.</div>
    <?php endif; ?>
</div>

<script>
let currentCategory = 'all';
function filterCategory(cat, label) {
    currentCategory = cat;
    const rows = document.querySelectorAll('.report-row');
    const labelText = document.getElementById('pdf-cat-label');
    const resetBtn = document.getElementById('btn-reset-filter');
    if(labelText) labelText.innerText = label;
    if (cat === 'all') { resetBtn?.classList.add('d-none'); } else { resetBtn?.classList.remove('d-none'); }
    rows.forEach(row => {
        if (cat === 'all' || row.getAttribute('data-cat') === cat) { row.classList.remove('hidden-row'); } else { row.classList.add('hidden-row'); }
    });
}
document.getElementById('btn-reset-filter')?.addEventListener('click', () => { filterCategory('all', 'GENERAL ATTENDANCE'); });

document.getElementById('btn-generate-pdf')?.addEventListener('click', function() {
    const element = document.getElementById('pdf-content');
    const opt = {
        margin: 0,
        filename: `MJS_Report_<?= str_replace(" ", "_", $class_name) ?>_<?= date("Ymd") ?>.pdf`,
        image: { type: 'jpeg', quality: 1.0 },
        html2canvas: { scale: 2, useCORS: true, logging: false, letterRendering: true, backgroundColor: '#ffffff' },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape', compress: true },
        pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
    };
    
    Swal.fire({
        title: 'Generating Report...',
        text: 'Applying international professional layout standards.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
            html2pdf().set(opt).from(element).save().then(() => {
                Swal.fire({ icon: 'success', title: 'Success!', text: 'Professional Report Downloaded.', timer: 2000, showConfirmButton: false });
            });
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>