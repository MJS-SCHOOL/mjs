<?php
require_once 'includes/header.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_id'])) {
    $room_id = (int)$_POST['room_id'];
    
    try {
        // Check if room has students
        $check = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE room_id = ?");
        $check->execute([$room_id]);
        $result = $check->fetch();
        
        if ($result['count'] > 0) {
            $_SESSION['error'] = "Qolka waxaa ku jira " . $result['count'] . " arday. Fadlan ardayda ka soo safir marka hore.";
        } else {
            // Delete the room
            $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
            $stmt->execute([$room_id]);
            $_SESSION['success'] = "Qolka ayaa si guul leh loo tirtirtay!";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Khalad: " . $e->getMessage();
    }
}

header("Location: add_room.php");
exit();
?>
