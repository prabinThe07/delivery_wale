<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has branch_admin role
check_role('branch_admin');

// Include database connection
require_once '../config/db_connect.php';

// Get branch ID from session
$branch_id = $_SESSION['branch_id'];

// Check if product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Product ID is required.";
    header('Location: products.php');
    exit;
}

$product_id = $_GET['id'];

// Get product details
try {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND branch_id = ?');
    $stmt->execute([$product_id, $branch_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        $_SESSION['error_message'] = "Product not found or you don't have permission to edit it.";
        header('Location: products.php');
        exit;
    }
    
    // Get all locations for this branch
    $stmt = $pdo->prepare('SELECT * FROM locations WHERE branch_id = ? ORDER BY name');
    $stmt->execute([$branch_id]);
    $locations = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header('Location: products.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    // Validate and sanitize input
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $quantity = (int)$_POST['quantity'];
    $location_id = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null;
    $status = $_POST['status'];
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Product name is required.";
    }
    
    if ($quantity <= 0) {
        $errors[] = "Quantity must be greater than zero.";
    }
    
    // If no errors, update database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('UPDATE products 
                                  SET name = ?, description = ?, quantity = ?, location_id = ?, status = ?, updated_at = NOW() 
                                  WHERE id = ? AND branch_id = ?');
            $stmt->execute([$name, $description, $quantity, $location_id, $status, $product_id, $branch_id]);
            
            // Redirect to products page with success message
            $_SESSION['success_message'] = "Product updated successfully.";
            header('Location: products.php');
            exit;
            
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-edit me-2"></i>Edit Product</h2>
            <p class="text-muted">Update product or item details.</p>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Product Details</h5>
        </div>
        <div class="card-body">
            <form method="post" action="">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="quantity" name="quantity" value="<?php echo htmlspecialchars($product['quantity']); ?>" min="1" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($product['description']); ?></textarea>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="location_id" class="form-label">Delivery Location</label>
                        <select class="form-select" id="location_id" name="location_id">
                            <option value="">-- Select Location --</option>
                            <?php if (isset($locations) && count($locations) > 0): ?>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['id']; ?>" <?php echo ($product['location_id'] == $location['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($location['name']); ?> - <?php echo htmlspecialchars($location['address']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <div class="form-text">If no location is selected, you can assign it later.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="available" <?php echo ($product['status'] == 'available') ? 'selected' : ''; ?>>Available</option>
                            <option value="pending" <?php echo ($product['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="delivered" <?php echo ($product['status'] == 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                            <option value="cancelled" <?php echo ($product['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end mt-4">
                    <a href="products.php" class="btn btn-secondary me-2">Cancel</a>
                    <button type="submit" name="update_product" class="btn btn-primary">Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>
