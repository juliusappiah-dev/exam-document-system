<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle = "User Management";

// Pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$searchQuery = isset($_GET['search']) ? $_GET['search'] : null;
$roleFilter = isset($_GET['role']) ? $_GET['role'] : null;
$statusFilter = isset($_GET['status']) ? $_GET['status'] : null;

// Base query
$query = "SELECT * FROM users";
$conditions = [];
$params = [];

if ($searchQuery) {
    $conditions[] = "(username LIKE :search OR email LIKE :search)";
    $params[':search'] = "%$searchQuery%";
}

if ($roleFilter && in_array($roleFilter, ['admin', 'verifier'])) {
    $conditions[] = "role = :role";
    $params[':role'] = $roleFilter;
}

if ($statusFilter && in_array($statusFilter, ['active', 'inactive'])) {
    $conditions[] = "is_active = :status";
    $params[':status'] = $statusFilter === 'active' ? 1 : 0;
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

// Execute query
$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total users
$countQuery = "SELECT COUNT(*) FROM users";
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
$totalUsers = $stmt->fetchColumn();
$totalPages = ceil($totalUsers / $limit);

// Get activity stats
$activeUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
$adminsCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <style>
        .user-card {
            transition: all 0.3s;
            border-left: 4px solid;
            overflow: hidden;
        }
        
        .user-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .user-card.admin {
            border-left-color: #4361ee;
        }
        
        .user-card.verifier {
            border-left-color: #4cc9f0;
        }
        
        .user-card.inactive {
            opacity: 0.8;
            background-color: rgba(0,0,0,0.02);
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .status-toggle {
            width: 45px;
            height: 24px;
        }
        
        .role-badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        
        .stats-card {
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .user-card {
                flex-direction: column !important;
                text-align: center;
            }
            
            .user-avatar {
                margin-bottom: 15px;
            }
            
            .user-actions {
                justify-content: center !important;
                margin-top: 15px;
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
                    <i class="bi bi-people me-2"></i>User Management
                </h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Users</li>
                    </ol>
                </nav>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-plus-circle me-2"></i>Add User
            </button>
        </div>
        
        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stats-card card bg-primary bg-opacity-10 border-primary border-opacity-25">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase text-primary mb-2">Total Users</h6>
                                <h3 class="mb-0"><?= number_format($totalUsers) ?></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                                <i class="bi bi-people-fill text-primary fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stats-card card bg-success bg-opacity-10 border-success border-opacity-25">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase text-success mb-2">Active Users</h6>
                                <h3 class="mb-0"><?= number_format($activeUsers) ?></h3>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                                <i class="bi bi-check-circle-fill text-success fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stats-card card bg-warning bg-opacity-10 border-warning border-opacity-25">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase text-warning mb-2">Administrators</h6>
                                <h3 class="mb-0"><?= number_format($adminsCount) ?></h3>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                                <i class="bi bi-shield-fill-check text-warning fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filter Card -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-5">
                        <label for="search" class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Username or email..." 
                                   value="<?= htmlspecialchars($searchQuery ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="role" class="form-label">Role</label>
                        <select id="role" name="role" class="form-select">
                            <option value="">All Roles</option>
                            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="verifier" <?= $roleFilter === 'verifier' ? 'selected' : '' ?>>Verifier</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="d-flex gap-2 w-100">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-funnel me-1"></i>Filter
                            </button>
                            <a href="users.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Users List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">User Accounts</h5>
                <div class="text-muted small">
                    Showing <?= ($offset + 1) ?>-<?= min($offset + $limit, $totalUsers) ?> of <?= $totalUsers ?>
                </div>
            </div>
            
            <div class="card-body">
                <?php if (empty($users)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-people fs-1 text-muted"></i>
                        <h5 class="mt-3">No users found</h5>
                        <p class="text-muted">Try adjusting your search filters</p>
                        <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="bi bi-plus-circle me-2"></i>Add First User
                        </button>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($users as $user): ?>
                            <div class="list-group-item p-0 border-0 mb-3">
                                <div class="user-card card d-flex flex-row p-3 <?= $user['role'] ?> <?= $user['is_active'] ? '' : 'inactive' ?>">
                                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['username']) ?>&background=<?= $user['role'] === 'admin' ? '4361ee' : '4cc9f0' ?>&color=fff" 
                                         class="user-avatar me-3" alt="User Avatar">
                                    
                                    <div class="flex-grow-1">
                                        <div class="d-flex flex-column h-100">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h5 class="mb-1">
                                                        <?= htmlspecialchars($user['username']) ?>
                                                        <?php if (!$user['is_active']): ?>
                                                            <span class="badge bg-secondary ms-2">Inactive</span>
                                                        <?php endif; ?>
                                                    </h5>
                                                    <p class="mb-1 text-muted small">
                                                        <i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($user['email']) ?>
                                                    </p>
                                                    <p class="mb-0 text-muted small">
                                                        <i class="bi bi-calendar me-1"></i> Joined <?= date('M d, Y', strtotime($user['created_at'])) ?>
                                                        <?php if ($user['last_login']): ?>
                                                            • Last login: <?= date('M d, Y', strtotime($user['last_login'])) ?>
                                                        <?php else: ?>
                                                            • Never logged in
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                                
                                                <div class="d-flex flex-column align-items-end">
                                                    <span class="role-badge badge bg-<?= $user['role'] === 'admin' ? 'primary' : 'info' ?> mb-2">
                                                        <?= ucfirst($user['role']) ?>
                                                    </span>
                                                    <form class="toggle-form" action="toggle_user_status.php" method="POST">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input status-toggle" type="checkbox" 
                                                                   name="is_active" <?= $user['is_active'] ? 'checked' : '' ?> 
                                                                   onchange="this.form.submit()">
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                            
                                            <div class="user-actions mt-auto d-flex justify-content-between align-items-center">
                                                <div class="small text-muted">
                                                    <?php if ($user['last_login']): ?>
                                                        Last active: <?= time_elapsed_string($user['last_login']) ?>
                                                    <?php else: ?>
                                                        Never logged in
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                            type="button" data-bs-toggle="dropdown">
                                                        <i class="bi bi-three-dots-vertical"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <a class="dropdown-item" href="#" data-bs-toggle="modal" 
                                                               data-bs-target="#editUserModal" data-user-id="<?= $user['id'] ?>">
                                                                <i class="bi bi-pencil me-2"></i>Edit
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="user_activity.php?id=<?= $user['id'] ?>">
                                                                <i class="bi bi-activity me-2"></i>Activity Log
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" 
                                                               data-bs-target="#deleteUserModal" data-user-id="<?= $user['id'] ?>">
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

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addUserForm" action="add_user.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="verifier">Verifier</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" name="password" id="passwordField" class="form-control" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-outline-primary" type="button" id="generatePassword">
                                    <i class="bi bi-arrow-repeat"></i> Generate
                                </button>
                            </div>
                            <div class="form-text">Minimum 8 characters</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editUserForm" action="edit_user.php" method="POST">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" id="editUsername" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="editEmail" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" id="editRole" class="form-select" required>
                                <option value="verifier">Verifier</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="is_active" id="editStatus" class="form-select" required>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reset Password</label>
                            <div class="input-group">
                                <input type="password" name="password" id="editPassword" class="form-control" placeholder="Leave blank to keep current">
                                <button class="btn btn-outline-secondary" type="button" id="toggleEditPassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-outline-primary" type="button" id="generateEditPassword">
                                    <i class="bi bi-arrow-repeat"></i> Generate
                                </button>
                            </div>
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

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this user account? This action cannot be undone.</p>
                    <p class="text-muted small">All activity records associated with this user will also be deleted.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteUserForm" method="POST" action="delete_user.php">
                        <input type="hidden" name="user_id" id="deleteUserId">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-2"></i>Delete User
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
    
    <script>
        // Password visibility toggle
        function setupPasswordToggle(buttonId, fieldId) {
            const button = document.getElementById(buttonId);
            const field = document.getElementById(fieldId);
            
            if (button && field) {
                button.addEventListener('click', function() {
                    const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
                    field.setAttribute('type', type);
                    button.innerHTML = type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
                });
            }
        }
        
        // Generate random password
        function generatePassword(fieldId) {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()';
            let password = '';
            for (let i = 0; i < 12; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById(fieldId).value = password;
        }
        
        // Initialize modals
        document.addEventListener('DOMContentLoaded', function() {
            // Password toggles
            setupPasswordToggle('togglePassword', 'passwordField');
            setupPasswordToggle('toggleEditPassword', 'editPassword');
            
            // Password generators
            document.getElementById('generatePassword')?.addEventListener('click', () => generatePassword('passwordField'));
            document.getElementById('generateEditPassword')?.addEventListener('click', () => generatePassword('editPassword'));
            
            // Edit user modal
            const editModal = document.getElementById('editUserModal');
            if (editModal) {
                editModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const userId = button.getAttribute('data-user-id');
                    
                    // Fetch user data via AJAX
                    fetch(`get_user.php?id=${userId}`)
                        .then(response => response.json())
                        .then(user => {
                            document.getElementById('editUserId').value = user.id;
                            document.getElementById('editUsername').value = user.username;
                            document.getElementById('editEmail').value = user.email;
                            document.getElementById('editRole').value = user.role;
                            document.getElementById('editStatus').value = user.is_active;
                        });
                });
            }
            
            // Delete user modal
            const deleteModal = document.getElementById('deleteUserModal');
            if (deleteModal) {
                deleteModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const userId = button.getAttribute('data-user-id');
                    document.getElementById('deleteUserId').value = userId;
                });
            }
        });
        
        // Helper function for time elapsed string
        function time_elapsed_string(datetime) {
            const date = new Date(datetime);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);
            
            let interval = Math.floor(seconds / 31536000);
            if (interval >= 1) return interval + " year" + (interval === 1 ? "" : "s") + " ago";
            
            interval = Math.floor(seconds / 2592000);
            if (interval >= 1) return interval + " month" + (interval === 1 ? "" : "s") + " ago";
            
            interval = Math.floor(seconds / 86400);
            if (interval >= 1) return interval + " day" + (interval === 1 ? "" : "s") + " ago";
            
            interval = Math.floor(seconds / 3600);
            if (interval >= 1) return interval + " hour" + (interval === 1 ? "" : "s") + " ago";
            
            interval = Math.floor(seconds / 60);
            if (interval >= 1) return interval + " minute" + (interval === 1 ? "" : "s") + " ago";
            
            return Math.floor(seconds) + " second" + (seconds === 1 ? "" : "s") + " ago";
        }
    </script>
</body>
</html>