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
    $rendered_dates = $_POST['rendered_date'] ?? [];
    $rendered_hours = $_POST['rendered_hours'] ?? [];
    $rendered_reasons = $_POST['rendered_reason'] ?? [];
    $offset_dates = $_POST['offset_date'] ?? [];
    $offset_hours = $_POST['offset_hours'] ?? [];
    $offset_remarks = $_POST['offset_remarks'] ?? [];
    
    if (empty($rendered_dates) || empty($rendered_hours) || empty($rendered_reasons) || empty($offset_dates)) {
        $error = 'All fields are required.';
    } else {
        // Handle file upload
        $document_path = null;
        if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['application/pdf'];
            $file_type = $_FILES['document']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                $error = 'Only PDF files are allowed.';
            } else {
                $upload_dir = '../uploads/offset_documents/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = 'offset_' . uniqid() . '.pdf';
                $document_path = $upload_dir . $file_name;
                
                if (!move_uploaded_file($_FILES['document']['tmp_name'], $document_path)) {
                    $error = 'Failed to upload document.';
                }
            }
        }
        
        if (empty($error)) {
            try {
                $pdo->beginTransaction();
                
                // Insert main offset request for each rendered date
                // Insert main offset request once
                $stmt = $pdo->prepare("INSERT INTO offset_requests (user_id, status, document_path, created_at) 
                                      VALUES (?, 'pending', ?, NOW())");
                $stmt->execute([$_SESSION['user_id'], $document_path]);
                $offset_request_id = $pdo->lastInsertId();

                // Insert all rendered dates
                $stmt = $pdo->prepare("INSERT INTO rendered_dates (offset_request_id, rendered_date, hours, reason) VALUES (?, ?, ?, ?)");
                foreach ($rendered_dates as $key => $rendered_date) {
                    if (!empty($rendered_date) && !empty($rendered_hours[$key])) {
                        $stmt->execute([$offset_request_id, $rendered_date, $rendered_hours[$key], $rendered_reasons[$key]]);
                    }
                }
                
                // Insert all offset dates
                $stmt = $pdo->prepare("INSERT INTO offset_dates (offset_request_id, offset_date, hours, remarks) VALUES (?, ?, ?, ?)");
                foreach ($offset_dates as $offset_key => $date) {
                    if (!empty($date) && !empty($offset_hours[$offset_key])) {
                        $stmt->execute([$offset_request_id, $date, $offset_hours[$offset_key], $offset_remarks[$offset_key]]);
                    }
                }
                
                $pdo->commit();
                $success = 'Offset request submitted successfully!';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Failed to submit offset request. Please try again.';
            }
        }
    }
}

