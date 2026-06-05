<?php
// Wuxuu xannibayaa in wax HTML ah la soo saaro inta laga hubinayo AJAX
ob_start(); 
require_once 'includes/header.php';

// ==========================================
// 1. QAYBTA TIRTIRISTA AJAX (100% FIX & CLEAN JSON)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_id'])) {
    ob_end_clean(); // Wuxuu gebi ahaanba nadiifinayaa buffer-ka si looga hortago HTML khaldan
    header('Content-Type: application/json');
    
    try {
        $id = (int)$_POST['ajax_delete_id'];
        $stmt = $pdo->prepare("DELETE FROM exam_suspensions WHERE id = ?");
        
        if ($stmt->execute([$id])) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Cilad ayaa dhacday, dib u isku day.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database Error: Dhib ayaa ka dhacay server-ka.']);
    }
    exit; // Jooji fulinta koodhka kale
}

// Today's date
$today = date('Y-m-d');
$msg = '';
$msg_type = 'success'; // Default message type

// ==========================================
// 2. HANDLE CRUD OPERATIONS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_suspension'])) {
            $name   = sanitize($_POST['student_name']);
            $class  = sanitize($_POST['class_name']);
            $room   = sanitize($_POST['room_name']);
            $exam   = sanitize($_POST['exam_type']);
            $reason = sanitize($_POST['reason']);
            
            if (!empty($name) && !empty($class) && !empty($room)) {
                $stmt = $pdo->prepare("INSERT INTO exam_suspensions (student_name, class_name, room_name, exam_type, reason) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $class, $room, $exam, $reason]);
                $msg = "Ardayga si guul ah ayaa loogu daray liiska joojinta!";
                $msg_type = "success";
            } else {
                $msg = "Fadlan buuxi dhammaan xogta muhiimka ah.";
                $msg_type = "danger";
            }
        }
        
        if (isset($_POST['update_suspension'])) {
            $id     = (int)$_POST['id'];
            $name   = sanitize($_POST['student_name']);
            $class  = sanitize($_POST['class_name']);
            $room   = sanitize($_POST['room_name']);
            $exam   = sanitize($_POST['exam_type']);
            $reason = sanitize($_POST['reason']);
            
            $stmt = $pdo->prepare("UPDATE exam_suspensions SET student_name=?, class_name=?, room_name=?, exam_type=?, reason=? WHERE id=?");
            $stmt->execute([$name, $class, $room, $exam, $reason, $id]);
            $msg = "Xogta ardayga si guul ah ayaa loo cusboonaysiiyay!";
            $msg_type = "success";
        }
    } catch (PDOException $e) {
        $msg = "Cilad kaydka xogta ah: Fadlan la xiriir maamulaha.";
        $msg_type = "danger";
    }
}

