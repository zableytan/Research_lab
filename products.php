<?php
require_once 'includes/header.php';

// Initialize shopping cart in session if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}

// Handle clear cart action
if (isset($_POST['clear_cart'])) {
    $_SESSION['cart'] = array();
    header('Location: products.php?cleared=1');
    exit;
}

// Handle add to cart action
if (isset($_POST['add_to_cart']) && isset($_POST['product_id'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    if (!isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] = $quantity;
    } else {
        $_SESSION['cart'][$product_id] += $quantity;
    }
    
    header('Location: products.php?added=1');
    exit;
}

// Fetch all products with their attributes
$stmt = $pdo->query("SELECT p.*, GROUP_CONCAT(CONCAT(pa.attribute_name, ': ', pa.attribute_value) SEPARATOR ', ') as attributes 
                     FROM products p 
                     LEFT JOIN product_attributes pa ON p.id = pa.product_id 
                     GROUP BY p.id");
$products = $stmt->fetchAll();

// Calculate cart total
$cart_total = 0;
if (!empty($_SESSION['cart'])) {
    $product_ids = array_keys($_SESSION['cart']);
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id IN ($placeholders)");
    $stmt->execute($product_ids);
    $cart_products = $stmt->fetchAll();
    
    foreach ($cart_products as $product) {
        $cart_total += $product['price'] * $_SESSION['cart'][$product['id']];
    }
}
?>

<!-- Products Section -->
<div class="row mb-4">
    <div class="col-md-8">
        <h2>Our Products</h2>
    </div>
    <div class="col-md-4 text-end">
        <div class="btn-group">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#cartModal">
                <i class="fas fa-shopping-cart"></i> Cart (<?php echo array_sum($_SESSION['cart']); ?>)
            </button>
            <form method="POST" action="" class="d-inline">
                <button type="submit" name="clear_cart" class="btn btn-danger" onclick="return confirm('Are you sure you want to clear the cart?')">
                    <i class="fas fa-trash"></i> Clear Cart
                </button>
            </form>
        </div>
    </div>
</div>

<?php if (isset($_GET['added'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Product added to cart successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['cleared'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Cart cleared successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row row-cols-1 row-cols-md-3 g-4 mb-4">
    <?php foreach ($products as $product): ?>
        <div class="col">
            <div class="card h-100">
                <?php if (isset($product['image_path']) && $product['image_path']): ?>
                    <img src="<?php echo htmlspecialchars($product['image_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>" style="height: 200px; object-fit: cover;">
                <?php endif; ?>
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                    <p class="card-text"><?php echo htmlspecialchars($product['description']); ?></p>
                    <?php if ($product['attributes']): ?>
                        <p class="card-text"><small class="text-muted"><?php echo htmlspecialchars($product['attributes']); ?></small></p>
                    <?php endif; ?>
                    <p class="card-text"><strong>Price: $<?php echo number_format($product['price'], 2); ?></strong></p>
                    <p class="card-text"><small class="text-muted">In Stock: <?php echo $product['stock']; ?></small></p>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <div class="input-group mb-3">
                            <input type="number" class="form-control" name="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>">
                            <button type="submit" name="add_to_cart" class="btn btn-primary">
                                <i class="fas fa-cart-plus"></i> Add to Cart
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Cart Modal -->
<div class="modal fade" id="cartModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Shopping Cart</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (empty($_SESSION['cart'])): ?>
                    <p>Your cart is empty.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart_products as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo $_SESSION['cart'][$product['id']]; ?></td>
                                        <td>$<?php echo number_format($product['price'], 2); ?></td>
                                        <td>$<?php echo number_format($product['price'] * $_SESSION['cart'][$product['id']], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                    <td><strong>$<?php echo number_format($cart_total, 2); ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <form action="quotation.php" method="POST" class="mt-3">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-file-invoice"></i> Request Quotation
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>