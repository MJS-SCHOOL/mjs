<?php
require_once 'includes/header.php';
require_admin();

// Get search query if provided
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch all rooms with student count per class
$rooms_query = $pdo->query("
    SELECT r.id, r.room_name, r.capacity,
           COUNT(DISTINCT s.id) as total_students
    FROM rooms r
    LEFT JOIN students s ON s.room_id = r.id
    GROUP BY r.id, r.room_name, r.capacity
    ORDER BY r.room_name
");
$all_rooms = $rooms_query->fetchAll();

// Filter rooms based on search query
$rooms = $all_rooms;
if (!empty($search_query)) {
    $rooms = array_filter($all_rooms, function($room) use ($search_query) {
        return stripos($room['room_name'], $search_query) !== false;
    });
}

// For each room, get class breakdown
$room_classes = [];
foreach ($all_rooms as $room) {
    $class_query = $pdo->prepare("
        SELECT c.id, c.class_name, COUNT(s.id) as student_count
        FROM classes c
        LEFT JOIN students s ON s.class_id = c.id AND s.room_id = ?
        GROUP BY c.id, c.class_name
        ORDER BY c.class_name
    ");
    $class_query->execute([$room['id']]);
    $room_classes[$room['id']] = $class_query->fetchAll();
}
?>

<style>
    * {
        box-sizing: border-box;
    }

    /* Mobile-First Base Styles */
    .search-container {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 20px;
    }

    .search-input-group {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .search-input {
        flex: 1;
        min-width: 200px;
        padding: 12px 14px;
        border: 2px solid #e3e6f0;
        border-radius: 8px;
        font-size: 1rem;
        transition: all 0.3s ease;
        font-family: inherit;
    }

    .search-input:focus {
        outline: none;
        border-color: #4e73df;
        box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.1);
    }

    .btn-group {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-search, .btn-pdf, .btn-clear {
        padding: 12px 16px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
        font-size: 0.95rem;
        flex: 1;
        min-width: 120px;
    }

    .btn-search {
        background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
        color: white;
        box-shadow: 0 2px 8px rgba(78, 115, 223, 0.3);
    }

    .btn-search:active {
        transform: scale(0.98);
    }

    .btn-pdf {
        background: linear-gradient(135deg, #e74a3b 0%, #c82333 100%);
        color: white;
        box-shadow: 0 2px 8px rgba(231, 74, 59, 0.3);
    }

    .btn-pdf:active {
        transform: scale(0.98);
    }

    .btn-clear {
        background: #6c757d;
        color: white;
    }

    .btn-clear:active {
        transform: scale(0.98);
    }

    /* Room Cards - Mobile Optimized */
    .rooms-container {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .room-card {
        background: white;
        border-radius: 12px;
        padding: 0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        border-left: 5px solid #4e73df;
        overflow: hidden;
    }

    .room-card:active {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    }

    /* Room Header - Clickable */
    .room-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px;
        cursor: pointer;
        user-select: none;
        background: linear-gradient(135deg, #f8f9fc 0%, #ffffff 100%);
        transition: all 0.3s ease;
    }

    .room-header:active {
        background: linear-gradient(135deg, #eef2f7 0%, #f5f7fa 100%);
    }

    .room-info {
        display: flex;
        flex-direction: column;
        gap: 6px;
        flex: 1;
    }

    .room-name {
        font-size: 1.1rem;
        font-weight: 700;
        color: #2e3338;
    }

    .room-meta {
        font-size: 0.85rem;
        color: #5a5c69;
        display: flex;
        gap: 12px;
    }

    .total-badge {
        background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
        color: white;
        padding: 8px 12px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.9rem;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .expand-icon {
        font-size: 1.2rem;
        color: #4e73df;
        transition: transform 0.3s ease;
        margin-left: 12px;
    }

    .room-card.expanded .expand-icon {
        transform: rotate(180deg);
    }

    /* Class Details - Collapsible */
    .room-details {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
        background: #f8f9fc;
    }

    .room-card.expanded .room-details {
        max-height: 2000px;
    }

    .class-list {
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .class-item {
        background: white;
        padding: 12px 14px;
        border-radius: 8px;
        border-left: 4px solid #4e73df;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.2s ease;
    }

    .class-item:active {
        background: #f0f4ff;
    }

    .class-name {
        font-size: 0.95rem;
        font-weight: 600;
        color: #2e3338;
        flex: 1;
    }

    .class-count {
        background: #e7efff;
        color: #4e73df;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.9rem;
        min-width: 50px;
        text-align: center;
    }

    .no-students {
        color: #858796;
        font-style: italic;
        padding: 16px;
        text-align: center;
        background: white;
        border-radius: 8px;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        background: #f8f9fc;
        border-radius: 12px;
        border: 2px dashed #e3e6f0;
    }

    .empty-state-icon {
        font-size: 3rem;
        margin-bottom: 15px;
    }

    .empty-state-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: #2e3338;
        margin-bottom: 10px;
    }

    .empty-state-text {
        color: #5a5c69;
        font-size: 0.95rem;
    }

    /* Alert */
    .alert-info {
        background: #d1ecf1;
        border: 1px solid #bee5eb;
        color: #0c5460;
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 16px;
        font-size: 0.95rem;
    }

    /* PDF Content - Hidden */
    #pdf-content {
        display: none;
    }

    .pdf-header {
        text-align: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 3px solid #4e73df;
    }

    .pdf-header h1 {
        margin: 0;
        color: #4e73df;
        font-size: 2rem;
    }

    .pdf-header p {
        margin: 5px 0 0 0;
        color: #5a5c69;
        font-size: 0.95rem;
    }

    .pdf-room-section {
        margin-bottom: 30px;
        page-break-inside: avoid;
    }

    .pdf-room-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #2e3338;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #4e73df;
    }

    .pdf-class-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }

    .pdf-class-table th {
        background: #4e73df;
        color: white;
        padding: 12px;
        text-align: left;
        font-weight: 700;
        border: 1px solid #2e59d9;
    }

    .pdf-class-table td {
        padding: 12px;
        border: 1px solid #e3e6f0;
        font-size: 0.95rem;
    }

    .pdf-class-table tr:nth-child(even) {
        background: #f8f9fc;
    }

    .pdf-total-row {
        background: #e7efff;
        font-weight: 700;
    }

    /* Tablet and Desktop */
    @media (min-width: 768px) {
        .search-container {
            flex-direction: row;
            align-items: center;
            gap: 12px;
        }

        .search-input {
            min-width: 300px;
        }

        .btn-group {
            gap: 12px;
        }

        .btn-search, .btn-pdf, .btn-clear {
            min-width: auto;
            flex: 0 1 auto;
        }

        .rooms-container {
            gap: 16px;
        }

        .room-card {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .room-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .room-header:hover {
            background: linear-gradient(135deg, #eef2f7 0%, #f5f7fa 100%);
        }
    }

    /* Desktop */
    @media (min-width: 1024px) {
        .search-container {
            margin-bottom: 30px;
        }

        .rooms-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
        }
    }

    /* Print Styles */
    @media print {
        .search-container, .btn-pdf, .btn-search, .btn-clear {
            display: none !important;
        }
        
        .room-card {
            page-break-inside: avoid;
            box-shadow: none;
            border: 1px solid #e3e6f0;
        }
    }
</style>

<div class="container-fluid" style="padding: 16px;">
    <!-- Header -->
    <div style="margin-bottom: 20px;">
        <h1 style="font-size: 1.5rem; font-weight: 700; color: #2e3338; margin: 0 0 8px 0;">📋 Xogta Ardayda Qolalka</h1>
        <p style="color: #5a5c69; margin: 0; font-size: 0.9rem;">Riix qolka si aad u arko fasalladooda ardayda</p>
    </div>

    <!-- Search Bar -->
    <div class="search-container">
        <input 
            type="text" 
            class="search-input" 
            id="searchInput" 
            placeholder="Raadi qolka..." 
            value="<?php echo htmlspecialchars($search_query); ?>"
        >
        <div class="btn-group">
            <button class="btn-search" onclick="searchRooms()">
                <i class="fas fa-search"></i> Raadi
            </button>
            <button class="btn-pdf" onclick="generatePDF()">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <?php if (!empty($search_query)): ?>
            <button class="btn-clear" onclick="clearSearch()">
                <i class="fas fa-times"></i>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Results Info -->
    <?php if (!empty($search_query)): ?>
    <div class="alert-info">
        <i class="fas fa-info-circle"></i> 
        <strong><?php echo count($rooms); ?></strong> qolo oo la helay
    </div>
    <?php endif; ?>

    <!-- Rooms List -->
    <div class="rooms-container">
        <?php if (empty($rooms)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <div class="empty-state-title">Waxba lama helin</div>
                <div class="empty-state-text">
                    <?php if (!empty($search_query)): ?>
                        Qolal la raadiyay lama helin.
                    <?php else: ?>
                        Qolal lama helin.
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($rooms as $index => $room): ?>
            <div class="room-card" id="room-<?php echo $room['id']; ?>" onclick="toggleRoom(<?php echo $room['id']; ?>)">
                <div class="room-header">
                    <div class="room-info">
                        <div class="room-name"><?php echo htmlspecialchars($room['room_name']); ?></div>
                        <div class="room-meta">
                            <span><i class="fas fa-users"></i> <?php echo $room['total_students']; ?> ardayda</span>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div class="total-badge">
                            <?php echo $room['total_students']; ?>
                        </div>
                        <div class="expand-icon">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                </div>

                <!-- Class Details - Expandable -->
                <div class="room-details">
                    <?php if (!empty($room_classes[$room['id']])): ?>
                        <div class="class-list">
                            <?php 
                            $has_students = false;
                            foreach ($room_classes[$room['id']] as $class): 
                                if ($class['student_count'] > 0):
                                    $has_students = true;
                            ?>
                            <div class="class-item">
                                <div class="class-name"><?php echo htmlspecialchars($class['class_name']); ?></div>
                                <div class="class-count"><?php echo $class['student_count']; ?></div>
                            </div>
                            <?php 
                                endif;
                            endforeach; 
                            if (!$has_students):
                            ?>
                            <div class="no-students">
                                <i class="fas fa-inbox"></i> Ardayda lama helin
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-students">
                            <i class="fas fa-inbox"></i> Ardayda lama helin
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Hidden PDF Content -->
<div id="pdf-content">
    <div class="pdf-header">
        <h1><?php echo htmlspecialchars($settings['site_name'] ?? 'School'); ?></h1>
        <p>Xogta Ardayda Qolalka</p>
        <p style="font-size: 0.85rem; color: #858796; margin-top: 10px;">
            Tarikh: <?php echo date('d/m/Y H:i'); ?>
        </p>
    </div>

    <?php foreach ($rooms as $room): ?>
    <div class="pdf-room-section">
        <div class="pdf-room-title">
            <?php echo htmlspecialchars($room['room_name']); ?> 
            <span style="float: right; font-size: 0.9rem; color: #4e73df;">
                Guud: <?php echo $room['total_students']; ?> ardayda
            </span>
        </div>

        <table class="pdf-class-table">
            <thead>
                <tr>
                    <th width="70%">Fasalka</th>
                    <th width="30%" style="text-align: center;">Tirada Ardayda</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $room_total = 0;
                foreach ($room_classes[$room['id']] as $class): 
                    if ($class['student_count'] > 0):
                        $room_total += $class['student_count'];
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                    <td style="text-align: center; font-weight: 600;"><?php echo $class['student_count']; ?></td>
                </tr>
                <?php 
                    endif;
                endforeach; 
                ?>
                <tr class="pdf-total-row">
                    <td>Jumla</td>
                    <td style="text-align: center;"><?php echo $room_total; ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    function toggleRoom(roomId) {
        const card = document.getElementById('room-' + roomId);
        card.classList.toggle('expanded');
    }

    function searchRooms() {
        const searchValue = document.getElementById('searchInput').value;
        if (searchValue.trim() === '') {
            window.location.href = 'room_summary.php';
        } else {
            window.location.href = 'room_summary.php?search=' + encodeURIComponent(searchValue);
        }
    }

    function clearSearch() {
        window.location.href = 'room_summary.php';
    }

    function generatePDF() {
        const element = document.getElementById('pdf-content');
        const opt = {
            margin: 15,
            filename: 'Room_Summary_<?php echo date('Y-m-d_H-i-s'); ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, logging: false },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };

        try {
            html2pdf()
                .set(opt)
                .from(element)
                .save()
                .catch((error) => {
                    console.error('PDF generation error:', error);
                    alert('Error generating PDF. Please try again.');
                });
        } catch (error) {
            console.error('PDF error:', error);
            alert('Error generating PDF. Please try again.');
        }
    }

    // Allow Enter key to search
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchRooms();
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>
