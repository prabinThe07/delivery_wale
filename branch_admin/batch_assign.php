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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_assign'])) {
    // Validate and sanitize input
    $delivery_user_id = (int)$_POST['delivery_user_id'];
    $scheduled_date = $_POST['scheduled_date'];
    $priority = $_POST['priority'];
    $instructions = trim($_POST['instructions']);
    
    // Get selected items
    $selected_products = isset($_POST['products']) ? $_POST['products'] : [];
    $selected_shipments = isset($_POST['shipments']) ? $_POST['shipments'] : [];
    
    // Validation
    $errors = [];
    
    if (empty($delivery_user_id)) {
        $errors[] = "Please select a delivery user.";
    }
    
    if (empty($scheduled_date)) {
        $errors[] = "Please select a scheduled date.";
    }
    
    if (empty($selected_products) && empty($selected_shipments)) {
        $errors[] = "Please select at least one product or shipment.";
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            $tasks_created = 0;
            
            // Process selected products
            foreach ($selected_products as $product_id) {
                // Get product location
                $stmt = $pdo->prepare('SELECT location_id FROM products WHERE id = ? AND branch_id = ?');
                $stmt->execute([$product_id, $branch_id]);
                $location_id = $stmt->fetchColumn();
                
                // Insert delivery task
                $stmt = $pdo->prepare('INSERT INTO delivery_tasks (product_id, shipment_id, delivery_user_id, location_id, 
                                      instructions, priority, status, scheduled_date, branch_id, created_at, updated_at) 
                                      VALUES (?, NULL, ?, ?, ?, ?, "assigned", ?, ?, NOW(), NOW())');
                $stmt->execute([$product_id, $delivery_user_id, $location_id, $instructions, $priority, $scheduled_date, $branch_id]);
                
                $task_id = $pdo->lastInsertId();
                
                // Update product status
                $stmt = $pdo->prepare('UPDATE products SET status = "pending", updated_at = NOW() WHERE id = ?');
                $stmt->execute([$product_id]);
                
                // Add task history entry
                $stmt = $pdo->prepare('INSERT INTO task_history (task_id, status, notes, created_by, created_at) 
                                      VALUES (?, "assigned", "Task created in batch assignment", ?, NOW())');
                $stmt->execute([$task_id, $_SESSION['user_id']]);
                
                $tasks_created++;
            }
            
            // Process selected shipments
            foreach ($selected_shipments as $shipment_id) {
                // Insert delivery task
                $stmt = $pdo->prepare('INSERT INTO delivery_tasks (product_id, shipment_id, delivery_user_id, location_id, 
                                      instructions, priority, status, scheduled_date, branch_id, created_at, updated_at) 
                                      VALUES (NULL, ?, ?, NULL, ?, ?, "assigned", ?, ?, NOW(), NOW())');
                $stmt->execute([$shipment_id, $delivery_user_id, $instructions, $priority, $scheduled_date, $branch_id]);
                
                $task_id = $pdo->lastInsertId();
                
                // Update shipment status
                $stmt = $pdo->prepare('UPDATE shipments SET status = "in_transit", delivery_user_id = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$delivery_user_id, $shipment_id]);
                
                // Add to tracking history
                $stmt = $pdo->prepare('INSERT INTO tracking_history (shipment_id, status, notes, created_at) VALUES (?, "in_transit", "Task assigned to delivery user in batch", NOW())');
                $stmt->execute([$shipment_id]);
                
                // Add task history entry
                $stmt = $pdo->prepare('INSERT INTO task_history (task_id, status, notes, created_by, created_at) 
                                      VALUES (?, "assigned", "Task created in batch assignment", ?, NOW())');
                $stmt->execute([$task_id, $_SESSION['user_id']]);
                
                $tasks_created++;
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect with success message
            $_SESSION['success_message'] = "$tasks_created tasks created and assigned successfully.";
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
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-tasks me-2"></i>Batch Task Assignment</h2>
            <p class="text-muted">Assign multiple products or shipments to a delivery user in one go.</p>
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
            <h5 class="mb-0">Assignment Details</h5>
        </div>
        <div class="card-body">
            <form method="post" action="">
                <div class="row mb-4">
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
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="priority" class="form-label">Priority <span class="text-danger">*</span></label>
                        <select class="form-select" id="priority" name="priority" required>
                            <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'low') ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'medium') ? 'selected' : ''; ?> <?php echo (!isset($_POST['priority'])) ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'high') ? 'selected' : ''; ?>>High</option>
                            <option value="urgent" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'urgent') ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="instructions" class="form-label">Delivery Instructions</label>
                        <textarea class="form-control" id="instructions" name="instructions" rows="1"><?php echo isset($_POST['instructions']) ? htmlspecialchars($_POST['instructions']) : ''; ?></textarea>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Products Selection -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Available Products</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="select_all_products">
                                        <label class="form-check-label" for="select_all_products">Select All</label>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <?php if (isset($products) && count($products) > 0): ?>
                                    <div class="list-group">
                                        <?php foreach ($products as $product): ?>
                                            <label class="list-group-item">
                                                <input class="form-check-input me-1 product-checkbox" type="checkbox" name="products[]" value="<?php echo $product['id']; ?>">
                                                <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php if ($product['location_name']): ?>
                                                        Location: <?php echo htmlspecialchars($product['location_name']); ?>
                                                    <?php else: ?>
                                                        No location assigned
                                                    <?php endif; ?>
                                                </small>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <p class="mb-0">No available products found.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Shipments Selection -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Pending Shipments</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="select_all_shipments">
                                        <label class="form-check-label" for="select_all_shipments">Select All</label>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <?php if (isset($shipments) && count($shipments) > 0): ?>
                                    <div class="list-group">
                                        <?php foreach ($shipments as $shipment): ?>
                                            <label class="list-group-item">
                                                <input class="form-check-input me-1 shipment-checkbox" type="checkbox" name="shipments[]" value="<?php echo $shipment['id']; ?>">
                                                <strong><?php echo htmlspecialchars($shipment['tracking_number']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    Recipient: <?php echo htmlspecialchars($shipment['recipient_name']); ?><br>
                                                    Address: <?php echo htmlspecialchars(substr($shipment['recipient_address'], 0, 50) . (strlen($shipment['recipient_address']) > 50 ? '...' : '')); ?>
                                                </small>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <p class="mb-0">No pending shipments found.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end mt-4">
                    <a href="task_management.php" class="btn btn-secondary me-2">Cancel</a>
                    <button type="submit" name="batch_assign" class="btn btn-primary">Assign Tasks</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Select all products
        const selectAllProducts = document.getElementById('select_all_products');
        const productCheckboxes = document.querySelectorAll('.product-checkbox');
        
        if (selectAllProducts) {
            selectAllProducts.addEventListener('change', function() {
                productCheckboxes.forEach(checkbox => {
                    checkbox.checked = selectAllProducts.checked;
                });
            });
        }
        
        // Select all shipments
        const selectAllShipments = document.getElementById('select_all_shipments');
        const shipmentCheckboxes = document.querySelectorAll('.shipment-checkbox');
        
        if (selectAllShipments) {
            selectAllShipments.addEventListener('change', function() {
                shipmentCheckboxes.forEach(checkbox => {
                    checkbox.checked = selectAllShipments.checked;
                });
            });
        }
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
