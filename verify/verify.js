const video = document.getElementById('qr-video');
const resultDiv = document.getElementById('result');
const detailsDiv = document.getElementById('details');

// Start camera and scan for QR codes
function startScanner() {
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(stream => {
            video.srcObject = stream;
            video.play();
            requestAnimationFrame(scanQR);
        })
        .catch(err => {
            resultDiv.innerHTML = `<p class="text-danger">Camera access denied. Use manual entry.</p>`;
            console.error("Camera error:", err);
        });
}

// Scan video stream for QR codes
function scanQR() {
    if (video.readyState === video.HAVE_ENOUGH_DATA) {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, imageData.width, imageData.height);

        if (code) {
            verifyQRData(code.data);
        }
    }
    requestAnimationFrame(scanQR);
}

// Verify scanned QR data with backend
function verifyQRData(qrData) {
    try {
        const data = JSON.parse(qrData);
        $.post('/api/verify.php', { qr_data: qrData }, function(response) {
            showResult(response);
        });
    } catch (e) {
        resultDiv.innerHTML = `<p class="invalid">Invalid QR format</p>`;
    }
}

// Manual verification
$('#manual-submit').click(function() {
    const serial = $('#manual-serial').val().trim();
    if (serial) {
        $.post('/api/verify.php', { serial: serial }, function(response) {
            showResult(response);
        });
    }
});

// Display verification result
function showResult(response) {
    let statusClass = '';
    if (response.status === 'valid') statusClass = 'valid';
    else if (response.status === 'tampered') statusClass = 'tampered';
    else statusClass = 'invalid';

    resultDiv.innerHTML = `<p class="${statusClass}"><strong>${response.status.toUpperCase()}</strong></p>`;
    
    if (response.document) {
        detailsDiv.innerHTML = `
            <p><strong>Center:</strong> ${response.document.center_code}</p>
            <p><strong>Subject:</strong> ${response.document.subject_code}</p>
            <p><strong>Batch:</strong> ${response.document.batch_code}</p>
            <p><strong>Issued:</strong> ${new Date(response.document.timestamp).toLocaleString()}</p>
        `;
    }
}

// Initialize scanner when page loads
startScanner();

$.ajax({
    url: '/api/verify.php',
    method: 'POST',
    data: { 
        qr_data: qrData,
        user_id: window.USER_ID // USER_ID should be set in a <script> tag in your HTML
    },
    success: function(response) {
        showResult(response);
    }
});