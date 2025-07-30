$(document).ready(function() {
    // Load dropdowns dynamically
    $.get('/api/centers.php', function(data) {
        data.forEach(center => {
            $('#center').append(`<option value="${center.id}">${center.name}</option>`);
        });
    });

    $.get('/api/subjects.php', function(data) {
        data.forEach(subject => {
            $('#subject').append(`<option value="${subject.id}">${subject.name}</option>`);
        });
    });

    $('#subject').change(function() {
        $.get(`/api/batches.php?subject_id=${$(this).val()}`, function(data) {
            $('#batch').empty().append('<option value="">Select Batch</option>');
            data.forEach(batch => {
                $('#batch').append(`<option value="${batch.id}">${batch.batch_code}</option>`);
            });
        });
    });

    // Generate QR Document
    $('#qrForm').submit(function(e) {
        e.preventDefault();
        const formData = {
            center_id: $('#center').val(),
            subject_id: $('#subject').val(),
            batch_id: $('#batch').val(),
            user_id: 1 // Replace with logged-in user ID
        };

        $.ajax({
            url: '/api/generate_document.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) {
                $('#qrImage').attr('src', response.qr_image);
                $('#serial').text(response.serial);
                $('#preview').removeClass('d-none');
            }
        });
    });

    // Print Functionality
    $('#printBtn').click(function() {
        window.print();
    });
});

$('#printBtn').click(function() {
    const qrData = {
        center_code: $('#center option:selected').text(),
        subject_code: $('#subject option:selected').text(),
        batch_code: $('#batch option:selected').text(),
        serial: $('#serial').text()
    };

    $.ajax({
        url: '/libs/pdf_generator.php',
        method: 'POST',
        data: { qrData: JSON.stringify(qrData) },
        xhrFields: { responseType: 'blob' },
        success: function(data) {
            const blob = new Blob([data], { type: 'application/pdf' });
            const link = document.createElement('a');
            link.href = window.URL.createObjectURL(blob);
            link.download = 'exam_document.pdf';
            link.click();
        }
    });
});