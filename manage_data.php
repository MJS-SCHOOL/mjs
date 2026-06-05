<?php
require_once 'includes/header.php';
require_admin();

$classes = $pdo->query("SELECT * FROM classes ORDER BY class_name")->fetchAll();
$rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_name")->fetchAll();
$message = '';
$error = '';

// Handle Delete Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'delete_by_class_date') {
            $delete_date = sanitize($_POST['delete_date']);
            $delete_class_id = (int)$_POST['delete_class_id'];
            
            if (empty($delete_date) || $delete_class_id <= 0) {
                throw new Exception("Fadlan dooro taariikhda iyo fasalka.");
            }

            $stmt = $pdo->prepare("DELETE FROM attendance WHERE date = ? AND student_id IN (SELECT id FROM students WHERE class_id = ?)");
            $stmt->execute([$delete_date, $delete_class_id]);
            $count = $stmt->rowCount();
            $message = "Si guul leh ayaa loo tirtiray $count xogta xaadirinta ee taariikhda $delete_date.";
        } 
        elseif ($_POST['action'] === 'delete_all') {
            $pdo->query("TRUNCATE TABLE attendance");
            $message = "Dhammaan xogta xaadirinta ee nidaamka waa la tirtiray.";
        }
        elseif ($_POST['action'] === 'delete_all_students') {
            $pdo->query("TRUNCATE TABLE students");
            $message = "Dhammaan ardayda ee nidaamka waa la tirtiray.";
        }
        elseif ($_POST['action'] === 'delete_all_rooms') {
            $pdo->query("TRUNCATE TABLE rooms");
            $pdo->query("DELETE FROM user_rooms"); 
            $pdo->query("DELETE FROM rooms_classes"); 
            $message = "Dhammaan qololka (rooms) iyo xiriiradooda waa la tirtiray.";
        }
        elseif ($_POST['action'] === 'delete_all_classes') {
            $pdo->query("TRUNCATE TABLE classes");
            $pdo->query("DELETE FROM rooms_classes"); 
            $message = "Dhammaan fasalada iyo xiriiradooda waa la tirtiray.";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!-- Add Animate.css, SweetAlert2 and FontAwesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    :root {
        --primary: #4e73df;
        --danger: #ff4757;
        --warning: #ffa502;
        --success: #2ed573;
        --info: #1e90ff;
        --dark: #2f3542;
        --radius: 15px;
    }

    .manage-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    /* Premium Header */
    .premium-header {
        background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
        padding: 30px;
        border-radius: var(--radius);
        color: white;
        margin-bottom: 40px;
        box-shadow: 0 10px 25px rgba(108, 92, 231, 0.2);
        text-align: center;
    }

    /* Small Colored Cards */
    .grid-layout {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }

    .small-card {
        border: none;
        border-radius: var(--radius);
        padding: 20px;
        color: white;
        position: relative;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        cursor: pointer;
        min-height: 180px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 8px 15px rgba(0,0,0,0.1);
    }

    .small-card:hover {
        transform: scale(1.05);
        box-shadow: 0 15px 30px rgba(0,0,0,0.15);
    }

    .small-card i {
        font-size: 40px;
        opacity: 0.3;
        position: absolute;
        right: -10px;
        bottom: -10px;
        transition: all 0.3s ease;
    }

    .small-card:hover i {
        transform: scale(1.2) rotate(-10deg);
        opacity: 0.5;
    }

    .card-title {
        font-size: 1.2rem;
        font-weight: 800;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .card-subtitle {
        font-size: 0.85rem;
        opacity: 0.9;
        font-weight: 500;
    }

    /* Colors */
    .bg-gradient-blue { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); }
    .bg-gradient-red { background: linear-gradient(135deg, #ff4757 0%, #ff6b81 100%); }
    .bg-gradient-orange { background: linear-gradient(135deg, #ffa502 0%, #ff7f50 100%); }
    .bg-gradient-purple { background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%); }
    .bg-gradient-dark { background: linear-gradient(135deg, #2f3542 0%, #57606f 100%); }

    /* Action Button in Card */
    .card-btn {
        background: rgba(255,255,255,0.2);
        border: 1px solid rgba(255,255,255,0.4);
        color: white;
        border-radius: 10px;
        padding: 8px 15px;
        font-weight: 700;
        font-size: 0.8rem;
        width: fit-content;
        margin-top: 15px;
        backdrop-filter: blur(5px);
        transition: all 0.3s ease;
    }

    .small-card:hover .card-btn {
        background: white;
        color: #333;
    }

    /* Filter Modal Form */
    .swal2-select-custom {
        width: 100%;
        padding: 10px;
        border-radius: 10px;
        margin-top: 10px;
        border: 1px solid #ddd;
    }
</style>

<div class="manage-container">
    <!-- Header -->
    <div class="premium-header animate__animated animate__fadeInDown">
        <h1 class="font-weight-bold mb-1"><i class="fas fa-magic mr-2"></i> Maamulka Heersare</h1>
        <p class="mb-0">Xaqiiji, Nadiifi, oo Maamul xogtaada si qurux badan.</p>
    </div>

    <!-- Main Grid -->
    <div class="grid-layout">
        <!-- Card: Filter Delete -->
        <div class="small-card bg-gradient-blue animate__animated animate__zoomIn" onclick="openFilterDelete()">
            <div>
                <div class="card-title">Tirtir Filter</div>
                <div class="card-subtitle">Fasal & Taariikh gaar ah</div>
            </div>
            <div class="card-btn">BILOW TIRTIRISTA</div>
            <i class="fas fa-calendar-alt"></i>
        </div>

        <!-- Card: Students -->
        <div class="small-card bg-gradient-red animate__animated animate__zoomIn" style="animation-delay: 0.1s" onclick="confirmHeersare('deleteAllStudentsForm', 'Dhammaan Ardayda')">
            <div>
                <div class="card-title">Ardayda</div>
                <div class="card-subtitle">Nadiifi dhammaan ardayda</div>
            </div>
            <div class="card-btn">TIRTIR DHAMMAAN</div>
            <i class="fas fa-user-slash"></i>
        </div>

        <!-- Card: Attendance -->
        <div class="small-card bg-gradient-orange animate__animated animate__zoomIn" style="animation-delay: 0.2s" onclick="confirmHeersare('deleteAllAttendanceForm', 'Dhammaan Xaadirinta')">
            <div>
                <div class="card-title">Xaadirinta</div>
                <div class="card-subtitle">Nadiifi taariikhda guud</div>
            </div>
            <div class="card-btn">NADIIFI XOGTA</div>
            <i class="fas fa-history"></i>
        </div>

        <!-- Card: Rooms -->
        <div class="small-card bg-gradient-purple animate__animated animate__zoomIn" style="animation-delay: 0.3s" onclick="confirmHeersare('deleteAllRoomsForm', 'Dhammaan Qololka')">
            <div>
                <div class="card-title">Qololka</div>
                <div class="card-subtitle">Tirtir dhammaan Rooms</div>
            </div>
            <div class="card-btn">TIRTIR ROOMS</div>
            <i class="fas fa-door-closed"></i>
        </div>

        <!-- Card: Classes -->
        <div class="small-card bg-gradient-dark animate__animated animate__zoomIn" style="animation-delay: 0.4s" onclick="confirmHeersare('deleteAllClassesForm', 'Dhammaan Fasalada')">
            <div>
                <div class="card-title">Fasalada</div>
                <div class="card-subtitle">Tirtir dhammaan Classes</div>
            </div>
            <div class="card-btn">TIRTIR CLASSES</div>
            <i class="fas fa-school"></i>
        </div>
    </div>

    <!-- Hidden Forms -->
    <form id="deleteClassForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete_by_class_date">
        <input type="hidden" name="delete_class_id" id="hidden_class_id">
        <input type="hidden" name="delete_date" id="hidden_date">
    </form>
    <form id="deleteAllStudentsForm" method="POST" style="display:none;"><input type="hidden" name="action" value="delete_all_students"></form>
    <form id="deleteAllAttendanceForm" method="POST" style="display:none;"><input type="hidden" name="action" value="delete_all"></form>
    <form id="deleteAllRoomsForm" method="POST" style="display:none;"><input type="hidden" name="action" value="delete_all_rooms"></form>
    <form id="deleteAllClassesForm" method="POST" style="display:none;"><input type="hidden" name="action" value="delete_all_classes"></form>
</div>

<script>
// Show success message if redirecting with message
<?php if ($message): ?>
Swal.fire({
    icon: 'success',
    title: 'Guul!',
    text: '<?= $message ?>',
    timer: 3000,
    showConfirmButton: false,
    background: '#fff',
    iconColor: '#2ed573'
});
<?php endif; ?>

<?php if ($error): ?>
Swal.fire({
    icon: 'error',
    title: 'Khalad!',
    text: '<?= $error ?>',
    background: '#fff',
    iconColor: '#ff4757'
});
<?php endif; ?>

function openFilterDelete() {
    Swal.fire({
        title: 'Tirtir Fasal & Taariikh',
        html: `
            <div style="text-align: left; padding: 10px;">
                <label class="small font-weight-bold">Dooro Fasalka:</label>
                <select id="swal_class_id" class="swal2-select-custom">
                    <option value="">-- Dooro Fasal --</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= $class['id']; ?>"><?= htmlspecialchars($class['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <br><br>
                <label class="small font-weight-bold">Dooro Taariikhda:</label>
                <input type="date" id="swal_date" class="swal2-select-custom">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'XAQIIJI',
        cancelButtonText: 'ISKA DAA',
        confirmButtonColor: '#4e73df',
        preConfirm: () => {
            const classId = document.getElementById('swal_class_id').value;
            const date = document.getElementById('swal_date').value;
            if (!classId || !date) {
                Swal.showValidationMessage('Fadlan buuxi dhammaan meelaha banaan');
            }
            return { classId, date };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('hidden_class_id').value = result.value.classId;
            document.getElementById('hidden_date').value = result.value.date;
            confirmHeersare('deleteClassForm', 'Xogta Fasalka iyo Taariikhda la doortay');
        }
    });
}

function confirmHeersare(formId, itemName) {
    Swal.fire({
        title: 'HUBIN HEERSARE AH!',
        html: `Ma hubtaa inaad rabto inaad tirtirto <br><b>${itemName}</b>?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ff4757',
        cancelButtonColor: '#2f3542',
        confirmButtonText: 'HAA, TIRTIR',
        cancelButtonText: 'JOOJI',
        showClass: {
            popup: 'animate__animated animate__fadeInDown'
        },
        hideClass: {
            popup: 'animate__animated animate__fadeOutUp'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'XAQIIJI TIRTIRISTA',
                text: 'Fadlan qor ereyga "TIRTIR" si aad u fuliso hawshan.',
                input: 'text',
                inputAttributes: { autocapitalize: 'off' },
                showCancelButton: true,
                confirmButtonText: 'FULI',
                confirmButtonColor: '#ff4757',
                showLoaderOnConfirm: true,
                preConfirm: (input) => {
                    if (input.toUpperCase() !== 'TIRTIR') {
                        Swal.showValidationMessage('Ereygu waa khalad!');
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Tirtirista waa la fulinayaa...',
                        html: 'Fadlan sug xogta waa la tirtirayaa.',
                        timer: 1500,
                        timerProgressBar: true,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    }).then(() => {
                        document.getElementById(formId).submit();
                    });
                }
            });
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
