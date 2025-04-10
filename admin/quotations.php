<?php
require_once '../includes/header.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$success = '';
$error = '';

// Handle quotation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['quotation_id'])) {
        $quotation_id = (int)$_POST['quotation_id'];
        $action = $_POST['action'];
        
        try {
             if ($action === 'accept') {
                // Start transaction
                $pdo->beginTransaction();
                
                try {
                    // Get quotation items
                    $stmt = $pdo->prepare("SELECT product_id, quantity FROM quotation_items WHERE quotation_id = ?");
                    $stmt->execute([$quotation_id]);
                    $items = $stmt->fetchAll();
                    
                    // Update stock for each product
                    foreach ($items as $item) {
                        $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
                        $result = $stmt->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
                        
                        // Check if stock was sufficient
                        if ($stmt->rowCount() === 0) {
                            throw new Exception('Insufficient stock for one or more products');
                        }
                    }
                    
                    // Update quotation status
                    $stmt = $pdo->prepare("UPDATE quotations SET status = 'approved' WHERE id = ?");
                    $stmt->execute([$quotation_id]);
                    
                    // Add notification for reporting
                    $stmt = $pdo->prepare("INSERT INTO notifications (message) VALUES (?)");
                    $stmt->execute(["Quotation #$quotation_id has been accepted"]);
                    
                    // Commit transaction
                    $pdo->commit();
                    $success = 'Quotation accepted successfully';
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $pdo->rollBack();
                    $error = $e->getMessage();
                }
            } elseif ($action === 'cancel') {
                $stmt = $pdo->prepare("UPDATE quotations SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$quotation_id]);
                $success = 'Quotation cancelled successfully';
            }
        } catch (Exception $e) {
            $error = 'An error occurred while processing the request';
        }
    }
}

// Fetch pending quotations
$stmt = $pdo->query("SELECT q.*, 
                            GROUP_CONCAT(CONCAT(p.name, ' (', qi.quantity, ')') SEPARATOR ', ') as items
                     FROM quotations q
                     LEFT JOIN quotation_items qi ON q.id = qi.quotation_id
                     LEFT JOIN products p ON qi.product_id = p.id
                     WHERE q.status = 'pending'
                     GROUP BY q.id
                     ORDER BY q.created_at DESC");
$quotations = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col">
        <h2>Pending Quotations</h2>
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

<?php if (empty($quotations)): ?>
    <div class="alert alert-info">No pending quotations found.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Contact</th>
                    <th>Items</th>
                    <th>Total Amount</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($quotations as $quotation): ?>
                    <tr>
                        <td><?php echo $quotation['id']; ?></td>
                        <td><?php echo htmlspecialchars($quotation['customer_name']); ?></td>
                        <td>
                            <div><?php echo htmlspecialchars($quotation['customer_email']); ?></div>
                            <?php if ($quotation['customer_phone']): ?>
                                <div class="text-muted"><?php echo htmlspecialchars($quotation['customer_phone']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-link p-0" data-bs-toggle="modal" data-bs-target="#itemsModal<?php echo $quotation['id']; ?>">
                                View Items
                            </button>
                            <!-- Items Modal -->
                            <div class="modal fade" id="itemsModal<?php echo $quotation['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Quotation Items</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <?php
                                            $stmt = $pdo->prepare("SELECT p.name, p.price as unit_price, qi.quantity, qi.price
                                                                  FROM quotation_items qi
                                                                  JOIN products p ON qi.product_id = p.id
                                                                  WHERE qi.quotation_id = ?");
                                            $stmt->execute([$quotation['id']]);
                                            $items = $stmt->fetchAll();
                                            ?>
                                            <div class="table-responsive">
                                                <table class="table">
                                                    <thead>
                                                        <tr>
                                                            <th>Product</th>
                                                            <th>Unit Price</th>
                                                            <th>Quantity</th>
                                                            <th>Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($items as $item): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                                <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                                                <td><?php echo $item['quantity']; ?></td>
                                                                <td>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>$<?php echo number_format($quotation['total_amount'], 2); ?></td>
                        <td><?php echo date('M j, Y', strtotime($quotation['created_at'])); ?></td>
                        <td>
                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="quotation_id" value="<?php echo $quotation['id']; ?>">
                                <button type="submit" name="action" value="accept" class="btn btn-success btn-sm">
                                    <i class="fas fa-check"></i> Accept
                                </button>
                                <button type="submit" name="action" value="cancel" class="btn btn-danger btn-sm">
                                    <i class="fas fa-times"></i> Cancel
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