<?php
require_once 'includes/header.php';
require_admin();

// Get today's date
$today = date('Y-m-d');

// Fetch all rooms with attendance status for today
$rooms_query = $pdo->query("
    SELECT r.id, r.room_name, r.capacity,
           -- Count total students in room
           (SELECT COUNT(*) FROM students s WHERE s.room_id = r.id) as total_students,
           
           -- Count students with Period 1 marked as present
           (SELECT COUNT(*) FROM attendance a 
            JOIN students s ON a.student_id = s.id 
            WHERE s.room_id = r.id AND a.date = '$today' 
            AND a.period_1_status = 'present') as p1_present,
           
           -- Count students with Period 1 marked as absent
           (SELECT COUNT(*) FROM attendance a 
            JOIN students s ON a.student_id = s.id 
            WHERE s.room_id = r.id AND a.date = '$today' 
            AND a.period_1_status = 'absent') as p1_absent,
           
           -- Count students with Period 2 marked as present
           (SELECT COUNT(*) FROM attendance a 
            JOIN students s ON a.student_id = s.id 
            WHERE s.room_id = r.id AND a.date = '$today' 
            AND a.period_2_status = 'present') as p2_present,
           
           -- Count students with Period 2 marked as absent
           (SELECT COUNT(*) FROM attendance a 
            JOIN students s ON a.student_id = s.id 
            WHERE s.room_id = r.id AND a.date = '$today' 
            AND a.period_2_status = 'absent') as p2_absent
    FROM rooms r
    ORDER BY r.room_name
");
$rooms = $rooms_query->fetchAll();
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">📊 Xaaladda Xaadirinta Qolalka (Maanta - <?php echo date('d/m/Y'); ?>)</h1>
    </div>

    <!-- Legend -->
    <div class="card mb-4">
        <div class="card-body">
            <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 20px; height: 20px; background-color: #1cc88a; border-radius: 50%;"></div>
                    <span><strong>Green:</strong> La Xaadiriyay (Present)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 20px; height: 20px; background-color: #e74a3b; border-radius: 50%;"></div>
                    <span><strong>Red:</strong> Lama Xaadirin (Absent)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Rooms Grid -->
    <div class="row" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px;">
        <?php foreach ($rooms as $room): 
            $total = $room['total_students'];
            
            // Period 1 calculations
            $p1_present = $room['p1_present'];
            $p1_absent = $room['p1_absent'];
            $p1_total = $p1_present + $p1_absent;
            $p1_percentage = $total > 0 ? round(($p1_present / $total) * 100) : 0;
            
            // Period 2 calculations
            $p2_present = $room['p2_present'];
            $p2_absent = $room['p2_absent'];
            $p2_total = $p2_present + $p2_absent;
            $p2_percentage = $total > 0 ? round(($p2_present / $total) * 100) : 0;
            
            // Determine colors based on attendance status
            // Green if all present (100%), Red if all absent (0%), otherwise orange
            $p1_color = ($p1_percentage == 100) ? '#1cc88a' : (($p1_percentage == 0) ? '#e74a3b' : '#f6c23e');
            $p2_color = ($p2_percentage == 100) ? '#1cc88a' : (($p2_percentage == 0) ? '#e74a3b' : '#f6c23e');
        ?>
        
        <div class="card shadow" style="border-top: 4px solid #4e73df; transition: transform 0.2s; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
            <div class="card-body">
                <!-- Room Name -->
                <h5 class="font-weight-bold text-dark mb-4" style="font-size: 1.3rem;">
                    <?php echo htmlspecialchars($room['room_name']); ?>
                </h5>

                <!-- Total Students -->
                <div style="margin-bottom: 15px; padding: 10px; background: #f8f9fc; border-radius: 8px; border-left: 4px solid #4e73df;">
                    <span style="font-size: 0.9rem; color: #5a5c69;">Ardayda Guud:</span>
                    <strong style="font-size: 1.2rem; color: #4e73df; margin-left: 10px;"><?php echo $total; ?></strong>
                </div>

                <!-- Period 1 Status -->
                <div style="margin-bottom: 15px; padding: 12px; background: #f8f9fc; border-radius: 8px; border-left: 4px solid <?php echo $p1_color; ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 0.95rem; font-weight: 600;">📚 Xisada 1aad:</span>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="color: <?php echo $p1_color; ?>; font-weight: bold; font-size: 1.1rem;"><?php echo $p1_percentage; ?>%</span>
                            <div style="width: 18px; height: 18px; background-color: <?php echo $p1_color; ?>; border-radius: 50%; box-shadow: 0 0 8px <?php echo $p1_color; ?>;"></div>
                        </div>
                    </div>
                    <div style="font-size: 0.85rem; color: #5a5c69;">
                        <span style="color: #1cc88a; font-weight: bold;">✓ <?php echo $p1_present; ?></span> | 
                        <span style="color: #e74a3b; font-weight: bold;">✗ <?php echo $p1_absent; ?></span> | 
                        <span>Jumla: <?php echo $p1_total; ?></span>
                    </div>
                </div>

                <!-- Period 2 Status -->
                <div style="padding: 12px; background: #f8f9fc; border-radius: 8px; border-left: 4px solid <?php echo $p2_color; ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 0.95rem; font-weight: 600;">📚 Xisada 2aad:</span>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="color: <?php echo $p2_color; ?>; font-weight: bold; font-size: 1.1rem;"><?php echo $p2_percentage; ?>%</span>
                            <div style="width: 18px; height: 18px; background-color: <?php echo $p2_color; ?>; border-radius: 50%; box-shadow: 0 0 8px <?php echo $p2_color; ?>;"></div>
                        </div>
                    </div>
                    <div style="font-size: 0.85rem; color: #5a5c69;">
                        <span style="color: #1cc88a; font-weight: bold;">✓ <?php echo $p2_present; ?></span> | 
                        <span style="color: #e74a3b; font-weight: bold;">✗ <?php echo $p2_absent; ?></span> | 
                        <span>Jumla: <?php echo $p2_total; ?></span>
                    </div>
                </div>

                <!-- Action Button -->
                <div style="margin-top: 15px;">
                    <a href="room_detail.php?id=<?php echo $room['id']; ?>" class="btn btn-sm btn-info" style="width: 100%;">
                        Faah-faahinta Qolka →
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Empty State -->
    <?php if (empty($rooms)): ?>
    <div class="alert alert-info text-center" role="alert">
        <h4 class="alert-heading">Waxba lama helin!</h4>
        <p>Qolal la soo gelin kama jiraan.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
