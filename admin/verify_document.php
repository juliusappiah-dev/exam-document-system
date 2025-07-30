<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

$pageTitle = "Verify Document";

// Initialize variables
$verificationResult = null;
$documentDetails = null;
$error = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serialNumber = $_POST['serial'] ?? null;
    $qrData = $_POST['qr_data'] ?? null;
    
    try {
        if ($qrData) {
            // Verify by QR code data
            $decodedData = json_decode($qrData, true);
            if (!$decodedData || !isset($decodedData['serial'])) {
                throw new Exception("Invalid QR code data format");
            }
            $serialNumber = $decodedData['serial'];
        }
        
        if (!$serialNumber) {
            throw new Exception("No verification data provided");
        }
        
        // Check document in database
        $stmt = $pdo->prepare("
            SELECT d.*, ec.name as center_name, s.name as subject_name, b.batch_code
            FROM documents d
            LEFT JOIN exam_centers ec ON d.exam_center_id = ec.id
            LEFT JOIN batches b ON d.batch_id = b.id
            LEFT JOIN subjects s ON b.subject_id = s.id
            WHERE d.serial_number = ?
        ");
        $stmt->execute([$serialNumber]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$document) {
            $verificationResult = 'invalid';
            $error = "Document not found in our system";
        } else {
            // Check for tampering if QR data was provided
            if ($qrData && $document['qr_data'] !== $qrData) {
                $verificationResult = 'tampered';
                $error = "QR code data does not match our records";
            } else {
                $verificationResult = 'valid';
            }
            
            $documentDetails = [
                'serial' => $document['serial_number'],
                'center' => $document['center_name'],
                'subject' => $document['subject_name'],
                'batch' => $document['batch_code'],
                'created' => $document['created_at'],
                'expiry' => json_decode($document['qr_data'], true)['expiry'] ?? null
            ];
            
            // Log verification attempt
            $logStmt = $pdo->prepare("
                INSERT INTO verification_logs 
                (document_id, verified_by, status, verification_method) 
                VALUES (?, ?, ?, ?)
            ");
            $logStmt->execute([
                $document['id'],
                $_SESSION['user_id'],
                $verificationResult,
                $qrData ? 'qr_scan' : 'manual_entry'
            ]);
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        $verificationResult = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <style>
        .verification-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .scanner-container {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            background: #f8f9fa;
        }
        
        #qr-video {
            width: 100%;
            background: black;
        }
        
        .scanner-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            pointer-events: none;
        }
        
        .scan-region {
            width: 70%;
            max-width: 300px;
            height: 300px;
            border: 4px solid rgba(255, 255, 255, 0.5);
            border-radius: 12px;
            position: relative;
        }
        
        .scan-region::before, .scan-region::after {
            content: '';
            position: absolute;
            width: 40px;
            height: 40px;
            border-color: white;
            border-style: solid;
            border-width: 0;
        }
        
        .scan-region::before {
            top: 0;
            left: 0;
            border-top-width: 4px;
            border-left-width: 4px;
            border-top-left-radius: 8px;
        }
        
        .scan-region::after {
            top: 0;
            right: 0;
            border-top-width: 4px;
            border-right-width: 4px;
            border-top-right-radius: 8px;
        }
        
        .result-card {
            border-left: 5px solid;
            transition: all 0.3s;
        }
        
        .result-card.valid {
            border-left-color: #28a745;
        }
        
        .result-card.tampered {
            border-left-color: #ffc107;
        }
        
        .result-card.invalid {
            border-left-color: #dc3545;
        }
        
        .verification-badge {
            font-size: 1.2rem;
            padding: 0.5rem 1rem;
        }
        
        @media (max-width: 768px) {
            .scan-region {
                width: 90%;
                height: 250px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="verification-container">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-0">
                        <i class="bi bi-qr-code-scan me-2"></i>Verify Document
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Verify</li>
                        </ol>
                    </nav>
                </div>
            </div>
            
            <!-- Verification Methods Tabs -->
            <ul class="nav nav-tabs mb-4" id="verificationTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="scan-tab" data-bs-toggle="tab" 
                            data-bs-target="#scan-tab-pane" type="button" role="tab">
                        <i class="bi bi-camera me-2"></i>Scan QR Code
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="manual-tab" data-bs-toggle="tab" 
                            data-bs-target="#manual-tab-pane" type="button" role="tab">
                        <i class="bi bi-keyboard me-2"></i>Manual Entry
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="verificationTabContent">
                <!-- QR Scan Tab -->
                <div class="tab-pane fade show active" id="scan-tab-pane" role="tabpanel">
                    <div class="card mb-4">
                        <div class="card-body p-0">
                            <div class="scanner-container">
                                <video id="qr-video" playsinline></video>
                                <div class="scanner-overlay">
                                    <div class="scan-region"></div>
                                    <p class="text-white mt-3">Position QR code within frame</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mb-4">
                        <p class="text-muted">or</p>
                        <button class="btn btn-outline-primary" id="upload-qr">
                            <i class="bi bi-upload me-2"></i>Upload QR Image
                        </button>
                        <input type="file" id="qr-file-input" accept="image/*" style="display: none;">
                    </div>
                </div>
                
                <!-- Manual Entry Tab -->
                <div class="tab-pane fade" id="manual-tab-pane" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <form method="POST" id="manual-verify-form">
                                <div class="mb-3">
                                    <label for="serial-number" class="form-label">Document Serial Number</label>
                                    <input type="text" class="form-control form-control-lg" 
                                           id="serial-number" name="serial" 
                                           placeholder="DOC-XXXX-XXXX" required>
                                </div>
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="bi bi-search me-2"></i>Verify Document
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Verification Result -->
            <?php if ($verificationResult || $error): ?>
                <div class="card result-card <?= $verificationResult ?> mt-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Verification Result</h5>
                            <?php if ($verificationResult === 'valid'): ?>
                                <span class="verification-badge badge bg-success">
                                    <i class="bi bi-check-circle-fill me-2"></i>VALID
                                </span>
                            <?php elseif ($verificationResult === 'tampered'): ?>
                                <span class="verification-badge badge bg-warning text-dark">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>TAMPERED
                                </span>
                            <?php elseif ($verificationResult === 'invalid'): ?>
                                <span class="verification-badge badge bg-danger">
                                    <i class="bi bi-x-circle-fill me-2"></i>INVALID
                                </span>
                            <?php else: ?>
                                <span class="verification-badge badge bg-secondary">
                                    <i class="bi bi-question-circle-fill me-2"></i>ERROR
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($documentDetails): ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="detail-item mb-2">
                                        <span class="detail-label">Serial Number:</span>
                                        <span><?= htmlspecialchars($documentDetails['serial']) ?></span>
                                    </div>
                                    <div class="detail-item mb-2">
                                        <span class="detail-label">Exam Center:</span>
                                        <span><?= htmlspecialchars($documentDetails['center']) ?></span>
                                    </div>
                                    <div class="detail-item mb-2">
                                        <span class="detail-label">Subject:</span>
                                        <span><?= htmlspecialchars($documentDetails['subject']) ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-item mb-2">
                                        <span class="detail-label">Batch Code:</span>
                                        <span><?= htmlspecialchars($documentDetails['batch']) ?></span>
                                    </div>
                                    <div class="detail-item mb-2">
                                        <span class="detail-label">Issued Date:</span>
                                        <span><?= date('M d, Y', strtotime($documentDetails['created'])) ?></span>
                                    </div>
                                    <?php if ($documentDetails['expiry']): ?>
                                    <div class="detail-item mb-2">
                                        <span class="detail-label">Expiry Date:</span>
                                        <span><?= date('M d, Y', strtotime($documentDetails['expiry'])) ?></span>
                                        <?php if (strtotime($documentDetails['expiry']) < time()): ?>
                                            <span class="badge bg-danger ms-2">EXPIRED</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($verificationResult === 'valid'): ?>
                                <div class="alert alert-success mt-3">
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                    This document is valid and verified in our system.
                                </div>
                            <?php elseif ($verificationResult === 'tampered'): ?>
                                <div class="alert alert-warning mt-3">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    Warning: This document appears to have been altered. Please verify with the issuing authority.
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-4">
                                <a href="view_document.php?id=<?= $document['id'] ?? '' ?>" 
                                   class="btn btn-outline-primary me-2">
                                    <i class="bi bi-eye me-2"></i>View Document
                                </a>
                                <button class="btn btn-outline-secondary" onclick="window.print()">
                                    <i class="bi bi-printer me-2"></i>Print Result
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
    
    <!-- QR Scanner Library -->
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab persistence
            const verificationTabs = document.getElementById('verificationTabs');
            if (verificationTabs) {
                verificationTabs.addEventListener('shown.bs.tab', function(event) {
                    localStorage.setItem('lastVerificationTab', event.target.id);
                });
                
                const lastTab = localStorage.getItem('lastVerificationTab');
                if (lastTab) {
                    const tab = new bootstrap.Tab(document.getElementById(lastTab));
                    tab.show();
                }
            }
            
            // QR Code Scanner
            const qrVideo = document.getElementById('qr-video');
            let scanning = false;
            
            function startScanner() {
                navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        facingMode: 'environment',
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    } 
                }).then(stream => {
                    qrVideo.srcObject = stream;
                    qrVideo.play();
                    scanning = true;
                    scanQRCode();
                }).catch(err => {
                    console.error("Camera error:", err);
                    alert("Could not access camera. Please check permissions.");
                });
            }
            
            function stopScanner() {
                if (qrVideo.srcObject) {
                    qrVideo.srcObject.getTracks().forEach(track => track.stop());
                    scanning = false;
                }
            }
            
            function scanQRCode() {
                if (!scanning) return;
                
                const canvas = document.createElement('canvas');
                canvas.width = qrVideo.videoWidth;
                canvas.height = qrVideo.videoHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(qrVideo, 0, 0, canvas.width, canvas.height);
                
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const code = jsQR(imageData.data, imageData.width, imageData.height, {
                    inversionAttempts: 'dontInvert'
                });
                
                if (code) {
                    stopScanner();
                    document.getElementById('manual-verify-form').insertAdjacentHTML('beforebegin', `
                        <form id="qr-data-form" method="POST" style="display: none;">
                            <input type="hidden" name="qr_data" value='${JSON.stringify(code.data)}'>
                        </form>
                    `);
                    document.getElementById('qr-data-form').submit();
                } else {
                    requestAnimationFrame(scanQRCode);
                }
            }
            
            // Start scanner when QR tab is shown
            const scanTab = document.getElementById('scan-tab');
            if (scanTab) {
                scanTab.addEventListener('shown.bs.tab', startScanner);
                scanTab.addEventListener('hidden.bs.tab', stopScanner);
            }
            
            // QR Image Upload Handling
document.getElementById('upload-qr').addEventListener('click', function() {
    // Programmatically click the hidden file input
    document.getElementById('qr-file-input').click();
});

document.getElementById('qr-file-input').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    // Check if it's an image
    if (!file.type.match('image.*')) {
        alert('Please select an image file (JPEG, PNG, etc.)');
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(event) {
        const img = new Image();
        img.onload = function() {
            // Create canvas to process the image
            const canvas = document.createElement('canvas');
            canvas.width = img.width;
            canvas.height = img.height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0);
            
            // Extract image data for QR scanning
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height, {
                inversionAttempts: 'dontInvert'
            });
            
            if (code) {
                // Submit the form with QR data
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'qr_data';
                input.value = code.data;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            } else {
                alert("Could not find a QR code in the image. Please try another image.");
            }
        };
        img.src = event.target.result;
    };
    reader.readAsDataURL(file);
});
            
            // Start scanner immediately if on QR tab and no result yet
            if (document.getElementById('scan-tab-pane').classList.contains('active') && !<?= $verificationResult ? 'true' : 'false' ?>) {
                startScanner();
            }
        });
    </script>
</body>
</html>