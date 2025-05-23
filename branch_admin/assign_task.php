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
$product_id = isset($_GET['product_id']) ? $_GET['product_id'] : null;

// Get all delivery users for this branch
try {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE branch_id = ? AND role = "delivery_user" AND status = "active" ORDER BY username');
    $stmt->execute([$branch_id]);
    $delivery_users = $stmt->fetchAll();
    
    // If product ID is provided, get product details
    if ($product_id) {
        $stmt = $pdo->prepare('SELECT p.*, l.name as location_name, l.address as location_address 
                              FROM products p 
                              LEFT JOIN locations l ON p.location_id = l.id 
                              WHERE p.id = ? AND p.branch_id = ?');
        $stmt->execute([$product_id, $branch_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            $_SESSION['error_message'] = "Product not found or you don't have permission to assign tasks for it.";
            header('Location: products.php');
            exit;
        }
    } else {
        // Get all available products for this branch
        $stmt = $pdo->prepare('SELECT p.*, l.name as location_name 
                              FROM products p 
                              LEFT JOIN locations l ON p.location_id = l.id 
                              WHERE p.branch_id = ? AND p.status = "available" 
                              ORDER BY p.name');
        $stmt->execute([$branch_id]);
        $products = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header('Location: ' . ($product_id ? 'view_product.php?id=' . $product_id : 'products.php'));
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_task'])) {
    // Validate and sanitize input
    $delivery_user_id = (int)$_POST['delivery_user_id'];
    $product_id = (int)$_POST['product_id'];
    $instructions = trim($_POST['instructions']);
    $priority = $_POST['priority'];
    
    // Validation
    $errors = [];
    
    if (empty($delivery_user_id)) {
        $errors[] = "Please select a delivery user.";
    }
    
    if (empty($product_id)) {
        $errors[] = "Please select a product.";
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Insert delivery task
            $stmt = $pdo->prepare('INSERT INTO delivery_tasks (product_id, delivery_user_id, instructions, priority, status, created_at, updated_at) 
                                  VALUES (?, ?, ?, ?, "assigned", NOW(), NOW())');
            $stmt->execute([$product_id, $delivery_user_id, $instructions, $priority]);
            
            // Update product status
            $stmt = $pdo->prepare('UPDATE products SET status = "pending", updated_at = NOW() WHERE id = ?');
            $stmt->execute([$product_id]);
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect with success message
            $_SESSION['success_message'] = "Task assigned successfully.";
            header('Location: view_product.php?id=' . $product_id);
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
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
            <h2><i class="fas fa-tasks me-2"></i>Assign Delivery Task</h2>
            <p class="text-muted">Assign a product to a delivery user for delivery.</p>
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
            <h5 class="mb-0">Task Assignment Details</h5>
        </div>
        <div class="card-body">
            <form method="post" action="">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="delivery_user_id" class="form-label">Delivery User <span class="text-danger">*</span></label>
                        <select class="form-select" id="delivery_user_id" name="delivery_user_id" required>
                            <option value="">-- Select Delivery User --</option>
                            <?php if (isset($delivery_users) && count($delivery_users) > 0): ?>
                                <?php foreach ($delivery_users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo (isset($_POST['delivery_user_id']) && $_POST['delivery_user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No active delivery users found</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="product_id" class="form-label">Product <span class="text-danger">*</span></label>
                        <?php if (isset($product)): ?>
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" readonly>
                        <?php else: ?>
                            <select class="form-select" id="product_id" name="product_id" required>
                                <option value="">-- Select Product --</option>
                                <?php if (isset($products) && count($products) > 0): ?>
                                    <?php foreach ($products as $prod): ?>
                                        <option value="<?php echo $prod['id']; ?>" <?php echo (isset($_POST['product_id']) && $_POST['product_id'] == $prod['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($prod['name']); ?> 
                                            <?php if ($prod['location_name']): ?>
                                                (Location: <?php echo htmlspecialchars($prod['location_name']); ?>)
                                            <?php else: ?>
                                                (No location assigned)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>No available products found</option>
                                <?php endif; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="instructions" class="form-label">Delivery Instructions</label>
                    <textarea class="form-control" id="instructions" name="instructions" rows="3"><?php echo isset($_POST['instructions']) ? htmlspecialchars($_POST['instructions']) : ''; ?></textarea>
                    <div class="form-text">Provide any special instructions for the delivery user.</div>
                </div>
                
                <div class="mb-3">
                    <label for="priority" class="form-label">Priority <span class="text-danger">*</span></label>
                    <select class="form-select" id="priority" name="priority" required>
                        <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'low') ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'medium') ? 'selected' : ''; ?> <?php echo (!isset($_POST['priority'])) ? 'selected' : ''; ?>>Medium</option>
                        <option value="high" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'high') ? 'selected' : ''; ?>>High</option>
                        <option value="urgent" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'urgent') ? 'selected' : ''; ?>>Urgent</option>
                    </select>
                </div>
                
                <div class="d-flex justify-content-end mt-4">
                    <a href="<?php echo $product_id ? 'view_product.php?id=' . $product_id : 'products.php'; ?>" class="btn btn-secondary me-2">Cancel</a>
                    <button type="submit" name="assign_task" class="btn btn-primary">Assign Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>
