<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

$pageTitle = "Analytics Reports";

// Date range defaults (last 30 days)
$startDate = date('Y-m-d', strtotime('-30 days'));
$endDate = date('Y-m-d');

// Get verification stats
$verificationStats = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM verification_logs 
    WHERE verification_time BETWEEN '$startDate' AND '$endDate 23:59:59'
    GROUP BY status
")->fetchAll(PDO::FETCH_ASSOC);

// Daily verifications
$dailyVerifications = $pdo->query("
    SELECT DATE(verification_time) as date, COUNT(*) as count
    FROM verification_logs
    WHERE verification_time BETWEEN '$startDate' AND '$endDate 23:59:59'
    GROUP BY DATE(verification_time)
    ORDER BY date ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Document generation stats
$docGeneration = $pdo->query("
    SELECT DATE(created_at) as date, COUNT(*) as count
    FROM documents
    WHERE created_at BETWEEN '$startDate' AND '$endDate 23:59:59'
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Top verifiers
$topVerifiers = $pdo->query("
    SELECT u.username, COUNT(*) as count
    FROM verification_logs l
    JOIN users u ON l.verified_by = u.id
    WHERE l.verification_time BETWEEN '$startDate' AND '$endDate 23:59:59'
    GROUP BY l.verified_by
    ORDER BY count DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Documents by center
$documentsByCenter = $pdo->query("
    SELECT ec.name, COUNT(*) as count
    FROM documents d
    JOIN exam_centers ec ON d.exam_center_id = ec.id
    WHERE d.created_at BETWEEN '$startDate' AND '$endDate 23:59:59'
    GROUP BY d.exam_center_id
    ORDER BY count DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Recent activity
$recentActivity = $pdo->query("
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <style>
        .stat-card {
            border-radius: 12px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .stat-card .bg-pattern {
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            opacity: 0.05;
            background-size: 30px 30px;
            background-image: radial-gradient(circle, currentColor 1px, transparent 1px);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .activity-item {
            border-left: 3px solid;
            transition: all 0.3s;
        }
        
        .activity-item:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        [data-bs-theme="dark"] .activity-item:hover {
            background-color: rgba(255,255,255,0.05);
        }
        
        .leaderboard-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        [data-bs-theme="dark"] .leaderboard-item {
            border-bottom-color: rgba(255,255,255,0.05);
        }
        
        .leaderboard-item .rank {
            font-weight: bold;
            width: 30px;
            text-align: center;
        }
        
        .leaderboard-item .progress {
            height: 8px;
            flex-grow: 1;
            margin: 0 15px;
        }
        
        @media (max-width: 768px) {
            .chart-container {
                height: 250px;
            }
            
            .date-range-picker {
                flex-direction: column !important;
                gap: 10px !important;
            }
            
            .date-range-picker .btn {
                width: 100% !important;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">
                    <i class="bi bi-graph-up me-2"></i>Analytics Reports
                </h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Reports</li>
                    </ol>
                </nav>
            </div>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exportModal">
                    <i class="bi bi-download me-2"></i>Export Report
                </button>
            </div>
        </div>
        
        <!-- Date Range Picker -->
        <div class="card mb-4">
            <div class="card-body">
                <form id="dateRangeForm" method="GET" class="date-range-picker d-flex align-items-end gap-3">
                    <div class="flex-grow-1">
                        <label class="form-label">Date Range</label>
                        <div class="input-group">
                            <input type="text" class="form-control datepicker" name="start_date" 
                                   placeholder="Start date" value="<?= $startDate ?>">
                            <span class="input-group-text">to</span>
                            <input type="text" class="form-control datepicker" name="end_date" 
                                   placeholder="End date" value="<?= $endDate ?>">
                        </div>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-secondary range-btn" data-days="7">Last 7 Days</button>
                        <button type="button" class="btn btn-outline-secondary range-btn" data-days="30">Last 30 Days</button>
                        <button type="button" class="btn btn-outline-secondary range-btn" data-days="90">Last 90 Days</button>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel me-1"></i>Apply
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="stat-card text-white" style="background: linear-gradient(135deg, #4361ee, #3a0ca3);">
                    <div class="bg-pattern"></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-uppercase mb-2">Total Verifications</h6>
                                <h3 class="mb-0">
                                    <?= number_format(array_sum(array_column($verificationStats, 'count'))) ?>
                                </h3>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                                <i class="bi bi-check-circle fs-4"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-white bg-opacity-25">
                                <?= date('M d', strtotime($startDate)) ?> - <?= date('M d', strtotime($endDate)) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card text-white" style="background: linear-gradient(135deg, #4cc9f0, #4895ef);">
                    <div class="bg-pattern"></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-uppercase mb-2">Valid Documents</h6>
                                <h3 class="mb-0">
                                    <?= number_format($verificationStats[array_search('valid', array_column($verificationStats, 'status'))]['count'] ?? 0) ?>
                                </h3>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                                <i class="bi bi-shield-check fs-4"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-white bg-opacity-25">
                                <?= round(($verificationStats[array_search('valid', array_column($verificationStats, 'status'))]['count'] ?? 0) / max(1, array_sum(array_column($verificationStats, 'count'))) * 100) ?>% of total
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card text-white" style="background: linear-gradient(135deg, #f8961e, #f3722c);">
                    <div class="bg-pattern"></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-uppercase mb-2">Tampered Documents</h6>
                                <h3 class="mb-0">
                                    <?= number_format($verificationStats[array_search('tampered', array_column($verificationStats, 'status'))]['count'] ?? 0) ?>
                                </h3>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                                <i class="bi bi-exclamation-triangle fs-4"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-white bg-opacity-25">
                                <?= round(($verificationStats[array_search('tampered', array_column($verificationStats, 'status'))]['count'] ?? 0) / max(1, array_sum(array_column($verificationStats, 'count'))) * 100) ?>% of total
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card text-white" style="background: linear-gradient(135deg, #f72585, #b5179e);">
                    <div class="bg-pattern"></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-uppercase mb-2">Documents Generated</h6>
                                <h3 class="mb-0">
                                    <?= number_format(array_sum(array_column($docGeneration, 'count'))) ?>
                                </h3>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                                <i class="bi bi-file-earmark-text fs-4"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-white bg-opacity-25">
                                <?= round(array_sum(array_column($docGeneration, 'count')) / max(1, array_sum(array_column($verificationStats, 'count'))) * 100) ?>% verified
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Verification Activity Chart -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Verification Activity</h5>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary chart-toggle active" data-chart="combined">Combined</button>
                            <button type="button" class="btn btn-outline-secondary chart-toggle" data-chart="verifications">Verifications</button>
                            <button type="button" class="btn btn-outline-secondary chart-toggle" data-chart="generations">Generations</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Verification Status Chart -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Verification Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="chart-container" style="height: 250px;">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Status</th>
                                                <th>Count</th>
                                                <th>Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($verificationStats as $stat): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-<?= 
                                                            $stat['status'] === 'valid' ? 'success' : 
                                                            ($stat['status'] === 'tampered' ? 'warning' : 'danger') 
                                                        ?> me-2">
                                                            <?= ucfirst($stat['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= number_format($stat['count']) ?></td>
                                                    <td>
                                                        <?= round($stat['count'] / max(1, array_sum(array_column($verificationStats, 'count'))) * 100) ?>%
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="fw-bold">
                                                <td>Total</td>
                                                <td><?= number_format(array_sum(array_column($verificationStats, 'count'))) ?></td>
                                                <td>100%</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Sidebar -->
            <div class="col-lg-4">
                <!-- Top Verifiers -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Top Verifiers</h5>
                    </div>
                    <div class="card-body">
                        <div class="leaderboard">
                            <?php foreach ($topVerifiers as $index => $verifier): ?>
                                <div class="leaderboard-item">
                                    <div class="rank"><?= $index + 1 ?></div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between">
                                            <span><?= htmlspecialchars($verifier['username']) ?></span>
                                            <span class="text-muted"><?= $verifier['count'] ?></span>
                                        </div>
                                        <div class="progress mt-1">
                                            <div class="progress-bar bg-primary" 
                                                 style="width: <?= ($verifier['count'] / max(1, $topVerifiers[0]['count'])) * 100 ?>%">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Documents by Center -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Documents by Center</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="height: 200px;">
                            <canvas id="centerChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Activity</h5>
                        <a href="verification_logs.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentActivity as $log): ?>
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
                                                • <?= date('M d, h:i A', strtotime($log['verification_time'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Export Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="export_report.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Report Type</label>
                            <select class="form-select" name="report_type" required>
                                <option value="summary">Summary Report</option>
                                <option value="verifications">Verifications Detail</option>
                                <option value="documents">Documents Detail</option>
                                <option value="full">Full Data Export</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Format</label>
                            <select class="form-select" name="format" required>
                                <option value="pdf">PDF</option>
                                <option value="excel">Excel</option>
                                <option value="csv">CSV</option>
                            </select>
                        </div>
                        <input type="hidden" name="start_date" value="<?= $startDate ?>">
                        <input type="hidden" name="end_date" value="<?= $endDate ?>">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-download me-2"></i>Export
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
    
    <!-- Additional JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date picker
        flatpickr('.datepicker', {
            mode: "range",
            dateFormat: "Y-m-d",
            defaultDate: ["<?= $startDate ?>", "<?= $endDate ?>"]
        });
        
        // Quick range buttons
        document.querySelectorAll('.range-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const days = parseInt(this.getAttribute('data-days'));
                const endDate = new Date();
                const startDate = new Date();
                startDate.setDate(endDate.getDate() - days);
                
                document.querySelector('input[name="start_date"]').value = formatDate(startDate);
                document.querySelector('input[name="end_date"]').value = formatDate(endDate);
            });
        });
        
        function formatDate(date) {
            return date.toISOString().split('T')[0];
        }
        
        // Chart data preparation
        const activityLabels = [];
        const verificationData = [];
        const generationData = [];
        
        <?php 
        // Fill in missing dates for smooth charts
        $period = new DatePeriod(
            new DateTime($startDate),
            new DateInterval('P1D'),
            new DateTime($endDate)
        );
        
        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $verificationCount = 0;
            $generationCount = 0;
            
            foreach ($dailyVerifications as $v) {
                if ($v['date'] == $dateStr) {
                    $verificationCount = $v['count'];
                    break;
                }
            }
            
            foreach ($docGeneration as $g) {
                if ($g['date'] == $dateStr) {
                    $generationCount = $g['count'];
                    break;
                }
            }
            
            echo "activityLabels.push('" . $date->format('M j') . "');";
            echo "verificationData.push($verificationCount);";
            echo "generationData.push($generationCount);";
        }
        ?>
        
        // Verification Status Chart (Doughnut)
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php foreach ($verificationStats as $stat): ?>
                        '<?= ucfirst($stat['status']) ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($verificationStats as $stat): ?>
                            <?= $stat['count'] ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#4cc9f0',
                        '#f8961e',
                        '#f72585'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const value = context.raw;
                                const percentage = Math.round((value / total) * 100);
                                return `${context.label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });
        
        // Activity Chart (Line)
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        const activityChart = new Chart(activityCtx, {
            type: 'line',
            data: {
                labels: activityLabels,
                datasets: [
                    {
                        label: 'Document Verifications',
                        data: verificationData,
                        borderColor: '#4361ee',
                        backgroundColor: 'rgba(67, 97, 238, 0.1)',
                        tension: 0.3,
                        fill: true,
                        borderWidth: 2
                    },
                    {
                        label: 'Documents Generated',
                        data: generationData,
                        borderColor: '#4cc9f0',
                        backgroundColor: 'rgba(76, 201, 240, 0.1)',
                        tension: 0.3,
                        fill: true,
                        borderWidth: 2,
                        hidden: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        
        // Center Chart (Bar)
        const centerCtx = document.getElementById('centerChart').getContext('2d');
        const centerChart = new Chart(centerCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($documentsByCenter as $center): ?>
                        '<?= htmlspecialchars($center['name']) ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Documents Generated',
                    data: [
                        <?php foreach ($documentsByCenter as $center): ?>
                            <?= $center['count'] ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#4361ee',
                        '#4cc9f0',
                        '#f8961e',
                        '#f72585',
                        '#3a0ca3'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    datalabels: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });
        
        // Chart toggle buttons
        document.querySelectorAll('.chart-toggle').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.chart-toggle').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const chartType = this.getAttribute('data-chart');
                const chart = activityChart;
                
                switch(chartType) {
                    case 'combined':
                        chart.data.datasets[0].hidden = false;
                        chart.data.datasets[1].hidden = true;
                        break;
                    case 'verifications':
                        chart.data.datasets[0].hidden = false;
                        chart.data.datasets[1].hidden = true;
                        break;
                    case 'generations':
                        chart.data.datasets[0].hidden = true;
                        chart.data.datasets[1].hidden = false;
                        break;
                }
                
                chart.update();
            });
        });
    </script>
</body>
</html>