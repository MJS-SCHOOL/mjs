<?php
require_once 'includes/header.php';
require_admin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_room'])) {
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
            $stmt = $pdo->prepare("INSERT INTO rooms (room_name, capacity, description) VALUES (?, ?, ?)");
            $stmt->execute([$room_name, $capacity, $description]);
            $success = "Qolka cusub ayaa si guul leh loo soo daray!";
            
            // Clear form
            $room_name = '';
            $capacity = '';
            $description = '';
        } catch (PDOException $e) {
            $error = "Khalad: " . $e->getMessage();
        }
    }
}

$rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_name")->fetchAll();
?>

<style>
    .rooms-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 30px;
    }
    
    .room-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fc 100%);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        border: 2px solid transparent;
        position: relative;
    }
    
    .room-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        border-color: #4e73df;
    }
    
    .room-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 2px solid #e3e6f0;
    }
    
    .room-card-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #2e3338;
        margin: 0;
    }
    
    .room-card-icon {
        font-size: 1.8rem;
    }
    
    .room-card-info {
        margin-bottom: 15px;
    }
    
    .room-info-item {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
        font-size: 0.95rem;
        color: #5a5c69;
    }
    
    .room-info-label {
        font-weight: 600;
        margin-right: 8px;
        color: #2e3338;
        min-width: 100px;
    }
    
    .room-info-value {
        color: #4e73df;
        font-weight: 500;
    }
    
    .room-card-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #e3e6f0;
    }
    
    .room-card-actions button {
        flex: 1;
        padding: 8px 12px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
        font-size: 0.85rem;
    }
    
    .btn-edit {
        background: #4e73df;
        color: white;
    }
    
    .btn-edit:hover {
        background: #224abe;
        transform: translateY(-2px);
    }
    
    .btn-delete {
        background: #e74a3b;
        color: white;
    }
    
    .btn-delete:hover {
        background: #c82333;
        transform: translateY(-2px);
    }
    
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
    
    .btn-reset {
        background: #858796;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 1rem;
    }
    
    .btn-reset:hover {
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
    
    .btn-back {
        background: #858796;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-back:hover {
        background: #6c757d;
        transform: translateY(-2px);
    }
    
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #858796;
    }
    
    .empty-state-icon {
        font-size: 3rem;
        margin-bottom: 15px;
    }
</style>

<div class="container-fluid">
    <div class="page-header">
        <h1>🏫 Qolalka Dugsiga</h1>
        <a href="rooms.php" class="btn-back">← Dib u laabo</a>
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
    
    <!-- Add Room Form -->
    <div class="form-section">
        <h2>➕ Qol Cusub Soo Dar</h2>
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
                            value="<?php echo isset($room_name) ? htmlspecialchars($room_name) : ''; ?>"
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
                            value="<?php echo isset($capacity) ? htmlspecialchars($capacity) : ''; ?>"
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
                ><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="add_room" class="btn-submit">
                    ✓ Qol Soo Dar
                </button>
                <button type="reset" class="btn-reset">
                    ↻ Nadiifi
                </button>
            </div>
        </form>
    </div>
    
    <!-- Rooms List -->
    <div>
        <h2 style="color: #2e3338; margin-bottom: 25px; font-weight: 700;">📋 Liiska Qolalka (<?php echo count($rooms); ?>)</h2>
        
        <?php if (empty($rooms)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <p>Qolal la'aan jira. Fadlan qol cusub soo dar.</p>
            </div>
        <?php else: ?>
            <div class="rooms-container">
                <?php foreach ($rooms as $room): ?>
                    <div class="room-card">
                        <div class="room-card-header">
                            <h3 class="room-card-title">
                                <span class="room-card-icon">🏫</span> <?php echo htmlspecialchars($room['room_name']); ?>
                            </h3>
                        </div>
                        
                        <div class="room-card-info">
                            <div class="room-info-item">
                                <span class="room-info-label">👥 Awoodda:</span>
                                <span class="room-info-value"><?php echo (int)$room['capacity']; ?> Arday</span>
                            </div>
                            
                            <?php if (!empty($room['description'])): ?>
                                <div class="room-info-item">
                                    <span class="room-info-label">📝 Sharax:</span>
                                    <span class="room-info-value"><?php echo htmlspecialchars($room['description']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="room-card-actions">
                            <a href="edit_room.php?id=<?php echo $room['id']; ?>" class="btn-edit">
                                ✏️ Wax Ka Bedel
                            </a>
                            <button class="btn-delete" onclick="deleteRoom(<?php echo $room['id']; ?>, '<?php echo htmlspecialchars($room['room_name']); ?>')">
                                🗑️ Tirtir
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function deleteRoom(roomId, roomName) {
        if (confirm(`Ma hubtaa inaad tirtiraysaa qolka: "${roomName}"?`)) {
            // Create a form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'delete_room.php';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'room_id';
            input.value = roomId;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>
