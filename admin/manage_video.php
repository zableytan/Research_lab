<?php
require_once '../config/database.php';
require_once '../includes/header.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$success = '';
$error = '';

// Handle video upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
            // Validate file type
            $allowed_types = ['video/mp4'];
            $file_type = mime_content_type($_FILES['video']['tmp_name']);
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception('Only MP4 videos are allowed.');
            }

            // Create upload directory if it doesn't exist
            $upload_dir = '../assets/videos/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Save with fixed filename
            $video_path = $upload_dir . 'welcome.mp4';
            
            // Remove old video if exists
            if (file_exists($video_path)) {
                unlink($video_path);
            }

            // Upload new video
            if (!move_uploaded_file($_FILES['video']['tmp_name'], $video_path)) {
                throw new Exception('Failed to upload video');
            }

            $success = 'Video has been updated successfully.';
        } else {
            throw new Exception('Please select a video file to upload.');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Check if current video exists
$current_video_exists = file_exists('../assets/videos/welcome.mp4');
?>

<div class="container mt-4">
    <h2>Manage Welcome Video</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Current Welcome Video</h5>
        </div>
        <div class="card-body">
            <?php if ($current_video_exists): ?>
                <div class="ratio ratio-16x9">
                    <video controls>
                        <source src="../assets/videos/welcome.mp4?v=<?php echo time(); ?>" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>
            <?php else: ?>
                <p class="text-muted">No video has been uploaded yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Upload New Video</h5>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="video" class="form-label">Select Video File (MP4 only)</label>
                    <input type="file" class="form-control" id="video" name="video" accept="video/mp4" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Upload Video
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>