// ==========================================
// 3. FETCH DATA & STATISTICS
// ==========================================
try {
    if (is_admin()) {
        $total_students = $pdo->query("SELECT COUNT(*) FROM exam_suspensions")->fetchColumn();
        $total_rooms = $pdo->query("SELECT COUNT(DISTINCT room_name) FROM exam_suspensions")->fetchColumn();
        $total_classes = $pdo->query("SELECT COUNT(DISTINCT class_name) FROM exam_suspensions")->fetchColumn();

        $most_populous_class = $pdo->query("
            SELECT class_name FROM exam_suspensions 
            GROUP BY class_name ORDER BY COUNT(*) DESC LIMIT 1
        ")->fetchColumn();
        
        $present_count = $pdo->query("
            SELECT COUNT(*) FROM attendance 
            WHERE date = '$today' AND (period_1_status = 'present' OR period_2_status = 'present')
        ")->fetchColumn();
        
    } else {
        $user_id = $_SESSION['user_id'];

        $stmt = $pdo->prepare("SELECT room_name FROM rooms WHERE id IN (SELECT room_id FROM user_rooms WHERE user_id = ?)");
        $stmt->execute([$user_id]);
        $user_room_names = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $room_placeholders = count($user_room_names) > 0 ? implode(',', array_fill(0, count($user_room_names), '?')) : "'none'";

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM exam_suspensions WHERE room_name IN ($room_placeholders)");
        $stmt->execute($user_room_names);
        $total_students = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT room_name) FROM exam_suspensions WHERE room_name IN ($room_placeholders)");
        $stmt->execute($user_room_names);
        $total_rooms = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT class_name) FROM exam_suspensions WHERE room_name IN ($room_placeholders)");
        $stmt->execute($user_room_names);
        $total_classes = $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT class_name FROM exam_suspensions 
            WHERE room_name IN ($room_placeholders)
            GROUP BY class_name ORDER BY COUNT(*) DESC LIMIT 1
        ");
        $stmt->execute($user_room_names);
        $most_populous_class = $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM attendance a
            JOIN students s ON a.student_id = s.id
            WHERE a.date = ? AND (a.period_1_status = 'present' OR a.period_2_status = 'present')
            AND s.room_id IN (SELECT room_id FROM user_rooms WHERE user_id = ?)
        ");
        $stmt->execute([$today, $user_id]);
        $present_count = $stmt->fetchColumn();
    }

    $attendance_percentage = $total_students > 0 ? round(($present_count / $total_students) * 100, 1) : 0;

    // Dropdowns data
    $classes_list = $pdo->query("SELECT class_name FROM classes ORDER BY class_name ASC")->fetchAll(PDO::FETCH_COLUMN);
    $rooms_list = $pdo->query("SELECT room_name FROM rooms ORDER BY room_name ASC")->fetchAll(PDO::FETCH_COLUMN);

    // Main Table Data
    $suspensions = $pdo->query("SELECT * FROM exam_suspensions ORDER BY created_at DESC")->fetchAll();

} catch (PDOException $e) {
    die("Database Connection Failed.");
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* CSS Nadiif ah */
.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.stat-card {
    border-radius: 20px;
    padding: 25px;
    color: #fff;
    position: relative;
    overflow: hidden;
    transition: 0.4s ease;
    box-shadow: 0 15px 30px rgba(0,0,0,0.15);
}
.stat-card:hover { transform: translateY(-10px); }
.stat-icon {
    font-size: 35px;
    opacity: 0.3;
    position: absolute;
    right: 20px;
    top: 20px;
}
.stat-title {
    font-size: 15px;
    text-transform: uppercase;
    font-weight: 800;
    color: #ffffff;
    opacity: 1;
    text-shadow: 0 0 5px rgba(255, 255, 255, 0.3);
}
.stat-value {
    font-size: 36px;
    font-weight: 900;
    margin-top: 10px;
    color: #ffffff;
    text-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
}

.bg-blue { background: linear-gradient(135deg,#4e73df,#224abe); }
.bg-green { background: linear-gradient(135deg,#1cc88a,#13855c); }
.bg-orange { background: linear-gradient(135deg,#f6c23e,#dda20a); }
.bg-purple { background: linear-gradient(135deg,#6f42c1,#4e2c91); }

@keyframes float {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-5px); }
    100% { transform: translateY(0px); }
}
.stat-card i { animation: float 3s ease-in-out infinite; }

.suspension-section {
    background: #fff;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    margin-top: 30px;
}
.suspension-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    border-bottom: 2px solid #f8f9fc;
    padding-bottom: 15px;
    flex-wrap: wrap; /* Mobile friendly */
    gap: 10px;
}
.suspension-header h4 { margin: 0; color: #4e73df; font-weight: 700; }

/* MODAL CSS OPTIMIZED FOR MOBILE & DESKTOP */
.modal-custom {
    display: none;
    position: fixed;
    z-index: 1050;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.6);
    backdrop-filter: blur(4px); /* Modern blur effect */
    overflow-y: auto;
}
.modal-content-custom {
    background-color: #fff;
    margin: 5% auto;
    padding: 30px;
    border-radius: 15px;
    width: 90%; /* Updated for Mobile */
    max-width: 600px; /* Updated for Desktop */
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    animation: modalSlideIn 0.4s ease;
}
@keyframes modalSlideIn {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.form-group { margin-bottom: 15px; }
.form-group label { font-weight: 600; color: #4e73df; margin-bottom: 5px; display: block; }
.form-control { border-radius: 8px; border: 1px solid #d1d3e2; padding: 10px; width: 100%; transition: 0.3s; }
.form-control:focus { border-color: #4e73df; box-shadow: 0 0 5px rgba(78, 115, 223, 0.3); outline: none; }
.btn-custom { border-radius: 8px; padding: 10px 20px; font-weight: 600; transition: 0.3s; }

tr { transition: opacity 0.4s ease, transform 0.4s ease; }
</style>

<div class="container-fluid mb-5">
    
    <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-info-circle mr-2"></i> <?= htmlspecialchars($msg) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if (is_admin()): ?>
    <div class="dashboard-cards">
        <div class="stat-card bg-blue">
            <i class="fas fa-user-graduate stat-icon"></i>
            <div class="stat-title">Total Students</div>
            <div class="stat-value counter" data-target="<?= $total_students ?>">0</div>
        </div>

        <div class="stat-card bg-green">
            <i class="fas fa-door-open stat-icon"></i>
            <div class="stat-title">Total Rooms</div>
            <div class="stat-value counter" data-target="<?= $total_rooms ?>">0</div>
        </div>

        <div class="stat-card bg-orange">
            <i class="fas fa-school stat-icon"></i>
            <div class="stat-title">Total Classes</div>
            <div class="stat-value counter" data-target="<?= $total_classes ?>">0</div>
        </div>

        <div class="stat-card bg-purple">
            <i class="fas fa-users stat-icon"></i>
            <div class="stat-title">Fasalka u Ardayda Badan</div>
            <div class="stat-value" style="font-size: 24px;"><?= $most_populous_class ?: 'N/A' ?></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="suspension-section">
        <div class="suspension-header">
            <h4><i class="fas fa-exclamation-triangle mr-2"></i> Kajoojinta Imtixaanka Ardayda </h4>
            <div class="d-flex gap-2 flex-wrap">
                <a href="generate_suspension_pdf.php" target="_blank" class="btn btn-danger btn-custom mr-2">
                    <i class="fas fa-file-pdf mr-1"></i> Download PDF
                </a>
                <button class="btn btn-primary btn-custom" onclick="openModal('addModal')">
                    <i class="fas fa-plus mr-1"></i> Kudar Arday
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="bg-light text-secondary">
                    <tr>
                        <th>Magaca Ardayga</th>
                        <th>Fasalka</th>
                        <th>Roomka</th>
                        <th>Nuuca Imtixaanka</th>
                        <th>Ujeedada</th>
                        <th class="text-center">Maareyn</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($suspensions) > 0): ?>
                        <?php foreach ($suspensions as $s): ?>
                            <tr id="row-<?= $s['id'] ?>">
                                <td class="font-weight-bold text-dark"><?= htmlspecialchars($s['student_name']) ?></td>
                                <td><?= htmlspecialchars($s['class_name']) ?></td>
                                <td><?= htmlspecialchars($s['room_name']) ?></td>
                                <td><span class="badge badge-warning px-3 py-2 rounded-pill"><?= htmlspecialchars($s['exam_type']) ?></span></td>
                                <td><?= htmlspecialchars($s['reason']) ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-info shadow-sm" onclick='editSuspension(<?= json_encode($s) ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger shadow-sm ml-1" onclick="deleteSuspension(<?= $s['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Ma jiraan arday imtixaanka laga joojiyay xilligan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="addModal" class="modal-custom">
    <div class="modal-content-custom">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="text-primary font-weight-bold m-0">Ku dar Arday Cusub</h4>
            <span class="close text-secondary" style="cursor:pointer; font-size: 28px; line-height: 1;" onclick="closeModal('addModal')">&times;</span>
        </div>
        <form method="POST">
            <div class="form-group">
                <label>Magaca Ardayga <span class="text-danger">*</span></label>
                <input type="text" name="student_name" class="form-control" required placeholder="Gali magaca ardayga">
            </div>
            <div class="row">
                <div class="col-md-6 form-group">
                    <label>Fasalka <span class="text-danger">*</span></label>
                    <select name="class_name" class="form-control" required>
                        <option value="" disabled selected>Dooro Fasalka...</option>
                        <?php foreach($classes_list as $class_item): ?>
                            <option value="<?= htmlspecialchars($class_item) ?>"><?= htmlspecialchars($class_item) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 form-group">
                    <label>Roomka <span class="text-danger">*</span></label>
                    <select name="room_name" class="form-control" required>
                        <option value="" disabled selected>Dooro Roomka...</option>
                        <?php foreach($rooms_list as $room_item): ?>
                            <option value="<?= htmlspecialchars($room_item) ?>"><?= htmlspecialchars($room_item) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Nuuca Imtixaanka <span class="text-danger">*</span></label>
                <select name="exam_type" class="form-control" required>
                    <option value="Midterm">Midterm</option>
                    <option value="Final">Final</option>
                    <option value="Monthly 1">Monthly 1</option>
                    <option value="Monthly 2">Monthly 2</option>
                </select>
            </div>
            <div class="form-group">
                <label>Ujeedada (Reason) <span class="text-danger">*</span></label>
                <textarea name="reason" class="form-control" rows="3" required placeholder="Faahfaahin ku saabsan sababta..."></textarea>
            </div>
            <button type="submit" name="add_suspension" class="btn btn-primary btn-block btn-custom mt-4">Save Data</button>
        </form>
    </div>
</div>

<div id="editModal" class="modal-custom">
    <div class="modal-content-custom">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="text-info font-weight-bold m-0">Wax ka bedel Xogta</h4>
            <span class="close text-secondary" style="cursor:pointer; font-size: 28px; line-height: 1;" onclick="closeModal('editModal')">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label>Magaca Ardayga <span class="text-danger">*</span></label>
                <input type="text" name="student_name" id="edit_name" class="form-control" required>
            </div>
            <div class="row">
                <div class="col-md-6 form-group">
                    <label>Fasalka <span class="text-danger">*</span></label>
                    <select name="class_name" id="edit_class" class="form-control" required>
                        <option value="" disabled>Dooro Fasalka...</option>
                        <?php foreach($classes_list as $class_item): ?>
                            <option value="<?= htmlspecialchars($class_item) ?>"><?= htmlspecialchars($class_item) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 form-group">
                    <label>Roomka <span class="text-danger">*</span></label>
                    <select name="room_name" id="edit_room" class="form-control" required>
                        <option value="" disabled>Dooro Roomka...</option>
                        <?php foreach($rooms_list as $room_item): ?>
                            <option value="<?= htmlspecialchars($room_item) ?>"><?= htmlspecialchars($room_item) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Nuuca Imtixaanka <span class="text-danger">*</span></label>
                <select name="exam_type" id="edit_exam" class="form-control" required>
                    <option value="Midterm">Midterm</option>
                    <option value="Final">Final</option>
                    <option value="Monthly 1">Monthly 1</option>
                    <option value="Monthly 2">Monthly 2</option>
                </select>
            </div>
            <div class="form-group">
                <label>Ujeedada (Reason) <span class="text-danger">*</span></label>
                <textarea name="reason" id="edit_reason" class="form-control" rows="3" required></textarea>
            </div>
            <button type="submit" name="update_suspension" class="btn btn-info btn-block btn-custom mt-4 text-white">Update Data</button>
        </form>
    </div>
</div>

<script>
// Animated Counter
document.addEventListener("DOMContentLoaded", () => {
    const counters = document.querySelectorAll('.counter');
    counters.forEach(counter => {
        counter.innerText = '0';
        const updateCounter = () => {
            const target = +counter.getAttribute('data-target');
            const c = +counter.innerText;
            const increment = target / 50;
            if(c < target && target > 0) {
                counter.innerText = `${Math.ceil(c + increment)}`;
                setTimeout(updateCounter, 30);
            } else {
                counter.innerText = target;
            }
        };
        updateCounter();
    });
});

// Modal Functions
function openModal(id) { 
    document.getElementById(id).style.display = "block"; 
    document.body.style.overflow = "hidden"; // Ka hortag in background-ka la scroll-gareeyo
}
function closeModal(id) { 
    document.getElementById(id).style.display = "none"; 
    document.body.style.overflow = "auto";
}

// Xir Modal-ka marka banaanka la gujiyo
window.onclick = function(event) {
    if (event.target.classList.contains('modal-custom')) { 
        event.target.style.display = "none"; 
        document.body.style.overflow = "auto";
    }
}

// Data buuxinta foomka Edit-ka
function editSuspension(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_name').value = data.student_name;
    document.getElementById('edit_class').value = data.class_name;
    document.getElementById('edit_room').value = data.room_name;
    document.getElementById('edit_exam').value = data.exam_type;
    document.getElementById('edit_reason').value = data.reason;
    openModal('editModal');
}

// ==========================================
// QAYBTA POPUP-KA IYO TIRTIRISTA (SWEETALERT2)
// ==========================================
function deleteSuspension(id) {
    Swal.fire({
        title: 'Ma Hubtaa?',
        text: "Xogtan haddii aad tirtirto dib looma soo celin karo!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: '<i class="fas fa-trash"></i> Haa, Tirtir!',
        cancelButtonText: 'Jooji'
    }).then((result) => {
        if (result.isConfirmed) {
            
            const formData = new FormData();
            formData.append('ajax_delete_id', id);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if(!response.ok) throw new Error('Network response was not ok');
                return response.json();
            }) 
            .then(data => {
                if (data.status === 'success') {
                    const row = document.getElementById('row-' + id);
                    if (row) {
                        row.style.transform = 'scale(0.9)';
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 400);
                    }
                    
                    Swal.fire({
                        title: 'Waa la Tirtiray!',
                        text: 'Xogta ardayga si guul ah ayaa loo tirtiray.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire('Cilad!', data.message || 'Qalad ayaa dhacay.', 'error');
                }
            })
            .catch(error => {
                console.error('Qalad:', error);
                Swal.fire('Qalad!', 'Khadkaaga internet-ka hubi ama server-ka ayaa cilladaysan.', 'error');
            });
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>