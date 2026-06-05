<?php
require_once 'includes/header.php';
require_admin();

$error = '';
$success = '';

// Get all available teacher pages
$available_pages = get_available_teacher_pages();

// Handle User Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    // Verify CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = "Codsigaagu ma shaqeyn, fadlan isku day mar kale.";
    } else {
        $username = sanitize($_POST['username']);
        $password_plain = $_POST['password'];
        $role = sanitize($_POST['role']);
        $full_name = sanitize($_POST['full_name']);
        
        // Basic Validation
        if (empty($username) || empty($password_plain) || empty($full_name)) {
            $error = "Fadlan buuxi dhammaan meelaha bannaan.";
        } else {
            try {
                // Check if username already exists
                $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $check_stmt->execute([$username]);
                if ($check_stmt->fetch()) {
                    $error = "Magacan (Username) hore ayaa loo isticmaalay. Fadlan dooro mid kale.";
                } else {
                    $password = password_hash($password_plain, PASSWORD_DEFAULT);
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, full_name, status) VALUES (?, ?, ?, ?, 1)");
                    $stmt->execute([$username, $password, $role, $full_name]);
                    $user_id = $pdo->lastInsertId();

                    // Assign Page Access
                    if (isset($_POST['pages']) && is_array($_POST['pages'])) {
                        $page_stmt = $pdo->prepare("INSERT INTO teacher_pages (user_id, page_name) VALUES (?, ?)");
                        foreach ($_POST['pages'] as $page_name) {
                            if (array_key_exists($page_name, $available_pages)) {
                                $page_stmt->execute([$user_id, $page_name]);
                            }
                        }
                    }

                    // Assign Rooms
                    if (isset($_POST['rooms']) && is_array($_POST['rooms'])) {
                        $room_stmt = $pdo->prepare("INSERT INTO user_rooms (user_id, room_id) VALUES (?, ?)");
                        foreach ($_POST['rooms'] as $room_id) {
                            $room_stmt->execute([$user_id, (int)$room_id]);
                        }
                    }
                    
                    $pdo->commit();
                    $success = "User-ka si guul leh ayaa loo abuuray!";
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "Khalad ayaa dhacay: " . $e->getMessage();
            }
        }
    }
}

// Handle User Deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id != $_SESSION['user_id']) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM user_rooms WHERE user_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM teacher_pages WHERE user_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            $pdo->commit();
            $success = "User-ka waa la tirtiray!";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Naftaada ma tirtiri kartid!";
    }
}

// Handle User Status Toggle
if (isset($_GET['toggle_status'])) {
    $id = (int)$_GET['toggle_status'];
    $stmt = $pdo->prepare("UPDATE users SET status = 1 - status WHERE id = ?");
    $stmt->execute([$id]);
    $success = "Status-ka user-ka waa la beddelay!";
}

$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
$all_rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_name ASC")->fetchAll();
?>

<style>
/* POPUP MODAL STYLES - Matches students.php pattern */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    animation: fadeIn 0.2s ease-out;
    backdrop-filter: blur(4px);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes popupBounce {
    0% { opacity: 0; transform: scale(0.3) translateY(-50px); }
    60% { transform: scale(1.08); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
}

.popup-modal {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 30px;
    max-width: 800px;
    width: 95%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: popupBounce 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
    overflow-y: auto;
    max-height: 90vh;
}

.popup-icon {
    font-size: 3rem;
    margin-bottom: 10px;
    text-align: center;
}

.popup-title {
    color: white;
    font-size: 1.8rem;
    font-weight: 800;
    margin-bottom: 20px;
    text-align: center;
}

.popup-form-group {
    text-align: left;
    margin-bottom: 15px;
}

.popup-form-group label {
    color: white;
    font-weight: 600;
    margin-bottom: 5px;
    display: block;
}

.popup-input {
    width: 100%;
    padding: 10px 15px;
    border-radius: 10px;
    border: none;
    background: rgba(255, 255, 255, 0.9);
    font-size: 1rem;
}

.popup-btn-container {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-top: 25px;
}

.popup-btn {
    padding: 12px 30px;
    border-radius: 10px;
    border: none;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}

.popup-btn-cancel {
    background: rgba(255, 255, 255, 0.2);
    color: white;
}

.popup-btn-confirm {
    background: white;
    color: #667eea;
}

.popup-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.access-control-box {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 15px;
    max-height: 250px;
    overflow-y: auto;
}

.custom-checkbox-container {
    display: block;
    position: relative;
    padding-left: 35px;
    margin-bottom: 12px;
    cursor: pointer;
    color: white;
    font-size: 0.95rem;
    user-select: none;
}

.custom-checkbox-container input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}

