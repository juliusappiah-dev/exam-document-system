<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/db.php';

// Initialize response
$response = [
    'status' => 'invalid',
    'message' => '',
    'document' => null,
    'history' => []
];

try {
    // Get the serial number from request
    $serial = $_GET['serial'] ?? null;
    
    if (!$serial) {
        throw new Exception("No serial number provided");
    }

    // 1. Check if document exists
    $stmt = $pdo->prepare("
        SELECT d.*, 
               ec.name as center_name, ec.center_code,
               b.batch_code,
               s.name as subject_name, s.subject_code,
               u.username as creator
        FROM documents d
        LEFT JOIN exam_centers ec ON d.exam_center_id = ec.id
        LEFT JOIN batches b ON d.batch_id = b.id
        LEFT JOIN subjects s ON b.subject_id = s.id
        LEFT JOIN users u ON d.created_by = u.id
        WHERE d.serial_number = ?
    ");
    $stmt->execute([$serial]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        $response['message'] = "Document not found";
        echo json_encode($response);
        exit;
    }

    // 2. Check for tampering by comparing QR data
    $qrData = json_decode($document['qr_data'], true);
    $expectedSerial = $qrData['serial'] ?? null;
    
    if ($expectedSerial !== $serial) {
        $response['status'] = 'tampered';
        $response['message'] = "Serial number mismatch - possible tampering";
    } else {
        $response['status'] = 'valid';
        $response['message'] = "Document is valid";
    }

    // 3. Get verification history
    $historyStmt = $pdo->prepare("
        SELECT *, 
               (SELECT username FROM users WHERE id = verified_by) as verified_by_username
        FROM verification_logs
        WHERE document_id = ?
        ORDER BY verification_time DESC
        LIMIT 10
    ");
    $historyStmt->execute([$document['id']]);
    $response['history'] = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Format document data for response
    $response['document'] = [
        'serial_number' => $document['serial_number'],
        'center_code' => $document['center_code'],
        'center_name' => $document['center_name'],
        'subject_code' => $document['subject_code'],
        'subject_name' => $document['subject_name'],
        'batch_code' => $document['batch_code'],
        'creator' => $document['creator'],
        'timestamp' => $document['created_at']
    ];

    // 5. Log this verification attempt
    $logStmt = $pdo->prepare("
        INSERT INTO verification_logs 
        (document_id, verified_by, status, ip_address)
        VALUES (?, ?, ?, ?)
    ");
    $logStmt->execute([
        $document['id'],
        null, // or user ID if authenticated
        $response['status'],
        $_SERVER['REMOTE_ADDR']
    ]);

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>