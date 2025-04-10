<?php
require_once '../includes/header.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$success = '';
$error = '';

// Handle offset request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['request_id'])) {
        $request_id = (int)$_POST['request_id'];
        $action = $_POST['action'];
        
        try {
            if ($action === 'approve') {
                $stmt = $pdo->prepare("UPDATE offset_requests SET status = 'approved' WHERE id = ?");
                $stmt->execute([$request_id]);
                
                // Get user ID for notification
                $stmt = $pdo->prepare("SELECT user_id FROM offset_requests WHERE id = ?");
                $stmt->execute([$request_id]);
                $user_id = $stmt->fetch()['user_id'];
                
                // Add notification
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $stmt->execute([$user_id, "Your offset request #$request_id has been approved"]);
                
                $success = 'Offset request approved successfully';
            } elseif ($action === 'reject') {
                $stmt = $pdo->prepare("UPDATE offset_requests SET status = 'rejected' WHERE id = ?");
                $stmt->execute([$request_id]);
                
                // Get user ID for notification
                $stmt = $pdo->prepare("SELECT user_id FROM offset_requests WHERE id = ?");
                $stmt->execute([$request_id]);
                $user_id = $stmt->fetch()['user_id'];
                
                // Add notification
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $stmt->execute([$user_id, "Your offset request #$request_id has been rejected"]);
                
                $success = 'Offset request rejected successfully';
            }
        } catch (Exception $e) {
            $error = 'An error occurred while processing the request';
        }
    }
}

// Fetch pending offset requests with user information
$stmt = $pdo->query("SELECT r.*, u.full_name, u.email,
                     GROUP_CONCAT(DISTINCT CONCAT(rd.rendered_date, '|', rd.hours, '|', rd.reason) SEPARATOR '##') as rendered_dates_info,
                     GROUP_CONCAT(DISTINCT CONCAT(od.offset_date, '|', od.hours, '|', od.remarks) SEPARATOR '##') as offset_dates_info
                     FROM offset_requests r
                     JOIN users u ON r.user_id = u.id
                     LEFT JOIN rendered_dates rd ON r.id = rd.offset_request_id
                     LEFT JOIN offset_dates od ON r.id = od.offset_request_id
                     WHERE r.status = 'pending'
                     GROUP BY r.id
                     ORDER BY r.created_at DESC");
$requests = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col">
        <h2>Offset Requests</h2>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (empty($requests)): ?>
    <div class="alert alert-info">No pending offset requests found.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Staff Name</th>
                    <th>Email</th>
                    <th>Offset Date</th>
                    <th>Reason</th>
                    <th>Document</th>
                    <th>Submitted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $request): ?>
                    <tr>
                        <td><?php echo $request['id']; ?></td>
                        <td><?php echo htmlspecialchars($request['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($request['email']); ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#viewOffsetModal<?php echo $request['id']; ?>">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </td>
                        <td><?php echo htmlspecialchars($request['reason']); ?></td>
                        <td>
                            <?php if ($request['document_path']): ?>
                                <a href="<?php echo htmlspecialchars($request['document_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-file-pdf"></i> View
                                </a>
                            <?php else: ?>
                                <span class="text-muted">No document</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                        <td>
                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- View Offset Request Modal -->
<?php foreach ($requests as $request): ?>
<div class="modal fade" id="viewOffsetModal<?php echo $request['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">View Offset Request #<?php echo $request['id']; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>Staff Information</h6>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($request['full_name']); ?><br>
                           <strong>Email:</strong> <?php echo htmlspecialchars($request['email']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Request Status</h6>
                        <span class="badge bg-warning">Pending</span>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <h6>Rendered Dates</h6>
                        <?php if ($request['rendered_dates_info']): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Hours</th>
                                            <th>Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $rendered_dates = explode('##', $request['rendered_dates_info']);
                                        foreach ($rendered_dates as $date_info): 
                                            list($date, $hours, $reason) = explode('|', $date_info);
                                        ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($date)); ?></td>
                                            <td><?php echo htmlspecialchars($hours); ?></td>
                                            <td><?php echo htmlspecialchars($reason); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No rendered dates found.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <h6>Offset Dates</h6>
                        <?php if ($request['offset_dates_info']): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Hours</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $offset_dates = explode('##', $request['offset_dates_info']);
                                        foreach ($offset_dates as $date_info): 
                                            list($date, $hours, $remarks) = explode('|', $date_info);
                                        ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($date)); ?></td>
                                            <td><?php echo htmlspecialchars($hours); ?></td>
                                            <td><?php echo htmlspecialchars($remarks); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No offset dates found.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mb-3">
                    <h6>Reason</h6>
                    <p><?php echo htmlspecialchars($request['reason']); ?></p>
                </div>
                <?php if ($request['document_path']): ?>
                <div class="mb-3">
                    <h6>Supporting Document</h6>
                    <a href="<?php echo htmlspecialchars($request['document_path']); ?>" target="_blank" class="btn btn-sm btn-secondary">
                        <i class="fas fa-file-pdf"></i> View Document
                    </a>
                </div>
                <?php endif; ?>
                <div class="mb-3">
                    <h6>Submitted On</h6>
                    <p><?php echo date('M d, Y h:i A', strtotime($request['created_at'])); ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <form method="POST" action="" class="d-inline">
                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                    <button type="submit" name="action" value="approve" class="btn btn-success">
                        <i class="fas fa-check"></i> Approve
                    </button>
                    <button type="submit" name="action" value="reject" class="btn btn-danger">
                        <i class="fas fa-times"></i> Reject
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php require_once '../includes/footer.php'; ?>