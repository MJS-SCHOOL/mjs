<?php
ob_start();
require_once 'includes/header.php';
require_once 'includes/teacher_page_access.php';

// Check if user has access to this page
require_teacher_page_access('update_attendance.php', $pdo);

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

$error_message = '';
$students = [];

/* ================================
   HANDLE UPDATE (AJAX)
=============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_attendance'])) {
    try {
        if (empty($_POST['attendance'])) {
            throw new Exception("Xogta xaadirinta lama soo dirin.");
        }
        
        $pdo->beginTransaction();
        $date           = sanitize($_POST['date']);
        $marked_by      = $_SESSION['user_id'];
        
        foreach ($_POST['attendance'] as $student_id => $status_data) {
            $p1 = $status_data['p1'] ?? 'present';
            $p2 = $status_data['p2'] ?? 'present';

            // Check if record exists
            $check = $pdo->prepare("SELECT id FROM attendance WHERE student_id=? AND date=?");
            $check->execute([$student_id, $date]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $stmt = $pdo->prepare("
                    UPDATE attendance 
                    SET period_1_status = ?, period_2_status = ?, marked_by = ?
                    WHERE student_id = ? AND date = ?
                ");
                $stmt->execute([$p1, $p2, $marked_by, $student_id, $date]);
            } else {
                // If somehow it doesn't exist, we can insert it, but usually update page expects existing records
                $stmt = $pdo->prepare("
                    INSERT INTO attendance (student_id, date, period_1_status, period_2_status, marked_by)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$student_id, $date, $p1, $p2, $marked_by]);
            }
        }

        $pdo->commit();
        
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Xaadirinta si guul leh ayaa loo cusboonaysiiyey.']);
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
   FETCH STUDENTS WITH ATTENDANCE FOR SELECTED DATE & ROOM
================================ */
if ($selected_room > 0) {
    $stmt = $pdo->prepare("
        SELECT s.*, c.class_name, a.period_1_status, a.period_1_subject, a.period_2_status, a.period_2_subject
        FROM students s
        JOIN classes c ON s.class_id = c.id
        LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ?
        WHERE s.room_id = ? AND s.is_disabled = 0 AND c.is_disabled = 0
        ORDER BY s.full_name
    ");
    $stmt->execute([$selected_date, $selected_room]);
    $students = $stmt->fetchAll();

    // Halkan waxaa lagu daray hubinta taariikhda aan xogta lahayn
    $has_attendance_data = false;
    foreach ($students as $student) {
        if (!is_null($student['period_1_status']) || !is_null($student['period_2_status'])) {
            $has_attendance_data = true;
            break;
        }
    }

    // Haddii taariikhda la doorto aysan lahayn wax xog xaadirin ah, liiska ardayda eber ka dhig
    if (!$has_attendance_data) {
        $students = [];
    }
}
?>

<style>
    /* Attendance Status Colors */
    .status-present { 
        background-color: #28a745 !important; 
        color: white !important; 
        font-weight: bold; 
    }
    .status-absent { 
        background-color: #dc3545 !important; 
        color: white !important; 
        font-weight: bold; 
    }
    
    select option[value="present"] { background-color: #28a745; color: white; }
    select option[value="absent"] { background-color: #dc3545; color: white; }
    
    .table th { background-color: #f8f9fc; }
    .btn-save { transition: all 0.3s; }
    .btn-save:hover { transform: scale(1.05); }

    .attendance-select {
        border-radius: 5px;
        padding: 5px;
        border: 1px solid #ddd;
        width: 100%;
    }
</style>

<div class="d-sm-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0 text-gray-800">CUSBOONAYSIINTA XAADIRINTA</h1>
</div>

<div class="card mb-4 border-left-info shadow">
    <div class="card-body">
        <form method="GET" id="filterForm">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="small font-weight-bold">Taariikhda:</label>
                    <input type="date" name="date" class="form-control" value="<?= $selected_date ?>" onchange="this.form.submit();">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="small font-weight-bold">Qolka:</label>
                    <select name="room_id" class="form-control" onchange="this.form.submit();">
                        <option value="">Dooro Qolka</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?= $room['id'] ?>" <?= $selected_room==$room['id']?'selected':'' ?>><?= htmlspecialchars($room['room_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($selected_room > 0): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center bg-info text-white">
            <h6 class="m-0 font-weight-bold">Liiska Ardayda: <?= htmlspecialchars($rooms[array_search($selected_room, array_column($rooms, 'id'))]['room_name']) ?></h6>
            <span>Taariikhda: <?= $selected_date ?></span>
        </div>
        <div class="card-body">
            <?php if (empty($students)): ?>
                <div class="alert alert-warning">Taariikhdan weli wax xog xaadirin ah lama keydin ama qolkan arday laguma helin.</div>
            <?php else: ?>
                <form id="attendanceForm">
                    <input type="hidden" name="date" value="<?= $selected_date ?>">
                    <input type="hidden" name="room_id" value="<?= $selected_room ?>">
                    <input type="hidden" name="update_attendance" value="1">
                    
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th width="50">#</th>
                                    <th>Magaca Ardayga</th>
                                    <th>Fasalka</th>
                                    <th width="150">Xisada 1aad</th>
                                    <th width="150">Xisada 2aad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $index => $student): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td class="font-weight-bold"><?= htmlspecialchars($student['full_name']) ?></td>
                                        <td><?= htmlspecialchars($student['class_name']) ?></td>
                                        <td>
                                            <select name="attendance[<?= $student['id'] ?>][p1]" class="attendance-select <?= $student['period_1_status'] == 'absent' ? 'status-absent' : 'status-present' ?>" onchange="updateStatusColor(this)">
                                                <option value="present" <?= $student['period_1_status'] == 'present' ? 'selected' : '' ?>>Jooga</option>
                                                <option value="absent" <?= $student['period_1_status'] == 'absent' ? 'selected' : '' ?>>Maqan</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="attendance[<?= $student['id'] ?>][p2]" class="attendance-select <?= $student['period_2_status'] == 'absent' ? 'status-absent' : 'status-present' ?>" onchange="updateStatusColor(this)">
                                                <option value="present" <?= $student['period_2_status'] == 'present' ? 'selected' : '' ?>>Jooga</option>
                                                <option value="absent" <?= $student['period_2_status'] == 'absent' ? 'selected' : '' ?>>Maqan</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-4 text-right">
                        <button type="submit" class="btn btn-info btn-lg px-5 btn-save shadow-sm">
                            <i class="fas fa-save mr-2"></i> CUSBOONAYSIII XAADIRINTA
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="text-center py-5">
        <i class="fas fa-door-open fa-4x text-gray-300 mb-3"></i>
        <h4 class="text-gray-500">Fadlan dooro qol si aad u cusboonaysiiso xaadirinta.</h4>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function updateStatusColor(select) {
    if (select.value === 'absent') {
        select.classList.remove('status-present');
        select.classList.add('status-absent');
    } else {
        select.classList.remove('status-absent');
        select.classList.add('status-present');
    }
}

document.getElementById('attendanceForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    
    Swal.fire({
        title: 'Ma hubtaa?',
        text: "Ma rabtaa in aad cusboonaysiiso xaadirinta?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#36b9cc',
        cancelButtonColor: '#858796',
        confirmButtonText: 'Haa, Cusboonaysii',
        cancelButtonText: 'Jooji'
    }).then((result) => {
        if (result.isConfirmed) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Waa la keydinayaa...';
            
            fetch('update_attendance.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        title: 'Guul!',
                        text: data.message,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Khalad!', data.message, 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> CUSBOONAYSIII XAADIRINTA';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Khalad!', 'Wax baa khaldamay xilli la keydinayay xogta.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> CUSBOONAYSIII XAADIRINTA';
            });
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>