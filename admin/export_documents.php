<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

// Get export parameters
$format = $_POST['format'] ?? 'csv';
$filters = json_decode($_POST['filters'] ?? '{}', true);

// Build base query
$query = "
    SELECT 
        d.serial_number,
        ec.name as center_name,
        s.name as subject_name,
        b.batch_code,
        u.username as created_by,
        d.created_at,
        (SELECT COUNT(*) FROM verification_logs WHERE document_id = d.id) as verification_count
    FROM documents d
    LEFT JOIN exam_centers ec ON d.exam_center_id = ec.id
    LEFT JOIN batches b ON d.batch_id = b.id
    LEFT JOIN subjects s ON b.subject_id = s.id
    LEFT JOIN users u ON d.created_by = u.id
";

// Apply filters
$conditions = [];
$params = [];

if (!empty($filters['search'])) {
    $conditions[] = "(d.serial_number LIKE ? OR ec.name LIKE ? OR s.name LIKE ?)";
    $params[] = "%{$filters['search']}%";
    $params[] = "%{$filters['search']}%";
    $params[] = "%{$filters['search']}%";
}

if (!empty($filters['center'])) {
    $conditions[] = "d.exam_center_id = ?";
    $params[] = $filters['center'];
}

if (!empty($filters['status']) && $filters['status'] === 'verified') {
    $conditions[] = "EXISTS (SELECT 1 FROM verification_logs WHERE document_id = d.id)";
} elseif (!empty($filters['status']) && $filters['status'] === 'unverified') {
    $conditions[] = "NOT EXISTS (SELECT 1 FROM verification_logs WHERE document_id = d.id)";
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY d.created_at DESC";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate export file
switch ($format) {
    case 'csv':
        exportCSV($documents);
        break;
    case 'excel':
        exportExcel($documents);
        break;
    case 'pdf':
        exportPDF($documents);
        break;
    default:
        header("Content-Type: application/json");
        echo json_encode(['error' => 'Invalid export format']);
        exit();
}

function exportCSV($data) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="documents_export_' . date('Ymd') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Header row
    fputcsv($output, [
        'Serial Number',
        'Exam Center',
        'Subject',
        'Batch Code',
        'Created By',
        'Created At',
        'Verification Count'
    ]);
    
    // Data rows
    foreach ($data as $row) {
        fputcsv($output, [
            $row['serial_number'],
            $row['center_name'],
            $row['subject_name'],
            $row['batch_code'],
            $row['created_by'],
            $row['created_at'],
            $row['verification_count']
        ]);
    }
    
    fclose($output);
    exit();
}

function exportExcel($data) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="documents_export_' . date('Ymd') . '.xls"');
    
    echo '<table border="1">';
    echo '<tr><th>Serial Number</th><th>Exam Center</th><th>Subject</th><th>Batch Code</th><th>Created By</th><th>Created At</th><th>Verifications</th></tr>';
    
    foreach ($data as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['serial_number']) . '</td>';
        echo '<td>' . htmlspecialchars($row['center_name']) . '</td>';
        echo '<td>' . htmlspecialchars($row['subject_name']) . '</td>';
        echo '<td>' . htmlspecialchars($row['batch_code']) . '</td>';
        echo '<td>' . htmlspecialchars($row['created_by']) . '</td>';
        echo '<td>' . htmlspecialchars($row['created_at']) . '</td>';
        echo '<td>' . htmlspecialchars($row['verification_count']) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    exit();
}

function exportPDF($data) {
    require_once __DIR__ . '/../libs/tcpdf/tcpdf.php';
    
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Exam System');
    $pdf->SetAuthor('Admin');
    $pdf->SetTitle('Documents Export');
    $pdf->AddPage();
    
    // Header
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Documents Export - ' . date('Y-m-d'), 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    
    // Table header
    $header = ['Serial', 'Center', 'Subject', 'Batch', 'Created By', 'Created At', 'Verifications'];
    $w = [30, 35, 35, 25, 25, 30, 20];
    
    $pdf->SetFillColor(220, 220, 220);
    $pdf->SetFont('helvetica', 'B', 9);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Table data
    $pdf->SetFont('helvetica', '', 8);
    foreach ($data as $row) {
        $pdf->Cell($w[0], 6, $row['serial_number'], 'LR');
        $pdf->Cell($w[1], 6, $row['center_name'], 'LR');
        $pdf->Cell($w[2], 6, $row['subject_name'], 'LR');
        $pdf->Cell($w[3], 6, $row['batch_code'], 'LR');
        $pdf->Cell($w[4], 6, $row['created_by'], 'LR');
        $pdf->Cell($w[5], 6, $row['created_at'], 'LR');
        $pdf->Cell($w[6], 6, $row['verification_count'], 'LR', 0, 'C');
        $pdf->Ln();
    }
    
    $pdf->Output('documents_export_' . date('Ymd') . '.pdf', 'D');
    exit();
}