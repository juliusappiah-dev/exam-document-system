<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../libs/qr_helper.php'; // Include the QR helper library

$pageTitle = "Generate QR Code";

// Get exam centers and subjects for dropdowns
$centers = $pdo->query("SELECT id, name FROM exam_centers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$subjects = $pdo->query("SELECT id, name FROM subjects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Logo path - ensure this file exists
$logoPath = __DIR__ . '/../assets/logo.jpg';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $examCenterId = $_POST['exam_center'] ?? null;
    $subjectId = $_POST['subject'] ?? null;
    $batchCode = trim($_POST['batch_code'] ?? '');
    $expiryDate = $_POST['expiry_date'] ?? null;
    $quantity = min(max(1, intval($_POST['quantity'] ?? 1)), 100);

    // Validate inputs
    if (!$examCenterId || !$subjectId) {
        $error = "Please select both exam center and subject";
    } else {
        try {
            $generatedCodes = [];
            $pdo->beginTransaction();
            
            for ($i = 0; $i < $quantity; $i++) {
                $serialNumber = 'DOC-' . strtoupper(bin2hex(random_bytes(4)));
                
                // Handle batch creation/lookup
                $batchId = null;
                if (!empty($batchCode)) {
                    $stmt = $pdo->prepare("
                        SELECT id FROM batches 
                        WHERE batch_code = ? AND subject_id = ?
                        LIMIT 1
                    ");
                    $stmt->execute([$batchCode, $subjectId]);
                    $existingBatch = $stmt->fetch();
                     
                    if ($existingBatch) {
                        $batchId = $existingBatch['id'];
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO batches (batch_code, subject_id, exam_date)
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([
                            $batchCode, 
                            $subjectId,
                            $expiryDate ?: date('Y-m-d')
                        ]);
                        $batchId = $pdo->lastInsertId();
                    }
                }
                
                // Get center and subject codes
                $centerCode = array_values(array_filter($centers, fn($c) => $c['id'] == $examCenterId))[0]['name'];
                $subjectCode = array_values(array_filter($subjects, fn($s) => $s['id'] == $subjectId))[0]['name'];
                
                // Prepare QR data
                $qrData = [
                    'serial' => $serialNumber,
                    'center_code' => $centerCode,
                    'subject_code' => $subjectCode,
                    'batch_code' => $batchCode ?: null,
                    'timestamp' => date('c')
                ];
                
                // Generate QR code with logo if logo exists
                if (file_exists($logoPath)) {
                    $qrImage = generateQRCodeWithLogo(json_encode($qrData), null, $logoPath);
                } else {
                    $qrImage = generateQRCode(json_encode($qrData));
                }
                
                // Save to database
                $stmt = $pdo->prepare("
                    INSERT INTO documents 
                    (serial_number, qr_data, exam_center_id, batch_id, created_by) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $serialNumber,
                    json_encode($qrData),
                    $examCenterId,
                    $batchId,
                    $_SESSION['user_id']
                ]);
                
                $documentId = $pdo->lastInsertId();
                
                // Get names for display
                $centerName = $centerCode;
                $subjectName = $subjectCode;
                
                $generatedCodes[] = [
                    'id' => $documentId,
                    'serial' => $serialNumber,
                    'qr_image' => $qrImage,
                    'center' => $centerName,
                    'subject' => $subjectName,
                    'batch' => $batchCode
                ];
            }
            
            $pdo->commit();
            $success = "Successfully generated $quantity QR code(s)";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error generating QR codes: " . $e->getMessage();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "System error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .qr-generator-card {
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: none;
            overflow: hidden;
        }
        
        .qr-preview-container {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 300px;
            position: relative;
        }
        
        .qr-placeholder {
            text-align: center;
            color: #6c757d;
        }
        
        .qr-placeholder i {
            font-size: 5rem;
            opacity: 0.2;
            margin-bottom: 1rem;
        }
        
        .generated-codes {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .code-item {
            transition: all 0.2s;
        }
        
        .code-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .batch-badge {
            padding: 0.25em 0.5em;
            border-radius: 4px;
            font-size: 0.75em;
        }
        
        @media (max-width: 992px) {
            .qr-preview-container {
                min-height: 250px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">
                    <i class="bi bi-qr-code me-2"></i>Generate QR Codes
                </h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="documents.php">Documents</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Generate QR</li>
                    </ol>
                </nav>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="row g-4">
            <!-- Generation Form -->
            <div class="col-lg-6">
                <div class="qr-generator-card card">
                    <div class="card-header">
                        <h5 class="mb-0">QR Code Generator</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php elseif (isset($success)): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="exam_center" class="form-label">Exam Center*</label>
                                <select class="form-select form-control-lg" id="exam_center" name="exam_center" required>
                                    <option value="">Select Exam Center</option>
                                    <?php foreach ($centers as $center): ?>
                                        <option value="<?= $center['id'] ?>" <?= isset($_POST['exam_center']) && $_POST['exam_center'] == $center['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($center['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject*</label>
                                <select class="form-select form-control-lg" id="subject" name="subject" required>
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?= $subject['id'] ?>" <?= isset($_POST['subject']) && $_POST['subject'] == $subject['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($subject['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label for="batch_code" class="form-label">Batch Code</label>
                                    <input type="text" class="form-control form-control-lg" id="batch_code" 
                                           name="batch_code" placeholder="e.g. BATCH-2023" 
                                           value="<?= htmlspecialchars($_POST['batch_code'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="expiry_date" class="form-label">Expiry Date</label>
                                    <input type="text" class="form-control form-control-lg datepicker" 
                                           id="expiry_date" name="expiry_date" 
                                           placeholder="Select date" 
                                           value="<?= htmlspecialchars($_POST['expiry_date'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="quantity" class="form-label">Quantity</label>
                                <input type="number" class="form-control form-control-lg" id="quantity" 
                                       name="quantity" min="1" max="100" value="<?= htmlspecialchars($_POST['quantity'] ?? 1) ?>">
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-qr-code me-2"></i>Generate QR Codes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Preview Panel -->
            <div class="col-lg-6">
                <div class="qr-generator-card card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">QR Code Preview</h5>
                        <?php if (isset($generatedCodes) && !empty($generatedCodes)): ?>
                            <button class="btn btn-sm btn-outline-primary" id="downloadAll">
                                <i class="bi bi-download me-1"></i>Download All
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <div class="qr-preview-container">
                            <?php if (isset($generatedCodes) && !empty($generatedCodes)): ?>
                                <div class="qr-preview">
                                    <img src="<?= htmlspecialchars($generatedCodes[0]['qr_image']) ?>" 
                                         alt="Generated QR Code" id="mainQrPreview" class="img-fluid">
                                </div>
                                <h5 class="mb-1"><?= htmlspecialchars($generatedCodes[0]['serial']) ?></h5>
                                <p class="text-muted mb-1">
                                    <?= htmlspecialchars($generatedCodes[0]['center']) ?>
                                </p>
                                <p class="text-muted small">
                                    <?= htmlspecialchars($generatedCodes[0]['subject']) ?>
                                    <?php if ($generatedCodes[0]['batch']): ?>
                                        • <span class="batch-badge bg-primary"><?= htmlspecialchars($generatedCodes[0]['batch']) ?></span>
                                    <?php endif; ?>
                                </p>
                            <?php else: ?>
                                <div class="qr-placeholder">
                                    <i class="bi bi-qr-code"></i>
                                    <h5>QR Code Preview</h5>
                                    <p class="text-muted">Generated codes will appear here</p>
                                    <?php if (!file_exists($logoPath)): ?>
                                        <div class="alert alert-warning mt-3 small">
                                            <i class="bi bi-exclamation-triangle me-2"></i>
                                            No logo found at <?= htmlspecialchars($logoPath) ?>. Using plain QR codes.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (isset($generatedCodes) && !empty($generatedCodes)): ?>
                            <div class="p-3 border-top">
                                <h6 class="mb-3">Generated Codes (<?= count($generatedCodes) ?>)</h6>
                                <div class="generated-codes">
                                    <?php foreach ($generatedCodes as $index => $code): ?>
                                        <div class="code-item card mb-2 p-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="d-flex align-items-center">
                                                    <div class="me-3" style="width: 40px; height: 40px;">
                                                        <img src="<?= htmlspecialchars($code['qr_image']) ?>" 
                                                             alt="QR Code" class="img-fluid">
                                                    </div>
                                                    <div>
                                                        <p class="mb-0 fw-bold"><?= htmlspecialchars($code['serial']) ?></p>
                                                        <p class="mb-0 text-muted small">
                                                            <?= htmlspecialchars($code['center']) ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                            type="button" data-bs-toggle="dropdown">
                                                        <i class="bi bi-three-dots-vertical"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <a class="dropdown-item download-btn" href="#" 
                                                               data-qr="<?= htmlspecialchars($code['qr_image']) ?>" 
                                                               data-serial="<?= htmlspecialchars($code['serial']) ?>">
                                                                <i class="bi bi-download me-2"></i>Download
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="view_document.php?id=<?= $code['id'] ?>">
                                                                <i class="bi bi-eye me-2"></i>View Details
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="print_document.php?id=<?= $code['id'] ?>">
                                                                <i class="bi bi-printer me-2"></i>Print
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
    
    <!-- Additional JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date picker
        flatpickr('.datepicker', {
            dateFormat: "Y-m-d",
            minDate: "today",
            allowInput: true
        });
        
        // Download QR code
        function downloadQR(dataUrl, filename) {
            const link = document.createElement('a');
            link.href = dataUrl;
            link.download = filename || 'qrcode.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Setup download buttons
        document.addEventListener('DOMContentLoaded', function() {
            // Single QR download
            document.querySelectorAll('.download-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const qrData = this.getAttribute('data-qr');
                    const serial = this.getAttribute('data-serial');
                    downloadQR(qrData, `${serial}-qrcode.png`);
                });
            });
            
            // Download all QRs as ZIP
            document.getElementById('downloadAll')?.addEventListener('click', function(e) {
                e.preventDefault();
                alert('In a full implementation, this would generate a ZIP file with all QR codes');
                // Actual implementation would require JSZip library
            });
        });
    </script>
</body>
</html>