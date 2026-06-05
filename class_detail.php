<?php
require_once 'includes/header.php';

$class_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$today = date('Y-m-d');

$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
$stmt->execute([$class_id]);
$class = $stmt->fetch();

if (!$class) {
    header("Location: classes.php");
    exit();
}

// Fetch rooms in this class with student counts and today's attendance
$rooms_query = $pdo->prepare("
    SELECT r.*, 
           (SELECT COUNT(*) FROM students s WHERE s.room_id = r.id AND s.class_id = ?) as total_students,
           (SELECT COUNT(*) FROM attendance a 
            JOIN students s ON a.student_id = s.id 
            WHERE s.room_id = r.id AND s.class_id = ? AND a.date = ? AND (a.period_1_status = 'present' OR a.period_2_status = 'present')) as present_today
    FROM rooms r
    JOIN rooms_classes rc ON r.id = rc.room_id
    WHERE rc.class_id = ?
");
$rooms_query->execute([$class_id, $class_id, $today, $class_id]);
$rooms = $rooms_query->fetchAll();
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><?php echo $class['class_name']; ?> - Room Overview</h1>
    <a href="mark_attendance.php?class_id=<?php echo $class_id; ?>" class="btn btn-primary">Mark Attendance</a>
</div>

<div class="row" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
    <?php foreach ($rooms as $room): 
        $perc = $room['total_students'] > 0 ? round(($room['present_today'] / $room['total_students']) * 100, 1) : 0;
        $icon_color = $perc >= 80 ? '#1cc88a' : ($perc >= 50 ? '#f6c23e' : '#e74a3b');
    ?>
    <div class="card" onclick="location.href='room_detail.php?id=<?php echo $room['id']; ?>'" style="cursor: pointer;">
        <div class="card-body">
            <h5 class="card-title" style="font-weight: bold; color: #4e73df;"><?php echo $room['room_name']; ?></h5>
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <span>Total Students:</span>
                <strong><?php echo $room['total_students']; ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <span>Present Today:</span>
                <strong class="text-success"><?php echo $room['present_today']; ?></strong>
            </div>
            <div class="progress" style="height: 10px; background: #eaecf4; border-radius: 5px; margin-top: 15px;">
                <div class="progress-bar" style="width: <?php echo $perc; ?>%; background: <?php echo $icon_color; ?>; height: 100%; border-radius: 5px;"></div>
            </div>
            <div style="text-align: right; margin-top: 5px; font-size: 0.8rem; font-weight: bold;">
                <?php echo $perc; ?>% Attendance
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
