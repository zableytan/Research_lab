<?php
require_once '../includes/header.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$success = '';
$error = '';

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        try {
            if ($action === 'add' || $action === 'update') {
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $price = (float)$_POST['price'];
                $stock = (int)$_POST['stock'];
                $attributes = isset($_POST['attributes']) ? $_POST['attributes'] : [];
                
                // Handle image upload
                $image_path = '';
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/products/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (!in_array($file_extension, $allowed_extensions)) {
                        throw new Exception('Invalid file type. Only JPG, JPEG, PNG and GIF files are allowed.');
                    }
                    
                    $image_path = $upload_dir . uniqid('product_') . '.' . $file_extension;
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                        throw new Exception('Failed to upload image');
                    }
                    // Store the relative path from the web root
                    $image_path = 'uploads/products/' . basename($image_path);
                }
                
                if (empty($name) || $price <= 0) {
                    throw new Exception('Please fill in all required fields');
                }
                
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock, image_path) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $description, $price, $stock, $image_path]);
                    $product_id = $pdo->lastInsertId();
                    
                    // Add attributes
                    if (!empty($attributes)) {
                        $stmt = $pdo->prepare("INSERT INTO product_attributes (product_id, attribute_name, attribute_value) VALUES (?, ?, ?)");
                        foreach ($attributes as $attr) {
                            if (!empty($attr['name']) && !empty($attr['value'])) {
                                $stmt->execute([$product_id, $attr['name'], $attr['value']]);
                            }
                        }
                    }
                    
                    $success = 'Product added successfully';
                } else {
                    $product_id = (int)$_POST['product_id'];
                    $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, stock = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $price, $stock, $product_id]);
                    
                    if ($image_path) {
                        $stmt = $pdo->prepare("UPDATE products SET image_path = ? WHERE id = ?");
                        $stmt->execute([$image_path, $product_id]);
                    }
                    
                    // Update attributes
                    $stmt = $pdo->prepare("DELETE FROM product_attributes WHERE product_id = ?");
                    $stmt->execute([$product_id]);
                    
                    if (!empty($attributes)) {
                        $stmt = $pdo->prepare("INSERT INTO product_attributes (product_id, attribute_name, attribute_value) VALUES (?, ?, ?)");
                        foreach ($attributes as $attr) {
                            if (!empty($attr['name']) && !empty($attr['value'])) {
                                $stmt->execute([$product_id, $attr['name'], $attr['value']]);
                            }
                        }
                    }
                    
                    $success = 'Product updated successfully';
                }
            } elseif ($action === 'delete' && isset($_POST['product_id'])) {
                $product_id = (int)$_POST['product_id'];
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $success = 'Product deleted successfully';
            } elseif ($action === 'add_stock' && isset($_POST['product_id'])) {
                $product_id = (int)$_POST['product_id'];
                $quantity = (int)$_POST['quantity'];
                
                if ($quantity <= 0) {
                    throw new Exception('Please enter a valid quantity');
                }
                
                $stmt = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                $stmt->execute([$quantity, $product_id]);
                $success = 'Stock updated successfully';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Fetch all products with their attributes
$stmt = $pdo->query("SELECT p.*, 
                            GROUP_CONCAT(CONCAT(pa.attribute_name, ':', pa.attribute_value) SEPARATOR '|') as attributes
                     FROM products p
                     LEFT JOIN product_attributes pa ON p.id = pa.product_id
                     GROUP BY p.id
                     ORDER BY p.name");
$products = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col">
        <h2>Manage Products</h2>
    </div>
    <div class="col text-end">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
            <i class="fas fa-plus"></i> Add New Product
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

<!-- Products Table -->
<div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Image</th>
                <th>Name</th>
                <th>Description</th>
                <th>Attributes</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td>
                        <?php if (isset($product['image_path']) && $product['image_path']): ?>
                            <img src="../<?php echo htmlspecialchars($product['image_path']); ?>" alt="Product image" style="max-width: 100px; max-height: 60px;">
                        <?php else: ?>
                            <span class="text-muted">No image</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                    <td><?php echo htmlspecialchars($product['description']); ?></td>
                    <td>
                        <?php
                        if ($product['attributes']) {
                            $attrs = explode('|', $product['attributes']);
                            foreach ($attrs as $attr) {
                                list($name, $value) = explode(':', $attr);
                                echo "<span class='badge bg-secondary me-1'>";
                                echo htmlspecialchars($name) . ": " . htmlspecialchars($value);
                                echo "</span>";
                            }
                        }
                        ?>
                    </td>
                    <td>$<?php echo number_format($product['price'], 2); ?></td>
                    <td><?php echo $product['stock']; ?></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary" 
                                onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-success" 
                                onclick="showAddStockModal(<?php echo $product['id']; ?>)">
                            <i class="fas fa-plus"></i> Stock
                        </button>
                        <form method="POST" action="" class="d-inline" 
                              onsubmit="return confirm('Are you sure you want to delete this product?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
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

<!-- Add/Edit Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="productForm" method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" id="product_id">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Product Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="price" class="form-label">Price *</label>
                            <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label for="stock" class="form-label">Initial Stock</label>
                            <input type="number" class="form-control" id="stock" name="stock" min="0" value="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="image" class="form-label">Product Image</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                        <div class="form-text">Supported formats: JPG, JPEG, PNG, GIF</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Attributes</label>
                        <div id="attributesContainer">
                            <!-- Attribute fields will be added here -->
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="addAttributeField()">
                            <i class="fas fa-plus"></i> Add Attribute
                        </button>
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Stock Modal -->
<div class="modal fade" id="addStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_stock">
                    <input type="hidden" name="product_id" id="stock_product_id">
                    
                    <div class="mb-3">
                        <label for="quantity" class="form-label">Quantity to Add</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function addAttributeField(name = '', value = '') {
    const container = document.getElementById('attributesContainer');
    const index = container.children.length;
    
    const attributeRow = document.createElement('div');
    attributeRow.className = 'row mb-2';
    attributeRow.innerHTML = `
        <div class="col-5">
            <input type="text" class="form-control" name="attributes[${index}][name]" 
                   placeholder="Name" value="${name}">
        </div>
        <div class="col-5">
            <input type="text" class="form-control" name="attributes[${index}][value]" 
                   placeholder="Value" value="${value}">
        </div>
        <div class="col-2">
            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    container.appendChild(attributeRow);
}

function editProduct(product) {
    const form = document.getElementById('productForm');
    form.elements['action'].value = 'update';
    form.elements['product_id'].value = product.id;
    form.elements['name'].value = product.name;
    form.elements['description'].value = product.description;
    form.elements['price'].value = product.price;
    form.elements['stock'].value = product.stock;
    
    // Clear existing attributes
    document.getElementById('attributesContainer').innerHTML = '';
    
    // Add existing attributes
    if (product.attributes) {
        const attributes = product.attributes.split('|');
        attributes.forEach(attr => {
            const [name, value] = attr.split(':');
            addAttributeField(name, value);
        });
    }
    
    // Update modal title
    document.querySelector('#addProductModal .modal-title').textContent = 'Edit Product';
    
    // Show modal
    new bootstrap.Modal(document.getElementById('addProductModal')).show();
}

function showAddStockModal(productId) {
    document.getElementById('stock_product_id').value = productId;
    new bootstrap.Modal(document.getElementById('addStockModal')).show();
}

// Add one empty attribute field by default for new products
document.getElementById('addProductModal').addEventListener('show.bs.modal', function(event) {
    if (!event.relatedTarget) return; // Don't run on programmatic show (edit)
    
    const form = document.getElementById('productForm');
    form.reset();
    form.elements['action'].value = 'add';
    form.elements['product_id'].value = '';
    document.getElementById('attributesContainer').innerHTML = '';
    addAttributeField();
    
    document.querySelector('#addProductModal .modal-title').textContent = 'Add New Product';
});
</script>

<?php require_once '../includes/footer.php'; ?>