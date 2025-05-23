<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has delivery_user role
check_role('delivery_user');

// Include database connection
require_once '../config/db_connect.php';

// Get user ID from session
$user_id = $_SESSION['user_id'];
$branch_id = $_SESSION['branch_id'];

// Get statistics for dashboard
try {
    // Get branch details
    $stmt = $pdo->prepare('SELECT * FROM branches WHERE id = ?');
    $stmt->execute([$branch_id]);
    $branch = $stmt->fetch();
    
    // Total assigned shipments
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM delivery_tasks WHERE delivery_user_id = ?');
    $stmt->execute([$user_id]);
    $total_assigned = $stmt->fetchColumn();
    
    // Pending deliveries
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM delivery_tasks WHERE delivery_user_id = ? AND status IN ("assigned", "in_progress")');
    $stmt->execute([$user_id]);
    $pending_deliveries = $stmt->fetchColumn();
    
    // Completed deliveries
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM delivery_tasks WHERE delivery_user_id = ? AND status = "completed"');
    $stmt->execute([$user_id]);
    $completed_deliveries = $stmt->fetchColumn();
    
    // Today's deliveries
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM delivery_tasks WHERE delivery_user_id = ? AND DATE(completed_at) = ? AND status = "completed"');
    $stmt->execute([$user_id, $today]);
    $today_deliveries = $stmt->fetchColumn();
    
    // Current tasks
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
    $current_tasks = $stmt->fetchAll();
    
} catch (PDOException $e) {
    // Handle database error
    $error_message = $e->getMessage();
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-tachometer-alt me-2"></i>Delivery User Dashboard</h2>
            <p class="text-muted">
                Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!
                <?php if (isset($branch) && $branch): ?>
                    You are assigned to the <?php echo htmlspecialchars($branch['name']); ?> branch.
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Assigned</h6>
                            <h3 class="mb-0"><?php echo $total_assigned; ?></h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                            <i class="fas fa-box text-primary fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Pending Deliveries</h6>
                            <h3 class="mb-0"><?php echo $pending_deliveries; ?></h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-3 rounded">
                            <i class="fas fa-truck text-warning fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Completed</h6>
                            <h3 class="mb-0"><?php echo $completed_deliveries; ?></h3>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="fas fa-check-circle text-success fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Today's Deliveries</h6>
                            <h3 class="mb-0"><?php echo $today_deliveries; ?></h3>
                        </div>
                        <div class="bg-info bg-opacity-10 p-3 rounded">
                            <i class="fas fa-calendar-check text-info fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Current Tasks -->
    <div class="row">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Current Delivery Tasks</h5>
                        <a href="my_tasks.php" class="btn btn-sm btn-primary">View All Tasks</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (count($current_tasks) > 0): ?>
                        <div class="task-list">
                            <?php foreach ($current_tasks as $task): ?>
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
                            <h5>No pending tasks</h5>
                            <p class="text-muted">You have no pending delivery tasks at the moment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>
