<?php
require_once 'includes/header.php';

$room_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$today = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Export Logic
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $stmt = $pdo->prepare("
        SELECT s.full_name, c.class_name, a.period_1_status, a.period_2_status 
        FROM students s 
        JOIN classes c ON s.class_id = c.id 
        LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ?
        WHERE s.room_id = ? 
        ORDER BY s.full_name
    ");
    $stmt->execute([$today, $room_id]);
    $export_students = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT room_name FROM rooms WHERE id = ?");
    $stmt->execute([$room_id]);
    $room_info = $stmt->fetch();
    $filename = "Attendance_" . str_replace(' ', '_', $room_info['room_name']) . "_" . $today;

    if ($format == 'excel') {
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename.xls\"");
        header("Pragma: no-cache");
        header("Expires: 0");
        
        echo "<table border='1'>";
        echo "<tr><th colspan='4' style='background-color: #4e73df; color: white; font-size: 16px;'>Attendance Report: " . $room_info['room_name'] . " ($today)</th></tr>";
        echo "<tr><th style='background-color: #f8f9fc;'>Full Name</th><th style='background-color: #f8f9fc;'>Class</th><th style='background-color: #f8f9fc;'>P1 Status</th><th style='background-color: #f8f9fc;'>P2 Status</th></tr>";
        foreach ($export_students as $s) {
            echo "<tr><td>{$s['full_name']}</td><td>{$s['class_name']}</td><td>" . ucfirst($s['period_1_status'] ?? 'Not Marked') . "</td><td>" . ucfirst($s['period_2_status'] ?? 'Not Marked') . "</td></tr>";
        }
        echo "</table>";
        exit();
    }

    if ($format == 'word') {
        header("Content-Type: application/vnd.ms-word; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename.doc\"");
        header("Pragma: no-cache");
        header("Expires: 0");
        
        echo "<html>";
        echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=Windows-1252\">";
        echo "<body>";
        echo "<h2 style='text-align: center;'>Attendance Report: " . $room_info['room_name'] . "</h2>";
        echo "<p style='text-align: center;'>Date: $today</p>";
        echo "<table border='1' style='width: 100%; border-collapse: collapse;'>";
        echo "<thead><tr style='background-color: #f2f2f2;'><th>Full Name</th><th>Class</th><th>P1 Status</th><th>P2 Status</th></tr></thead><tbody>";
        foreach ($export_students as $s) {
            echo "<tr><td>{$s['full_name']}</td><td>{$s['class_name']}</td><td>" . ucfirst($s['period_1_status'] ?? 'Not Marked') . "</td><td>" . ucfirst($s['period_2_status'] ?? 'Not Marked') . "</td></tr>";
        }
        echo "</tbody></table>";
        echo "</body></html>";
        exit();
    }
}

// Delete Attendance Logic
if (isset($_POST['delete_attendance'])) {
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE date = ? AND student_id IN (SELECT id FROM students WHERE room_id = ?)");
    $stmt->execute([$today, $room_id]);
    header("Location: room_detail.php?id=$room_id&date=$today&deleted=1");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch();

if (!$room) {
    header("Location: rooms.php");
    exit();
}

$stmt = $pdo->prepare("
    SELECT s.*, c.class_name, a.period_1_status, a.period_2_status 
    FROM students s 
    JOIN classes c ON s.class_id = c.id 
    LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ?
    WHERE s.room_id = ? 
    ORDER BY s.full_name
");
$stmt->execute([$today, $room_id]);
$students = $stmt->fetchAll();
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><?php echo $room['room_name']; ?> - Names of the Student List</h1>
    <div class="d-flex align-items-center">
        <form method="GET" class="form-inline mr-3">
            <input type="hidden" name="id" value="<?php echo $room_id; ?>">
            <input type="date" name="date" value="<?php echo $today; ?>" class="form-control" onchange="this.form.submit()">
        </form>
        <a href="mark_attendance.php?room_id=<?php echo $room_id; ?>&date=<?php echo $today; ?>" class="btn btn-primary mr-2">Mark Attendance</a>
        <form method="POST" onsubmit="return confirm('Ma hubtaa in aad tirtirto dhamaan xaadirinta qolkan ee taariikhdan?');" style="display:inline;">
            <button type="submit" name="delete_attendance" class="btn btn-danger mr-2">Delete Attendance</button>
        </form>
        <div class="btn-group">
            <button type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                Export
            </button>
            <div class="dropdown-menu">
                <a class="dropdown-item" href="?id=<?php echo $room_id; ?>&date=<?php echo $today; ?>&export=excel">Excel (.xls)</a>
                <a class="dropdown-item" href="?id=<?php echo $room_id; ?>&date=<?php echo $today; ?>&export=word">Word (.doc)</a>
                <a class="dropdown-item" href="#" onclick="window.print()">Print / PDF</a>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    Xogta xaadirinta waa la tirtiray si guul leh!
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Students in <?php echo $room['room_name']; ?> (Today: <?php echo $today; ?>)</span>
        <?php 
            $count_present = 0;
            $count_absent = 0;
            foreach ($students as $s) {
                if ($s['period_1_status'] == 'present' && $s['period_2_status'] == 'present') {
                    $count_present++;
                } elseif ($s['period_1_status'] == 'absent' || $s['period_2_status'] == 'absent') {
                    $count_absent++;
                }
            }
        ?>
        <div>
            <span class="badge bg-success">Present: <?php echo $count_present; ?></span>
            <span class="badge bg-danger">Absent: <?php echo $count_absent; ?></span>
        </div>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Full Name</th>
                    <th>Class</th>
                    <th>P1 Status</th>
                    <th>P2 Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                <tr>
                    <td><img src="images/students/<?php echo $student['photo']; ?>" width="40" height="40" class="rounded-circle"></td>
                    <td><?php echo $student['full_name']; ?></td>
                    <td><?php echo $student['class_name']; ?></td>
                    <td><span class="badge" style="background: <?php echo $student['period_1_status'] == 'present' ? '#1cc88a' : '#e74a3b'; ?>; color: #fff; padding: 5px 10px; border-radius: 5px;"><?php echo ucfirst($student['period_1_status'] ?? 'Not Marked'); ?></span></td>
                    <td><span class="badge" style="background: <?php echo $student['period_2_status'] == 'present' ? '#1cc88a' : '#e74a3b'; ?>; color: #fff; padding: 5px 10px; border-radius: 5px;"><?php echo ucfirst($student['period_2_status'] ?? 'Not Marked'); ?></span></td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick="showHistory(<?php echo $student['id']; ?>)">History</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Student History Modal -->
<div id="historyModal" style="display:none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);">
    <div style="background: #fff; margin: 5% auto; padding: 20px; width: 80%; max-width: 800px; border-radius: 10px; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Student Attendance History (Past 30 Days)</h3>
            <button class="btn btn-danger" onclick="document.getElementById('historyModal').style.display='none'">&times;</button>
        </div>
        <div id="historyContent">Loading...</div>
    </div>
</div>

<script>
function showHistory(studentId) {
    document.getElementById('historyModal').style.display = 'block';
    document.getElementById('historyContent').innerHTML = 'Loading...';
    
    fetch('get_student_history.php?id=' + studentId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('historyContent').innerHTML = data;
        });
}
</script>

<?php require_once 'includes/footer.php'; ?>
