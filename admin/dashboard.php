<?php
require_once '../includes/header.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Fetch counts for dashboard
$stmt = $pdo->query("SELECT COUNT(*) as quotation_count FROM quotations WHERE status = 'pending'");
$quotation_count = $stmt->fetch()['quotation_count'];

$stmt = $pdo->query("SELECT COUNT(*) as leave_count FROM leave_requests WHERE status = 'pending'");
$leave_count = $stmt->fetch()['leave_count'];

$stmt = $pdo->query("SELECT COUNT(*) as offset_count FROM offset_requests WHERE status = 'pending'");
$offset_count = $stmt->fetch()['offset_count'];

$stmt = $pdo->query("SELECT COUNT(*) as staff_count FROM users WHERE role = 'staff'");
$staff_count = $stmt->fetch()['staff_count'];
?>

<div class="row mb-4">
    <div class="col">
        <h2>Admin Dashboard</h2>
    </div>
</div>

<!-- Dashboard Cards -->
<div class="row row-cols-1 row-cols-md-4 g-4 mb-4">
    <div class="col">
        <div class="card h-100 shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Pending Quotations</h5>
                <p class="card-text display-4"><?php echo $quotation_count; ?></p>
                <a href="quotations.php" class="btn btn-primary">View Quotations</a>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card h-100 shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Leave Requests</h5>
                <p class="card-text display-4"><?php echo $leave_count; ?></p>
                <a href="leave_requests.php" class="btn btn-primary">View Requests</a>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card h-100 shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Offset Requests</h5>
                <p class="card-text display-4"><?php echo $offset_count; ?></p>
                <a href="offset_requests.php" class="btn btn-primary">View Requests</a>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card h-100 shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Total Staff</h5>
                <p class="card-text display-4"><?php echo $staff_count; ?></p>
                <a href="staff.php" class="btn btn-primary">Manage Staff</a>
            </div>
        </div>
    </div>
</div>

<!-- Quick Links -->
<div class="row mb-4">
    <div class="col">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Quick Links</h5>
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="products.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-box"></i> Manage Products
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="events.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-calendar"></i> Manage Events
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="reports.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-chart-bar"></i> View Reports
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="manage_video.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-video"></i> Manage Video
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>