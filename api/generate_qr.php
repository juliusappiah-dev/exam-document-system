<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/phpqrcode/qrlib.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Extract data
    $examCenterId = $data['exam_center_id'];
    $batchId = $data['batch_id'];
    $createdBy = $data['created_by']; // Admin user ID
    
    // Generate a unique serial number
    $serialNumber = uniqid('EXAM-') . bin2hex(random_bytes(4));
    
    // Prepare QR data (JSON format)
    $qrData = json_encode([
        'serial' => $serialNumber,
        'center_id' => $examCenterId,
        'batch_id' => $batchId,
        'timestamp' => time()
    ]);
    
    // Generate QR code image
    $qrTempFile = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
    QRcode::png($qrData, $qrTempFile, QR_ECLEVEL_H, 10);
    
    // Store in database
    $stmt = $pdo->prepare("
        INSERT INTO documents 
        (serial_number, qr_data, exam_center_id, batch_id, created_by) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$serialNumber, $qrData, $examCenterId, $batchId, $createdBy]);
    
    // Return response
    echo json_encode([
        'success' => true,
        'serial_number' => $serialNumber,
        'qr_image' => base64_encode(file_get_contents($qrTempFile)),
        'qr_data' => $qrData
    ]);
    
    // Clean up
    unlink($qrTempFile);
}
?>