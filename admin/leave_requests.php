<?php
require_once '../includes/header.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$success = '';
$error = '';

// Handle leave request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['request_id'])) {
        $request_id = (int)$_POST['request_id'];
        $action = $_POST['action'];
        
        try {
            if ($action === 'approve') {
                $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'approved' WHERE id = ?");
                $stmt->execute([$request_id]);
                
                // Get user ID for notification
                $stmt = $pdo->prepare("SELECT user_id FROM leave_requests WHERE id = ?");
                $stmt->execute([$request_id]);
                $user_id = $stmt->fetch()['user_id'];
                
                // Add notification
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $stmt->execute([$user_id, "Your leave request #$request_id has been approved"]);
                
                $success = 'Leave request approved successfully';
            } elseif ($action === 'reject') {
                $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'rejected' WHERE id = ?");
                $stmt->execute([$request_id]);
                
                // Get user ID for notification
                $stmt = $pdo->prepare("SELECT user_id FROM leave_requests WHERE id = ?");
                $stmt->execute([$request_id]);
                $user_id = $stmt->fetch()['user_id'];
                
                // Add notification
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $stmt->execute([$user_id, "Your leave request #$request_id has been rejected"]);
                
                $success = 'Leave request rejected successfully';
            }
        } catch (Exception $e) {
            $error = 'An error occurred while processing the request';
        }
    }
}

// Fetch pending leave requests with user information
$stmt = $pdo->query("SELECT lr.*, u.full_name, u.email 
                     FROM leave_requests lr
                     JOIN users u ON lr.user_id = u.id
                     WHERE lr.status = 'pending'
                     ORDER BY lr.created_at DESC");
$requests = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col">
        <h2>Leave Requests</h2>
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
    <div class="alert alert-info">No pending leave requests found.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Staff Name</th>
                    <th>Email</th>
                    <th>Date Range</th>
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
                            <?php echo date('M j, Y', strtotime($request['start_date'])); ?> -
                            <?php echo date('M j, Y', strtotime($request['end_date'])); ?>
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

<?php require_once '../includes/footer.php'; ?>