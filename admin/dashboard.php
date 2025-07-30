<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

$pageTitle = "Dashboard";

// Fetch stats
$stats = [
    'documents' => $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn(),
    'verifications' => $pdo->query("SELECT COUNT(*) FROM verification_logs")->fetchColumn(),
    'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'centers' => $pdo->query("SELECT COUNT(*) FROM exam_centers")->fetchColumn()
];

// Recent activity
$recentLogs = $pdo->query("
    SELECT l.*, d.serial_number, u.username as verifier 
    FROM verification_logs l
    LEFT JOIN documents d ON l.document_id = d.id
    LEFT JOIN users u ON l.verified_by = u.id
    ORDER BY l.verification_time DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #4361ee, #3a0ca3);
            --success-gradient: linear-gradient(135deg, #4cc9f0, #4895ef);
            --warning-gradient: linear-gradient(135deg, #f8961e, #f3722c);
            --danger-gradient: linear-gradient(135deg, #f72585, #b5179e);
        }
        
        .stat-card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .stat-card .card-body {
            position: relative;
            z-index: 1;
        }
        
        .stat-card .bg-pattern {
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            opacity: 0.1;
            background-size: 40px 40px;
            background-image: radial-gradient(circle, currentColor 1px, transparent 1px);
        }
        
        .activity-item {
            border-left: 3px solid;
            transition: all 0.3s;
        }
        
        .activity-item:hover {
            background-color: rgba(0,0,0,0.02);
            transform: translateX(5px);
        }
        
        .theme-toggle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .theme-toggle:hover {
            background-color: rgba(0,0,0,0.1);
        }
        
        [data-bs-theme="dark"] .stat-card {
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
        }
        
        [data-bs-theme="dark"] .activity-item:hover {
            background-color: rgba(255,255,255,0.05);
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Top Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard Overview
                </h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="theme-toggle" id="themeToggle">
                    <i class="bi bi-moon-stars"></i>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-calendar3 me-2"></i>
                        Last 30 Days
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#">Today</a></li>
                        <li><a class="dropdown-item" href="#">Last 7 Days</a></li>
                        <li><a class="dropdown-item" href="#">Last 30 Days</a></li>
                        <li><a class="dropdown-item" href="#">This Year</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="stat-card text-white" style="background: var(--primary-gradient);">
                    <div class="bg-pattern"></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-uppercase mb-2">QR Codes</h6>
                                <h2 class="mb-0"><?= number_format($stats['documents']) ?></h2>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                                <i class="bi bi-file-earmark-text fs-4"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-white bg-opacity-25">
                                <i class="bi bi-arrow-up me-1"></i> 12% from last month
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card text-white" style="background: var(--success-gradient);">
                    <div class="bg-pattern"></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-uppercase mb-2">Verifications</h6>
                                <h2 class="mb-0"><?= number_format($stats['verifications']) ?></h2>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                                <i class="bi bi-check-circle fs-4"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-white bg-opacity-25">
                                <i class="bi bi-arrow-up me-1"></i> 24% from last month
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card text-white" style="background: var(--warning-gradient);">
                    <div class="bg-pattern"></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-uppercase mb-2">Users</h6>
                                <h2 class="mb-0"><?= number_format($stats['users']) ?></h2>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                                <i class="bi bi-people fs-4"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-white bg-opacity-25">
                                <i class="bi bi-arrow-up me-1"></i> 5% from last month
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card text-white" style="background: var(--danger-gradient);">
                    <div class="bg-pattern"></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-uppercase mb-2">Exam Centers</h6>
                                <h2 class="mb-0"><?= number_format($stats['centers']) ?></h2>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                                <i class="bi bi-building fs-4"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-white bg-opacity-25">
                                <i class="bi bi-arrow-down me-1"></i> 2% from last month
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Recent Activity -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Verification Activity</h5>
                        <a href="verification_logs.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentLogs as $log): ?>
                                <div class="list-group-item activity-item p-3 border-0 mb-2 rounded"
                                     style="border-left-color: <?= 
                                        $log['status'] === 'valid' ? '#4cc9f0' : 
                                        ($log['status'] === 'tampered' ? '#f8961e' : '#f72585') 
                                     ?> !important;">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1">
                                                <?= htmlspecialchars($log['verifier'] ?? 'System') ?>
                                                <span class="badge bg-<?= 
                                                    $log['status'] === 'valid' ? 'success' : 
                                                    ($log['status'] === 'tampered' ? 'warning' : 'danger') 
                                                ?> ms-2">
                                                    <?= ucfirst($log['status']) ?>
                                                </span>
                                            </h6>
                                            <p class="mb-0 text-muted small">
                                                <?= htmlspecialchars($log['serial_number'] ?? 'N/A') ?>
                                                • <?= date('M d, Y h:i A', strtotime($log['verification_time'])) ?>
                                            </p>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="generate_qr.php" class="btn btn-primary">
                                <i class="bi bi-file-earmark-plus me-2"></i> Generate QR Code
                            </a>
                            <a href="users.php" class="btn btn-outline-primary">
                                <i class="bi bi-person-plus me-2"></i> Add New User
                            </a>
                            <a href="reports.php" class="btn btn-outline-primary">
                                <i class="bi bi-download me-2"></i> Export Reports
                            </a>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h6 class="mb-3">System Status</h6>
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-success bg-opacity-10 p-2 rounded me-3">
                                <i class="bi bi-check-circle text-success"></i>
                            </div>
                            <div>
                                <p class="mb-0 small">Database</p>
                                <p class="mb-0 text-muted small">Operational</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-success bg-opacity-10 p-2 rounded me-3">
                                <i class="bi bi-check-circle text-success"></i>
                            </div>
                            <div>
                                <p class="mb-0 small">Storage</p>
                                <p class="mb-0 text-muted small">1.2GB of 10GB used</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="bg-success bg-opacity-10 p-2 rounded me-3">
                                <i class="bi bi-check-circle text-success"></i>
                            </div>
                            <div>
                                <p class="mb-0 small">Last Backup</p>
                                <p class="mb-0 text-muted small">Today, 02:00 AM</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
    
    <script>
        // Theme toggle
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;
        
        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', newTheme);
            themeToggle.innerHTML = newTheme === 'dark' 
                ? '<i class="bi bi-sun"></i>' 
                : '<i class="bi bi-moon-stars"></i>';
            
            // Save preference to localStorage
            localStorage.setItem('theme', newTheme);
        });
        
        // Check for saved theme preference
        if (localStorage.getItem('theme') === 'dark') {
            html.setAttribute('data-bs-theme', 'dark');
            themeToggle.innerHTML = '<i class="bi bi-sun"></i>';
        }
    </script>
</body>
</html>