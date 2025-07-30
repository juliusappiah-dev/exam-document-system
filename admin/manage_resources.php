<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

$pageTitle = "Manage Resources";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_center'])) {
        $centerName = trim($_POST['center_name']);
        $centerCode = trim($_POST['center_code']);
        
        if (!empty($centerName) && !empty($centerCode)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO exam_centers (name, center_code) VALUES (?, ?)");
                $stmt->execute([$centerName, $centerCode]);
                $centerSuccess = "Exam center added successfully!";
            } catch (PDOException $e) {
                $centerError = "Error adding exam center: " . $e->getMessage();
            }
        } else {
            $centerError = "Please fill all fields";
        }
    }
    
    if (isset($_POST['add_subject'])) {
        $subjectName = trim($_POST['subject_name']);
        $subjectCode = trim($_POST['subject_code']);
        
        if (!empty($subjectName) && !empty($subjectCode)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO subjects (name, subject_code) VALUES (?, ?)");
                $stmt->execute([$subjectName, $subjectCode]);
                $subjectSuccess = "Subject added successfully!";
            } catch (PDOException $e) {
                $subjectError = "Error adding subject: " . $e->getMessage();
            }
        } else {
            $subjectError = "Please fill all fields";
        }
    }
    
    // Handle deletions
    if (isset($_POST['delete_center'])) {
        $centerId = $_POST['center_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM exam_centers WHERE id = ?");
            $stmt->execute([$centerId]);
            $centerSuccess = "Exam center deleted successfully!";
        } catch (PDOException $e) {
            $centerError = "Error deleting exam center: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['delete_subject'])) {
        $subjectId = $_POST['subject_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
            $stmt->execute([$subjectId]);
            $subjectSuccess = "Subject deleted successfully!";
        } catch (PDOException $e) {
            $subjectError = "Error deleting subject: " . $e->getMessage();
        }
    }
}

