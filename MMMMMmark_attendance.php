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

// Detailed subjects for Class 6/7 and Secondary Classes (9, 10, 11)
$subjects_data = [
    'Fasalka 6 iyo 7 aad' => [
        'name' => 'subject_8',
        'list' => ['Af-Soomaali', 'Carabi', 'Xisaab', 'Saynis', 'Tarbiyada', 'Cilmiga Bulshada', 'English', 'Tiknoloji']
    ],
    'Fasalada 9aad, 10aad, 11aad' => [
        'name' => 'subject_9_11',
        'list' => ['Af-Soomaali', 'Carabi', 'Xisaab', 'Juqraafi', 'Taariikh', 'Tarbiyada', 'Business', 'Technology', 'English', 'Physics', 'Chemistry', 'Biology']
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
    $stmt8 = $pdo->prepare("
        SELECT DISTINCT period_1_subject as subject FROM attendance a JOIN students s ON a.student_id = s.id JOIN classes c ON s.class_id = c.id WHERE s.room_id = ? AND (c.class_name LIKE '%6%' OR c.class_name LIKE '%7%' OR c.class_name LIKE '%Grade 6%' OR c.class_name LIKE '%Grade 7%') AND period_1_subject IS NOT NULL AND period_1_subject != '' AND period_1_status != '0'
        UNION
        SELECT DISTINCT period_2_subject as subject FROM attendance a JOIN students s ON a.student_id = s.id JOIN classes c ON s.class_id = c.id WHERE s.room_id = ? AND (c.class_name LIKE '%6%' OR c.class_name LIKE '%7%' OR c.class_name LIKE '%Grade 6%' OR c.class_name LIKE '%Grade 7%') AND period_2_subject IS NOT NULL AND period_2_subject != '' AND period_2_status != '0'
    ");
    $stmt8->execute([$selected_room, $selected_room]);
    $marked_subjects_8 = $stmt8->fetchAll(PDO::FETCH_COLUMN);

    $stmt9_11 = $pdo->prepare("
        SELECT DISTINCT period_1_subject as subject FROM attendance a JOIN students s ON a.student_id = s.id JOIN classes c ON s.class_id = c.id WHERE s.room_id = ? AND (c.class_name LIKE '%9%' OR c.class_name LIKE '%10%' OR c.class_name LIKE '%11%') AND period_1_subject IS NOT NULL AND period_1_subject != '' AND period_1_status != '0'
        UNION
        SELECT DISTINCT period_2_subject as subject FROM attendance a JOIN students s ON a.student_id = s.id JOIN classes c ON s.class_id = c.id WHERE s.room_id = ? AND (c.class_name LIKE '%9%' OR c.class_name LIKE '%10%' OR c.class_name LIKE '%11%') AND period_2_subject IS NOT NULL AND period_2_subject != '' AND period_2_status != '0'
    ");
    $stmt9_11->execute([$selected_room, $selected_room]);
    $marked_subjects_9_11 = $stmt9_11->fetchAll(PDO::FETCH_COLUMN);
}

$completed_8 = (count(array_diff($subjects_data['Fasalka 6 iyo 7 aad']['list'], $marked_subjects_8)) === 0);
$completed_9_11 = (count(array_diff($subjects_data['Fasalada 9aad, 10aad, 11aad']['list'], $marked_subjects_9_11)) === 0);
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

        if (empty($sub8) && empty($sub9_11)) {
            throw new Exception("Fadlan dooro ugu yaraan hal maaddo.");
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
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Xaadirinta si guul leh ayaa loo keydiyey.']);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        ob_clean();
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
    $stmt = $pdo->prepare("
        SELECT s.*, c.class_name, a.period_1_status, a.period_1_subject, a.period_2_status, a.period_2_subject
        FROM students s
        JOIN classes c ON s.class_id = c.id
        LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ?
        WHERE s.room_id = ? AND s.is_disabled = 0 AND c.is_disabled = 0
        AND (a.$status_col IS NULL OR a.$status_col = '0' OR a.$status_col = '')
        ORDER BY s.full_name
    ");
    $stmt->execute([$selected_date, $selected_room]);
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
    
    /* Subject Modal Styling */
    .subject-modal-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; padding: 10px; }
    .subject-modal-item { border: 2px solid #eaecf4; padding: 12px; border-radius: 10px; cursor: pointer; transition: all 0.2s; text-align: center; font-weight: 600; color: #5a5c69; }
    .subject-modal-item:hover { border-color: #4e73df; background: #f8f9fc; }
    .subject-modal-item.selected { border-color: #4e73df; background: #4e73df; color: white; }
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
    <input type="hidden" name="subject_8" id="hidden_subject_8" value="">
    <input type="hidden" name="subject_9_11" id="hidden_subject_9_11" value="">

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-light d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Liiska Ardayda (<?= count($students) ?>)</h6>
            <div id="subjectBadge" class="badge badge-primary p-2">
                <?= ($selected_period == 'p1') ? 'Xisada 1aad' : 'Xisada 2aad' ?>
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
                <button type="button" id="preSubmitBtn" class="btn btn-success btn-lg px-5 shadow-sm">
                    <i class="fas fa-save mr-2"></i> KEEDI XOGTA
                </button>
                <button type="submit" id="submitBtn" class="d-none">Submit</button>
            </div>
        </div>
    </div>
</form>

<!-- Subject Selection Modal -->
<div class="modal fade" id="subjectModal" tabindex="-1" role="dialog" aria-labelledby="subjectModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="subjectModalLabel">DOORO MAADDADA (WAA QASAB)</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <div class="alert alert-info small"><i class="fas fa-info-circle mr-1"></i> Fadlan dooro maaddada aad dhigtay ka hor inta aan xogta la keydin.</div>
          <?php foreach ($subjects_data as $class_label => $data): ?>
              <div class="mb-4">
                  <h6 class="font-weight-bold text-primary border-bottom pb-2"><?= $class_label ?></h6>
                  <div class="subject-modal-grid">
                      <?php 
                          $group_name = $data['name'];
                          $marked_list = ($group_name == 'subject_8') ? $marked_subjects_8 : $marked_subjects_9_11;
                      ?>
                      <?php foreach ($data['list'] as $sub): ?>
                          <?php if (in_array($sub, $marked_list)) continue; ?>
                          <div class="subject-modal-item" onclick="selectModalSubject(this, '<?= $group_name ?>', '<?= $sub ?>')">
                              <?= $sub ?>
                          </div>
                      <?php endforeach; ?>
                  </div>
              </div>
          <?php endforeach; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Ka Noqo</button>
        <button type="button" id="confirmSaveBtn" class="btn btn-success px-4" disabled title="Fadlan dooro maaddo si aad u keydiso">
            <i class="fas fa-check-circle mr-1"></i> XAQIJI OO KEYDI
        </button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let selectedSub8 = '';
let selectedSub9_11 = '';

function selectModalSubject(element, groupName, subject) {
    // Deselect others in the same group
    const siblings = element.parentElement.querySelectorAll('.subject-modal-item');
    siblings.forEach(el => el.classList.remove('selected'));
    
    // Select current
    element.classList.add('selected');
    
    if (groupName === 'subject_8') {
        selectedSub8 = subject;
    } else {
        selectedSub9_11 = subject;
    }
    
    // Enable confirm button ONLY if at least one subject is selected
    const confirmBtn = document.getElementById('confirmSaveBtn');
    if (selectedSub8 || selectedSub9_11) {
        confirmBtn.disabled = false;
        confirmBtn.classList.remove('btn-secondary');
        confirmBtn.classList.add('btn-success');
    } else {
        confirmBtn.disabled = true;
    }
}

document.getElementById('preSubmitBtn')?.addEventListener('click', function() {
    // Reset selections when opening modal
    selectedSub8 = '';
    selectedSub9_11 = '';
    document.querySelectorAll('.subject-modal-item').forEach(el => el.classList.remove('selected'));
    document.getElementById('confirmSaveBtn').disabled = true;
    
    $('#subjectModal').modal('show');
});

document.getElementById('confirmSaveBtn')?.addEventListener('click', function() {
    if (!selectedSub8 && !selectedSub9_11) {
        Swal.fire({ icon: 'warning', title: 'Feejignaan!', text: 'Fadlan dooro ugu yaraan hal maaddo.' });
        return;
    }
    document.getElementById('hidden_subject_8').value = selectedSub8;
    document.getElementById('hidden_subject_9_11').value = selectedSub9_11;
    $('#subjectModal').modal('hide');
    document.getElementById('submitBtn').click();
});

function updateStatusColor(select) {
    select.classList.remove('status-present', 'status-absent');
    select.classList.add(select.value === 'absent' ? 'status-absent' : 'status-present');
}

document.getElementById('attendanceForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Double check subjects
    const s8 = document.getElementById('hidden_subject_8').value;
    const s9 = document.getElementById('hidden_subject_9_11').value;
    
    if (!s8 && !s9) {
        Swal.fire({ icon: 'error', title: 'Khalad!', text: 'Maaddada lama dooran. Fadlan isku day markale.' });
        return;
    }

    const preSubmitBtn = document.getElementById('preSubmitBtn');
    const overlay = document.getElementById('loadingOverlay');
    
    overlay.style.display = 'flex';
    preSubmitBtn.disabled = true;

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
            preSubmitBtn.disabled = false;
        }
    })
    .catch(error => {
        overlay.style.display = 'none';
        Swal.fire({ icon: 'error', title: 'Khalad!', text: 'Xogta lama keydin karo.' });
        preSubmitBtn.disabled = false;
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
