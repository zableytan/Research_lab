<?php
require_once '../includes/header.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Get date range for filtering
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Fetch accepted quotations within date range
$stmt = $pdo->prepare("SELECT q.*, 
                              GROUP_CONCAT(CONCAT(p.name, ' (', qi.quantity, ')') SEPARATOR ', ') as items
                       FROM quotations q
                       LEFT JOIN quotation_items qi ON q.id = qi.quotation_id
                       LEFT JOIN products p ON qi.product_id = p.id
                       WHERE q.status = 'approved'
                       AND q.created_at BETWEEN ? AND ?
                       GROUP BY q.id
                       ORDER BY q.created_at DESC");
$stmt->execute([$start_date, $end_date . ' 23:59:59']);
$quotations = $stmt->fetchAll();

// Calculate total revenue
$total_revenue = 0;
foreach ($quotations as $quotation) {
    $total_revenue += $quotation['total_amount'];
}

// Get top selling products
$stmt = $pdo->prepare("SELECT p.name, SUM(qi.quantity) as total_quantity, SUM(qi.quantity * qi.price) as total_revenue
                       FROM quotation_items qi
                       JOIN products p ON qi.product_id = p.id
                       JOIN quotations q ON qi.quotation_id = q.id
                       WHERE q.status = 'approved'
                       AND q.created_at BETWEEN ? AND ?
                       GROUP BY p.id
                       ORDER BY total_quantity DESC
                       LIMIT 5");
$stmt->execute([$start_date, $end_date . ' 23:59:59']);
$top_products = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col">
        <h2>Reports</h2>
    </div>
</div>

<!-- Date Range Filter -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" 
                       value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-4">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" 
                       value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Total Revenue</h5>
                <p class="display-4">$<?php echo number_format($total_revenue, 2); ?></p>
                <p class="text-muted">For period: <?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Total Orders</h5>
                <p class="display-4"><?php echo count($quotations); ?></p>
                <p class="text-muted">Approved quotations in this period</p>
            </div>
        </div>
    </div>
</div>

<!-- Top Selling Products -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h5 class="card-title">Top Selling Products</h5>
        <?php if (empty($top_products)): ?>
            <p class="text-muted">No data available for the selected period.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo $product['total_quantity']; ?></td>
                                <td>$<?php echo number_format($product['total_revenue'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Accepted Quotations -->
<div class="card shadow-sm">
    <div class="card-body">
        <h5 class="card-title">Approved Quotations</h5>
        <?php if (empty($quotations)): ?>
            <p class="text-muted">No approved quotations found for the selected period.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quotations as $quotation): ?>
                            <tr>
                                <td><?php echo $quotation['id']; ?></td>
                                <td>
                                    <div><?php echo htmlspecialchars($quotation['customer_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($quotation['customer_email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($quotation['items']); ?></td>
                                <td>$<?php echo number_format($quotation['total_amount'], 2); ?></td>
                                <td><?php echo date('M j, Y', strtotime($quotation['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>