.checkmark {
    position: absolute;
    top: 0;
    left: 0;
    height: 22px;
    width: 22px;
    background-color: rgba(255, 255, 255, 0.3);
    border-radius: 6px;
}

.custom-checkbox-container:hover input ~ .checkmark {
    background-color: rgba(255, 255, 255, 0.5);
}

.custom-checkbox-container input:checked ~ .checkmark {
    background-color: #28a745;
}

.checkmark:after {
    content: "";
    position: absolute;
    display: none;
}

.custom-checkbox-container input:checked ~ .checkmark:after {
    display: block;
}

.custom-checkbox-container .checkmark:after {
    left: 8px;
    top: 4px;
    width: 6px;
    height: 12px;
    border: solid white;
    border-width: 0 3px 3px 0;
    transform: rotate(45deg);
}
</style>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Maareynta Isticmaalayaasha</h1>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('addUserModal').style.display='flex'">
        <i class="fas fa-user-plus mr-1"></i> Kudar User Cusub
    </button>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Magaca Buuxa</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Taariikhda</th>
                        <th>Maareen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><span class="badge badge-<?php echo $user['role'] == 'admin' ? 'primary' : 'info'; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                            <td>
                                <a href="?toggle_status=<?php echo $user['id']; ?>" class="badge badge-<?php echo $user['status'] ? 'success' : 'danger'; ?>">
                                    <?php echo $user['status'] ? 'Active' : 'Inactive'; ?>
                                </a>
                            </td>
                            <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                            <td>
                                <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-edit"></i></a>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <a href="?delete=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Ma hubtaa inaad tirtirto user-kan?')"><i class="fas fa-trash"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal-overlay">
    <div class="popup-modal">
        <div class="popup-icon">👤</div>
        <div class="popup-title">Kudar Isticmaale Cusub</div>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="row">
                <div class="col-md-6">
                    <div class="popup-form-group">
                        <label>Magaca Buuxa</label>
                        <input type="text" name="full_name" class="popup-input" required placeholder="Gali magaca buuxa">
                    </div>
                    <div class="popup-form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="popup-input" required placeholder="Gali username-ka">
                    </div>
                    <div class="popup-form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="popup-input" required placeholder="Gali password-ka">
                    </div>
                    <div class="popup-form-group">
                        <label>Role (Xilka)</label>
                        <select name="role" class="popup-input">
                            <option value="teacher">Macallin (Teacher)</option>
                            <option value="admin">Maamule (Admin)</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="popup-form-group">
                        <label class="font-weight-bold">Xakameynta Bogagga (Page Access)</label>
                        <div class="access-control-box">
                            <?php foreach ($available_pages as $page_name => $page_label): ?>
                                <label class="custom-checkbox-container">
                                    <?php echo htmlspecialchars($page_label); ?>
                                    <input type="checkbox" name="pages[]" value="<?php echo htmlspecialchars($page_name); ?>">
                                    <span class="checkmark"></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="popup-form-group">
                        <label class="font-weight-bold">Rooms Access</label>
                        <div class="access-control-box" style="max-height: 150px;">
                            <?php foreach ($all_rooms as $room): ?>
                                <label class="custom-checkbox-container">
                                    <?php echo htmlspecialchars($room['room_name']); ?>
                                    <input type="checkbox" name="rooms[]" value="<?php echo $room['id']; ?>">
                                    <span class="checkmark"></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="popup-btn-container">
                <button type="button" class="popup-btn popup-btn-cancel" onclick="document.getElementById('addUserModal').style.display='none'">Ka laabo</button>
                <button type="submit" name="create_user" class="popup-btn popup-btn-confirm">Keydi User-ka</button>
            </div>
        </form>
    </div>
</div>

<script>
// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
