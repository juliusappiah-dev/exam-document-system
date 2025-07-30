<!-- Footer -->
<footer class="footer mt-auto py-3 bg-light border-top">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <span class="text-muted">
                    &copy; <?= date('Y') ?> Exam Security System 
                    <span class="d-none d-md-inline">•</span> 
                    <span class="d-block d-md-inline">v1.0.0</span>
                </span>
            </div>
            <div class="col-md-6 text-center text-md-end mt-2 mt-md-0">
                <span class="text-muted small">
                    <i class="bi bi-clock-history me-1"></i> Page loaded in <?= round(microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"], 3) ?>s
                    <span class="d-none d-md-inline">•</span> 
                    <span class="d-block d-md-inline">
                        <i class="bi bi-database me-1"></i> 
                        <?= round(memory_get_usage() / 1024 / 1024, 2) ?>MB memory
                    </span>
                </span>
            </div>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Page-specific scripts will be included by individual pages -->
<?php if (isset($pageScripts)): ?>
    <?php foreach ($pageScripts as $script): ?>
        <script src="<?= $script ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

<style>
    .footer {
        background-color: rgba(var(--bs-light-rgb), 0.5) !important;
        backdrop-filter: blur(10px);
    }
    
    [data-bs-theme="dark"] .footer {
        background-color: rgba(var(--bs-dark-rgb), 0.5) !important;
        border-color: rgba(var(--bs-border-color-rgb), 0.1) !important;
    }
    
    @media (max-width: 768px) {
        .footer {
            font-size: 0.85rem;
        }
    }
</style>