// Fetch all offset requests for the current user with rendered dates and offset dates
$stmt = $pdo->prepare("SELECT r.*, 
                              GROUP_CONCAT(DISTINCT CONCAT(rd.rendered_date, '|', rd.hours, '|', rd.reason) SEPARATOR '##') as rendered_dates_info,
                              GROUP_CONCAT(DISTINCT CONCAT(d.offset_date, '|', d.hours, '|', d.remarks) SEPARATOR '##') as offset_dates_info
                       FROM offset_requests r
                       LEFT JOIN rendered_dates rd ON r.id = rd.offset_request_id
                       LEFT JOIN offset_dates d ON r.id = d.offset_request_id
                       WHERE r.user_id = ?
                       GROUP BY r.id
                       ORDER BY r.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$offset_requests = $stmt->fetchAll();
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Offset Requests</h2>
        </div>
        <div class="col-md-4 text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newOffsetRequestModal">
                <i class="fas fa-plus"></i> New Offset Request
            </button>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Offset Requests Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($offset_requests)): ?>
                <p class="text-muted">No offset requests found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Rendered Date</th>
                                <th>Hours</th>
                                <th>Reason</th>
                                <th>Offset Dates</th>
                                <th>Status</th>
                                <th>Document</th>
                                <th>Submitted On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($offset_requests as $request): ?>
                                <tr>
                                    <td>
                                        <?php
                                        if ($request['rendered_dates_info']) {
                                            $rendered_dates = explode('##', $request['rendered_dates_info']);
                                            foreach ($rendered_dates as $date_info) {
                                                list($date, $hours, $reason) = explode('|', $date_info);
                                                echo date('M d, Y', strtotime($date)) . '<br>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($request['rendered_dates_info']) {
                                            $rendered_dates = explode('##', $request['rendered_dates_info']);
                                            foreach ($rendered_dates as $date_info) {
                                                list($date, $hours, $reason) = explode('|', $date_info);
                                                echo $hours . ' hrs<br>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($request['rendered_dates_info']) {
                                            $rendered_dates = explode('##', $request['rendered_dates_info']);
                                            foreach ($rendered_dates as $date_info) {
                                                list($date, $hours, $reason) = explode('|', $date_info);
                                                echo htmlspecialchars($reason) . '<br>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($request['offset_dates_info']) {
                                            $offset_dates = explode('##', $request['offset_dates_info']);
                                            foreach ($offset_dates as $date_info) {
                                                list($date, $hours, $remarks) = explode('|', $date_info);
                                                echo date('M d, Y', strtotime($date)) . ' (' . $hours . ' hrs)<br>';
                                            }
                                        }
                                        ?>
                                    </td>
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

<!-- New Offset Request Modal -->
<div class="modal fade" id="newOffsetRequestModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Offset Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data">
                    <h6 class="mb-3">Rendered Dates</h6>
                    <div id="rendered-dates-container">
                        <div class="rendered-date-entry mb-3">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label">Date</label>
                                    <input type="date" class="form-control" name="rendered_date[]" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Hours</label>
                                    <input type="number" class="form-control" name="rendered_hours[]" min="1" max="24" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Reason</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="rendered_reason[]" required>
                                        <button type="button" class="btn btn-danger remove-rendered-date" style="display: none;"><i class="fas fa-times"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <button type="button" class="btn btn-secondary btn-sm" id="add-rendered-date">
                            <i class="fas fa-plus"></i> Add Another Rendered Date
                        </button>
                    </div>

                    <h6 class="mb-3 mt-4">Offset Dates</h6>
                    <div id="offset-dates-container">
                        <div class="offset-date-entry mb-3">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label">Date</label>
                                    <input type="date" class="form-control" name="offset_date[]" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Hours</label>
                                    <input type="number" class="form-control" name="offset_hours[]" min="1" max="24" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Remarks</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="offset_remarks[]" required>
                                        <button type="button" class="btn btn-danger remove-date" style="display: none;"><i class="fas fa-times"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <button type="button" class="btn btn-secondary btn-sm" id="add-date">
                            <i class="fas fa-plus"></i> Add Another Date
                        </button>
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
    // Rendered dates functionality
    const renderedContainer = document.getElementById('rendered-dates-container');
    const addRenderedButton = document.getElementById('add-rendered-date');
    
    addRenderedButton.addEventListener('click', function() {
        const entry = renderedContainer.querySelector('.rendered-date-entry').cloneNode(true);
        entry.querySelector('.remove-rendered-date').style.display = 'block';
        renderedContainer.appendChild(entry);
        
        entry.querySelector('.remove-rendered-date').addEventListener('click', function() {
            entry.remove();
        });
    });

    // Offset dates functionality
    const offsetContainer = document.getElementById('offset-dates-container');
    const addOffsetButton = document.getElementById('add-date');
    
    addOffsetButton.addEventListener('click', function() {
        const entry = offsetContainer.querySelector('.offset-date-entry').cloneNode(true);
        entry.querySelector('.remove-date').style.display = 'block';
        offsetContainer.appendChild(entry);
        
        entry.querySelector('.remove-date').addEventListener('click', function() {
            entry.remove();
        });
    });
});
</script>

<!-- View Offset Request Modal -->
<?php foreach ($offset_requests as $request): ?>
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
                        <h6>Rendered Date</h6>
                        <p><?php echo date('M d, Y', strtotime($request['offset_date'])); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Hours</h6>
                        <p><?php echo htmlspecialchars($request['hours']); ?></p>
                    </div>
                </div>
                <div class="mb-3">
                    <h6>Reason</h6>
                    <p><?php echo htmlspecialchars($request['reason']); ?></p>
                </div>
                <div class="mb-3">
                    <h6>Status</h6>
                    <span class="badge bg-<?php echo $request['status'] === 'approved' ? 'success' : ($request['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                        <?php echo ucfirst(htmlspecialchars($request['status'])); ?>
                    </span>
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
                    <h6>Offset Dates</h6>
                    <?php
                    if ($request['offset_dates']) {
                        $dates = explode(',', $request['offset_dates']);
                        $hours = explode(',', $request['offset_hours']);
                        $remarks = explode(',', $request['offset_remarks']);
                        echo '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Date</th><th>Hours</th><th>Remarks</th></tr></thead><tbody>';
                        foreach ($dates as $key => $date) {
                            echo '<tr>';
                            echo '<td>' . date('M d, Y', strtotime($date)) . '</td>';
                            echo '<td>' . htmlspecialchars($hours[$key]) . '</td>';
                            echo '<td>' . htmlspecialchars($remarks[$key]) . '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody></table></div>';
                    } else {
                        echo '<p class="text-muted">No offset dates found.</p>';
                    }
                    ?>
                </div>
                <div class="mb-3">
                    <h6>Submitted On</h6>
                    <p><?php echo date('M d, Y h:i A', strtotime($request['created_at'])); ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php require_once '../includes/footer.php'; ?>
