// Global variables
let currentStream = null;
let currentCamera = 'environment';

// DOM Elements
const videoElement = document.getElementById('qr-video');
const scannerSection = document.getElementById('scannerSection');
const resultsSection = document.getElementById('resultsSection');
const documentDetails = document.getElementById('documentDetails');
const historyList = document.getElementById('historyList');
const resultAlert = document.getElementById('resultAlert');
const alertMessage = document.getElementById('alertMessage');

// Initialize scanner when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on the scan page
    if (window.location.pathname.includes('scan.html')) {
        initScanner();
        
        // Setup manual entry button
        document.getElementById('manualEntry').addEventListener('click', function() {
            const modal = new bootstrap.Modal(document.getElementById('manualModal'));
            modal.show();
        });
        
        // Setup manual form submission
        document.getElementById('manualForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const serial = document.getElementById('serialNumber').value.trim();
            if (serial) {
                verifyDocument(serial);
                const modal = bootstrap.Modal.getInstance(document.getElementById('manualModal'));
                modal.hide();
            }
        });
        
        // Setup camera toggle
        document.getElementById('toggleCamera').addEventListener('click', toggleCamera);
    }
});

// Initialize QR scanner
function initScanner() {
    stopScanner(); // Stop any existing stream
    
    const constraints = {
        video: {
            facingMode: currentCamera,
            width: { ideal: 1280 },
            height: { ideal: 720 }
        }
    };
    
    navigator.mediaDevices.getUserMedia(constraints)
        .then(function(stream) {
            currentStream = stream;
            videoElement.srcObject = stream;
            videoElement.play();
            requestAnimationFrame(scanQR);
        })
        .catch(function(err) {
            console.error("Camera error: ", err);
            showManualOption();
        });
}

// Stop camera stream
function stopScanner() {
    if (currentStream) {
        currentStream.getTracks().forEach(track => track.stop());
        currentStream = null;
    }
}

// Toggle between front and back camera
function toggleCamera() {
    currentCamera = currentCamera === 'environment' ? 'user' : 'environment';
    initScanner();
}

// QR scanning function
function scanQR() {
    if (videoElement.readyState === videoElement.HAVE_ENOUGH_DATA) {
        const canvas = document.createElement('canvas');
        canvas.width = videoElement.videoWidth;
        canvas.height = videoElement.videoHeight;
        const context = canvas.getContext('2d');
        context.drawImage(videoElement, 0, 0, canvas.width, canvas.height);
        const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, imageData.width, imageData.height);
        
        if (code) {
            try {
                const qrData = JSON.parse(code.data);
                if (qrData.serial) {
                    stopScanner();
                    verifyDocument(qrData.serial);
                }
            } catch (e) {
                // If not JSON, try as direct serial
                if (code.data.startsWith('DOC-')) {
                    stopScanner();
                    verifyDocument(code.data);
                }
            }
        }
    }
    
    if (currentStream) {
        requestAnimationFrame(scanQR);
    }
}

// Verify document with backend
function verifyDocument(serial) {
    // Show loading state
    scannerSection.classList.add('d-none');
    resultsSection.classList.remove('d-none');
    documentDetails.innerHTML = `
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3">Verifying document ${serial}...</p>
        </div>
    `;
    
    // Call your backend API
    fetch(`/api/verify.php?serial=${encodeURIComponent(serial)}`)
        .then(response => response.json())
        .then(data => {
            displayResults(data, serial);
        })
        .catch(error => {
            console.error('Error:', error);
            showError("Failed to verify document. Please try again.");
        });
}

