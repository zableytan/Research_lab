<?php
require_once '../includes/header.php';

// Ensure user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit;
}

// Fetch staff information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'staff'");
$stmt->execute([$_SESSION['user_id']]);
$staff = $stmt->fetch();

// Fetch leave requests
$stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$leave_requests = $stmt->fetchAll();

// Fetch offset requests
$stmt = $pdo->prepare("SELECT * FROM offset_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$offset_requests = $stmt->fetchAll();
?>

<div class="container py-4">
    <h2 class="mb-4">Staff Dashboard</h2>

    <!-- Profile Section -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-2">
                    <img src="../assets/images/default-avatar.svg" alt="Profile" class="img-fluid rounded-circle">
                </div>
                <div class="col-md-10">
                    <h3><?php echo htmlspecialchars($staff['full_name']); ?></h3>
                    <p class="text-muted mb-2"><?php echo htmlspecialchars($staff['position']); ?></p>
                    <p class="mb-2"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($staff['email']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Leave Requests Section -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Leave Requests</h5>
                    <a href="leave_requests.php" class="btn btn-primary btn-sm">New Request</a>
                </div>
                <div class="card-body">
                    <?php if (empty($leave_requests)): ?>
                        <p class="text-muted">No leave requests found.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($leave_requests as $request): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($request['leave_type']); ?></h6>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($request['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-1">Status: <span class="badge bg-<?php echo $request['status'] === 'approved' ? 'success' : ($request['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst(htmlspecialchars($request['status'])); ?>
                                    </span></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Offset Requests Section -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Offset Requests</h5>
                    <a href="offset_requests.php" class="btn btn-primary btn-sm">New Request</a>
                </div>
                <div class="card-body">
                    <?php if (empty($offset_requests)): ?>
                        <p class="text-muted">No offset requests found.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($offset_requests as $request): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo date('M d, Y', strtotime($request['offset_date'])); ?></h6>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($request['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-1">Status: <span class="badge bg-<?php echo $request['status'] === 'approved' ? 'success' : ($request['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst(htmlspecialchars($request['status'])); ?>
                                    </span></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>