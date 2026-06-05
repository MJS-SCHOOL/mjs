<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/teacher_page_access.php';
require_login();

// Fetch Exam Suspensions
$suspensions = $pdo->query("SELECT * FROM exam_suspensions ORDER BY created_at DESC")->fetchAll();

// Fetch settings for logo
$stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
$settings = $stmt->fetch();
if (!$settings) {
    $settings = ['site_name' => 'Attendance System', 'logo' => 'logo.png'];
}

$logo_path = "assets/img/logo.png"; // Default Fallback
if (!empty($settings['logo'])) {
    $saved_logo = "images/logo/" . $settings['logo'];
    if (file_exists($saved_logo)) {
        $logo_path = $saved_logo;
    }
}

// Convert logo to base64 for PDF embedding
$logo_data = base64_encode(file_get_contents($logo_path));
$logo_type = pathinfo($logo_path, PATHINFO_EXTENSION);
$logo_base64 = 'data:image/' . $logo_type . ';base64,' . $logo_data;
?>
<!DOCTYPE html>
<html lang="so">
<head>
    <meta charset="UTF-8">
    <title>Warbixinta Kajoojinta Imtixaanka</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body { font-family: 'Nunito', sans-serif; margin: 0; padding: 0; background: white; }
        #pdf-content { padding: 40px; position: relative; min-height: 1000px; }
        
        /* Watermark */
        .watermark {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg);
            opacity: 0.05; font-size: 80px; font-weight: 900; color: #000; pointer-events: none; z-index: 0;
            white-space: nowrap;
        }
        
        .header {
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 4px solid #4e73df; padding-bottom: 20px; margin-bottom: 30px;
            position: relative; z-index: 1;
        }
        .header-left { display: flex; align-items: center; }
        .logo { width: 100px; height: 100px; object-fit: contain; margin-right: 20px; }
        .header-info h1 { margin: 0; color: #4e73df; font-size: 28px; font-weight: 800; text-transform: uppercase; }
        .header-info p { margin: 5px 0 0 0; color: #5a5c69; font-size: 14px; font-weight: 600; }
        
        .header-right { text-align: right; }
        .badge { background: #4e73df; color: white; padding: 5px 15px; border-radius: 5px; font-weight: 800; font-size: 12px; display: inline-block; margin-bottom: 10px; }
        .date-info { font-size: 12px; color: #3a3b45; font-weight: 700; }

        .description {
            background: #f8f9fc; padding: 20px; border-radius: 10px; border-left: 5px solid #4e73df;
            margin-bottom: 30px; position: relative; z-index: 1;
        }
        .description h3 { margin: 0 0 10px 0; color: #2e59d9; font-size: 18px; }
        .description p { margin: 0; color: #5a5c69; font-size: 14px; line-height: 1.6; }

        .report-table { width: 100%; border-collapse: collapse; position: relative; z-index: 1; }
        .report-table th { background: #4e73df; color: white; padding: 12px 10px; text-align: left; font-size: 13px; text-transform: uppercase; border: 1px solid #2e59d9; }
        .report-table td { padding: 12px 10px; border: 1px solid #e3e6f0; font-size: 13px; color: #3a3b45; }
        .report-table tr:nth-child(even) { background: #fcfdfe; }
        .report-table .student-name { font-weight: 800; color: #2e59d9; }
        .report-table .badge-warning { background: #f6c23e; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }

        .footer {
            position: absolute; bottom: 40px; left: 40px; right: 40px;
            display: flex; justify-content: space-between; align-items: flex-end;
            border-top: 2px solid #e3e6f0; padding-top: 20px;
        }
        .footer-text { font-size: 11px; color: #858796; font-style: italic; }
        .signature-box { text-align: center; width: 200px; }
        .signature-line { border-top: 2px solid #3a3b45; margin-top: 50px; padding-top: 5px; font-weight: 800; font-size: 12px; }

        .no-print { position: fixed; top: 20px; right: 20px; z-index: 9999; }
        .btn-download { background: #e74a3b; color: white; border: none; padding: 12px 25px; border-radius: 30px; font-weight: 700; cursor: pointer; box-shadow: 0 5px 15px rgba(231, 74, 59, 0.4); }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-download" onclick="generatePDF()">
            <i class="fas fa-file-pdf"></i> SOO DEJI PDF-KA
        </button>
    </div>

    <div id="pdf-content">
        <div class="watermark"><?php echo $settings['site_name']; ?></div>
        
        <div class="header">
            <div class="header-left">
                <img src="<?= $logo_base64 ?>" class="logo">
                <div class="header-info">
                    <h1><?php echo $settings['site_name']; ?></h1>
                    <p>Official Exam Suspension Report</p>
                </div>
            </div>
            <div class="header-right">
                <div class="badge">MJS-2026</div>
                <div class="date-info">Date: <?= date('d/m/Y') ?></div>
                <div class="date-info">Time: <?= date('H:i A') ?></div>
            </div>
        </div>

        <div class="description">
            <h3>Warbixinta Ardayda Imtixaanka Laga Joojiyay</h3>
            <p>Warbixintan waxay xambaarsan tahay liiska ardayda laga joojiyay inay u siiwatan imtixaanka sababo la xiriira arrimo anshax ama qiyaano. halkan hoose waxaa ku xusan magaca fasalka iyo ujeedadda guud.</p>
        </div>

        <table class="report-table">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="25%">Magaca Ardayga</th>
                    <th width="15%">Fasalka</th>
                    <th width="15%">Roomka</th>
                    <th width="15%">Imtixaanka</th>
                    <th width="25%">Sababta</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suspensions as $index => $s): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td class="student-name"><?= htmlspecialchars($s['student_name']) ?></td>
                    <td><?= htmlspecialchars($s['class_name']) ?></td>
                    <td><?= htmlspecialchars($s['room_name']) ?></td>
                    <td><span class="badge-warning"><?= htmlspecialchars($s['exam_type']) ?></span></td>
                    <td><?= htmlspecialchars($s['reason']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="footer">
            <div class="footer-text">
                Generated by <?= htmlspecialchars($settings['site_name']) ?> System<br>
                © <?= date('Y') ?> All Rights Reserved.
            </div>
            <div class="signature-box">
                <div class="signature-line">Maamulaha Dugsiga</div>
            </div>
        </div>
    </div>

    <script>
        function generatePDF() {
            const element = document.getElementById('pdf-content');
            const opt = {
                margin: 10,
                filename: 'Exam_Suspension_Report_<?= date('Y-m-d_H-i-s') ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, logging: false },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            try {
                html2pdf()
                    .set(opt)
                    .from(element)
                    .save()
                    .then(() => {
                        // Delay before closing to ensure PDF is saved
                        setTimeout(() => {
                            window.close();
                        }, 1000);
                    })
                    .catch((error) => {
                        console.error('PDF generation error:', error);
                        alert('Error generating PDF. Please try again.');
                    });
            } catch (error) {
                console.error('PDF error:', error);
                alert('Error generating PDF. Please try again.');
            }
        }
        
        // Auto-trigger download when page loads
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                generatePDF();
            }, 500);
        });
    </script>
</body>
</html>
