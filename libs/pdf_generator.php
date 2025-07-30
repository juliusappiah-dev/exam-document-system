<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/qr_helper.php';

use TCPDF as TCPDF;

function generateSecurePDF($qrData, $outputPath = null) {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document metadata
    $pdf->SetCreator('Exam Security System');
    $pdf->SetAuthor('Admin');
    $pdf->SetTitle('Secure Exam Document');
    $pdf->SetSubject('QR Code Verification');
    
    // Disable copy/paste/modify
    $pdf->SetProtection(['copy', 'print'], '', 'admin123'); // Password-protected editing
    
    // Add a page
    $pdf->AddPage();
    
    // Generate QR code image
    $qrImagePath = __DIR__ . '/../temp/qr_' . uniqid() . '.png';
    generateQRCode(json_encode($qrData), $qrImagePath);
    
    // HTML content (using the template)
    $html = file_get_contents(__DIR__ . '/../templates/document_template.html');
    $html = str_replace(
        ['id="qrImage"', 'id="serialNumber"', 'id="examCenter"', 'id="subject"', 'id="batch"'],
        [
            'id="qrImage" src="' . $qrImagePath . '"',
            'id="serialNumber">' . $qrData['serial'],
            'id="examCenter">' . $qrData['center_code'],
            'id="subject">' . $qrData['subject_code'],
            'id="batch">' . $qrData['batch_code']
        ],
        $html
    );
    
    // Add HTML to PDF
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Add invisible watermark (text overlay)
    $pdf->SetAlpha(0.1);
    $pdf->SetFont('helvetica', 'B', 60);
    $pdf->RotatedText(50, 150, 'VALID', 45);
    $pdf->SetAlpha(1);
    
    // Output PDF
    if ($outputPath) {
        $pdf->Output($outputPath, 'F');
    } else {
        $pdf->Output('exam_document.pdf', 'I'); // Inline display
    }
    
    // Clean up
    unlink($qrImagePath);
}
?>