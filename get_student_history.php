<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_login();

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($student_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if ($student) {
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ORDER BY date DESC");
        $stmt->execute([$student_id]);
        $history = $stmt->fetchAll();
        
        echo "<h4>" . $student['full_name'] . "</h4>";
        echo "<p>Parent Contact: " . ($student['parent_contact'] ?: 'N/A') . "</p>";
        
        if (empty($history)) {
            echo "<div class='alert alert-info'>No attendance records found for the past 30 days.</div>";
        } else {
            echo "<table class='table'>";
            echo "<thead><tr><th>Date</th><th>Period 1</th><th>Period 2</th></tr></thead>";
            echo "<tbody>";
            foreach ($history as $row) {
                $p1_color = $row['period_1_status'] == 'present' ? '#1cc88a' : '#e74a3b';
                $p2_color = $row['period_2_status'] == 'present' ? '#1cc88a' : '#e74a3b';
                echo "<tr>";
                echo "<td>" . format_date($row['date']) . "</td>";
                echo "<td><span class='badge' style='background: $p1_color; color: #fff; padding: 5px 10px; border-radius: 5px;'>" . ucfirst($row['period_1_status']) . "</span></td>";
                echo "<td><span class='badge' style='background: $p2_color; color: #fff; padding: 5px 10px; border-radius: 5px;'>" . ucfirst($row['period_2_status']) . "</span></td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        }
    } else {
        echo "Student not found.";
    }
} else {
    echo "Invalid student ID.";
}
?>
