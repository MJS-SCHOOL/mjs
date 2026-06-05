<?php
require_once 'includes/header.php';
require_admin();

$error = '';
$success = '';

if (!isset($_GET['id'])) {
    header("Location: add_room.php");
    exit();
}

$id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->execute([$id]);
$room = $stmt->fetch();

if (!$room) {
    header("Location: add_room.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_room'])) {
    $room_name = sanitize($_POST['room_name']);
    $capacity = (int)$_POST['capacity'];
    $description = sanitize($_POST['description']);
    
    // Validate input
    if (empty($room_name)) {
        $error = "Fadlan magaca qolka ku qor.";
    } elseif ($capacity <= 0) {
        $error = "Awoodda qolka waa inay ka weyn tahay 0.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE rooms SET room_name = ?, capacity = ?, description = ? WHERE id = ?");
            $stmt->execute([$room_name, $capacity, $description, $id]);
            $success = "Qolka ayaa si guul leh loo cusboonaysiyey!";
            
            // Refresh room data
            $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
            $stmt->execute([$id]);
            $room = $stmt->fetch();
        } catch (PDOException $e) {
            $error = "Khalad: " . $e->getMessage();
        }
    }
}
?>

<style>
    .form-section {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        margin-bottom: 40px;
    }
    
    .form-section h2 {
        color: #2e3338;
        margin-bottom: 25px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #2e3338;
    }
    
    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e3e6f0;
        border-radius: 8px;
        font-size: 1rem;
        transition: all 0.3s ease;
        font-family: inherit;
    }
    
    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #4e73df;
        box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.1);
    }
    
    .form-group textarea {
        resize: vertical;
        min-height: 100px;
    }
    
    .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 25px;
        flex-wrap: wrap;
    }
    
    .btn-submit {
        background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 1rem;
    }
    
    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(28, 200, 138, 0.4);
    }
    
    .btn-back {
        background: #858796;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 1rem;
    }
    
    .btn-back:hover {
        background: #6c757d;
        transform: translateY(-2px);
    }
    
    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-weight: 500;
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .alert-danger {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .page-header h1 {
        margin: 0;
        color: #2e3338;
        font-weight: 700;
    }
    
    .room-info-card {
        background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
        color: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 30px;
    }
    
    .room-info-card h3 {
        margin: 0 0 15px 0;
        font-size: 1.3rem;
    }
    
    .room-info-item {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
        font-size: 0.95rem;
    }
    
    .room-info-label {
        font-weight: 600;
        margin-right: 10px;
        min-width: 120px;
    }
</style>

<div class="container-fluid">
    <div class="page-header">
        <h1>✏️ Wax ka Bedel Qolka</h1>
        <a href="add_room.php" class="btn-back">← Dib u laabo</a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <strong>⚠️ Khalad:</strong> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <strong>✓ Guul:</strong> <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <!-- Current Room Info -->
    <div class="room-info-card">
        <h3>🏫 <?php echo htmlspecialchars($room['room_name']); ?></h3>
        <div class="room-info-item">
            <span class="room-info-label">👥 Awoodda:</span>
            <span><?php echo (int)$room['capacity']; ?> Arday</span>
        </div>
        <?php if (!empty($room['description'])): ?>
            <div class="room-info-item">
                <span class="room-info-label">📝 Sharax:</span>
                <span><?php echo htmlspecialchars($room['description']); ?></span>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Edit Room Form -->
    <div class="form-section">
        <h2>📝 Xogta Qolka Wax Ka Bedel</h2>
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="room_name">Magaca Qolka *</label>
                        <input 
                            type="text" 
                            id="room_name" 
                            name="room_name" 
                            class="form-control" 
                            value="<?php echo htmlspecialchars($room['room_name']); ?>"
                            placeholder="Tusaale: Fasalka 1A" 
                            required
                        >
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="capacity">Awoodda (Tirada Ardayda) *</label>
                        <input 
                            type="number" 
                            id="capacity" 
                            name="capacity" 
                            class="form-control" 
                            value="<?php echo (int)$room['capacity']; ?>"
                            placeholder="Tusaale: 40" 
                            min="1"
                            required
                        >
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Sharaxaadda</label>
                <textarea 
                    id="description" 
                    name="description" 
                    class="form-control"
                    placeholder="Tusaale: Qolka fasalka ugu sarreeya, dhulka labaad"
                ><?php echo htmlspecialchars($room['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="update_room" class="btn-submit">
                    ✓ Cusboonaysii
                </button>
                <a href="add_room.php" class="btn-back">
                    ← Dib u laabo
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
