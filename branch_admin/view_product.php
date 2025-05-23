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
    $stmt = $pdo->prepare('SELECT p.*, l.name as location_name, l.address as location_address 
                          FROM products p 
                          LEFT JOIN locations l ON p.location_id = l.id 
                          WHERE p.id = ? AND p.branch_id = ?');
    $stmt->execute([$product_id, $branch_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        $_SESSION['error_message'] = "Product not found or you don't have permission to view it.";
        header('Location: products.php');
        exit;
    }
    
    // Get delivery tasks associated with this product
    $stmt = $pdo->prepare('SELECT t.*, u.username as delivery_user 
                          FROM delivery_tasks t 
                          LEFT JOIN users u ON t.delivery_user_id = u.id 
                          WHERE t.product_id = ? 
                          ORDER BY t.created_at DESC');
    $stmt->execute([$product_id]);
    $delivery_tasks = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header('Location: products.php');
    exit;
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-box me-2"></i>Product Details</h2>
                    <p class="text-muted">Viewing detailed information for the selected product.</p>
                </div>
                <div>
                    <a href="products.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-arrow-left me-1"></i> Back to Products
                    </a>
                    <a href="edit_product.php?id=<?php echo $product_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-1"></i> Edit Product
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Product Information</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3 fw-bold">Product Name:</div>
                        <div class="col-md-9"><?php echo htmlspecialchars($product['name']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3 fw-bold">Description:</div>
                        <div class="col-md-9"><?php echo nl2br(htmlspecialchars($product['description'])); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3 fw-bold">Quantity:</div>
                        <div class="col-md-9"><?php echo htmlspecialchars($product['quantity']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3 fw-bold">Status:</div>
                        <div class="col-md-9">
                            <?php 
                            $status_class = '';
                            switch ($product['status']) {
                                case 'available':
                                    $status_class = 'bg-success';
                                    break;
                                case 'pending':
                                    $status_class = 'bg-warning';
                                    break;
                                case 'delivered':
                                    $status_class = 'bg-info';
                                    break;
                                case 'cancelled':
                                    $status_class = 'bg-danger';
                                    break;
                            }
                            ?>
                            <span class="badge <?php echo $status_class; ?>">
                                <?php echo ucfirst($product['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3 fw-bold">Created:</div>
                        <div class="col-md-9"><?php echo date('F d, Y h:i A', strtotime($product['created_at'])); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3 fw-bold">Last Updated:</div>
                        <div class="col-md-9"><?php echo date('F d, Y h:i A', strtotime($product['updated_at'])); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Delivery Tasks -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Delivery Tasks</h5>
                        <a href="assign_task.php?product_id=<?php echo $product_id; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus me-1"></i> Assign New Task
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (isset($delivery_tasks) && count($delivery_tasks) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Delivery User</th>
                                        <th>Status</th>
                                        <th>Assigned Date</th>
                                        <th>Completed Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($delivery_tasks as $task): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($task['id']); ?></td>
                                        <td><?php echo htmlspecialchars($task['delivery_user']); ?></td>
                                        <td>
                                            <?php 
                                            $status_class = '';
                                            switch ($task['status']) {
                                                case 'assigned':
                                                    $status_class = 'bg-warning';
                                                    break;
                                                case 'in_progress':
                                                    $status_class = 'bg-info';
                                                    break;
                                                case 'completed':
                                                    $status_class = 'bg-success';
                                                    break;
                                                case 'cancelled':
                                                    $status_class = 'bg-danger';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($task['created_at'])); ?></td>
                                        <td><?php echo $task['completed_at'] ? date('M d, Y', strtotime($task['completed_at'])) : 'N/A'; ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="view_task.php?id=<?php echo $task['id']; ?>" class="btn btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="update_task.php?id=<?php echo $task['id']; ?>" class="btn btn-outline-secondary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                            <p class="mb-0">No delivery tasks assigned for this product yet.</p>
                            <p class="text-muted">Assign a delivery task to track delivery progress.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Location Information -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Delivery Location</h5>
                </div>
                <div class="card-body">
                    <?php if ($product['location_id']): ?>
                        <div class="mb-3">
                            <h6 class="fw-bold"><?php echo htmlspecialchars($product['location_name']); ?></h6>
                            <p class="mb-2"><?php echo nl2br(htmlspecialchars($product['location_address'])); ?></p>
                        </div>
                        <div class="text-center">
                            <a href="https://maps.google.com/?q=<?php echo urlencode($product['location_address']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-map-marker-alt me-1"></i> View on Map
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-map-marker-slash fa-3x text-muted mb-3"></i>
                            <p class="mb-0">No location assigned yet.</p>
                            <p class="text-muted">Edit the product to assign a delivery location.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="assign_task.php?product_id=<?php echo $product_id; ?>" class="btn btn-primary">
                            <i class="fas fa-user-plus me-1"></i> Assign to Delivery User
                        </a>
                        <a href="edit_product.php?id=<?php echo $product_id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-edit me-1"></i> Edit Product
                        </a>
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteProductModal">
                            <i class="fas fa-trash me-1"></i> Delete Product
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Modal -->
    <div class="modal fade" id="deleteProductModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the product: <strong><?php echo htmlspecialchars($product['name']); ?></strong>?</p>
                    <p class="text-danger">This action cannot be undone and will also delete all associated delivery tasks.</p>
                </div>
                <div class="modal-footer">
                    <form method="post" action="products.php">
                        <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_product" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>