// Display verification results
function displayResults(data, serial) {
    let statusClass, statusIcon, statusText;
    
    if (data.status === 'valid') {
        statusClass = 'alert-success';
        statusIcon = 'bi-check-circle-fill';
        statusText = 'Valid Document';
    } else if (data.status === 'tampered') {
        statusClass = 'alert-warning';
        statusIcon = 'bi-exclamation-triangle-fill';
        statusText = 'Tampered Document';
    } else {
        statusClass = 'alert-danger';
        statusIcon = 'bi-x-circle-fill';
        statusText = 'Invalid Document';
    }
    
    // Update alert
    resultAlert.className = `alert ${statusClass} d-flex align-items-center`;
    resultAlert.innerHTML = `
        <i class="bi ${statusIcon} me-2"></i>
        <span id="alertMessage">${statusText}</span>
    `;
    resultAlert.classList.remove('d-none');
    
    // Display document details
    documentDetails.innerHTML = `
        <div class="col-md-5 mb-4 mb-md-0">
            <div class="document-info-card card h-100 ${data.status}">
                <div class="card-body text-center">
                    <div class="qr-display mb-3">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(serial)}" 
                             alt="QR Code" class="img-fluid">
                    </div>
                    <h5>${serial}</h5>
                    <div class="badge bg-${data.status === 'valid' ? 'success' : data.status === 'tampered' ? 'warning' : 'danger'} mb-3">
                        ${data.status.toUpperCase()}
                    </div>
                    <button class="btn btn-outline-primary btn-sm" onclick="printDocument('${serial}')">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                </div>
            </div>
        </div>
        
        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Document Information</h5>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Exam Center</dt>
                        <dd class="col-sm-8">${data.document?.center_code || 'N/A'}</dd>
                        
                        <dt class="col-sm-4">Subject</dt>
                        <dd class="col-sm-8">${data.document?.subject_code || 'N/A'}</dd>
                        
                        <dt class="col-sm-4">Batch</dt>
                        <dd class="col-sm-8">${data.document?.batch_code || 'N/A'}</dd>
                        
                        <dt class="col-sm-4">Generated On</dt>
                        <dd class="col-sm-8">${data.document?.timestamp ? new Date(data.document.timestamp).toLocaleString() : 'N/A'}</dd>
                        
                        <dt class="col-sm-4">Generated By</dt>
                        <dd class="col-sm-8">${data.document?.creator || 'System'}</dd>
                    </dl>
                </div>
            </div>
        </div>
    `;
    
    // Display verification history
    if (data.history && data.history.length > 0) {
        historyList.innerHTML = data.history.map(item => `
            <div class="list-group-item verification-item ${item.status}">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="mb-1">Verified by ${item.verified_by || 'System'}</h6>
                        <small class="text-muted">${new Date(item.verification_time).toLocaleString()}</small>
                    </div>
                    <span class="badge bg-${item.status === 'valid' ? 'success' : item.status === 'tampered' ? 'warning' : 'danger'}">
                        ${item.status.toUpperCase()}
                    </span>
                </div>
                ${item.ip_address ? `<small class="text-muted">IP: ${item.ip_address}</small>` : ''}
            </div>
        `).join('');
    } else {
        historyList.innerHTML = `
            <div class="list-group-item text-center text-muted py-4">
                No verification history found
            </div>
        `;
    }
}

// Show error message
function showError(message) {
    resultAlert.className = 'alert alert-danger d-flex align-items-center';
    alertMessage.textContent = message;
    resultAlert.classList.remove('d-none');
    
    documentDetails.innerHTML = `
        <div class="col-12 text-center py-4">
            <i class="bi bi-exclamation-octagon fs-1 text-danger mb-3"></i>
            <h5>Verification Failed</h5>
            <p class="text-muted">${message}</p>
            <button class="btn btn-primary mt-2" onclick="window.location.reload()">
                <i class="bi bi-arrow-repeat me-2"></i> Try Again
            </button>
        </div>
    `;
    
    historyList.innerHTML = '';
}

// Show manual entry option
function showManualOption() {
    const modal = new bootstrap.Modal(document.getElementById('manualModal'));
    modal.show();
}

// Print document function
function printDocument(serial) {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Document ${serial}</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                @page { size: auto; margin: 5mm; }
                body { padding: 20px; }
                .print-qr { width: 100px; height: 100px; margin: 0 auto; }
            </style>
        </head>
        <body>
            <div class="text-center">
                <h4>Exam Document Verification</h4>
                <p>Serial Number: ${serial}</p>
                <div class="print-qr mb-3">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${encodeURIComponent(serial)}" 
                         alt="QR Code" class="img-fluid">
                </div>
                <p class="small text-muted">Scan this QR code to verify authenticity</p>
                <p class="small">Generated on ${new Date().toLocaleString()}</p>
            </div>
            <script>
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                        window.close();
                    }, 200);
                }
            </script>
        </body>
        </html>
    `);
    printWindow.document.close();
}