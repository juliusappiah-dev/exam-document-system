<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

$pageTitle = "Document Management";

// Pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$searchQuery = isset($_GET['search']) ? $_GET['search'] : null;
$statusFilter = isset($_GET['status']) ? $_GET['status'] : null;
$centerFilter = isset($_GET['center']) ? $_GET['center'] : null;
$dateFilter = isset($_GET['date']) ? $_GET['date'] : null;

// Base query
$query = "
    SELECT 
        d.*, 
        ec.name as center_name, 
        s.name as subject_name, 
        b.batch_code,
        u.username as creator,
        (SELECT COUNT(*) FROM verification_logs WHERE document_id = d.id) as verification_count
    FROM documents d
    LEFT JOIN exam_centers ec ON d.exam_center_id = ec.id
    LEFT JOIN batches b ON d.batch_id = b.id
    LEFT JOIN subjects s ON b.subject_id = s.id
    LEFT JOIN users u ON d.created_by = u.id
";

$conditions = [];
$params = [];

if ($searchQuery) {
    $conditions[] = "(d.serial_number LIKE :search OR ec.name LIKE :search OR s.name LIKE :search)";
    $params[':search'] = "%$searchQuery%";
}

if ($statusFilter && in_array($statusFilter, ['verified', 'unverified'])) {
    $conditions[] = $statusFilter === 'verified' 
        ? "EXISTS (SELECT 1 FROM verification_logs WHERE document_id = d.id)"
        : "NOT EXISTS (SELECT 1 FROM verification_logs WHERE document_id = d.id)";
}

if ($centerFilter) {
    $conditions[] = "d.exam_center_id = :center";
    $params[':center'] = $centerFilter;
}

