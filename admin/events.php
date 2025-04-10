<?php
require_once '../includes/header.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$success = '';
$error = '';

// Handle event actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        try {
            if ($action === 'add' || $action === 'update') {
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $event_date = $_POST['event_date'];
                $entrance_fee = (float)$_POST['entrance_fee'];
                $location = trim($_POST['location']);
                
                // Handle image upload
                $image_path = '';
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/events/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (!in_array($file_extension, $allowed_extensions)) {
                        throw new Exception('Invalid file type. Only JPG, JPEG, PNG and GIF files are allowed.');
                    }
                    
                    $image_path = $upload_dir . uniqid('event_') . '.' . $file_extension;
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                        throw new Exception('Failed to upload image');
                    }
                    $image_path = substr($image_path, 3); // Remove '../' from path
                }
                
                if (empty($title) || empty($event_date)) {
                    throw new Exception('Please fill in all required fields');
                }
                
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO events (title, description, event_date, entrance_fee, location, image_path) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $description, $event_date, $entrance_fee, $location, $image_path]);
                    $success = 'Event added successfully';
                } else {
                    $event_id = (int)$_POST['event_id'];
                    $stmt = $pdo->prepare("UPDATE events SET title = ?, description = ?, event_date = ?, entrance_fee = ?, location = ? WHERE id = ?");
                    $stmt->execute([$title, $description, $event_date, $entrance_fee, $location, $event_id]);
                    
                    if ($image_path) {
                        $stmt = $pdo->prepare("UPDATE events SET image_path = ? WHERE id = ?");
                        $stmt->execute([$image_path, $event_id]);
                    }
                    $success = 'Event updated successfully';
                }
            } elseif ($action === 'delete' && isset($_POST['event_id'])) {
                $event_id = (int)$_POST['event_id'];
                $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
                $stmt->execute([$event_id]);
                $success = 'Event deleted successfully';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Fetch all events
$stmt = $pdo->query("SELECT * FROM events ORDER BY event_date DESC");
$events = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col">
        <h2>Manage Events</h2>
    </div>
    <div class="col text-end">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#eventModal">
            <i class="fas fa-plus"></i> Add New Event
        </button>
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

<!-- Events Table -->
<div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Banner</th>
                <th>Title</th>
                <th>Description</th>
                <th>Date</th>
                <th>Location</th>
                <th>Entrance Fee</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($events as $event): ?>
                <tr>
                    <td>
                        <?php if (isset($event['image_path']) && $event['image_path']): ?>
                            <img src="../<?php echo htmlspecialchars($event['image_path']); ?>" alt="Event banner" style="max-width: 100px; max-height: 60px;">
                        <?php else: ?>
                            <span class="text-muted">No image</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($event['title']); ?></td>
                    <td><?php echo htmlspecialchars($event['description']); ?></td>
                    <td><?php echo date('F j, Y', strtotime($event['event_date'])); ?></td> 
                    <td><?php echo htmlspecialchars($event['location']); ?></td>
                    <td>
                        <?php if ($event['entrance_fee'] > 0): ?>
                            $<?php echo number_format($event['entrance_fee'], 2); ?>
                        <?php else: ?>
                            <span class="text-success">Free</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary" 
                                onclick="editEvent(<?php echo htmlspecialchars(json_encode($event)); ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" action="" class="d-inline" 
                              onsubmit="return confirm('Are you sure you want to delete this event?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add/Edit Event Modal -->
<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="eventForm" method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="event_id" id="event_id">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Event Title *</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="event_date" class="form-label">Event Date *</label>
                        <input type="date" class="form-control" id="event_date" name="event_date" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="entrance_fee" class="form-label">Entrance Fee</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="entrance_fee" name="entrance_fee" 
                                   step="0.01" min="0" value="0">
                        </div>
                        <div class="form-text">Leave as 0 for free entry</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="location" class="form-label">Location *</label>
                        <input type="text" class="form-control" id="location" name="location" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="image" class="form-label">Event Banner</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                        <div class="form-text">Supported formats: JPG, JPEG, PNG, GIF</div>
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function editEvent(event) {
    const form = document.getElementById('eventForm');
    form.elements['action'].value = 'update';
    form.elements['event_id'].value = event.id;
    form.elements['title'].value = event.title;
    form.elements['description'].value = event.description;
    form.elements['event_date'].value = event.event_date;
    form.elements['entrance_fee'].value = event.entrance_fee;
    form.elements['location'].value = event.location;
    
    // Update modal title
    document.querySelector('#eventModal .modal-title').textContent = 'Edit Event';
    
    // Show modal
    new bootstrap.Modal(document.getElementById('eventModal')).show();
}

// Reset form when adding new event
document.getElementById('eventModal').addEventListener('show.bs.modal', function(event) {
    if (!event.relatedTarget) return; // Don't run on programmatic show (edit)
    
    const form = document.getElementById('eventForm');
    form.reset();
    form.elements['action'].value = 'add';
    form.elements['event_id'].value = '';
    
    document.querySelector('#eventModal .modal-title').textContent = 'Add New Event';
});
</script>

<?php require_once '../includes/footer.php'; ?>