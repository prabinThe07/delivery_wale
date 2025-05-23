<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has branch_admin role
check_role('branch_admin');

// Include database connection
require_once '../config/db_connect.php';

// Get branch ID from session
$branch_id = $_SESSION['branch_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    // Validate and sanitize input
    $task_type = $_POST['task_type'];
    $delivery_user_id = !empty($_POST['delivery_user_id']) ? (int)$_POST['delivery_user_id'] : null;
    $priority = $_POST['priority'];
    $instructions = trim($_POST['instructions']);
    $scheduled_date = $_POST['scheduled_date'];
    
    // Task type specific fields
    if ($task_type === 'product') {
        $product_id = (int)$_POST['product_id'];
        $shipment_id = null;
        $location_id = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null;
    } else { // shipment
        $shipment_id = (int)$_POST['shipment_id'];
        $product_id = null;
        $location_id = null; // Location comes from shipment
    }
    
    // Validation
    $errors = [];
    
    if (empty($delivery_user_id)) {
        $errors[] = "Please select a delivery user.";
    }
    
    if ($task_type === 'product' && empty($product_id)) {
        $errors[] = "Please select a product.";
    }
    
    if ($task_type === 'shipment' && empty($shipment_id)) {
        $errors[] = "Please select a shipment.";
    }
    
    if (empty($scheduled_date)) {
        $errors[] = "Please select a scheduled date.";
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Insert delivery task
            $stmt = $pdo->prepare('INSERT INTO delivery_tasks (product_id, shipment_id, delivery_user_id, location_id, 
                                  instructions, priority, status, scheduled_date, branch_id, created_at, updated_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, "assigned", ?, ?, NOW(), NOW())');
            $stmt->execute([$product_id, $shipment_id, $delivery_user_id, $location_id, $instructions, $priority, $scheduled_date, $branch_id]);
            
            $task_id = $pdo->lastInsertId();
            
            // Update product status if product task
            if ($task_type === 'product' && $product_id) {
                $stmt = $pdo->prepare('UPDATE products SET status = "pending", updated_at = NOW() WHERE id = ?');
                $stmt->execute([$product_id]);
            }
            
            // Update shipment status if shipment task
            if ($task_type === 'shipment' && $shipment_id) {
                $stmt = $pdo->prepare('UPDATE shipments SET status = "in_transit", delivery_user_id = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$delivery_user_id, $shipment_id]);
                
                // Add to tracking history
                $stmt = $pdo->prepare('INSERT INTO tracking_history (shipment_id, status, notes, created_at) VALUES (?, "in_transit", "Task assigned to delivery user", NOW())');
                $stmt->execute([$shipment_id]);
            }
            
            // Add task history entry
            $stmt = $pdo->prepare('INSERT INTO task_history (task_id, status, notes, created_by, created_at) 
                                  VALUES (?, "assigned", "Task created and assigned", ?, NOW())');
            $stmt->execute([$task_id, $_SESSION['user_id']]);
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect with success message
            $_SESSION['success_message'] = "Task created and assigned successfully.";
            header('Location: task_management.php');
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Get all delivery users for this branch
try {
    $stmt = $pdo->prepare('SELECT id, username, phone, email FROM users 
                          WHERE branch_id = ? AND role = "delivery_user" AND status = "active" 
                          ORDER BY username');
    $stmt->execute([$branch_id]);
    $delivery_users = $stmt->fetchAll();
    
    // Get all available products for this branch
    $stmt = $pdo->prepare('SELECT p.*, l.name as location_name 
                          FROM products p 
                          LEFT JOIN locations l ON p.location_id = l.id 
                          WHERE p.branch_id = ? AND p.status = "available" 
                          ORDER BY p.name');
    $stmt->execute([$branch_id]);
    $products = $stmt->fetchAll();
    
    // Get all pending shipments for this branch
    $stmt = $pdo->prepare('SELECT s.*, u.username as assigned_user 
                          FROM shipments s 
                          LEFT JOIN users u ON s.delivery_user_id = u.id 
                          WHERE s.branch_id = ? AND s.status IN ("pending", "processing") 
                          ORDER BY s.created_at DESC');
    $stmt->execute([$branch_id]);
    $shipments = $stmt->fetchAll();
    
    // Get all locations for this branch
    $stmt = $pdo->prepare('SELECT * FROM locations WHERE branch_id = ? AND status = "active" ORDER BY name');
    $stmt->execute([$branch_id]);
    $locations = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-plus-circle me-2"></i>Create New Task</h2>
            <p class="text-muted">Assign a new delivery task to a delivery user.</p>
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
            <h5 class="mb-0">Task Details</h5>
        </div>
        <div class="card-body">
            <form method="post" action="" id="taskForm">
                <div class="mb-4">
                    <label class="form-label">Task Type</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="task_type" id="task_type_product" value="product" checked>
                        <label class="btn btn-outline-primary" for="task_type_product">
                            <i class="fas fa-box me-1"></i> Product Delivery
                        </label>
                        
                        <input type="radio" class="btn-check" name="task_type" id="task_type_shipment" value="shipment">
                        <label class="btn btn-outline-primary" for="task_type_shipment">
                            <i class="fas fa-shipping-fast me-1"></i> Shipment Delivery
                        </label>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="delivery_user_id" class="form-label">Assign to Delivery User <span class="text-danger">*</span></label>
                        <select class="form-select" id="delivery_user_id" name="delivery_user_id" required>
                            <option value="">-- Select Delivery User --</option>
                            <?php if (isset($delivery_users) && count($delivery_users) > 0): ?>
                                <?php foreach ($delivery_users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo (isset($_POST['delivery_user_id']) && $_POST['delivery_user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['phone']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No active delivery users found</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="scheduled_date" class="form-label">Scheduled Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="scheduled_date" name="scheduled_date" 
                               value="<?php echo isset($_POST['scheduled_date']) ? htmlspecialchars($_POST['scheduled_date']) : date('Y-m-d'); ?>" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                
                <!-- Product Selection (shown when task_type is product) -->
                <div id="product_section" class="mb-3">
                    <label for="product_id" class="form-label">Select Product <span class="text-danger">*</span></label>
                    <select class="form-select" id="product_id" name="product_id">
                        <option value="">-- Select Product --</option>
                        <?php if (isset($products) && count($products) > 0): ?>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>" <?php echo (isset($_POST['product_id']) && $_POST['product_id'] == $product['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($product['name']); ?> 
                                    <?php if ($product['location_name']): ?>
                                        (Location: <?php echo htmlspecialchars($product['location_name']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No available products found</option>
                        <?php endif; ?>
                    </select>
                    <div class="form-text">Select a product that needs to be delivered.</div>
                </div>
                
                <!-- Location Selection (shown when task_type is product and product has no location) -->
                <div id="location_section" class="mb-3">
                    <label for="location_id" class="form-label">Delivery Location</label>
                    <select class="form-select" id="location_id" name="location_id">
                        <option value="">-- Select Location --</option>
                        <?php if (isset($locations) && count($locations) > 0): ?>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?php echo $location['id']; ?>" <?php echo (isset($_POST['location_id']) && $_POST['location_id'] == $location['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location['name']); ?> (<?php echo htmlspecialchars($location['address']); ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No locations found</option>
                        <?php endif; ?>
                    </select>
                    <div class="form-text">Optional: Select a delivery location if the product doesn't have one assigned.</div>
                </div>
                
                <!-- Shipment Selection (shown when task_type is shipment) -->
                <div id="shipment_section" class="mb-3" style="display: none;">
                    <label for="shipment_id" class="form-label">Select Shipment <span class="text-danger">*</span></label>
                    <select class="form-select" id="shipment_id" name="shipment_id">
                        <option value="">-- Select Shipment --</option>
                        <?php if (isset($shipments) && count($shipments) > 0): ?>
                            <?php foreach ($shipments as $shipment): ?>
                                <option value="<?php echo $shipment['id']; ?>" <?php echo (isset($_POST['shipment_id']) && $_POST['shipment_id'] == $shipment['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($shipment['tracking_number']); ?> - 
                                    <?php echo htmlspecialchars($shipment['recipient_name']); ?> 
                                    (<?php echo htmlspecialchars(substr($shipment['recipient_address'], 0, 30) . (strlen($shipment['recipient_address']) > 30 ? '...' : '')); ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No pending shipments found</option>
                        <?php endif; ?>
                    </select>
                    <div class="form-text">Select a shipment that needs to be delivered.</div>
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
                
                <div class="mb-3">
                    <label for="instructions" class="form-label">Delivery Instructions</label>
                    <textarea class="form-control" id="instructions" name="instructions" rows="3"><?php echo isset($_POST['instructions']) ? htmlspecialchars($_POST['instructions']) : ''; ?></textarea>
                    <div class="form-text">Provide any special instructions for the delivery user.</div>
                </div>
                
                <div class="d-flex justify-content-end mt-4">
                    <a href="task_management.php" class="btn btn-secondary me-2">Cancel</a>
                    <button type="submit" name="create_task" class="btn btn-primary">Create Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Task type toggle
        const taskTypeProduct = document.getElementById('task_type_product');
        const taskTypeShipment = document.getElementById('task_type_shipment');
        const productSection = document.getElementById('product_section');
        const locationSection = document.getElementById('location_section');
        const shipmentSection = document.getElementById('shipment_section');
        
        function toggleSections() {
            if (taskTypeProduct.checked) {
                productSection.style.display = 'block';
                locationSection.style.display = 'block';
                shipmentSection.style.display = 'none';
                document.getElementById('shipment_id').removeAttribute('required');
                document.getElementById('product_id').setAttribute('required', 'required');
            } else {
                productSection.style.display = 'none';
                locationSection.style.display = 'none';
                shipmentSection.style.display = 'block';
                document.getElementById('product_id').removeAttribute('required');
                document.getElementById('shipment_id').setAttribute('required', 'required');
            }
        }
        
        taskTypeProduct.addEventListener('change', toggleSections);
        taskTypeShipment.addEventListener('change', toggleSections);
        
        // Initialize sections
        toggleSections();
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
