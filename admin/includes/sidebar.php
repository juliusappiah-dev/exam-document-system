<div class="sidebar" id="sidebar">
    <!-- Mobile Header -->
    <div class="d-lg-none d-flex align-items-center justify-content-between p-3 border-bottom">
        <h5 class="mb-0 text-white">
            <i class="bi bi-shield-lock me-2"></i> ExamSecure
        </h5>
        <button class="btn btn-sm btn-outline-light" id="sidebarClose">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    
    <!-- Desktop Logo -->
    <div class="logo d-none d-lg-block">
        <h4><i class="bi bi-shield-lock"></i> ExamSecure</h4>
    </div>
    
    <!-- Navigation Menu -->
    <ul class="nav flex-column mt-3">
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                <i class="bi bi-speedometer2"></i> <span class="menu-text">Dashboard</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'generate_qr.php' ? 'active' : '' ?>" href="generate_qr.php">
                <i class="bi bi-file-earmark-plus me-2"></i> <span class="menu-text">Generate QR</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'manage_resources.php' ? 'active' : '' ?>" href="manage_resources.php">
                <i class="bi bi-list-check"></i> <span class="menu-text">Manage Resources</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'documents.php' ? 'active' : '' ?>" href="documents.php">
                <i class="bi bi-file-earmark-text"></i> <span class="menu-text">QR Codes</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'verification_logs.php' ? 'active' : '' ?>" href="verification_logs.php">
                <i class="bi bi-list-check"></i> <span class="menu-text">Verification Logs</span>
            </a>
        </li>
        
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>" href="users.php">
                <i class="bi bi-people"></i> <span class="menu-text">Users</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>" href="reports.php">
                <i class="bi bi-graph-up"></i> <span class="menu-text">Reports</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>" href="settings.php">
                <i class="bi bi-gear"></i> <span class="menu-text">Settings</span>
            </a>
        </li>
    </ul>
    
    <!-- User Profile -->
    <div class="user-profile mt-auto">
        <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['username'] ?? 'Admin') ?>&background=random" alt="User">
        <h6 class="mb-1"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></h6>
        <p class="text-muted small mb-0"><?= ucfirst($_SESSION['role'] ?? 'Admin') ?></p>
        <a href="../auth/logout.php" class="btn btn-sm btn-outline-light mt-2">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
    
    <!-- Mobile Toggle Button (shown only on mobile) -->
    <button class="btn btn-primary d-lg-none sidebar-toggle" id="sidebarToggle">
        <i class="bi bi-list"></i>
    </button>
</div>

<style>
    .sidebar {
        background: linear-gradient(180deg, var(--primary-dark), var(--primary-color));
        color: white;
        height: 100vh;
        position: fixed;
        width: 250px;
        transition: all 0.3s;
        box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        display: flex;
        flex-direction: column;
    }
    
    [data-bs-theme="dark"] .sidebar {
        box-shadow: 4px 0 10px rgba(0, 0, 0, 0.3);
    }
    
    .nav-link {
        color: rgba(255, 255, 255, 0.8);
        border-radius: 8px;
        margin: 5px 10px;
        padding: 10px 15px;
        transition: all 0.3s;
        display: flex;
        align-items: center;
    }
    
    .nav-link:hover, .nav-link.active {
        background-color: rgba(255, 255, 255, 0.1);
        color: white;
    }
    
    .nav-link i {
        margin-right: 10px;
        width: 20px;
        text-align: center;
    }
    
    .user-profile {
        padding: 20px;
        text-align: center;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .user-profile img {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        margin-bottom: 10px;
        border: 3px solid rgba(255, 255, 255, 0.2);
    }
    
    /* Mobile Styles */
    @media (max-width: 992px) {
        .sidebar {
            transform: translateX(-100%);
            width: 280px;
        }
        
        .sidebar.show {
            transform: translateX(0);
        }
        
        .main-content {
            margin-left: 0 !important;
        }
        
        .sidebar-toggle {
            position: fixed;
            bottom: 20px;
            left: 20px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            z-index: 999;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        
        .menu-text {
            display: inline;
        }
    }
    
    /* Desktop Styles */
    @media (min-width: 992px) {
        .sidebar-toggle {
            display: none !important;
        }
        
        .sidebar {
            transform: translateX(0) !important;
        }
    }
</style>

<script>
    // Mobile sidebar toggle
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarClose = document.getElementById('sidebarClose');
        
        // Toggle sidebar on mobile
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.add('show');
        });
        
        // Close sidebar
        sidebarClose.addEventListener('click', () => {
            sidebar.classList.remove('show');
        });
        
        // Close when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 992 && !sidebar.contains(e.target) && 
                e.target !== sidebarToggle && !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });
        
        // Auto-close sidebar when menu item clicked (mobile)
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 992) {
                    sidebar.classList.remove('show');
                }
            });
        });
    });
</script>