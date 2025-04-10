<?php
require_once '../includes/header.php';

// Ensure user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit;
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_type = $_POST['leave_type'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $hours = $_POST['hours'] ?? '';
    if (empty($leave_type) || empty($reason) || empty($start_date) || empty($end_date)) {
            $error = 'All fields are required.';
        } elseif (!strtotime($start_date) || !strtotime($end_date)) {
            $error = 'Invalid date format.';
        } else {
            // Handle file upload
            $document_path = null;
            // Validate dates
            if (!strtotime($start_date) || !strtotime($end_date)) {
                $error = 'Invalid date format.';
            } elseif (strtotime($start_date) > strtotime($end_date)) {
                $error = 'Start date cannot be later than end date.';
            } else {
                if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                    $allowed_types = ['application/pdf'];
                    $file_type = $_FILES['document']['type'];

                    if (!in_array($file_type, $allowed_types)) {
                        $error = 'Only PDF files are allowed.';
                    } else {
                        $upload_dir = '../uploads/leave_documents/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }

                        $file_name = 'leave_' . uniqid() . '.pdf';
                        $document_path = $upload_dir . $file_name;

                        if (!move_uploaded_file($_FILES['document']['tmp_name'], $document_path)) {
                            $error = 'Failed to upload document.';
                        }
                    }
                }

                if (empty($error)) {
                    try {
                        $pdo->beginTransaction();

                        // Insert leave request with start and end dates
                        $stmt = $pdo->prepare("INSERT INTO leave_requests (user_id, leave_type, reason, document_path, start_date, end_date, status, created_at) 
                                              VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
                        $stmt->execute([$_SESSION['user_id'], $leave_type, $reason, $document_path, $start_date, $end_date]);

                        $pdo->commit();
                        $success = 'Leave request submitted successfully!';
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        $error = 'Failed to submit leave request. Please try again.';
                    }
                }
            }
        }
}

// Fetch all leave requests for the current user
$stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$leave_requests = $stmt->fetchAll();
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Leave Requests</h2>
        </div>
        <div class="col-md-4 text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newLeaveRequestModal">
                <i class="fas fa-plus"></i> New Leave Request
            </button>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Leave Requests Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($leave_requests)): ?>
                <p class="text-muted">No leave requests found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Leave Type</th>
                                <th>Dates</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Document</th>
                                <th>Submitted On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leave_requests as $request): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                                    <td>
                                        <?php
                                        echo date('M d, Y', strtotime($request['start_date'])) . ' to ' . 
                                             date('M d, Y', strtotime($request['end_date']));
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['reason']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $request['status'] === 'approved' ? 'success' : ($request['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                            <?php echo ucfirst(htmlspecialchars($request['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($request['document_path']): ?>
                                            <a href="<?php echo htmlspecialchars($request['document_path']); ?>" target="_blank" class="btn btn-sm btn-secondary">
                                                <i class="fas fa-file-pdf"></i> View
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">No document</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- New Leave Request Modal -->
<div class="modal fade" id="newLeaveRequestModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Leave Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="leave_type" class="form-label">Leave Type</label>
                        <select class="form-select" id="leave_type" name="leave_type" required>
                            <option value="">--SELECT--</option>
                            <option value="Official">Official Leave</option>
                            <option value="Sick">Sick Leave</option>
                            <option value="Leave of Absence">Leave of Absence</option>
                            <option value="Vacation">Vacation Leave</option>
                            <option value="Maternity">Maternity Leave</option>
                            <option value="Paternity">Paternity Leave</option>
                            <option value="Half Day">Half Day Leave</option>
                            <option value="Work From Home">Work From Home</option>
                            <option value="Out for Sampling">Out for Sampling</option>
                            <option value="Out for Recruitment">Out for Recruitment</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Leave Period</label>
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" required>
                            </div>

                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="document" class="form-label">Supporting Document (PDF only, optional)</label>
                        <input type="file" class="form-control" id="document" name="document" accept="application/pdf">
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startDate = document.querySelector('input[name="start_date"]');
    const endDate = document.querySelector('input[name="end_date"]');
    
    startDate.addEventListener('change', function() {
        endDate.min = this.value;
    });
    
    endDate.addEventListener('change', function() {
        if (startDate.value && this.value < startDate.value) {
            this.value = startDate.value;
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
