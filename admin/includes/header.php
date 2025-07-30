<?php
$pageTitle = $pageTitle ?? 'Admin Dashboard'; // Default title
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> | Exam Security System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
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
        font-family: 'Inter', sans-serif;
        background-color: #f8fafc;
        color: var(--dark-color);
    }
    
    .main-content {
        margin-left: 250px;
        padding: 20px;
        transition: all 0.3s;
    }
    
    .card {
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        border: none;
        margin-bottom: 20px;
    }
    
    /* Add other global styles here */
</style>