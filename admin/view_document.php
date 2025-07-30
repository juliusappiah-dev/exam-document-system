<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

$pageTitle = "Document Details";

// Get document ID from URL
$documentId = $_GET['id'] ?? null;

if (!$documentId) {
    header("Location: documents.php");
    exit();
}

// Fetch document details
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        ec.name as center_name,
        ec.center_code,
        s.name as subject_name,
        s.subject_code,
        b.batch_code,
        u.username as creator
    FROM documents d
    LEFT JOIN exam_centers ec ON d.exam_center_id = ec.id
    LEFT JOIN batches b ON d.batch_id = b.id
    LEFT JOIN subjects s ON b.subject_id = s.id
    LEFT JOIN users u ON d.created_by = u.id
    WHERE d.id = ?
");
$stmt->execute([$documentId]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    header("Location: documents.php");
    exit();
}

// Decode QR data
$qrData = json_decode($document['qr_data'], true);

// Fetch verification history
$verifications = $pdo->prepare("
    SELECT vl.*, u.username as verifier 
    FROM verification_logs vl
    LEFT JOIN users u ON vl.verified_by = u.id
    WHERE vl.document_id = ?
    ORDER BY vl.verification_time DESC
");
$verifications->execute([$documentId]);

// Generate QR image if not already stored
require_once __DIR__ . '/../libs/qr_generator.php';
$qrImage = generateQRCode($document['qr_data']);
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <style>
        .document-card {
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: none;
            overflow: hidden;
        }
        
        .qr-display {
            width: 100%;
            max-width: 300px;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            margin: 0 auto;
        }
        
        .qr-display img {
            width: 100%;
            height: auto;
        }
        
        .verification-item {
            border-left: 3px solid;
            transition: all 0.3s;
        }
        
        .verification-item.valid {
            border-left-color: #4cc9f0;
        }
        
        .verification-item.tampered {
            border-left-color: #f8961e;
        }
        
        .verification-item.invalid {
            border-left-color: #f72585;
        }
        
        .document-details dt {
            font-weight: 500;
            color: #4361ee;
        }
        
        .action-btns .btn {
            min-width: 120px;
        }
        
        @media (max-width: 768px) {
            .document-actions {
                flex-direction: column !important;
                gap: 10px !important;
            }
            
            .action-btns .btn {
                width: 100%;
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
                    <i class="bi bi-qr-code me-2"></i>Document Details
                </h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="documents.php">Documents</a></li>
                        <li class="breadcrumb-item active" aria-current="page">View</li>
                    </ol>
                </nav>
            </div>
            <div class="document-actions d-flex gap-2">
                <a href="print_document.php?id=<?= $document['id'] ?>" class="btn btn-outline-primary">
                    <i class="bi bi-printer me-2"></i>Print
                </a>
                <a href="edit_document.php?id=<?= $document['id'] ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-pencil me-2"></i>Edit
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="row g-4">
            <!-- QR Code and Basic Info -->
            <div class="col-lg-4">
                <div class="document-card card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">QR Code</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="qr-display mb-4">
                            <img src="<?= $qrImage ?>" alt="Document QR Code">
                        </div>
                        <h4 class="mb-1"><?= htmlspecialchars($document['serial_number']) ?></h4>
                        <p class="text-muted">Generated on <?= date('M d, Y H:i', strtotime($document['created_at'])) ?></p>
                        
                        <div class="action-btns d-flex flex-wrap justify-content-center gap-2 mt-4">
                            <button class="btn btn-primary" onclick="downloadQR()">
                                <i class="bi bi-download me-2"></i>Download QR
                            </button>
                            <a href="verify_document.php?id=<?= $document['id'] ?>" class="btn btn-success">
                                <i class="bi bi-check-circle me-2"></i>Verify Now
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Document Details -->
            <div class="col-lg-8">
                <div class="document-card card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Document Information</h5>
                        <span class="badge bg-<?= $verifications->rowCount() > 0 ? 'success' : 'warning' ?>">
                            <?= $verifications->rowCount() > 0 ? 'Verified' : 'Unverified' ?>
                            <?php if ($verifications->rowCount() > 0): ?>
                                <span class="badge bg-white text-dark ms-1"><?= $verifications->rowCount() ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <dl class="document-details">
                                    <dt>Exam Center</dt>
                                    <dd><?= htmlspecialchars($document['center_name'] ?? 'N/A') ?> 
                                        <span class="badge bg-primary bg-opacity-10 text-primary ms-2">
                                            <?= htmlspecialchars($document['center_code'] ?? '') ?>
                                        </span>
                                    </dd>
                                    
                                    <dt>Subject</dt>
                                    <dd><?= htmlspecialchars($document['subject_name'] ?? 'N/A') ?>
                                        <span class="badge bg-info bg-opacity-10 text-info ms-2">
                                            <?= htmlspecialchars($document['subject_code'] ?? '') ?>
                                        </span>
                                    </dd>
                                    
                                    <dt>Batch Code</dt>
                                    <dd>
                                        <?php if ($document['batch_code']): ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary">
                                                <?= htmlspecialchars($document['batch_code']) ?>
                                            </span>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </dd>
                                </dl>
                            </div>
                            <div class="col-md-6">
                                <dl class="document-details">
                                    <dt>Created By</dt>
                                    <dd><?= htmlspecialchars($document['creator'] ?? 'System') ?></dd>
                                    
                                    <dt>Created On</dt>
                                    <dd><?= date('M d, Y H:i', strtotime($document['created_at'])) ?></dd>
                                    
                                    <dt>Expiry Date</dt>
                                    <dd>
                                        <?php if ($qrData['expiry'] ?? null): ?>
                                            <?= date('M d, Y', strtotime($qrData['expiry'])) ?>
                                            <?php if (strtotime($qrData['expiry']) < time()): ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger ms-2">Expired</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            No expiry
                                        <?php endif; ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h5 class="mb-3">QR Code Data</h5>
                        <div class="bg-light p-3 rounded mb-4">
                            <pre class="mb-0"><?= htmlspecialchars(json_encode($qrData, JSON_PRETTY_PRINT)) ?></pre>
                        </div>
                        
                        <!-- Verification History -->
                        <h5 class="mb-3">Verification History</h5>
                        <?php if ($verifications->rowCount() > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php while ($verification = $verifications->fetch(PDO::FETCH_ASSOC)): ?>
                                    <div class="list-group-item verification-item <?= $verification['status'] ?> p-3 border-0 mb-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1">
                                                    <?= htmlspecialchars($verification['verifier'] ?? 'System') ?>
                                                    <span class="badge bg-<?= 
                                                        $verification['status'] === 'valid' ? 'success' : 
                                                        ($verification['status'] === 'tampered' ? 'warning' : 'danger')
                                                    ?> ms-2">
                                                        <?= ucfirst($verification['status']) ?>
                                                    </span>
                                                </h6>
                                                <p class="mb-0 text-muted small">
                                                    <?= date('M d, Y H:i', strtotime($verification['verification_time'])) ?>
                                                    • IP: <?= htmlspecialchars($verification['ip_address'] ?? 'N/A') ?>
                                                </p>
                                            </div>
                                            <div>
                                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" 
                                                        data-bs-target="#verificationDetails<?= $verification['id'] ?>">
                                                    <i class="bi bi-chevron-down"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="collapse mt-2" id="verificationDetails<?= $verification['id'] ?>">
                                            <div class="card card-body bg-light">
                                                <pre class="mb-0 small"><?= htmlspecialchars(json_encode(json_decode($verification['verification_data'], true), JSON_PRETTY_PRINT)) ?></pre>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-light">
                                <i class="bi bi-info-circle me-2"></i> This document has not been verified yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
    
    <script>
        // Download QR code
        function downloadQR() {
            const link = document.createElement('a');
            link.href = '<?= $qrImage ?>';
            link.download = 'QR_<?= $document['serial_number'] ?>.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Print document
        function printDocument() {
            window.open('print_document.php?id=<?= $document['id'] ?>', '_blank');
        }
    </script>
</body>
</html>