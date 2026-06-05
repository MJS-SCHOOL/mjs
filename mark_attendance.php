<?php

ob_start();

require_once 'includes/header.php';

require_once 'includes/teacher_page_access.php';

// Check if user has access to this page

require_teacher_page_access('mark_attendance.php', $pdo);

/* ================================

   INITIAL VARIABLES

================================ */

if (is_admin()) {

    $rooms = $pdo->query("SELECT * FROM rooms")->fetchAll();

} else {

    $stmt = $pdo->prepare("

        SELECT r.* FROM rooms r

        JOIN user_rooms ur ON r.id = ur.room_id

        WHERE ur.user_id = ?

    ");

    $stmt->execute([$_SESSION['user_id']]);

    $rooms = $stmt->fetchAll();

}

// Natural sorting for rooms

usort($rooms, function($a, $b) {

    return strnatcmp($a['room_name'], $b['room_name']);

});

$selected_room    = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;

$selected_date    = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');

$selected_period  = isset($_GET['period']) ? $_GET['period'] : 'p1';

// Selected subjects for each class group

$selected_subject_8 = isset($_GET['subject_8']) ? sanitize($_GET['subject_8']) : '';

$selected_subject_9_11 = isset($_GET['subject_9_11']) ? sanitize($_GET['subject_9_11']) : '';

$today = date('Y-m-d');

$error_message = '';

$students = [];

// Helper function to get subjects based on date and class level
function get_scheduled_subjects($date, $class_group) {
    $day_month = date('j M Y', strtotime($date));
    $timestamp = strtotime($date);
    
    $schedule = [
        'Fasalka 6 iyo 7 aad' => [
            '6 Jun 2026' => ['Tarbiyada', 'Saynis'],
            '7 Jun 2026' => ['C.Bulsho', 'Carabi'],
            '8 Jun 2026' => ['English', 'Xisaab'],
            '9 Jun 2026' => ['ICT', 'Soomaali']
        ],
        'Fasalada 9aad, 10aad, 11aad' => [
            '3 Jun 2026' => ['Xisaab', 'ICT'], // Added June 3rd
            '6 Jun 2026' => ['Tarbiyada', 'Chems'],
            '7 Jun 2026' => ['Phy', 'Geo'],
            '8 Jun 2026' => ['Eng', 'His'],
            '9 Jun 2026' => ['Arb', 'Som'],
            '10 Jun 2026' => ['Bio', 'Bus']
        ]
    ];

    // Check if date is after the schedule
    if ($class_group === 'Fasalka 6 iyo 7 aad' && $timestamp > strtotime('2026-06-09')) {
        return 'DHAMMAAD';
    }
    if ($class_group === 'Fasalada 9aad, 10aad, 11aad' && $timestamp > strtotime('2026-06-10')) {
        return 'DHAMMAAD';
    }

    return $schedule[$class_group][$day_month] ?? [];
}

$sched_6_7 = get_scheduled_subjects($selected_date, 'Fasalka 6 iyo 7 aad');
$sched_9_11 = get_scheduled_subjects($selected_date, 'Fasalada 9aad, 10aad, 11aad');

// Detailed subjects for Class 6/7 and Secondary Classes (9, 10, 11)

$subjects_data = [

    'Fasalka 6 iyo 7 aad' => [

        'name' => 'subject_8',

        'list' => is_array($sched_6_7) ? $sched_6_7 : [],
        
        'status' => is_string($sched_6_7) ? $sched_6_7 : ''

    ],

    'Fasalada 9aad, 10aad, 11aad' => [

        'name' => 'subject_9_11',

        'list' => is_array($sched_9_11) ? $sched_9_11 : [],
        
        'status' => is_string($sched_9_11) ? $sched_9_11 : ''

    ]

];

/* ================================

   CHECK IF PERIOD 1 IS MARKED FOR THE SELECTED DATE

================================ */

$p1_marked = false;

if ($selected_room > 0) {

    $check_p1 = $pdo->prepare("

        SELECT COUNT(*) as count FROM attendance a

        JOIN students s ON a.student_id = s.id

        WHERE s.room_id = ? AND a.date = ? AND a.period_1_status IS NOT NULL AND a.period_1_status != '0'

    ");

    $check_p1->execute([$selected_room, $selected_date]);

    $result = $check_p1->fetch(PDO::FETCH_ASSOC);

    $p1_marked = $result['count'] > 0;

}

// If P1 is not marked, force selection to P1

if (!$p1_marked && $selected_period === 'p2') {

    $selected_period = 'p1';

}

/* ================================

   GET ALL MARKED SUBJECTS TO HIDE THEM

================================ */

$marked_subjects_8 = [];

$marked_subjects_9_11 = [];

if ($selected_room > 0) {

    // KHALADKII WAA LA SAXAY: Waxaa lagu daray AND a.date = ? si maaddooyinka shalay aysan u qarin kuwa maanta.
    $stmt8 = $pdo->prepare("

        SELECT DISTINCT period_1_subject as subject FROM attendance a JOIN students s ON a.student_id = s.id JOIN classes c ON s.class_id = c.id WHERE s.room_id = ? AND a.date = ? AND (c.class_name LIKE '%6%' OR c.class_name LIKE '%7%' OR c.class_name LIKE '%Grade 6%' OR c.class_name LIKE '%Grade 7%') AND period_1_subject IS NOT NULL AND period_1_subject != '' AND period_1_status != '0'

        UNION

        SELECT DISTINCT period_2_subject as subject FROM attendance a JOIN students s ON a.student_id = s.id JOIN classes c ON s.class_id = c.id WHERE s.room_id = ? AND a.date = ? AND (c.class_name LIKE '%6%' OR c.class_name LIKE '%7%' OR c.class_name LIKE '%Grade 6%' OR c.class_name LIKE '%Grade 7%') AND period_2_subject IS NOT NULL AND period_2_subject != '' AND period_2_status != '0'

    ");

    $stmt8->execute([$selected_room, $selected_date, $selected_room, $selected_date]);

    $marked_subjects_8 = $stmt8->fetchAll(PDO::FETCH_COLUMN);

    $stmt9_11 = $pdo->prepare("

        SELECT DISTINCT period_1_subject as subject FROM attendance a JOIN students s ON a.student_id = s.id JOIN classes c ON s.class_id = c.id WHERE s.room_id = ? AND a.date = ? AND (c.class_name LIKE '%9%' OR c.class_name LIKE '%10%' OR c.class_name LIKE '%11%') AND period_1_subject IS NOT NULL AND period_1_subject != '' AND period_1_status != '0'

        UNION

        SELECT DISTINCT period_2_subject as subject FROM attendance a JOIN students s ON a.student_id = s.id JOIN classes c ON s.class_id = c.id WHERE s.room_id = ? AND a.date = ? AND (c.class_name LIKE '%9%' OR c.class_name LIKE '%10%' OR c.class_name LIKE '%11%') AND period_2_subject IS NOT NULL AND period_2_subject != '' AND period_2_status != '0'

    ");

    $stmt9_11->execute([$selected_room, $selected_date, $selected_room, $selected_date]);

    $marked_subjects_9_11 = $stmt9_11->fetchAll(PDO::FETCH_COLUMN);

}

$completed_8 = ($subjects_data['Fasalka 6 iyo 7 aad']['status'] === 'DHAMMAAD' || count(array_diff($subjects_data['Fasalka 6 iyo 7 aad']['list'], $marked_subjects_8)) === 0);

$completed_9_11 = ($subjects_data['Fasalada 9aad, 10aad, 11aad']['status'] === 'DHAMMAAD' || count(array_diff($subjects_data['Fasalada 9aad, 10aad, 11aad']['list'], $marked_subjects_9_11)) === 0);

$all_completed = ($completed_8 && $completed_9_11);

/* ================================

   HANDLE SAVE (AJAX)

=============================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {

    try {

        if (empty($_POST['attendance'])) {

            throw new Exception("Xogta xaadirinta lama soo dirin.");

        }

        

        $pdo->beginTransaction();

        $date           = sanitize($_POST['date']);

        $marked_by      = $_SESSION['user_id'];

        $current_period = $_POST['period'] ?? 'p1';

        

        $sub8 = sanitize($_POST['subject_8'] ?? '');

        $sub9_11 = sanitize($_POST['subject_9_11'] ?? '');

        // Backend validation: both must be selected if they have pending subjects

        if (empty($sub8) && !$completed_8) {

            throw new Exception("Fadlan dooro maaddo Fasalka 6 iyo 7 aad.");

        }

        if (empty($sub9_11) && !$completed_9_11) {

            throw new Exception("Fadlan dooro maaddo Fasalada 9aad, 10aad, 11aad.");

        }

        foreach ($_POST['attendance'] as $student_id => $status_data) {

            $s_info = $pdo->prepare("SELECT c.class_name FROM students s JOIN classes c ON s.class_id = c.id WHERE s.id = ?");

            $s_info->execute([$student_id]);

            $class_row = $s_info->fetch();

            $c_name = $class_row ? $class_row['class_name'] : '';

            $student_subject = '';

            if (strpos($c_name, '6') !== false || strpos($c_name, '7') !== false) {

                $student_subject = $sub8;

            } elseif (strpos($c_name, '9') !== false || strpos($c_name, '10') !== false || strpos($c_name, '11') !== false) {

                $student_subject = $sub9_11;

            } else {

                $student_subject = !empty($sub8) ? $sub8 : $sub9_11;

            }

            if (empty($student_subject)) continue;

            $check = $pdo->prepare("SELECT period_1_status, period_1_subject, period_2_status, period_2_subject FROM attendance WHERE student_id=? AND date=?");

            $check->execute([$student_id, $date]);

            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if ($existing) {

                $p1 = $existing['period_1_status'];

                $s1 = $existing['period_1_subject'];

                $p2 = $existing['period_2_status'];

                $s2 = $existing['period_2_subject'];

                if ($current_period === 'p1') {

                    $p1 = $status_data['p1'] ?? 'present';

                    $s1 = $student_subject;

                    if ($p2 === null || $p2 === '') $p2 = '0';

                } elseif ($current_period === 'p2') {

                    $p2 = $status_data['p2'] ?? 'present';

                    $s2 = $student_subject;

                }

                $stmt = $pdo->prepare("UPDATE attendance SET period_1_status = ?, period_1_subject = ?, period_2_status = ?, period_2_subject = ?, marked_by = ? WHERE student_id = ? AND date = ?");

                $stmt->execute([$p1, $s1, $p2, $s2, $marked_by, $student_id, $date]);

            } else {

                if ($current_period === 'p1') {

                    $p1 = $status_data['p1'] ?? 'present';

                    $s1 = $student_subject;

                    $p2 = '0';

                    $s2 = null;

                } else {

                    $p1 = '0';

                    $s1 = null;

                    $p2 = $status_data['p2'] ?? 'present';

                    $s2 = $student_subject;

                }

                $stmt = $pdo->prepare("INSERT INTO attendance (student_id, date, period_1_status, period_1_subject, period_2_status, period_2_subject, marked_by) VALUES (?, ?, ?, ?, ?, ?, ?)");

                $stmt->execute([$student_id, $date, $p1, $s1, $p2, $s2, $marked_by]);

            }

        }

        $pdo->commit();

        if (ob_get_level() > 0) ob_clean(); // KHALADKII 2AAD WAA LA SAXAY: Wuxuu ka hortagayaa PHP notice jebinaya JSON-ka

        header('Content-Type: application/json');

        echo json_encode(['status' => 'success', 'message' => 'Xaadirinta si guul leh ayaa loo keydiyey.']);

        exit;

    } catch (Exception $e) {

        if ($pdo->inTransaction()) $pdo->rollBack();

        if (ob_get_level() > 0) ob_clean(); // KHALADKII 2AAD WAA LA SAXAY

        header('Content-Type: application/json');

        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);

        exit;

    }

}

/* ================================

   FETCH STUDENTS

================================ */

if ($selected_room > 0 && !$all_completed) {

    $status_col = ($selected_period == 'p1') ? 'period_1_status' : 'period_2_status';

    $sql = "
        SELECT s.*, c.class_name, a.period_1_status, a.period_1_subject, a.period_2_status, a.period_2_subject
        FROM students s
        JOIN classes c ON s.class_id = c.id
        LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ?
        WHERE s.room_id = ? AND s.is_disabled = 0 AND c.is_disabled = 0
        AND (a.$status_col IS NULL OR a.$status_col = '0' OR a.$status_col = '')
    ";

    $params = [$selected_date, $selected_room];

    $sql .= " ORDER BY s.full_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $all_room_students = $stmt->fetchAll();

    

    $students = [];

    foreach ($all_room_students as $s) {

        $c_name = $s['class_name'];

        if (strpos($c_name, '6') !== false || strpos($c_name, '7') !== false) {

            if (!$completed_8) $students[] = $s;

        } elseif (strpos($c_name, '9') !== false || strpos($c_name, '10') !== false || strpos($c_name, '11') !== false) {

            if (!$completed_9_11) $students[] = $s;

        } else {

            $students[] = $s;

        }

    }

}

?>

<style>

    .period-select option[value="p1"] { background-color: #e3f2fd; color: #0d47a1; font-weight: bold; }

    .period-select option[value="p2"]:not([disabled]) { background-color: #f1f8e9; color: #33691e; font-weight: bold; }

    .status-present { background-color: #28a745 !important; color: white !important; font-weight: bold; }

    .status-absent { background-color: #dc3545 !important; color: white !important; font-weight: bold; }

    .subject-section { background: #f8f9fc; border: 1px solid #e3e6f0; border-radius: 8px; padding: 15px; margin-top: 10px; }

    .subject-group-title { font-size: 0.85rem; font-weight: 800; color: #4e73df; text-transform: uppercase; margin-bottom: 10px; border-bottom: 2px solid #eaecf4; padding-bottom: 5px; }

    .subject-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }

    .subject-item { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.9rem; color: #5a5c69; padding: 8px 12px; border-radius: 6px; transition: all 0.2s; border: 1px solid transparent; }

    .subject-item:hover { background: #eaecf4; }

    .subject-item input { width: 18px; height: 18px; cursor: pointer; accent-color: #4e73df; }

    .subject-item.checked { color: #4e73df; font-weight: bold; background: #eef2ff; border-color: #d1d9ff; }

    .loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.7); display: none; align-items: center; justify-content: center; z-index: 9999; }

</style>

<div id="loadingOverlay" class="loading-overlay">

    <div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>

</div>

<div class="d-sm-flex align-items-center justify-content-between mb-3">

    <h1 class="h3 mb-0 text-gray-800">XAADIRINTA MAANTA</h1>

</div>

<div class="card mb-4 border-left-primary shadow">

    <div class="card-body">

        <form method="GET" id="filterForm">

            <div class="row">

                <div class="col-md-4 mb-3">

                    <label class="small font-weight-bold">Taariikhda:</label>

                    <input type="date" name="date" class="form-control" value="<?= $selected_date ?>" onchange="this.form.submit();">

                </div>

                <div class="col-md-4 mb-3">

                    <label class="small font-weight-bold">Qolka:</label>

                    <select name="room_id" class="form-control" onchange="this.form.submit();">

                        <option value="">Dooro Qolka</option>

                        <?php foreach ($rooms as $room): ?>

                            <option value="<?= $room['id'] ?>" <?= $selected_room==$room['id']?'selected':'' ?>><?= htmlspecialchars($room['room_name']) ?></option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-md-4 mb-3">

                    <label class="small font-weight-bold">Xisada:</label>

                    <select name="period" class="form-control period-select" onchange="this.form.submit();">

                        <option value="p1" <?= $selected_period=='p1'?'selected':'' ?>>Xisada 1aad</option>

                        <option value="p2" <?= $selected_period=='p2'?'selected':'' ?> <?= !$p1_marked ? 'disabled' : '' ?>>

                            Xisada 2aad <?= !$p1_marked ? '(Waa Xiran Tahay)' : '' ?>

                        </option>

                    </select>

                </div>

                <div class="col-12 mt-2">

                    <div class="row">

                    <?php foreach ($subjects_data as $class_label => $data): ?>

                        <div class="col-md-6 mb-3">

                            <div class="subject-group-title"><?= $class_label ?></div>

                            <div class="subject-grid">

                                <?php 
                                    if ($data['status'] === 'DHAMMAAD'):
                                ?>
                                    <div class="small text-danger font-weight-bold"><i class="fas fa-calendar-check mr-1"></i> DHAMMAAD.</div>
                                <?php
                                    else:

                                    $group_name = $data['name'];

                                    $current_selected = ($group_name == 'subject_8') ? $selected_subject_8 : $selected_subject_9_11;

                                    $marked_list = ($group_name == 'subject_8') ? $marked_subjects_8 : $marked_subjects_9_11;

                                    $visible_count = 0;

                                ?>

                                <?php foreach ($data['list'] as $sub): ?>

                                    <?php 

                                        if (in_array($sub, $marked_list)) continue; 

                                        $visible_count++;

                                        $is_checked = ($current_selected == $sub); 

                                    ?>

                                    <label class="subject-item <?= $is_checked ? 'checked' : '' ?>">

                                        <input type="checkbox" name="<?= $group_name ?>" value="<?= $sub ?>" <?= $is_checked ? 'checked' : '' ?> onclick="handleSubjectClick(this, '<?= $group_name ?>')">

                                        <?= $sub ?>

                                    </label>

                                <?php endforeach; ?>

                                <?php if ($visible_count === 0): ?>

                                    <div class="small text-success font-weight-bold"><i class="fas fa-check-double mr-1"></i> Dhammaan waa la xaadiriyay.</div>

                                <?php endif; ?>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                    </div>

                </div>

            </div>

        </form>

    </div>

</div>

<?php if ($selected_room > 0 && $all_completed): ?>

    <div class="card shadow mb-4 border-bottom-success"><div class="card-body text-center py-5"><div class="mb-4"><i class="fas fa-check-circle text-success fa-4x"></i></div><h2 class="text-gray-800 font-weight-bold">DHAMMAAN WAA LA XAADIRIYAY</h2><p class="text-muted lead">Ma jiraan maaddooyin haray.</p><hr class="my-4"><a href="dashboard.php" class="btn btn-primary btn-lg"><i class="fas fa-home mr-2"></i> Dashboard</a></div></div>

<?php elseif ($selected_room > 0 && empty($students) && !$all_completed): ?>

    <div class="card shadow mb-4"><div class="card-body text-center py-5"><div class="mb-3"><i class="fas fa-info-circle text-primary fa-3x"></i></div><h4 class="text-gray-800">XISADAN WAA LA DHAMEEYAY</h4><p class="text-muted">Ardayda waa loo xaadiriyay xisadan.</p><a href="mark_attendance.php?date=<?= $selected_date ?>&room_id=<?= $selected_room ?>&period=<?= ($selected_period == 'p1') ? 'p2' : 'p1' ?>" class="btn btn-primary"><i class="fas fa-arrow-right mr-2"></i> <?= ($selected_period == 'p1') ? 'U gudub Xisada 2aad' : 'Ku laabo Xisada 1aad' ?></a></div></div>

<?php elseif (!empty($students)): ?>

<form method="POST" id="attendanceForm">

    <input type="hidden" name="date" value="<?= $selected_date ?>">

    <input type="hidden" name="period" value="<?= $selected_period ?>">

    <input type="hidden" name="room_id" value="<?= $selected_room ?>">

    <input type="hidden" name="subject_8" id="hidden_subject_8" value="<?= htmlspecialchars($selected_subject_8) ?>">

    <input type="hidden" name="subject_9_11" id="hidden_subject_9_11" value="<?= htmlspecialchars($selected_subject_9_11) ?>">

    <div class="card shadow mb-4">

        <div class="card-header py-3 bg-light d-flex justify-content-between align-items-center">

            <h6 class="m-0 font-weight-bold text-primary">Liiska Ardayda (<?= count($students) ?>)</h6>

            <div id="subjectBadge" class="badge badge-primary p-2">

                <?= ($selected_period == 'p1') ? 'Xisada 1aad' : 'Xisada 2aad' ?> - 

                <span id="badge_text"><?= $selected_subject_8 ?: '' ?> <?= ($selected_subject_8 && $selected_subject_9_11) ? ' / ' : '' ?> <?= $selected_subject_9_11 ?: '' ?></span>

            </div>

        </div>

        <div class="card-body p-0">

            <table class="table table-bordered table-hover mb-0">

                <thead><tr><th width="50">#</th><th>Magaca Ardayga</th><th>Fasalka</th><th width="200">Xaadirinta</th></tr></thead>

                <tbody>

                    <?php foreach ($students as $index => $student): ?>

                        <?php 

                            $status_field = ($selected_period == 'p1') ? 'period_1_status' : 'period_2_status';

                            $display_status = $student[$status_field] ?? 'present';

                        ?>

                        <tr>

                            <td><?= $index + 1 ?></td>

                            <td class="font-weight-bold text-dark"><?= htmlspecialchars($student['full_name']) ?></td>

                            <td><span class="badge badge-secondary"><?= htmlspecialchars($student['class_name']) ?></span></td>

                            <td>

                                <select name="attendance[<?= $student['id'] ?>][<?= $selected_period ?>]" class="form-control <?= ($display_status == 'absent') ? 'status-absent' : 'status-present' ?>" onchange="updateStatusColor(this)">

                                    <option value="present" <?= ($display_status == 'present') ? 'selected' : '' ?>>Present (Jooga)</option>

                                    <option value="absent" <?= ($display_status == 'absent') ? 'selected' : '' ?>>Absent (Ma Joogo)</option>

                                </select>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

            <div class="text-right mt-3 p-3">

                <button type="submit" id="submitBtn" class="btn btn-success btn-lg px-5 shadow-sm btn-save">

                    <i class="fas fa-save mr-2"></i> KEEDI XOGTA

                </button>

            </div>

        </div>

    </div>

</form>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>

function handleSubjectClick(checkbox, groupName) {

    const checkboxes = document.getElementsByName(groupName);

    checkboxes.forEach((item) => { if (item !== checkbox) { item.checked = false; item.parentElement.classList.remove('checked'); } });

    

    if (checkbox.checked) {

        checkbox.parentElement.classList.add('checked');

    } else {

        checkbox.parentElement.classList.remove('checked');

    }

    // Update hidden inputs and badge without reload

    const sub8 = document.querySelector('input[name="subject_8"]:checked')?.value || '';

    const sub9_11 = document.querySelector('input[name="subject_9_11"]:checked')?.value || '';

    

    document.getElementById('hidden_subject_8').value = sub8;

    document.getElementById('hidden_subject_9_11').value = sub9_11;

    

    const badgeText = (sub8 + (sub8 && sub9_11 ? ' / ' : '') + sub9_11).trim();

    document.getElementById('badge_text').innerText = badgeText;

}

function updateStatusColor(select) {

    select.classList.remove('status-present', 'status-absent');

    select.classList.add(select.value === 'absent' ? 'status-absent' : 'status-present');

}

document.getElementById('attendanceForm')?.addEventListener('submit', function(e) {

    e.preventDefault();

    

    // Mandatory Subject Selection Check for BOTH groups

    const sub8 = document.getElementById('hidden_subject_8').value;

    const sub9_11 = document.getElementById('hidden_subject_9_11').value;

    

    // We check if subjects are pending for each group

    const needsSub8 = <?= $completed_8 ? 'false' : 'true' ?>;

    const needsSub9_11 = <?= $completed_9_11 ? 'false' : 'true' ?>;

    

    let errorMsg = "";

    if (needsSub8 && !sub8 && needsSub9_11 && !sub9_11) {

        errorMsg = "Fadlan labada qayboodba ka dooro maadooyinka saxda ah ee ay galayaan maanta (6/7 iyo 9/10/11).";

    } else if (needsSub8 && !sub8) {

        errorMsg = "Fadlan maaddada saxda ah  ka dooro qaybta Fasalka 6 iyo 7 aad.";

    } else if (needsSub9_11 && !sub9_11) {

        errorMsg = "Fadlan maaddada saxda ah  ka dooro qaybta Fasalada 9aad, 10aad, 11aad.";

    }

    if (errorMsg) {

        Swal.fire({

            icon: 'warning',

            title: 'XOGTA WALI LAMA KEEDIN !',

            text: errorMsg,

            confirmButtonText: 'Hagaag',

            confirmButtonColor: '#4e73df'

        });

        return;

    }

    const submitBtn = document.getElementById('submitBtn');

    const overlay = document.getElementById('loadingOverlay');

    

    overlay.style.display = 'flex';

    submitBtn.disabled = true;

    const formData = new FormData(this);

    formData.append('save_attendance', '1');

    fetch(window.location.href, { method: 'POST', body: formData })

    .then(response => response.json())

    .then(data => {

        overlay.style.display = 'none';

        if (data.status === 'success') {

            Swal.fire({ icon: 'success', title: 'Guul!', text: data.message, timer: 1500, showConfirmButton: false })

            .then(() => { location.reload(); });

        } else {

            Swal.fire({ icon: 'error', title: 'Khalad!', text: data.message });

            submitBtn.disabled = false;

        }

    })

    .catch(error => {

        overlay.style.display = 'none';

        Swal.fire({ icon: 'error', title: 'Khalad!', text: 'Xogta lama keydin karo.' });

        submitBtn.disabled = false;

    });

});

</script>

<?php require_once 'includes/footer.php'; ?>