// Fetch existing data
$examCenters = $pdo->query("SELECT * FROM exam_centers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <style>
        .resource-card {
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: none;
            transition: all 0.3s;
            overflow: hidden;
        }
        
        .resource-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 25px rgba(0,0,0,0.1);
        }
        
        .resource-card .card-header {
            background-color: rgba(67, 97, 238, 0.1);
            border-bottom: 1px solid rgba(67, 97, 238, 0.2);
        }
        
        .resource-item {
            border-left: 3px solid;
            transition: all 0.3s;
        }
        
        .resource-item:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        .center-item {
            border-left-color: #4361ee;
        }
        
        .subject-item {
            border-left-color: #4cc9f0;
        }
        
        .badge-code {
            font-family: 'Courier New', monospace;
            background-color: rgba(0,0,0,0.05);
            color: #4361ee;
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        @media (max-width: 768px) {
            .resource-grid {
                grid-template-columns: 1fr !important;
                gap: 1rem !important;
            }
            
            .resource-item {
                flex-direction: column !important;
            }
            
            .resource-actions {
                margin-top: 10px;
                justify-content: flex-start !important;
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
                    <i class="bi bi-collection me-2"></i>Manage Resources
                </h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Resources</li>
                    </ol>
                </nav>
            </div>
        </div>
        
        <!-- Main Content Grid -->
        <div class="resource-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <!-- Exam Centers Card -->
            <div class="resource-card card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-building me-2"></i>Exam Centers
                    </h5>
                    <span class="badge bg-primary rounded-pill">
                        <?= count($examCenters) ?> centers
                    </span>
                </div>
                <div class="card-body">
                    <!-- Add Center Form -->
                    <form method="POST" class="mb-4">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="center_name" class="form-label">Center Name</label>
                                <input type="text" class="form-control" id="center_name" name="center_name" 
                                       placeholder="e.g. Main Campus" required>
                            </div>
                            <div class="col-md-4">
                                <label for="center_code" class="form-label">Center Code</label>
                                <input type="text" class="form-control" id="center_code" name="center_code" 
                                       placeholder="e.g. CENT-001" required>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" name="add_center" class="btn btn-primary w-100">
                                    <i class="bi bi-plus-lg"></i> Add
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <?php if (isset($centerError)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($centerError) ?></div>
                    <?php elseif (isset($centerSuccess)): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($centerSuccess) ?></div>
                    <?php endif; ?>
                    
                    <!-- Centers List -->
                    <?php if (empty($examCenters)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-building fs-1 text-muted"></i>
                            <h5 class="mt-3">No exam centers found</h5>
                            <p class="text-muted">Add your first exam center using the form above</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($examCenters as $center): ?>
                                <div class="list-group-item resource-item center-item p-3 border-0 mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($center['name']) ?></h6>
                                            <span class="badge badge-code"><?= htmlspecialchars($center['center_code']) ?></span>
                                        </div>
                                        <div class="resource-actions d-flex gap-1">
                                            <button class="action-btn btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" data-bs-target="#editCenterModal" 
                                                    data-id="<?= $center['id'] ?>" data-name="<?= htmlspecialchars($center['name']) ?>" 
                                                    data-code="<?= htmlspecialchars($center['center_code']) ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this exam center?');">
                                                <input type="hidden" name="center_id" value="<?= $center['id'] ?>">
                                                <button type="submit" name="delete_center" class="action-btn btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Subjects Card -->
            <div class="resource-card card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-book me-2"></i>Subjects
                    </h5>
                    <span class="badge bg-info rounded-pill">
                        <?= count($subjects) ?> subjects
                    </span>
                </div>
                <div class="card-body">
                    <!-- Add Subject Form -->
                    <form method="POST" class="mb-4">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="subject_name" class="form-label">Subject Name</label>
                                <input type="text" class="form-control" id="subject_name" name="subject_name" 
                                       placeholder="e.g. Mathematics" required>
                            </div>
                            <div class="col-md-4">
                                <label for="subject_code" class="form-label">Subject Code</label>
                                <input type="text" class="form-control" id="subject_code" name="subject_code" 
                                       placeholder="e.g. MATH-101" required>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" name="add_subject" class="btn btn-primary w-100">
                                    <i class="bi bi-plus-lg"></i> Add
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <?php if (isset($subjectError)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($subjectError) ?></div>
                    <?php elseif (isset($subjectSuccess)): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($subjectSuccess) ?></div>
                    <?php endif; ?>
                    
                    <!-- Subjects List -->
                    <?php if (empty($subjects)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-book fs-1 text-muted"></i>
                            <h5 class="mt-3">No subjects found</h5>
                            <p class="text-muted">Add your first subject using the form above</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($subjects as $subject): ?>
                                <div class="list-group-item resource-item subject-item p-3 border-0 mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($subject['name']) ?></h6>
                                            <span class="badge badge-code"><?= htmlspecialchars($subject['subject_code']) ?></span>
                                        </div>
                                        <div class="resource-actions d-flex gap-1">
                                            <button class="action-btn btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" data-bs-target="#editSubjectModal" 
                                                    data-id="<?= $subject['id'] ?>" data-name="<?= htmlspecialchars($subject['name']) ?>" 
                                                    data-code="<?= htmlspecialchars($subject['subject_code']) ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this subject?');">
                                                <input type="hidden" name="subject_id" value="<?= $subject['id'] ?>">
                                                <button type="submit" name="delete_subject" class="action-btn btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Center Modal -->
    <div class="modal fade" id="editCenterModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Exam Center</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="update_center.php">
                    <input type="hidden" name="center_id" id="editCenterId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Center Name</label>
                            <input type="text" class="form-control" name="center_name" id="editCenterName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Center Code</label>
                            <input type="text" class="form-control" name="center_code" id="editCenterCode" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Subject Modal -->
    <div class="modal fade" id="editSubjectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="update_subject.php">
                    <input type="hidden" name="subject_id" id="editSubjectId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Subject Name</label>
                            <input type="text" class="form-control" name="subject_name" id="editSubjectName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject Code</label>
                            <input type="text" class="form-control" name="subject_code" id="editSubjectCode" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
    
    <script>
        // Initialize edit modals
        document.addEventListener('DOMContentLoaded', function() {
            // Center modal
            const centerModal = document.getElementById('editCenterModal');
            if (centerModal) {
                centerModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    document.getElementById('editCenterId').value = button.getAttribute('data-id');
                    document.getElementById('editCenterName').value = button.getAttribute('data-name');
                    document.getElementById('editCenterCode').value = button.getAttribute('data-code');
                });
            }
            
            // Subject modal
            const subjectModal = document.getElementById('editSubjectModal');
            if (subjectModal) {
                subjectModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    document.getElementById('editSubjectId').value = button.getAttribute('data-id');
                    document.getElementById('editSubjectName').value = button.getAttribute('data-name');
                    document.getElementById('editSubjectCode').value = button.getAttribute('data-code');
                });
            }
        });
    </script>
</body>
</html>