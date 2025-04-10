<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Initialize variables
$error = '';
$success = '';

// Check if cart is empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: products.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate customer information
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if (empty($customer_name) || empty($customer_email)) {
        $error = 'Please provide your name and email.';
    } elseif (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } else {
        try {
            // Calculate total amount
            $total_amount = 0;
            foreach ($_SESSION['cart'] as $product_id => $quantity) {
                $stmt = $pdo->prepare('SELECT price FROM products WHERE id = ?');
                $stmt->execute([$product_id]);
                $product = $stmt->fetch();
                $total_amount += $product['price'] * $quantity;
            }

            // Begin transaction
            $pdo->beginTransaction();

            // Insert quotation
            $stmt = $pdo->prepare('INSERT INTO quotations (customer_name, customer_email, customer_phone, remarks, total_amount) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$customer_name, $customer_email, $customer_phone, $remarks, $total_amount]);
            $quotation_id = $pdo->lastInsertId();

            // Insert quotation items
            $stmt = $pdo->prepare('INSERT INTO quotation_items (quotation_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
            foreach ($_SESSION['cart'] as $product_id => $quantity) {
                $price_stmt = $pdo->prepare('SELECT price FROM products WHERE id = ?');
                $price_stmt->execute([$product_id]);
                $product = $price_stmt->fetch();
                $stmt->execute([$quotation_id, $product_id, $quantity, $product['price']]);
            }

            // Commit transaction
            $pdo->commit();

            // Clear cart
            unset($_SESSION['cart']);

            $success = 'Your quotation request has been submitted successfully. We will contact you soon.';
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $error = 'An error occurred while processing your request. Please try again.';
        }
    }
}

// Calculate cart total (only if cart exists and no success message)
$cart_total = 0;
if (!$success && isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        $stmt = $pdo->prepare('SELECT price FROM products WHERE id = ?');
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        $cart_total += $product['price'] * $quantity;
    }
        }
?>

<div class="container mt-4">
    <h2>Request Quotation</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <p>You will be redirected to the products page in 5 seconds...</p>
        <script>
            setTimeout(function() {
                window.location.href = 'products.php';
            }, 5000);
        </script>
    <?php else: ?>
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="customer_name" class="form-label">Your Name *</label>
                        <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="customer_email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="customer_email" name="customer_email" required>
                    </div>
                    <div class="mb-3">
                        <label for="customer_phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="customer_phone" name="customer_phone">
                    </div>
                    <div class="mb-3">
                        <label for="remarks" class="form-label">Additional Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="3"></textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])): ?>
                                        <?php foreach ($_SESSION['cart'] as $product_id => $quantity): 
                                            $stmt = $pdo->prepare('SELECT name, price FROM products WHERE id = ?');
                                            $stmt->execute([$product_id]);
                                            $product = $stmt->fetch();
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                <td><?php echo $quantity; ?></td>
                                                <td>$<?php echo number_format($product['price'] * $quantity, 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="2"><strong>Total</strong></td>
                                        <td><strong>$<?php echo number_format($cart_total, 2); ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Submit Quotation Request
                </button>
                <a href="products.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Products
                </a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
