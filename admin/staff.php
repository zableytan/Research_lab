<?php
require_once '../includes/header.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Create staff photos directory if it doesn't exist
$upload_dir = '../uploads/staff_photos/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$success = '';
$error = '';

// Handle staff actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $username = trim($_POST['username']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $photo_path = 'assets/images/default-avatar.svg';

                // Handle photo upload
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $file_info = pathinfo($_FILES['photo']['name']);
                    $ext = strtolower($file_info['extension']);
                    
                    // Validate file type
                    if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                        $new_filename = 'staff_' . uniqid() . '.' . $ext;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                            $photo_path = 'uploads/staff_photos/' . $new_filename;
                        } else {
                            $error = 'Failed to upload photo. Using default avatar.';
                        }
                    } else {
                        $error = 'Invalid file type. Only JPG, JPEG, and PNG files are allowed.';
                    }
                }
                
                try {
                    // Input validation
                    if (empty($name) || empty($email) || empty($username) || empty($_POST['password'])) {
                        $error = 'All fields are required.';
                        break;
                    }

                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $error = 'Please enter a valid email address.';
                        break;
                    }

                    // Start transaction
                    $pdo->beginTransaction();

                    // Check if email already exists
                    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                    $check_stmt->execute([$email]);
                    if ($check_stmt->fetchColumn() > 0) {
                        $error = 'This email address is already registered in the system.';
                        $pdo->rollBack();
                        break;
                    }

                    // Check if username already exists
                    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                    $check_stmt->execute([$username]);
                    if ($check_stmt->fetchColumn() > 0) {
                        $error = 'This username is already taken. Please choose a different username.';
                        $pdo->rollBack();
                        break;
                    }

                    // Insert new staff member
                    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, username, password, photo_path, role, created_at) VALUES (?, ?, ?, ?, ?, 'staff', NOW())");
                    if ($stmt->execute([$name, $email, $username, $password, $photo_path])) {
                        $pdo->commit();
                        $success = 'Staff member added successfully';
                    } else {
                        $pdo->rollBack();
                        $error = 'Failed to add staff member. Please try again.';
                    }
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = 'Database error occurred. Please try again later.';
                    error_log('Staff addition error: ' . $e->getMessage());
                }
                break;

            case 'edit':
                $id = $_POST['user_id'];
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $username = trim($_POST['username']);
                
                // Handle photo upload for edit
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $file_info = pathinfo($_FILES['photo']['name']);
                    $ext = strtolower($file_info['extension']);
                    
                    if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                        $new_filename = 'staff_' . uniqid() . '.' . $ext;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                            // Get current photo path
                            $stmt = $pdo->prepare("SELECT photo_path FROM users WHERE id = ?");
                            $stmt->execute([$id]);
                            $current_photo = $stmt->fetchColumn();
                            
                            // Delete old photo if it exists and is not the default avatar
                            if ($current_photo && $current_photo !== 'assets/images/default-avatar.svg' && file_exists('../' . $current_photo)) {
                                unlink('../' . $current_photo);
                            }
                            
                            // Update photo path in database
                            $photo_path = 'uploads/staff_photos/' . $new_filename;
                            $stmt = $pdo->prepare("UPDATE users SET photo_path = ? WHERE id = ?");
                            $stmt->execute([$photo_path, $id]);
                        } else {
                            $error = 'Failed to upload photo.';
                        }
                    } else {
                        $error = 'Invalid file type. Only JPG, JPEG, and PNG files are allowed.';
                    }
                }
                
                try {
                    if (!empty($_POST['password'])) {
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, username = ?, password = ? WHERE id = ? AND role = 'staff'");
                        $stmt->execute([$name, $email, $username, $password, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, username = ? WHERE id = ? AND role = 'staff'");
                        $stmt->execute([$name, $email, $username, $id]);
                    }
                    $success = 'Staff information updated successfully';
                } catch (PDOException $e) {
                    $error = 'Error updating staff information';
                }
                break;

            case 'delete':
                $id = $_POST['user_id'];
                
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'staff'");
                    $stmt->execute([$id]);
                    $success = 'Staff member deleted successfully';
                } catch (PDOException $e) {
                    $error = 'Error deleting staff member';
                }
                break;
        }
    }
}

// Fetch all staff members
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$query = "SELECT * FROM users WHERE role = 'staff'";
$params = [];

if ($search) {
    $query .= " AND (full_name LIKE ? OR email LIKE ?)"; 
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$staff_members = $stmt->fetchAll();
?>

<div class="container-fluid px-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Staff Management</h2>
        </div>
        <div class="col text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                <i class="fas fa-plus"></i> Add New Staff
            </button>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Search and Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search by name or email" value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>

    <!-- Staff Table -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Username</th>
                            <th>Joined Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff_members as $staff): ?>
                            <tr>
                                <td>
                                    <img src="../<?php echo isset($staff['photo_path']) ? htmlspecialchars($staff['photo_path']) : 'assets/images/default-avatar.svg'; ?>" 
                                         alt="Profile Photo" 
                                         class="rounded-circle"
                                         style="width: 40px; height: 40px; object-fit: cover;">
                                </td>
                                <td><?php echo isset($staff['full_name']) ? htmlspecialchars($staff['full_name']) : ''; ?></td>
                                <td><?php echo isset($staff['email']) ? htmlspecialchars($staff['email']) : ''; ?></td>
                                <td><?php echo isset($staff['username']) ? htmlspecialchars($staff['username']) : ''; ?></td>
                                <td><?php echo isset($staff['created_at']) ? date('M d, Y', strtotime($staff['created_at'])) : ''; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editStaff(<?php echo htmlspecialchars(json_encode($staff)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteStaff(<?php echo $staff['id']; ?>, '<?php echo htmlspecialchars($staff['full_name']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($staff_members)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No staff members found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Photo</label>
                        <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Staff</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Staff Modal -->
<div class="modal fade" id="editStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="mb-3">
                        <label class="form-label">Photo</label>
                        <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png">
                        <small class="text-muted">Leave empty to keep current photo</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" id="edit_username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" name="password" class="form-control">
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

<script>
function editStaff(staff) {
    document.getElementById('edit_user_id').value = staff.id;
    document.getElementById('edit_name').value = staff.full_name;
    document.getElementById('edit_email').value = staff.email;
    document.getElementById('edit_username').value = staff.username;
    new bootstrap.Modal(document.getElementById('editStaffModal')).show();
}

function deleteStaff(id, name) {
    const escapedName = name.replace(/["'\\]/g, '\\$&');
    if (confirm(`Are you sure you want to delete staff member "${escapedName}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>