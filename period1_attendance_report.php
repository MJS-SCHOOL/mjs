<?php
require_once 'includes/header.php';
require_admin();

// Get today's date
$today = date('Y-m-d');

// Fetch all rooms with Period 1 attendance details
$rooms_query = $pdo->query("
    SELECT r.id, r.room_name, r.capacity,
           (SELECT COUNT(*) FROM students s WHERE s.room_id = r.id) as total_students
    FROM rooms r
    ORDER BY r.room_name
");
$rooms = $rooms_query->fetchAll();

// Get selected room from GET parameter
$selected_room = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;

?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">📊 XAALADDA XAADIRINTA XISADA 1AAD (Maanta - <?php echo date('d/m/Y'); ?>)</h1>
    </div>

    <!-- Filter by Room -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row">
                <div class="col-md-6">
                    <label for="room_select"><strong>Dooro Qolka:</strong></label>
                    <select name="room_id" id="room_select" class="form-control" onchange="this.form.submit();">
                        <option value="">-- Dhammaan Qolalka --</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?php echo $room['id']; ?>" <?php echo $selected_room == $room['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($room['room_name']); ?> (<?php echo $room['total_students']; ?> ardayda)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Legend -->
    <div class="card mb-4">
        <div class="card-body">
            <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 20px; height: 20px; background-color: #1cc88a; border-radius: 50%;"></div>
                    <span><strong>Cagaaran:</strong> La Xaadiriyay (Present)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 20px; height: 20px; background-color: #e74a3b; border-radius: 50%;"></div>
                    <span><strong>Cas:</strong> Lama Xaadirin (Absent)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 20px; height: 20px; background-color: #cccccc; border-radius: 50%;"></div>
                    <span><strong>Cad:</strong> Lama Xisaabin (Not Marked)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Rooms Grid -->
    <div class="row" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 25px;">
        <?php 
        // If a specific room is selected, show detailed view
        if ($selected_room > 0) {
            $room_data = null;
            foreach ($rooms as $room) {
                if ($room['id'] == $selected_room) {
                    $room_data = $room;
                    break;
                }
            }

            if ($room_data) {
                // Get all students in the room with their Period 1 status
                $students_query = $pdo->prepare("
                    SELECT s.id, s.full_name, s.photo,
                           a.period_1_status
                    FROM students s
                    LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ?
                    WHERE s.room_id = ?
                    ORDER BY s.full_name
                ");
                $students_query->execute([$today, $selected_room]);
                $students = $students_query->fetchAll();

                // Count statistics
                $present_count = 0;
                $absent_count = 0;
                $not_marked_count = 0;

                foreach ($students as $student) {
                    if ($student['period_1_status'] === 'present') {
                        $present_count++;
                    } elseif ($student['period_1_status'] === 'absent') {
                        $absent_count++;
                    } else {
                        $not_marked_count++;
                    }
                }

                $total = count($students);
                $present_percentage = $total > 0 ? round(($present_count / $total) * 100) : 0;
                $absent_percentage = $total > 0 ? round(($absent_count / $total) * 100) : 0;
                $not_marked_percentage = $total > 0 ? round(($not_marked_count / $total) * 100) : 0;
        ?>

        <!-- Summary Card -->
        <div class="card shadow" style="border-top: 4px solid #4e73df; grid-column: 1 / -1;">
            <div class="card-body">
                <h5 class="font-weight-bold text-dark mb-4" style="font-size: 1.3rem;">
                    <?php echo htmlspecialchars($room_data['room_name']); ?> - XISADA 1AAD
                </h5>

                <!-- Statistics -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                    <div style="padding: 15px; background: #f8f9fc; border-radius: 8px; border-left: 4px solid #1cc88a;">
                        <span style="font-size: 0.9rem; color: #5a5c69;">La Xaadiriyay:</span>
                        <strong style="font-size: 1.5rem; color: #1cc88a; display: block; margin-top: 5px;">
                            <?php echo $present_count; ?> / <?php echo $total; ?> (<?php echo $present_percentage; ?>%)
                        </strong>
                    </div>

                    <div style="padding: 15px; background: #f8f9fc; border-radius: 8px; border-left: 4px solid #e74a3b;">
                        <span style="font-size: 0.9rem; color: #5a5c69;">Lama Xaadirin:</span>
                        <strong style="font-size: 1.5rem; color: #e74a3b; display: block; margin-top: 5px;">
                            <?php echo $absent_count; ?> / <?php echo $total; ?> (<?php echo $absent_percentage; ?>%)
                        </strong>
                    </div>

                    <div style="padding: 15px; background: #f8f9fc; border-radius: 8px; border-left: 4px solid #cccccc;">
                        <span style="font-size: 0.9rem; color: #5a5c69;">Lama Xisaabin:</span>
                        <strong style="font-size: 1.5rem; color: #666; display: block; margin-top: 5px;">
                            <?php echo $not_marked_count; ?> / <?php echo $total; ?> (<?php echo $not_marked_percentage; ?>%)
                        </strong>
                    </div>
                </div>

                <!-- Progress Bars -->
                <div style="margin-bottom: 20px;">
                    <div style="margin-bottom: 10px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span style="font-size: 0.9rem; font-weight: 600;">La Xaadiriyay</span>
                            <span style="font-size: 0.9rem; font-weight: 600; color: #1cc88a;"><?php echo $present_percentage; ?>%</span>
                        </div>
                        <div style="width: 100%; height: 20px; background: #e3e6f0; border-radius: 10px; overflow: hidden;">
                            <div style="width: <?php echo $present_percentage; ?>%; height: 100%; background: #1cc88a; transition: width 0.3s;"></div>
                        </div>
                    </div>

                    <div style="margin-bottom: 10px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span style="font-size: 0.9rem; font-weight: 600;">Lama Xaadirin</span>
                            <span style="font-size: 0.9rem; font-weight: 600; color: #e74a3b;"><?php echo $absent_percentage; ?>%</span>
                        </div>
                        <div style="width: 100%; height: 20px; background: #e3e6f0; border-radius: 10px; overflow: hidden;">
                            <div style="width: <?php echo $absent_percentage; ?>%; height: 100%; background: #e74a3b; transition: width 0.3s;"></div>
                        </div>
                    </div>

                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span style="font-size: 0.9rem; font-weight: 600;">Lama Xisaabin</span>
                            <span style="font-size: 0.9rem; font-weight: 600; color: #999;"><?php echo $not_marked_percentage; ?>%</span>
                        </div>
                        <div style="width: 100%; height: 20px; background: #e3e6f0; border-radius: 10px; overflow: hidden;">
                            <div style="width: <?php echo $not_marked_percentage; ?>%; height: 100%; background: #cccccc; transition: width 0.3s;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Present Students Section -->
        <div class="card shadow" style="border-top: 4px solid #1cc88a;">
            <div class="card-header" style="background: #1cc88a; color: white; font-weight: bold;">
                ✓ LA XAADIRIYAY (<?php echo $present_count; ?> ardayda)
            </div>
            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                <?php if ($present_count > 0): ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px;">
                        <?php foreach ($students as $student): 
                            if ($student['period_1_status'] === 'present'):
                        ?>
                        <div style="padding: 12px; background: #f0fdf4; border: 2px solid #1cc88a; border-radius: 8px; text-align: center;">
                            <div style="width: 50px; height: 50px; margin: 0 auto 8px; border-radius: 50%; overflow: hidden; background: #e8f5e9; display: flex; align-items: center; justify-content: center;">
                                <img src="<?php echo htmlspecialchars($student['photo']); ?>" alt="<?php echo htmlspecialchars($student['full_name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <strong style="font-size: 0.85rem; color: #1cc88a; display: block;"><?php echo htmlspecialchars($student['full_name']); ?></strong>
                            <span style="font-size: 0.75rem; color: #666;">✓ Joogta</span>
                        </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 20px; color: #999;">
                        Ardayda lama xaadirin waxa jira.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Absent Students Section -->
        <div class="card shadow" style="border-top: 4px solid #e74a3b;">
            <div class="card-header" style="background: #e74a3b; color: white; font-weight: bold;">
                ✗ LAMA XAADIRIN (<?php echo $absent_count; ?> ardayda)
            </div>
            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                <?php if ($absent_count > 0): ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px;">
                        <?php foreach ($students as $student): 
                            if ($student['period_1_status'] === 'absent'):
                        ?>
                        <div style="padding: 12px; background: #fef2f2; border: 2px solid #e74a3b; border-radius: 8px; text-align: center;">
                            <div style="width: 50px; height: 50px; margin: 0 auto 8px; border-radius: 50%; overflow: hidden; background: #ffe8e8; display: flex; align-items: center; justify-content: center;">
                                <img src="<?php echo htmlspecialchars($student['photo']); ?>" alt="<?php echo htmlspecialchars($student['full_name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <strong style="font-size: 0.85rem; color: #e74a3b; display: block;"><?php echo htmlspecialchars($student['full_name']); ?></strong>
                            <span style="font-size: 0.75rem; color: #666;">✗ Maqan</span>
                        </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 20px; color: #999;">
                        Ardayda lama xaadirin waxa jira.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Not Marked Students Section -->
        <div class="card shadow" style="border-top: 4px solid #cccccc;">
            <div class="card-header" style="background: #cccccc; color: #333; font-weight: bold;">
                ⊘ LAMA XISAABIN (<?php echo $not_marked_count; ?> ardayda)
            </div>
            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                <?php if ($not_marked_count > 0): ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px;">
                        <?php foreach ($students as $student): 
                            if ($student['period_1_status'] === null):
                        ?>
                        <div style="padding: 12px; background: #f5f5f5; border: 2px solid #cccccc; border-radius: 8px; text-align: center;">
                            <div style="width: 50px; height: 50px; margin: 0 auto 8px; border-radius: 50%; overflow: hidden; background: #e8e8e8; display: flex; align-items: center; justify-content: center;">
                                <img src="<?php echo htmlspecialchars($student['photo']); ?>" alt="<?php echo htmlspecialchars($student['full_name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <strong style="font-size: 0.85rem; color: #666; display: block;"><?php echo htmlspecialchars($student['full_name']); ?></strong>
                            <span style="font-size: 0.75rem; color: #999;">⊘ Lama xisaabin</span>
                        </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 20px; color: #999;">
                        Dhammaan ardayda waa la xisaabiyay.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php
            }
        } else {
            // Show all rooms overview
            foreach ($rooms as $room):
                // Get Period 1 statistics for this room
                $stats_query = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total_students,
                        SUM(CASE WHEN a.period_1_status = 'present' THEN 1 ELSE 0 END) as present_count,
                        SUM(CASE WHEN a.period_1_status = 'absent' THEN 1 ELSE 0 END) as absent_count
                    FROM students s
                    LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ?
                    WHERE s.room_id = ?
                ");
                $stats_query->execute([$today, $room['id']]);
                $stats = $stats_query->fetch();

                $total = $stats['total_students'];
                $present = $stats['present_count'] ?? 0;
                $absent = $stats['absent_count'] ?? 0;
                $not_marked = $total - $present - $absent;

                $present_percentage = $total > 0 ? round(($present / $total) * 100) : 0;
                $absent_percentage = $total > 0 ? round(($absent / $total) * 100) : 0;

                // Determine color based on attendance status
                $color = ($present_percentage == 100) ? '#1cc88a' : (($present_percentage == 0) ? '#e74a3b' : '#f6c23e');
        ?>

        <div class="card shadow" style="border-top: 4px solid #4e73df; transition: transform 0.2s; cursor: pointer;" onclick="window.location.href='?room_id=<?php echo $room['id']; ?>';" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
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
                <div style="margin-bottom: 15px; padding: 12px; background: #f8f9fc; border-radius: 8px; border-left: 4px solid <?php echo $color; ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 0.95rem; font-weight: 600;">📚 Xisada 1aad:</span>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="color: <?php echo $color; ?>; font-weight: bold; font-size: 1.1rem;"><?php echo $present_percentage; ?>%</span>
                            <div style="width: 18px; height: 18px; background-color: <?php echo $color; ?>; border-radius: 50%; box-shadow: 0 0 8px <?php echo $color; ?>;"></div>
                        </div>
                    </div>
                    <div style="font-size: 0.85rem; color: #5a5c69;">
                        <span style="color: #1cc88a; font-weight: bold;">✓ <?php echo $present; ?></span> | 
                        <span style="color: #e74a3b; font-weight: bold;">✗ <?php echo $absent; ?></span> | 
                        <span>⊘ <?php echo $not_marked; ?></span>
                    </div>
                </div>

                <!-- Action Button -->
                <div style="margin-top: 15px;">
                    <button class="btn btn-sm btn-info" style="width: 100%; cursor: pointer;">
                        Faah-faahinta Qolka →
                    </button>
                </div>
            </div>
        </div>

        <?php endforeach; ?>
        <?php } ?>
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