if ($dateFilter) {
    $conditions[] = "DATE(d.created_at) = :date";
    $params[':date'] = $dateFilter;
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY d.created_at DESC LIMIT :limit OFFSET :offset";

// Execute query
$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total documents
$countQuery = "SELECT COUNT(*) FROM documents d";
if (!empty($conditions)) {
    $countQuery .= " WHERE " . implode(" AND ", $conditions);
}
$stmt = $pdo->prepare($countQuery);
foreach ($params as $key => $value) {
    if ($key !== ':limit' && $key !== ':offset') {
        $stmt->bindValue($key, $value);
    }
}
$stmt->execute();
$totalDocuments = $stmt->fetchColumn();
$totalPages = ceil($totalDocuments / $limit);

// Get centers for filter dropdown
$centers = $pdo->query("SELECT id, name FROM exam_centers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .document-card {
            border-left: 4px solid;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .document-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .document-card.verified {
            border-left-color: #4cc9f0;
        }
        
        .document-card.unverified {
            border-left-color: #f8961e;
        }
        
        .document-card .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
        }
        
        .document-card .qr-preview {
            width: 80px;
            height: 80px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .document-card .qr-preview img {
            max-width: 100%;
            max-height: 100%;
        }
        
        .action-dropdown .dropdown-toggle::after {
            display: none;
        }
        
        @media (max-width: 768px) {
            .document-card {
                flex-direction: column !important;
            }
            
            .document-card .qr-preview {
                margin-bottom: 15px;
                align-self: center;
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
                    <i class="bi bi-file-earmark-text me-2"></i>Document Management
                </h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Documents</li>
                    </ol>
                </nav>
            </div>
            <a href="generate_qr.php" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Generate New
            </a>
        </div>
        
        <!-- Filter Card -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Serial, center or subject..." 
                                   value="<?= htmlspecialchars($searchQuery ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="verified" <?= $statusFilter === 'verified' ? 'selected' : '' ?>>Verified</option>
                            <option value="unverified" <?= $statusFilter === 'unverified' ? 'selected' : '' ?>>Unverified</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="center" class="form-label">Exam Center</label>
                        <select id="center" name="center" class="form-select">
                            <option value="">All Centers</option>
                            <?php foreach ($centers as $center): ?>
                                <option value="<?= $center['id'] ?>" <?= $centerFilter == $center['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($center['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="date" class="form-label">Date</label>
                        <input type="text" class="form-control datepicker" id="date" name="date" 
                               placeholder="Select date" value="<?= htmlspecialchars($dateFilter ?? '') ?>">
                    </div>
                    
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel me-2"></i>Apply Filters
                        </button>
                        <a href="documents.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-counterclockwise me-2"></i>Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Documents List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Document Records</h5>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">
                        Showing <?= ($offset + 1) ?>-<?= min($offset + $limit, $totalDocuments) ?> of <?= $totalDocuments ?>
                    </span>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#exportModal">
                        <i class="bi bi-download me-1"></i>Export
                    </button>
                </div>
            </div>
            
            <div class="card-body">
                <?php if (empty($documents)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-file-earmark-excel fs-1 text-muted"></i>
                        <h5 class="mt-3">No documents found</h5>
                        <p class="text-muted">Try adjusting your search filters</p>
                        <a href="generate_document.php" class="btn btn-primary mt-2">
                            <i class="bi bi-plus-circle me-2"></i>Generate First Document
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($documents as $doc): ?>
                            <div class="col-12">
                                <div class="document-card card d-flex flex-row p-3 <?= $doc['verification_count'] > 0 ? 'verified' : 'unverified' ?>">
                                    <div class="qr-preview me-3">
                                        <img src="data:image/svg+xml;base64,<?= base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24"><rect width="24" height="24" fill="#f8f9fa"/><path d="M3 3h18v18H3V3zm2 2v14h14V5H5zm2 2h2v2H7zm4 0h2v2h-2zm4 0h2v2h-2zm-8 4h2v2H7zm4 0h2v2h-2zm4 0h2v2h-2zm-8 4h2v2H7zm4 0h2v2h-2zm4 0h2v2h-2z" fill="#4361ee"/></svg>') ?>" 
                                             alt="QR Preview">
                                    </div>
                                    
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="mb-1"><?= htmlspecialchars($doc['serial_number']) ?></h5>
                                                <p class="mb-1 text-muted small">
                                                    <i class="bi bi-building me-1"></i> <?= htmlspecialchars($doc['center_name'] ?? 'N/A') ?>
                                                </p>
                                                <p class="mb-1 text-muted small">
                                                    <i class="bi bi-book me-1"></i> <?= htmlspecialchars($doc['subject_name'] ?? 'N/A') ?>
                                                    (Batch: <?= htmlspecialchars($doc['batch_code'] ?? 'N/A') ?>)
                                                </p>
                                                <p class="mb-0 text-muted small">
                                                    <i class="bi bi-calendar me-1"></i> <?= date('M d, Y H:i', strtotime($doc['created_at'])) ?>
                                                    • <i class="bi bi-person me-1"></i> <?= htmlspecialchars($doc['creator'] ?? 'System') ?>
                                                </p>
                                            </div>
                                            
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge rounded-pill bg-<?= $doc['verification_count'] > 0 ? 'success' : 'warning' ?>">
                                                    <?= $doc['verification_count'] > 0 ? 'Verified' : 'Unverified' ?>
                                                    <?php if ($doc['verification_count'] > 0): ?>
                                                        <span class="badge bg-white text-dark ms-1"><?= $doc['verification_count'] ?></span>
                                                    <?php endif; ?>
                                                </span>
                                                
                                                <div class="dropdown action-dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                                            data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="bi bi-three-dots-vertical"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <a class="dropdown-item" href="view_document.php?id=<?= $doc['id'] ?>">
                                                                <i class="bi bi-eye me-2"></i>View Details
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="print_document.php?id=<?= $doc['id'] ?>">
                                                                <i class="bi bi-printer me-2"></i>Print
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="verify_document.php?id=<?= $doc['id'] ?>">
                                                                <i class="bi bi-qr-code-scan me-2"></i>Verify Now
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" 
                                                               data-bs-target="#deleteModal" data-id="<?= $doc['id'] ?>">
                                                                <i class="bi bi-trash me-2"></i>Delete
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page-1 ?>&<?= http_build_query($_GET) ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query($_GET) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page+1 ?>&<?= http_build_query($_GET) ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Export Documents</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="export_documents.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Format</label>
                            <select class="form-select" name="format" required>
                                <option value="csv">CSV</option>
                                <option value="excel">Excel</option>
                                <option value="pdf">PDF</option>
                            </select>
                        </div>
                        <input type="hidden" name="filters" value="<?= htmlspecialchars(json_encode($_GET)) ?>">
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

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this document? This action cannot be undone.</p>
                    <p class="text-muted small">All verification records associated with this document will also be deleted.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="POST" action="delete_document.php">
                        <input type="hidden" name="id" id="deleteDocumentId">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-2"></i>Delete Permanently
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
    
    <!-- Additional JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date picker
        flatpickr('.datepicker', {
            dateFormat: "Y-m-d",
            allowInput: true
        });
        
        // Delete modal handler
        const deleteModal = document.getElementById('deleteModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const documentId = button.getAttribute('data-id');
                document.getElementById('deleteDocumentId').value = documentId;
            });
        }
    </script>
</body>
</html>