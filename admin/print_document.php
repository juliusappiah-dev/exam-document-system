<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

$pageTitle = "Print Document";

// Get document ID from URL
$documentId = $_GET['id'] ?? null;

if (!$documentId) {
    header("Location: documents.php");
    exit();
}

// Fetch document details
$stmt = $pdo->prepare("
    SELECT d.*, ec.name as center_name, s.name as subject_name, b.batch_code, u.username as creator
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Document | <?= htmlspecialchars($document['serial_number']) ?> | Exam Security System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Poppins', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .print-container {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .security-paper {
            background-image: url('data:image/svg+xml;utf8,<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><text x="10" y="20" font-size="15" opacity="0.03" fill="%23000">SECURE</text></svg>');
            padding: 20mm;
        }
        
        .document-header {
            border-bottom: 2px solid #4361ee;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        
        .document-title {
            color: #4361ee;
            font-weight: 700;
        }
        
        .document-qr {
            width: 60mm;
            height: 60mm;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 5mm;
        }
        
        .document-qr img {
            width: 100%;
            height: 100%;
        }
        
        .security-feature {
            position: absolute;
            top: 20mm;
            right: 20mm;
            width: 30mm;
            height: 30mm;
            background: linear-gradient(45deg, #ff0, #f0f, #0ff, #0f0, #ff0);
            background-size: 400% 400%;
            opacity: 0.7;
            border-radius: 5mm;
        }
        
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 5rem;
            font-weight: 700;
            color: rgba(0,0,0,0.05);
            z-index: 0;
            pointer-events: none;
        }
        
        .detail-item {
            margin-bottom: 8px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #495057;
            min-width: 120px;
            display: inline-block;
        }
        
        .print-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }
        
        .back-button {
            position: fixed;
            bottom: 30px;
            left: 30px;
            z-index: 1000;
        }
        
        @media print {
            body {
                background: none;
            }
            
            .print-container {
                box-shadow: none;
                width: auto;
                height: auto;
                margin: 0;
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            .security-paper {
                padding: 0;
                background-image: none;
            }
        }
    </style>
</head>
<body>
    <!-- Print Preview Container -->
    <div class="print-container security-paper">
        <!-- Security Features -->
        <div class="security-feature"></div>
        <div class="watermark">OFFICIAL</div>
        
        <!-- Document Header -->
        <div class="document-header text-center">
            <img src="/path/to/your/logo.png" alt="Institution Logo" style="height: 25mm; margin-bottom: 10px;">
            <h1 class="document-title">Exam Authorization Document</h1>
            <p class="text-muted">This document is valid only with original QR code</p>
        </div>
        
        <!-- Document Content -->
        <div class="row">
            <div class="col-md-6">
                <div class="mb-4">
                    <h4 class="mb-3">Document Information</h4>
                    <div class="detail-item">
                        <span class="detail-label">Serial Number:</span>
                        <span><?= htmlspecialchars($document['serial_number']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Exam Center:</span>
                        <span><?= htmlspecialchars($document['center_name']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Subject:</span>
                        <span><?= htmlspecialchars($document['subject_name']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Batch Code:</span>
                        <span><?= htmlspecialchars($document['batch_code']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Issued Date:</span>
                        <span><?= date('F j, Y', strtotime($document['created_at'])) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Issued By:</span>
                        <span><?= htmlspecialchars($document['creator']) ?></span>
                    </div>
                    <?php if (isset($qrData['expiry'])): ?>
                    <div class="detail-item">
                        <span class="detail-label">Expiry Date:</span>
                        <span><?= date('F j, Y', strtotime($qrData['expiry'])) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-4">
                    <h4 class="mb-3">Security Features</h4>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Unique QR code identifier</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Holographic security stripe</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Microtext pattern</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Tamper-evident design</li>
                    </ul>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="text-center">
                    <h4 class="mb-3">Verification QR Code</h4>
                    <div class="document-qr mb-3">
                        <img src="data:image/png;base64,<?= base64_encode(file_get_contents(__DIR__ . '/../libs/qr_generator.php?data=' . urlencode($document['qr_data']))) ?>" 
                             alt="Document QR Code">
                    </div>
                    <p class="text-muted">Scan this code to verify document authenticity</p>
                    
                    <div class="alert alert-info mt-4">
                        <h5><i class="bi bi-info-circle-fill me-2"></i>Important</h5>
                        <p class="mb-0">This document must be presented in original form. Photocopies or digital copies are not valid for exam authorization.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Document Footer -->
        <div class="mt-5 pt-4 border-top">
            <div class="row">
                <div class="col-md-6">
                    <h6>Institution Information</h6>
                    <p class="small text-muted mb-0"><?= htmlspecialchars($document['center_name']) ?></p>
                    <p class="small text-muted mb-0">123 Education Street, Campus City</p>
                    <p class="small text-muted mb-0">Phone: (123) 456-7890</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <h6>Verification</h6>
                    <p class="small text-muted mb-0">Document ID: <?= htmlspecialchars($document['serial_number']) ?></p>
                    <p class="small text-muted mb-0">Issued: <?= date('Y-m-d H:i', strtotime($document['created_at'])) ?></p>
                    <p class="small text-muted mb-0">System: ExamSecure v2.0</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Action Buttons -->
    <button onclick="window.print()" class="btn btn-primary print-button no-print">
        <i class="bi bi-printer-fill me-2"></i>Print Document
    </button>
    
    <a href="view_document.php?id=<?= $documentId ?>" class="btn btn-outline-secondary back-button no-print">
        <i class="bi bi-arrow-left me-2"></i>Back to Document
    </a>

    <!-- Print Optimization Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add print event listener
            window.addEventListener('beforeprint', function() {
                // You could add last-minute print optimizations here
                console.log('Preparing for print...');
            });
            
            // Return to document after printing
            window.onafterprint = function() {
                // Optional: Add any post-print actions
            };
        });
    </script>
</body>
</html>