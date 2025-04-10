<?php
require_once 'includes/header.php';

// Fetch all upcoming events
$stmt = $pdo->query("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC");
$events = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col">
        <h2>Upcoming Events</h2>
    </div>
</div>

<div class="row row-cols-1 row-cols-md-3 g-4 mb-4">
    <?php if (empty($events)): ?>
        <div class="col-12">
            <div class="alert alert-info">
                No upcoming events at the moment.
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($events as $event): ?>
            <div class="col">
                <div class="card h-100 shadow-sm">
                    <?php if (isset($event['image_path']) && $event['image_path']): ?>
                        <img src="<?php echo htmlspecialchars($event['image_path']); ?>" class="card-img-top" alt="Event banner" style="max-height: 200px; object-fit: cover;">
                    <?php endif; ?>
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                        <p class="card-text"><?php echo htmlspecialchars($event['description']); ?></p>
                        <p class="card-text">
                            <small class="text-muted">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('F j, Y', strtotime($event['event_date'])); ?>
                            </small>
                            <small class="text-muted ms-3">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($event['location']); ?>
                            </small>
                            <?php if ($event['entrance_fee'] > 0): ?>
                                <span class="ms-3">
                                    <i class="fas fa-ticket-alt"></i>
                                    $<?php echo number_format($event['entrance_fee'], 2); ?>
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>