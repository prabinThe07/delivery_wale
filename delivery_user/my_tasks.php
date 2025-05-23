<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has delivery_user role
check_role('delivery_user');

// Include database connection
require_once '../config/db_connect.php';

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Get all tasks for this user
try {
    // Active tasks
    $stmt = $pdo->prepare('SELECT t.*, 
                          p.name as product_name, 
                          p.description as product_description,
                          p.quantity as product_quantity,
                          l.name as location_name,
                          l.address as location_address
                          FROM delivery_tasks t 
                          JOIN products p ON t.product_id = p.id 
                          LEFT JOIN locations l ON p.location_id = l.id 
                          WHERE t.delivery_user_id = ? AND t.status IN ("assigned", "in_progress") 
                          ORDER BY 
                            CASE t.priority
                                WHEN "urgent" THEN 1
                                WHEN "high" THEN 2
                                WHEN "medium" THEN 3
                                WHEN "low" THEN 4
                            END, 
                            t.created_at ASC');
    $stmt->execute([$user_id]);
    $active_tasks = $stmt->fetchAll();
    
    // Completed tasks
    $stmt = $pdo->prepare('SELECT t.*, 
                          p.name as product_name, 
                          p.description as product_description,
                          p.quantity as product_quantity,
                          l.name as location_name,
                          l.address as location_address
                          FROM delivery_tasks t 
                          JOIN products p ON t.product_id = p.id 
                          LEFT JOIN locations l ON p.location_id = l.id 
                          WHERE t.delivery_user_id = ? AND t.status = "completed" 
                          ORDER BY t.completed_at DESC');
    $stmt->execute([$user_id]);
    $completed_tasks = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-tasks me-2"></i>My Delivery Tasks</h2>
            <p class="text-muted">View and manage all your assigned delivery tasks.</p>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php 
        echo $_SESSION['success_message'];
        unset($_SESSION['success_message']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php 
        echo $_SESSION['error_message'];
        unset($_SESSION['error_message']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Active Tasks -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Active Tasks</h5>
        </div>
        <div class="card-body">
            <?php if (isset($active_tasks) && count($active_tasks) > 0): ?>
                <div class="task-list">
                    <?php foreach ($active_tasks as $task): ?>
                        <div class="card mb-3 border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($task['product_name']); ?></h5>
                                    <div>
                                        <?php 
                                        $priority_class = '';
                                        $priority_text = '';
                                        switch ($task['priority']) {
                                            case 'low':
                                                $priority_class = 'bg-secondary';
                                                $priority_text = 'Low Priority';
                                                break;
                                            case 'medium':
                                                $priority_class = 'bg-info';
                                                $priority_text = 'Medium Priority';
                                                break;
                                            case 'high':
                                                $priority_class = 'bg-warning';
                                                $priority_text = 'High Priority';
                                                break;
                                            case 'urgent':
                                                $priority_class = 'bg-danger';
                                                $priority_text = 'URGENT';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $priority_class; ?>" data-bs-toggle="tooltip" title="<?php echo $priority_text; ?>">
                                            <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                        <?php 
                                        $status_class = '';
                                        switch ($task['status']) {
                                            case 'assigned':
                                                $status_class = 'bg-warning';
                                                break;
                                            case 'in_progress':
                                                $status_class = 'bg-info';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Quantity:</strong> <?php echo htmlspecialchars($task['product_quantity']); ?></p>
                                        <p class="mb-1"><strong>Assigned:</strong> <?php echo date('M d, Y', strtotime($task['created_at'])); ?></p>
                                        <?php if (!empty($task['instructions'])): ?>
                                            <p class="mb-1"><strong>Instructions:</strong> <?php echo htmlspecialchars($task['instructions']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($task['location_name']): ?>
                                            <p class="mb-1"><strong>Delivery Location:</strong> <?php echo htmlspecialchars($task['location_name']); ?></p>
                                            <p class="mb-1"><strong>Address:</strong> <?php echo htmlspecialchars($task['location_address']); ?></p>
                                        <?php else: ?>
                                            <p class="text-muted">No location assigned</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <?php if ($task['status'] == 'assigned'): ?>
                                        <form method="post" action="update_task_status.php" class="me-2">
                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                            <input type="hidden" name="status" value="in_progress">
                                            <button type="submit" class="btn btn-warning">
                                                <i class="fas fa-truck me-1"></i> Start Delivery
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($task['status'] == 'in_progress'): ?>
                                        <form method="post" action="update_task_status.php" class="me-2">
                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-check-circle me-1"></i> Mark as Delivered
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($task['location_name']): ?>
                                        <a href="https://maps.google.com/?q=<?php echo urlencode($task['location_address']); ?>" target="_blank" class="btn btn-primary">
                                            <i class="fas fa-map-marker-alt me-1"></i> Navigate
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-check-circle fa-4x text-muted mb-3"></i>
                    <h5>No active tasks</h5>
                    <p class="text-muted">You have no active delivery tasks at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Completed Tasks -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Completed Tasks</h5>
        </div>
        <div class="card-body">
            <?php if (isset($completed_tasks) && count($completed_tasks) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product</th>
                                <th>Location</th>
                                <th>Priority</th>
                                <th>Assigned</th>
                                <th>Completed</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completed_tasks as $task): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($task['id']); ?></td>
                                <td><?php echo htmlspecialchars($task['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($task['location_name'] ?? 'Not Assigned'); ?></td>
                                <td>
                                    <?php 
                                    $priority_class = '';
                                    switch ($task['priority']) {
                                        case 'low':
                                            $priority_class = 'bg-secondary';
                                            break;
                                        case 'medium':
                                            $priority_class = 'bg-info';
                                            break;
                                        case 'high':
                                            $priority_class = 'bg-warning';
                                            break;
                                        case 'urgent':
                                            $priority_class = 'bg-danger';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $priority_class; ?>">
                                        <?php echo ucfirst($task['priority']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($task['created_at'])); ?></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($task['completed_at'])); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#taskModal<?php echo $task['id']; ?>">
                                        <i class="fas fa-eye me-1"></i> Details
                                    </button>
                                    
                                    <!-- Task Details Modal -->
                                    <div class="modal fade" id="taskModal<?php echo $task['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Task Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <h6>Product Information</h6>
                                                            <p><strong>Name:</strong> <?php echo htmlspecialchars($task['product_name']); ?></p>
                                                            <p><strong>Description:</strong> <?php echo htmlspecialchars($task['product_description']); ?></p>
                                                            <p><strong>Quantity:</strong> <?php echo htmlspecialchars($task['product_quantity']); ?></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h6>Delivery Information</h6>
                                                            <p><strong>Location:</strong> <?php echo htmlspecialchars($task['location_name'] ?? 'Not Assigned'); ?></p>
                                                            <p><strong>Address:</strong> <?php echo htmlspecialchars($task['location_address'] ?? 'N/A'); ?></p>
                                                            <p><strong>Instructions:</strong> <?php echo htmlspecialchars($task['instructions'] ?: 'None'); ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <h6>Task Timeline</h6>
                                                            <ul class="list-group">
                                                                <li class="list-group-item">
                                                                    <i class="fas fa-plus-circle text-success me-2"></i>
                                                                    <strong>Assigned:</strong> <?php echo date('M d, Y h:i A', strtotime($task['created_at'])); ?>
                                                                </li>
                                                                <li class="list-group-item">
                                                                    <i class="fas fa-check-circle text-primary me-2"></i>
                                                                    <strong>Completed:</strong> <?php echo date('M d, Y h:i A', strtotime($task['completed_at'])); ?>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                    <p class="mb-0">No completed tasks found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>
