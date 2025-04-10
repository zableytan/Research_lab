<?php
require_once 'includes/header.php';

// Fetch all staff members
$stmt = $pdo->query("SELECT full_name, position, email, photo_path FROM users WHERE role = 'staff'");
$staff_members = $stmt->fetchAll();
?>

<!-- Hero Section with Video -->
<section class="hero-section py-5 text-center">
    <div class="container">
        <h1 class="display-4 mb-4">Welcome to Research Lab</h1>
        <div class="ratio ratio-16x9 mx-auto" style="max-width: 800px;">
            <video class="rounded shadow" controls>
                <source src="assets/videos/welcome.mp4" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        </div>
    </div>
</section>

<!-- Staff Directory Section -->
<section class="staff-directory py-5 bg-light">
    <div class="container">
        <h2 class="text-center mb-5">Our Team</h2>
        <div class="row row-cols-1 row-cols-md-3 g-4">
            <?php foreach($staff_members as $staff): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <img src="<?php echo isset($staff['photo_path']) ? htmlspecialchars($staff['photo_path']) : 'assets/images/default-avatar.svg'; ?>" 
                                     alt="<?php echo htmlspecialchars($staff['full_name']); ?>" 
                                     class="rounded-circle" width="100" height="100" style="object-fit: cover;">
                            </div>
                            <h5 class="card-title"><?php echo htmlspecialchars($staff['full_name']); ?></h5>
                            <p class="card-text text-muted"><?php echo htmlspecialchars($staff['position']); ?></p>
                            <p class="card-text"><small><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($staff['email']); ?></small></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>