<?php
require_once __DIR__ . '/../config/db.php';

$serial = $_GET['serial'] ?? null;

// Get document data if serial exists
if ($serial) {
    $stmt = $pdo->prepare("
        SELECT d.*, 
               ec.name as center_name, 
               s.name as subject_name, 
               b.batch_code,
               u.username as creator,
               (SELECT COUNT(*) FROM verification_logs WHERE document_id = d.id) as verification_count
        FROM documents d
        LEFT JOIN exam_centers ec ON d.exam_center_id = ec.id
        LEFT JOIN batches b ON d.batch_id = b.id
        LEFT JOIN subjects s ON b.subject_id = s.id
        LEFT JOIN users u ON d.created_by = u.id
        WHERE d.serial_number = ?
    ");
    $stmt->execute([$serial]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get verification history
    $history = $pdo->prepare("
        SELECT l.*, u.username as verifier 
        FROM verification_logs l
        LEFT JOIN users u ON l.verified_by = u.id
        WHERE document_id = ?
        ORDER BY verification_time DESC
    ");
    $history->execute([$document['id'] ?? 0]);
    $verificationHistory = $history->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify QR Code | ExamSecure</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-dark: #3a0ca3;
            --success-color: #4cc9f0;
            --warning-color: #f8961e;
            --danger-color: #f72585;
            --dark-color: #1a1a2e;
            --light-color: #f8f9fa;
        }
        
        body {
            font-family: 'Poppins', system-ui, -apple-system, sans-serif;
            background-color: var(--light-color);
        }
        
        .verification-card {
            border-left: 4px solid;
            transition: all 0.3s;
            overflow: hidden;
        }
        
        .verification-card.valid {
            border-left-color: var(--success-color);
        }
        
        .verification-card.unverified {
            border-left-color: var(--warning-color);
        }
        
        .verification-card.tampered {
            border-left-color: var(--danger-color);
        }
        
        .qr-display {
            width: 180px;
            height: 180px;
            background: white;
            padding: 10px;
            border-radius: 12px;
            margin: 0 auto;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .scanner-container {
            position: relative;
            max-width: 500px;
            margin: 0 auto;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .scanner-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                180deg,
                rgba(0, 0, 0, 0.6) 0%,
                rgba(0, 0, 0, 0) 20%,
                rgba(0, 0, 0, 0) 80%,
                rgba(0, 0, 0, 0.6) 100%
            );
            pointer-events: none;
        }
        
        .verification-badge {
            font-size: 0.9rem;
            padding: 0.5em 0.8em;
        }
        
        .history-item {
            border-left: 3px solid;
            transition: all 0.3s;
        }
        
        .history-item.valid {
            border-left-color: var(--success-color);
        }
        
        .history-item.tampered {
            border-left-color: var(--warning-color);
        }
        
        .history-item.invalid {
            border-left-color: var(--danger-color);
        }
        
        @media (max-width: 768px) {
            .qr-display {
                width: 150px;
                height: 150px;
                margin-bottom: 20px;
            }
            
            .verification-card {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container py-4 py-lg-5">
        <!-- Header -->
        <div class="text-center mb-4">
            <img src="../assets/logo.jpg" alt="Logo" class="mb-3" style="height: 100px;">
            <h1 class="h3 fw-bold">QR Code Verification</h1>
            <p class="text-muted">Verify the authenticity of exam documents</p>
        </div>
        
        <?php if ($serial): ?>
            <!-- Results View -->
            <?php if ($document): ?>
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        <div class="verification-card card mb-4 <?= $document['verification_count'] > 0 ? 'valid' : 'unverified' ?>">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-4 text-center mb-3 mb-md-0">
                                        <div class="qr-display mb-3">
                                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($serial) ?>" 
                                                 alt="QR Code" class="img-fluid">
                                        </div>
                                        <h4 class="mb-2"><?= htmlspecialchars($serial) ?></h4>
                                        <span class="verification-badge badge bg-<?= $document['verification_count'] > 0 ? 'success' : 'warning' ?>">
                                            <?= $document['verification_count'] > 0 ? 'Verified' : 'Unverified' ?>
                                            <?php if ($document['verification_count'] > 0): ?>
                                                <span class="badge bg-white text-dark ms-1"><?= $document['verification_count'] ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="col-md-8">
                                        <h3 class="h5 mb-3">Document Information</h3>
                                        <div class="row">
                                            <div class="col-sm-6 mb-2">
                                                <div class="fw-bold text-muted small">Exam Center</div>
                                                <div><?= htmlspecialchars($document['center_name'] ?? 'N/A') ?></div>
                                            </div>
                                            <div class="col-sm-6 mb-2">
                                                <div class="fw-bold text-muted small">Subject</div>
                                                <div><?= htmlspecialchars($document['subject_name'] ?? 'N/A') ?></div>
                                            </div>
                                            <div class="col-sm-6 mb-2">
                                                <div class="fw-bold text-muted small">Batch Code</div>
                                                <div><?= htmlspecialchars($document['batch_code'] ?? 'N/A') ?></div>
                                            </div>
                                            <div class="col-sm-6 mb-2">
                                                <div class="fw-bold text-muted small">Generated On</div>
                                                <div><?= date('M d, Y H:i', strtotime($document['created_at'])) ?></div>
                                            </div>
                                            <div class="col-sm-6 mb-2">
                                                <div class="fw-bold text-muted small">Created By</div>
                                                <div><?= htmlspecialchars($document['creator'] ?? 'System') ?></div>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3 d-flex gap-2">
                                            <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
                                                <i class="bi bi-printer me-1"></i> Print
                                            </button>
                                            <a href="verify.php" class="btn btn-outline-secondary btn-sm">
                                                <i class="bi bi-qr-code-scan me-1"></i> Scan Another
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Verification History -->
                        <?php if ($verificationHistory): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Verification History</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($verificationHistory as $log): ?>
                                            <div class="list-group-item history-item <?= $log['status'] ?> p-3">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1"><?= htmlspecialchars($log['verifier'] ?? 'System') ?></h6>
                                                        <small class="text-muted">
                                                            <?= date('M d, Y H:i', strtotime($log['verification_time'])) ?>
                                                            <?php if ($log['ip_address']): ?>
                                                                • IP: <?= $log['ip_address'] ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                    <span class="badge bg-<?= $log['status'] === 'valid' ? 'success' : ($log['status'] === 'tampered' ? 'warning' : 'danger') ?>">
                                                        <?= ucfirst($log['status']) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Not Found -->
                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6">
                        <div class="card border-danger">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-exclamation-octagon text-danger fs-1 mb-3"></i>
                                <h4 class="mb-3">Document Not Found</h4>
                                <p class="text-muted mb-4">The document with serial number <strong><?= htmlspecialchars($serial) ?></strong> was not found in our system.</p>
                                <a href="verify.php" class="btn btn-primary">
                                    <i class="bi bi-qr-code-scan me-2"></i> Try Another Document
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Scanner View -->
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-qr-code-scan me-2"></i> Scan Document QR Code
                            </h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="scanner-container mb-3">
                                <video id="qr-video" width="100%" playsinline></video>
                                <div class="scanner-overlay"></div>
                            </div>
                            <p class="text-muted mb-4">Point your camera at the document's QR code</p>
                            
                            <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                                <button id="toggle-camera" class="btn btn-outline-primary">
                                    <i class="bi bi-camera-reverse me-1"></i> Switch Camera
                                </button>
                                <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#manualModal">
                                    <i class="bi bi-keyboard me-1"></i> Enter Manually
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <p class="text-muted small">Powered by ExamSecure Document Verification System</p>
                    </div>
                </div>
            </div>
            
            <!-- Manual Entry Modal -->
            <div class="modal fade" id="manualModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Enter Document Serial</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="GET" action="verify.php">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="serialInput" class="form-label">Document Serial Number</label>
                                    <input type="text" class="form-control" id="serialInput" name="serial" 
                                           placeholder="DOC-XXXX-XXXX" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Verify</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <script>
        <?php if (!$serial): ?>
            // QR Scanner Functionality
            const video = document.getElementById('qr-video');
            let currentStream = null;
            let currentCamera = 'environment';
            
            // Initialize scanner
            function initScanner() {
                stopScanner();
                
                navigator.mediaDevices.getUserMedia({
                    video: { 
                        facingMode: currentCamera,
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }
                }).then(stream => {
                    currentStream = stream;
                    video.srcObject = stream;
                    video.play();
                    scan();
                }).catch(err => {
                    console.error("Camera error:", err);
                    document.getElementById('manualModal').classList.add('show');
                });
            }
            
            // Stop scanner
            function stopScanner() {
                if (currentStream) {
                    currentStream.getTracks().forEach(track => track.stop());
                }
            }
            
            // Toggle camera
            document.getElementById('toggle-camera').addEventListener('click', () => {
                currentCamera = currentCamera === 'environment' ? 'user' : 'environment';
                initScanner();
            });
            
            // QR scanning function
            function scan() {
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    const canvas = document.createElement('canvas');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const code = jsQR(imageData.data, imageData.width, imageData.height);
                    
                    if (code) {
                        let serial;
                        try {
                            const json = JSON.parse(code.data);
                            serial = json.serial || json.url?.split('serial=')[1];
                        } catch {
                            serial = code.data.startsWith('DOC-') ? code.data : null;
                        }
                        
                        if (serial) {
                            stopScanner();
                            window.location.href = `verify.php?serial=${encodeURIComponent(serial)}`;
                        }
                    }
                }
                
                if (currentStream) {
                    requestAnimationFrame(scan);
                }
            }
            
            // Initialize scanner when page loads
            initScanner();
        <?php endif; ?>
        
        // Print styling
        window.onbeforeprint = function() {
            document.querySelector('.container').classList.add('py-0');
        };
        window.onafterprint = function() {
            document.querySelector('.container').classList.remove('py-0');
        };
    </script>
</body>
</html>