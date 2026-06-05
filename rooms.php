<?php
require_once 'includes/header.php';
require_admin();

// 1. DATA FETCHING - Hubinta rasmiga ah ee Period 1 iyo Period 2
$today = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$rooms_query = $pdo->query("
    SELECT r.*, 
           -- Tirada guud ee ardayda qolka
           (SELECT COUNT(*) FROM students s WHERE s.room_id = r.id) as total_students,
           
           -- Hubinta haddii Period 1 ay jiraan ardayda leh 'present' ama 'absent'
           (SELECT COUNT(*) FROM attendance a 
            JOIN students s ON a.student_id = s.id 
            WHERE s.room_id = r.id AND a.date = '$today' 
            AND (a.period_1_status = 'present' OR a.period_1_status = 'absent') LIMIT 1) as p1_has_data,
           
           -- Hubinta haddii Period 2 ay jiraan ardayda leh 'present' ama 'absent'
           (SELECT COUNT(*) FROM attendance a 
            JOIN students s ON a.student_id = s.id 
            WHERE s.room_id = r.id AND a.date = '$today' 
            AND (a.period_2_status = 'present' OR a.period_2_status = 'absent') LIMIT 1) as p2_has_data,

           -- Period 1: Tirada ardayda 'present'
           (SELECT COUNT(*) FROM attendance a 
            JOIN students s ON a.student_id = s.id 
            WHERE s.room_id = r.id AND a.date = '$today' 
            AND a.period_1_status = 'present') as p1_present_students,
           
           -- Period 1: Tirada ardayda 'absent'
           (SELECT COUNT(*) FROM attendance a 
            JOIN students s ON a.student_id = s.id 
            WHERE s.room_id = r.id AND a.date = '$today' 
            AND a.period_1_status = 'absent') as p1_absent_students,
           
           -- Period 2: Tirada ardayda 'present'
           (SELECT COUNT(*) FROM attendance a 
            JOIN students s ON a.student_id = s.id 
            WHERE s.room_id = r.id AND a.date = '$today' 
            AND a.period_2_status = 'present') as p2_present_students,
           
           -- Period 2: Tirada ardayda 'absent'
           (SELECT COUNT(*) FROM attendance a 
            JOIN students s ON a.student_id = s.id 
            WHERE s.room_id = r.id AND a.date = '$today' 
            AND a.period_2_status = 'absent') as p2_absent_students,

           -- Tirada ardayda 'present' (labada period-ba jooga)
           (SELECT COUNT(*) FROM attendance a 
            JOIN students s ON a.student_id = s.id 
            WHERE s.room_id = r.id AND a.date = '$today' 
            AND a.period_1_status = 'present' AND a.period_2_status = 'present') as total_present_students,
           
           -- Tirada ardayda 'absent' (Maqan ugu yaraan hal period)
           (SELECT COUNT(*) FROM attendance a 
            JOIN students s ON a.student_id = s.id 
            WHERE s.room_id = r.id AND a.date = '$today' 
            AND (a.period_1_status = 'absent' OR a.period_2_status = 'absent')) as total_absent_students
    FROM rooms r
");
$rooms = $rooms_query->fetchAll();

// Calculate Global Totals for Period 1
$grand_total_present_p1 = 0;
$grand_total_absent_p1 = 0;

// Calculate Global Totals for Period 2
$grand_total_present_p2 = 0;
$grand_total_absent_p2 = 0;

foreach ($rooms as $room) {
    $grand_total_present_p1 += (int)$room['p1_present_students'];
    $grand_total_absent_p1 += (int)$room['p1_absent_students'];
    
    $grand_total_present_p2 += (int)$room['p2_present_students'];
    $grand_total_absent_p2 += (int)$room['p2_absent_students'];
}

$grand_total_all_p1 = $grand_total_present_p1 + $grand_total_absent_p1;
$grand_total_all_p2 = $grand_total_present_p2 + $grand_total_absent_p2;
?>

<style>
    /* Summary Cards Styles */
    .summary-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
        animation: fadeInUp 0.8s ease-out;
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .summary-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        border-bottom: 5px solid transparent;
        position: relative;
        overflow: hidden;
    }

    .summary-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    }

    .summary-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(45deg, rgba(255,255,255,0.1), rgba(255,255,255,0));
        pointer-events: none;
    }

    .summary-card.present { border-bottom-color: #1cc88a; }
    .summary-card.absent { border-bottom-color: #e74a3b; }
    .summary-card.total { border-bottom-color: #4e73df; }
    
    /* Period 2 specific colors */
    .summary-card.present-p2 { border-bottom-color: #00a86b; }
    .summary-card.absent-p2 { border-bottom-color: #c0392b; }
    .summary-card.total-p2 { border-bottom-color: #2980b9; }

    .summary-info h3 {
        margin: 0;
        font-size: 2.2rem;
        font-weight: 800;
        color: #2e3338;
    }

    .summary-info p {
        margin: 0;
        color: #858796;
        text-transform: uppercase;
        font-size: 0.85rem;
        font-weight: 700;
        letter-spacing: 1px;
    }

    .summary-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
    }

    .summary-card.present .summary-icon { background: rgba(28, 200, 138, 0.1); color: #1cc88a; }
    .summary-card.absent .summary-icon { background: rgba(231, 74, 59, 0.1); color: #e74a3b; }
    .summary-card.total .summary-icon { background: rgba(78, 115, 223, 0.1); color: #4e73df; }
    
    .summary-card.present-p2 .summary-icon { background: rgba(0, 168, 107, 0.1); color: #00a86b; }
    .summary-card.absent-p2 .summary-icon { background: rgba(192, 57, 43, 0.1); color: #c0392b; }
    .summary-card.total-p2 .summary-icon { background: rgba(41, 128, 185, 0.1); color: #2980b9; }

    /* Floating Animation for Icons */
    @keyframes float {
        0% { transform: translateY(0px); }
        50% { transform: translateY(-5px); }
        100% { transform: translateY(0px); }
    }

    .summary-icon i {
        animation: float 3s ease-in-out infinite;
    }

    /* Existing Styles */
    @keyframes pulse-glow {
        0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
        50% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }
    @keyframes blink {
        0%, 49% { opacity: 1; }
        50%, 100% { opacity: 0.4; }
    }
    
    @keyframes spin-refresh {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .refresh-icon {
        display: inline-block;
        width: 24px;
        height: 24px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .refresh-icon:hover {
        transform: scale(1.1);
    }
    
    .refresh-icon.spinning {
        animation: spin-refresh 1s linear infinite;
    }
    
    .refresh-btn {
        background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
        border: none;
        color: white;
        padding: 10px 16px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(78, 115, 223, 0.3);
    }
    
    .refresh-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(78, 115, 223, 0.4);
    }
    
    .live-badge {
        display: inline-flex; align-items: center; gap: 6px;
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        color: white; padding: 8px 16px; border-radius: 20px;
        font-weight: 700; font-size: 0.9rem; animation: pulse-glow 2s infinite;
    }
    .live-dot { width: 10px; height: 10px; background-color: #fff; border-radius: 50%; animation: blink 1s infinite; }
    .header-container { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; }
    .attendance-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fc 100%);
        border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease; cursor: pointer; border: 2px solid transparent;
        overflow: hidden; position: relative;
    }
    .attendance-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15); border-color: #4e73df; }
    .card-header-section { padding: 20px; border-bottom: 1px solid #e3e6f0; background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); }
    .card-header-section h5 { color: white; margin: 0; font-weight: 700; font-size: 1.2rem; display: flex; align-items: center; gap: 10px; }
    .card-body-section { padding: 20px; }
    .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px; }
    .stat-box { padding: 15px; border-radius: 10px; border-left: 4px solid #4e73df; transition: all 0.3s ease; }
    .stat-box.present { border-left-color: #1cc88a; background: #f0fdf4; }
    .stat-box.absent { border-left-color: #e74a3b; background: #fef2f2; }
    .stat-box.total { border-left-color: #36b9cc; background: #f0f9fb; }
    .stat-box.percentage { border-left-color: #f6c23e; background: #fffbeb; }
    .stat-label { font-size: 0.75rem; color: #5a5c69; font-weight: 600; text-transform: uppercase; margin-bottom: 5px; }
    .stat-value { font-size: 1.8rem; font-weight: 700; color: #2e3338; }
    .stat-value.present { color: #1cc88a; }
    .stat-value.absent { color: #e74a3b; }
    .stat-value.total { color: #36b9cc; }
    .stat-value.percentage { color: #f6c23e; }
    .period-status { background: #f8f9fc; padding: 15px; border-radius: 10px; margin-bottom: 15px; }
    .period-item { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #e3e6f0; }
    .period-item:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
    .period-status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
    .period-status-badge.completed { background: #d4edda; color: #155724; }
    .period-status-badge.not-started { background: #f8d7da; color: #721c24; }
    .progress-bar-custom { height: 10px; background: #e3e6f0; border-radius: 10px; overflow: hidden; }
    .progress-fill { height: 100%; background: linear-gradient(90deg, #4e73df 0%, #224abe 100%); transition: width 0.4s ease; }
    .cards-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; padding: 20px; }
    
    /* Popup Modal Styles */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9998;
        animation: fadeIn 0.2s ease-out;
        backdrop-filter: blur(4px);
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes popupBounce {
        0% {
            opacity: 0;
            transform: scale(0.3) translateY(-50px);
        }
        60% {
            transform: scale(1.08);
        }
        100% {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }
    
    @keyframes slideOut {
        0% {
            opacity: 1;
            transform: scale(1);
        }
        100% {
            opacity: 0;
            transform: scale(0.8) translateY(50px);
        }
    }
    
    .popup-modal {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 20px;
        padding: 40px;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        text-align: center;
        animation: popupBounce 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        position: relative;
        overflow: hidden;
    }
    
    .popup-modal::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
        background-size: 50px 50px;
        animation: moveBackground 20s linear infinite;
    }
    
    @keyframes moveBackground {
        0% { transform: translate(0, 0); }
        100% { transform: translate(50px, 50px); }
    }
    
    .popup-modal.closing {
        animation: slideOut 0.3s ease-in;
    }
    
    .popup-content {
        position: relative;
        z-index: 1;
    }
    
    .popup-icon {
        font-size: 4rem;
        margin-bottom: 20px;
        animation: float 1.5s ease-in-out infinite;
    }
    
    .popup-title {
        color: white;
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 15px;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }
    
    .popup-message {
        color: rgba(255, 255, 255, 0.95);
        font-size: 1.1rem;
        margin-bottom: 10px;
        font-weight: 500;
    }
    
    .popup-time {
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.9rem;
        margin-bottom: 30px;
    }
    
    .popup-button {
        background: white;
        color: #667eea;
        border: none;
        padding: 12px 30px;
        border-radius: 25px;
        font-weight: 700;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }
    
    .popup-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
    }
    
    .popup-button:active {
        transform: translateY(0);
    }
    
    .pulse-dot {
        display: inline-block;
        width: 12px;
        height: 12px;
        background: #1cc88a;
        border-radius: 50%;
        margin-right: 8px;
        animation: pulse 1.5s ease-in-out infinite;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.3); opacity: 0.7; }
    }
</style>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div class="header-container">
            <h1 class="h3 mb-0 text-gray-800">Maareynta Qolalka</h1>
            <span class="live-badge"><span class="live-dot"></span> <?php echo ($today == date('Y-m-d')) ? 'LIVE' : 'HISTORY'; ?></span>
        </div>
        <div class="date-filter" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <button class="refresh-btn" id="refreshBtn" onclick="refreshPage()">
                <svg class="refresh-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M23 4v6h-6M1 20v-6h6M3.51 9a9 9 0 0 1 14.85-3.36M20.49 15a9 9 0 0 1-14.85 3.36"/>
                </svg>
                <span>Refresh</span>
            </button>
            <form method="GET" style="margin: 0;">
                <input type="date" name="date" value="<?php echo $today; ?>" class="form-control" onchange="this.form.submit()">
            </form>
        </div>
    </div>

    <!-- Summary Cards Section - Period 1 -->
    <div style="font-weight: 800; color: #4e73df; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 1px;">📊 Tirakoobka Period 1</div>
    <div class="summary-container">
        <div class="summary-card present">
            <div class="summary-info">
                <p>Wadarta Joogta (P1)</p>
                <h3><?php echo number_format($grand_total_present_p1); ?></h3>
            </div>
            <div class="summary-icon">
                <i class="fas fa-user-check"></i>
            </div>
        </div>
        <div class="summary-card absent">
            <div class="summary-info">
                <p>Wadarta Maqan (P1)</p>
                <h3><?php echo number_format($grand_total_absent_p1); ?></h3>
            </div>
            <div class="summary-icon">
                <i class="fas fa-user-times"></i>
            </div>
        </div>
        <div class="summary-card total">
            <div class="summary-info">
                <p>Wadarta Guud (P1)</p>
                <h3><?php echo number_format($grand_total_all_p1); ?></h3>
            </div>
            <div class="summary-icon">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>

    <!-- Summary Cards Section - Period 2 -->
    <div style="font-weight: 800; color: #1cc88a; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 1px;">📊 Tirakoobka Period 2</div>
    <div class="summary-container">
        <div class="summary-card present-p2">
            <div class="summary-info">
                <p>Wadarta Joogta (P2)</p>
                <h3><?php echo number_format($grand_total_present_p2); ?></h3>
            </div>
            <div class="summary-icon">
                <i class="fas fa-user-check"></i>
            </div>
        </div>
        <div class="summary-card absent-p2">
            <div class="summary-info">
                <p>Wadarta Maqan (P2)</p>
                <h3><?php echo number_format($grand_total_absent_p2); ?></h3>
            </div>
            <div class="summary-icon">
                <i class="fas fa-user-times"></i>
            </div>
        </div>
        <div class="summary-card total-p2">
            <div class="summary-info">
                <p>Wadarta Guud (P2)</p>
                <h3><?php echo number_format($grand_total_all_p2); ?></h3>
            </div>
            <div class="summary-icon">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>

    <div class="cards-container">
        <?php $room_number = 1; foreach ($rooms as $room): 
            // Period 1 Statistics
            $p1_present = (int)$room['p1_present_students'];
            $p1_absent = (int)$room['p1_absent_students'];
            $p1_total = $p1_present + $p1_absent;
            $p1_percentage = ($p1_total > 0) ? round(($p1_present / $p1_total) * 100) : 0;
            
            // Period 2 Statistics
            $p2_present = (int)$room['p2_present_students'];
            $p2_absent = (int)$room['p2_absent_students'];
            $p2_total = $p2_present + $p2_absent;
            $p2_percentage = ($p2_total > 0) ? round(($p2_present / $p2_total) * 100) : 0;
            
            // Status Logic
            $p1_has_data = ($room['p1_has_data'] > 0);
            $p2_has_data = ($room['p2_has_data'] > 0);
            $attendance_recorded = ($p1_has_data || $p2_has_data);
        ?>
        
        <div class="attendance-card" onclick="location.href='room_detail.php?id=<?php echo $room['id']; ?>&date=<?php echo $today; ?>'">
            <div class="card-header-section">
                <h5>🏫 ROOM<?php echo $room_number; ?></h5>
            </div>
            <div class="card-body-section">
                <div class="period-status">
                    <div class="period-item">
                        <span class="period-label">📍 Period 1</span>
                        <span class="period-status-badge <?php echo $p1_has_data ? 'completed' : 'not-started'; ?>">
                            <?php echo $p1_has_data ? '✓ La Xaadiriyay' : '✗ Lama Xaadirin'; ?>
                        </span>
                    </div>
                    <div class="period-item">
                        <span class="period-label">📍 Period 2</span>
                        <span class="period-status-badge <?php echo $p2_has_data ? 'completed' : 'not-started'; ?>">
                            <?php echo $p2_has_data ? '✓ La Xaadiriyay' : '✗ Lama Xaadirin'; ?>
                        </span>
                    </div>
                </div>
                
                <!-- Period 1 Statistics -->
                <div style="margin-bottom: 20px; padding: 15px; background: #e7f3ff; border-radius: 10px; border-left: 4px solid #4e73df;">
                    <div style="font-weight: 700; color: #4e73df; margin-bottom: 10px;">📍 Period 1 - Xaadir iyo Maqan</div>
                    <div class="stats-grid">
                        <div class="stat-box present">
                            <div class="stat-label">Joogista</div>
                            <div class="stat-value present"><?php echo $p1_present; ?></div>
                        </div>
                        <div class="stat-box absent">
                            <div class="stat-label">Maqnaasho</div>
                            <div class="stat-value absent"><?php echo $p1_absent; ?></div>
                        </div>
                        <div class="stat-box total">
                            <div class="stat-label">Wadar</div>
                            <div class="stat-value total"><?php echo $p1_total; ?></div>
                        </div>
                        <div class="stat-box percentage">
                            <div class="stat-label">Boqolleyda</div>
                            <div class="stat-value percentage"><?php echo $p1_percentage; ?>%</div>
                        </div>
                    </div>
                    <div class="progress-bar-custom">
                        <div class="progress-fill" style="width: <?php echo $p1_percentage; ?>%;"></div>
                    </div>
                </div>
                
                <!-- Period 2 Statistics (if available) -->
                <?php if ($p2_has_data): ?>
                <div style="margin-bottom: 20px; padding: 15px; background: #f0fdf4; border-radius: 10px; border-left: 4px solid #1cc88a;">
                    <div style="font-weight: 700; color: #1cc88a; margin-bottom: 10px;">📍 Period 2 - Xaadir iyo Maqan</div>
                    <div class="stats-grid">
                        <div class="stat-box present">
                            <div class="stat-label">Joogista</div>
                            <div class="stat-value present"><?php echo $p2_present; ?></div>
                        </div>
                        <div class="stat-box absent">
                            <div class="stat-label">Maqnaasho</div>
                            <div class="stat-value absent"><?php echo $p2_absent; ?></div>
                        </div>
                        <div class="stat-box total">
                            <div class="stat-label">Wadar</div>
                            <div class="stat-value total"><?php echo $p2_total; ?></div>
                        </div>
                        <div class="stat-box percentage">
                            <div class="stat-label">Boqolleyda</div>
                            <div class="stat-value percentage"><?php echo $p2_percentage; ?>%</div>
                        </div>
                    </div>
                    <div class="progress-bar-custom">
                        <div class="progress-fill" style="width: <?php echo $p2_percentage; ?>%;"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php $room_number++; endforeach; ?>
    </div>
</div>

<!-- Global Monitoring Script - Runs on ALL pages -->
<script>
    // ============================================
    // GLOBAL BACKGROUND MONITORING SYSTEM
    // Runs on every page and monitors attendance updates
    // ============================================
    
    window.globalMonitoringActive = true;
    
    // Ultra-Fast & Beautiful Sound Notification
    function playNotificationSound() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const now = audioContext.currentTime;
            
            // Create oscillators for melodic sound
            const osc1 = audioContext.createOscillator();
            const osc2 = audioContext.createOscillator();
            const osc3 = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            const gainNode2 = audioContext.createGain();
            const gainNode3 = audioContext.createGain();
            
            osc1.connect(gainNode);
            osc2.connect(gainNode2);
            osc3.connect(gainNode3);
            gainNode.connect(audioContext.destination);
            gainNode2.connect(audioContext.destination);
            gainNode3.connect(audioContext.destination);
            
            // First note - E5 (659.25 Hz)
            osc1.frequency.setValueAtTime(659.25, now);
            gainNode.gain.setValueAtTime(0.25, now);
            gainNode.gain.exponentialRampToValueAtTime(0.01, now + 0.1);
            osc1.start(now);
            osc1.stop(now + 0.1);
            
            // Second note - G5 (783.99 Hz)
            osc2.frequency.setValueAtTime(783.99, now + 0.05);
            gainNode2.gain.setValueAtTime(0.25, now + 0.05);
            gainNode2.gain.exponentialRampToValueAtTime(0.01, now + 0.15);
            osc2.start(now + 0.05);
            osc2.stop(now + 0.15);
            
            // Third note - C6 (1046.50 Hz)
            osc3.frequency.setValueAtTime(1046.50, now + 0.1);
            gainNode3.gain.setValueAtTime(0.25, now + 0.1);
            gainNode3.gain.exponentialRampToValueAtTime(0.01, now + 0.2);
            osc3.start(now + 0.1);
            osc3.stop(now + 0.2);
        } catch (e) {
            console.log('Audio context not available');
        }
    }
    
    // Show Beautiful Popup
    function showUpdatePopup() {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        
        const now = new Date();
        const timeString = now.toLocaleTimeString('so-SO', { 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit' 
        });
        
        overlay.innerHTML = `
            <div class="popup-modal">
                <div class="popup-content">
                    <div class="popup-icon">✨</div>
                    <div class="popup-title">Xog Cusub!</div>
                    <div class="popup-message">
                        <span class="pulse-dot"></span>
                        Xogta wax cusub ayaa soo galay
                    </div>
                    <div class="popup-time">⏰ ${timeString}</div>
                    <button class="popup-button" onclick="closePopupAndScroll()">
                        Okey
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(overlay);
        
        // Play sound IMMEDIATELY
        playNotificationSound();
        
        // Close on overlay click
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                closePopupAndScroll();
            }
        });
    }
    
    // Close popup and scroll to new content
    function closePopupAndScroll() {
        const overlay = document.querySelector('.modal-overlay');
        if (overlay) {
            const modal = overlay.querySelector('.popup-modal');
            if (modal) {
                modal.classList.add('closing');
                setTimeout(() => {
                    overlay.remove();
                    // Scroll to top to see the updated content
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }, 300);
            }
        }
    }
    
    // Store last content hash to detect changes
    let lastGlobalHash = null;
    
    function getGlobalHash() {
        const container = document.querySelector('.cards-container');
        const summaries = document.querySelectorAll('.summary-container');
        if (container && summaries.length > 0) {
            let summaryHTML = "";
            summaries.forEach(s => summaryHTML += s.innerHTML);
            return container.innerHTML + summaryHTML;
        }
        return null;
    }
    
    // Real-time Auto-refresh - Every 3 seconds (BACKGROUND MONITORING)
    let globalAutoRefreshInterval;
    
    function startGlobalAutoRefresh() {
        globalAutoRefreshInterval = setInterval(() => {
            if (!window.globalMonitoringActive) return;
            
            fetch(window.location.href, { cache: 'no-store' })
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const newDoc = parser.parseFromString(html, 'text/html');
                    const newContent = newDoc.querySelector('.cards-container');
                    const currentContent = document.querySelector('.cards-container');
                    
                    if (newContent && currentContent) {
                        const newSummaries = newDoc.querySelectorAll('.summary-container');
                        let newSummaryHTML = "";
                        newSummaries.forEach(s => newSummaryHTML += s.innerHTML);
                        
                        const newHash = newContent.innerHTML + newSummaryHTML;
                        
                        // Check if content has changed
                        if (newHash !== lastGlobalHash) {
                            lastGlobalHash = newHash;
                            
                            // Update content with animation
                            currentContent.innerHTML = newContent.innerHTML;
                            
                            // Update summary containers
                            const currentSummaries = document.querySelectorAll('.summary-container');
                            if (newSummaries.length === currentSummaries.length) {
                                newSummaries.forEach((ns, index) => {
                                    currentSummaries[index].innerHTML = ns.innerHTML;
                                });
                            }
                            
                            // Show popup and play sound INSTANTLY
                            showUpdatePopup();
                        }
                    }
                })
                .catch(error => console.error('Global auto-refresh error:', error));
        }, 3000); // Check every 3 seconds for REAL-TIME updates
    }
    
    function stopGlobalAutoRefresh() {
        window.globalMonitoringActive = false;
        if (globalAutoRefreshInterval) {
            clearInterval(globalAutoRefreshInterval);
        }
    }
    
    // Start global monitoring on page load
    document.addEventListener('DOMContentLoaded', () => {
        lastGlobalHash = getGlobalHash();
        startGlobalAutoRefresh();
    });
    
    // Keep monitoring even when page is in background
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            // Page is hidden but monitoring continues
            console.log('Page is in background - monitoring continues');
        } else {
            // Page is visible again
            console.log('Page is visible again');
        }
    });
    
    // Stop monitoring when user leaves page
    window.addEventListener('beforeunload', () => {
        stopGlobalAutoRefresh();
    });
    
    function refreshPage() {
        const refreshBtn = document.getElementById('refreshBtn');
        const refreshIcon = refreshBtn.querySelector('.refresh-icon');
        
        // Add spinning animation
        refreshIcon.classList.add('spinning');
        refreshBtn.disabled = true;
        
        // Fetch and update content
        fetch(window.location.href, { cache: 'no-store' })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const newDoc = parser.parseFromString(html, 'text/html');
                const newContent = newDoc.querySelector('.cards-container');
                const currentContent = document.querySelector('.cards-container');
                
                if (newContent && currentContent) {
                    currentContent.innerHTML = newContent.innerHTML;
                    
                    // Update summaries
                    const newSummaries = newDoc.querySelectorAll('.summary-container');
                    const currentSummaries = document.querySelectorAll('.summary-container');
                    if (newSummaries.length === currentSummaries.length) {
                        newSummaries.forEach((ns, index) => {
                            currentSummaries[index].innerHTML = ns.innerHTML;
                        });
                    }
                }
                
                // Stop spinning after a short delay
                setTimeout(() => {
                    refreshIcon.classList.remove('spinning');
                    refreshBtn.disabled = false;
                }, 500);
            })
            .catch(error => {
                console.error('Refresh error:', error);
                refreshIcon.classList.remove('spinning');
                refreshBtn.disabled = false;
            });
    }
</script>

<?php require_once 'includes/footer.php'; ?>
