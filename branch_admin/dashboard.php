<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has branch_admin role
check_role('branch_admin');

// Include database connection
require_once '../config/db_connect.php';

// Get branch ID from session
$branch_id = $_SESSION['branch_id'];

// Get statistics for dashboard
try {
    // Get branch details
    $stmt = $pdo->prepare('SELECT * FROM branches WHERE id = ?');
    $stmt->execute([$branch_id]);
    $branch = $stmt->fetch();
    
    // Total staff in this branch
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE branch_id = ?');
    $stmt->execute([$branch_id]);
    $total_staff = $stmt->fetchColumn();
    
    // Total shipments from this branch
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM shipments WHERE branch_id = ?');
    $stmt->execute([$branch_id]);
    $total_shipments = $stmt->fetchColumn();
    
    // Pending shipments
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM shipments WHERE branch_id = ? AND status = "pending"');
    $stmt->execute([$branch_id]);
    $pending_shipments = $stmt->fetchColumn();
    
    // Delivered shipments
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM shipments WHERE branch_id = ? AND status = "delivered"');
    $stmt->execute([$branch_id]);
    $delivered_shipments = $stmt->fetchColumn();
    
    // In transit shipments
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM shipments WHERE branch_id = ? AND status = "in_transit"');
    $stmt->execute([$branch_id]);
    $in_transit_shipments = $stmt->fetchColumn();
    
    // Issues/Delays
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM shipments WHERE branch_id = ? AND (status = "delayed" OR status = "issue")');
    $stmt->execute([$branch_id]);
    $issues_delays = $stmt->fetchColumn();
    
    // Today's deliveries
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM shipments WHERE branch_id = ? AND DATE(updated_at) = ? AND status = "delivered"');
    $stmt->execute([$branch_id, $today]);
    $today_deliveries = $stmt->fetchColumn();
    
    // Recent shipments
    $stmt = $pdo->prepare('SELECT s.*, u.username as delivery_user FROM shipments s 
                          LEFT JOIN users u ON s.delivery_user_id = u.id 
                          WHERE s.branch_id = ? 
                          ORDER BY s.created_at DESC LIMIT 5');
    $stmt->execute([$branch_id]);
    $recent_shipments = $stmt->fetchAll();
    
    // Active delivery users
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE branch_id = ? AND role = "delivery_user" AND status = "active"');
    $stmt->execute([$branch_id]);
    $active_delivery_users = $stmt->fetchColumn();
    
    // Task statistics - Check if delivery_tasks table exists first
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'delivery_tasks'");
        $stmt->execute();
        $tableExists = $stmt->rowCount() > 0;
        
        if ($tableExists) {
            // Task statistics
            $stmt = $pdo->prepare('SELECT status, COUNT(*) as count FROM delivery_tasks 
                                  WHERE branch_id = ? GROUP BY status');
            $stmt->execute([$branch_id]);
            $task_status_counts = [];
            while ($row = $stmt->fetch()) {
                $task_status_counts[$row['status']] = $row['count'];
            }
            
            // Total tasks
            $total_tasks = array_sum($task_status_counts);
            
            // Tasks by priority
            $stmt = $pdo->prepare('SELECT priority, COUNT(*) as count FROM delivery_tasks 
                                  WHERE branch_id = ? GROUP BY priority');
            $stmt->execute([$branch_id]);
            $task_priority_counts = [];
            while ($row = $stmt->fetch()) {
                $task_priority_counts[$row['priority']] = $row['count'];
            }
            
            // Recent tasks
            $stmt = $pdo->prepare('SELECT t.*, 
                                  p.name as product_name, 
                                  s.tracking_number as shipment_tracking,
                                  s.recipient_name as recipient_name,
                                  u.username as delivery_user
                                  FROM delivery_tasks t 
                                  LEFT JOIN products p ON t.product_id = p.id 
                                  LEFT JOIN shipments s ON t.shipment_id = s.id
                                  LEFT JOIN users u ON t.delivery_user_id = u.id 
                                  WHERE t.branch_id = ? 
                                  ORDER BY t.created_at DESC LIMIT 5');
            $stmt->execute([$branch_id]);
            $recent_tasks = $stmt->fetchAll();
            
            // Delivery users with active tasks
            $stmt = $pdo->prepare('SELECT u.id, u.username, u.phone, u.email, u.status,
                                  COUNT(t.id) as task_count
                                  FROM users u
                                  LEFT JOIN delivery_tasks t ON u.id = t.delivery_user_id AND t.status IN ("assigned", "in_progress")
                                  WHERE u.branch_id = ? AND u.role = "delivery_user" AND u.status = "active"
                                  GROUP BY u.id
                                  ORDER BY task_count DESC
                                  LIMIT 5');
            $stmt->execute([$branch_id]);
            $delivery_users_with_tasks = $stmt->fetchAll();
        } else {
            // If table doesn't exist, set default values
            $task_status_counts = [];
            $total_tasks = 0;
            $task_priority_counts = [];
            $recent_tasks = [];
            $delivery_users_with_tasks = [];
            
            // Show database update message
            $db_update_needed = true;
        }
    } catch (PDOException $e) {
        // If there's an error, set default values
        $task_status_counts = [];
        $total_tasks = 0;
        $task_priority_counts = [];
        $recent_tasks = [];
        $delivery_users_with_tasks = [];
        
        // Show database update message
        $db_update_needed = true;
    }
    
    // Check if products table exists and get pending products count
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'products'");
        $stmt->execute();
        $productsTableExists = $stmt->rowCount() > 0;
        
        if ($productsTableExists) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE branch_id = ? AND status = "pending"');
            $stmt->execute([$branch_id]);
            $pending_products = $stmt->fetchColumn();
        } else {
            $pending_products = 0;
        }
    } catch (PDOException $e) {
        $pending_products = 0;
    }
    
} catch (PDOException $e) {
    // Handle database error
    $error_message = $e->getMessage();
}

// Include header
include_once '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="fas fa-tachometer-alt me-2"></i>Branch Admin Dashboard</h2>
        <p class="text-muted">
            Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!
            <?php if (isset($branch) && $branch): ?>
                You are managing the <?php echo htmlspecialchars($branch['name']); ?> branch.
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if (isset($error_message)): ?>
<div class="alert alert-danger">
    <?php echo htmlspecialchars($error_message); ?>
</div>
<?php endif; ?>

<?php if (isset($db_update_needed) && $db_update_needed): ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <strong>Database Update Required!</strong> Some features may not work correctly. Please run the database update script.
    <a href="../database/update_schema.php" class="btn btn-sm btn-warning ms-3">Update Database</a>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Quick Access Cards -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Quick Access</h5>
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="branch_details.php" class="btn btn-light border w-100 text-start p-3">
                            <i class="fas fa-building text-primary me-2"></i> Branch Details
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="branch_users.php" class="btn btn-light border w-100 text-start p-3">
                            <i class="fas fa-users text-success me-2"></i> Manage Users
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="task_management.php" class="btn btn-light border w-100 text-start p-3">
                            <i class="fas fa-tasks text-warning me-2"></i> Task Management
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="shipment.php" class="btn btn-light border w-100 text-start p-3">
                            <i class="fas fa-box text-info me-2"></i> Create Shipment
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Task Management Quick Actions -->
<?php if (!isset($db_update_needed) || !$db_update_needed): ?>
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Task Management</h5>
                    <a href="task_management.php" class="btn btn-sm btn-primary">View All Tasks</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <div class="display-4 text-primary mb-2"><?php echo $total_tasks ?? 0; ?></div>
                                        <h6 class="text-muted">Total Tasks</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <div class="display-4 text-warning mb-2"><?php echo $task_status_counts['assigned'] ?? 0; ?></div>
                                        <h6 class="text-muted">Assigned</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <div class="display-4 text-info mb-2"><?php echo $task_status_counts['in_progress'] ?? 0; ?></div>
                                        <h6 class="text-muted">In Progress</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <div class="display-4 text-success mb-2"><?php echo $task_status_counts['completed'] ?? 0; ?></div>
                                        <h6 class="text-muted">Completed</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body">
                                        <h6 class="text-muted mb-3">Tasks by Priority</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Urgent</span>
                                            <span class="badge bg-danger"><?php echo $task_priority_counts['urgent'] ?? 0; ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>High</span>
                                            <span class="badge bg-warning"><?php echo $task_priority_counts['high'] ?? 0; ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Medium</span>
                                            <span class="badge bg-info"><?php echo $task_priority_counts['medium'] ?? 0; ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Low</span>
                                            <span class="badge bg-secondary"><?php echo $task_priority_counts['low'] ?? 0; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body">
                                        <h6 class="text-muted mb-3">Task Quick Actions</h6>
                                        <div class="d-grid gap-2">
                                            <a href="create_task.php" class="btn btn-outline-primary">
                                                <i class="fas fa-plus me-2"></i> Create New Task
                                            </a>
                                            <a href="batch_assign.php" class="btn btn-outline-success">
                                                <i class="fas fa-tasks me-2"></i> Batch Assign Tasks
                                            </a>
                                            <a href="task_tracking.php" class="btn btn-outline-info">
                                                <i class="fas fa-map-marked-alt me-2"></i> Track Delivery Users
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h6 class="mb-0">Delivery Users with Active Tasks</h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php if (isset($delivery_users_with_tasks) && count($delivery_users_with_tasks) > 0): ?>
                                        <?php foreach ($delivery_users_with_tasks as $user): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($user['username']); ?></h6>
                                                        <small class="text-muted"><?php echo htmlspecialchars($user['phone']); ?></small>
                                                    </div>
                                                    <span class="badge bg-primary rounded-pill"><?php echo $user['task_count']; ?> tasks</span>
                                                </div>
                                                <div class="progress mt-2" style="height: 5px;">
                                                    <?php 
                                                    $percentage = min(100, ($user['task_count'] / 10) * 100);
                                                    $color_class = 'bg-success';
                                                    if ($percentage > 80) {
                                                        $color_class = 'bg-danger';
                                                    } elseif ($percentage > 50) {
                                                        $color_class = 'bg-warning';
                                                    }
                                                    ?>
                                                    <div class="progress-bar <?php echo $color_class; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $user['task_count']; ?>" aria-valuemin="0" aria-valuemax="10"></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="list-group-item text-center py-4">
                                            <p class="mb-0 text-muted">No active delivery users with tasks</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Total Staff</h6>
                        <h3 class="mb-0"><?php echo $total_staff; ?></h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                        <i class="fas fa-users text-primary fa-2x"></i>
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
                        <h6 class="text-muted mb-2">Total Shipments</h6>
                        <h3 class="mb-0"><?php echo $total_shipments; ?></h3>
                    </div>
                    <div class="bg-success bg-opacity-10 p-3 rounded">
                        <i class="fas fa-box text-success fa-2x"></i>
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
                        <h6 class="text-muted mb-2">Pending Products</h6>
                        <h3 class="mb-0"><?php echo $pending_products ?? 0; ?></h3>
                    </div>
                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                        <i class="fas fa-boxes text-warning fa-2x"></i>
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
                        <h6 class="text-muted mb-2">Delivered Today</h6>
                        <h3 class="mb-0"><?php echo $today_deliveries; ?></h3>
                    </div>
                    <div class="bg-info bg-opacity-10 p-3 rounded">
                        <i class="fas fa-check-circle text-info fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Additional KPIs -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">In Transit</h6>
                        <h3 class="mb-0"><?php echo $in_transit_shipments; ?></h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                        <i class="fas fa-truck text-primary fa-2x"></i>
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
                        <h6 class="text-muted mb-2">Issues/Delays</h6>
                        <h3 class="mb-0"><?php echo $issues_delays; ?></h3>
                    </div>
                    <div class="bg-danger bg-opacity-10 p-3 rounded">
                        <i class="fas fa-exclamation-triangle text-danger fa-2x"></i>
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
                        <h6 class="text-muted mb-2">Pending Shipments</h6>
                        <h3 class="mb-0"><?php echo $pending_shipments; ?></h3>
                    </div>
                    <div class="bg-success bg-opacity-10 p-3 rounded">
                        <i class="fas fa-calendar-check text-success fa-2x"></i>
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
                        <h6 class="text-muted mb-2">Active Delivery Users</h6>
                        <h3 class="mb-0"><?php echo $active_delivery_users; ?></h3>
                    </div>
                    <div class="bg-info bg-opacity-10 p-3 rounded">
                        <i class="fas fa-user-check text-info fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Tasks -->
<?php if (!isset($db_update_needed) || !$db_update_needed): ?>
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Tasks</h5>
                    <a href="task_management.php" class="btn btn-sm btn-primary">View All</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Details</th>
                                <th>Delivery User</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($recent_tasks) && count($recent_tasks) > 0): ?>
                                <?php foreach ($recent_tasks as $task): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($task['id']); ?></td>
                                    <td>
                                        <?php if (!empty($task['shipment_tracking'])): ?>
                                            <span class="badge bg-info">Shipment</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Product</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($task['shipment_tracking'])): ?>
                                            <strong>Tracking:</strong> <?php echo htmlspecialchars($task['shipment_tracking']); ?><br>
                                            <small><?php echo htmlspecialchars($task['recipient_name']); ?></small>
                                        <?php else: ?>
                                            <strong><?php echo htmlspecialchars($task['product_name'] ?? 'N/A'); ?></strong>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($task['delivery_user'] ?? 'Not Assigned'); ?></td>
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
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="view_task.php?id=<?php echo $task['id']; ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_task.php?id=<?php echo $task['id']; ?>" class="btn btn-outline-secondary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No recent tasks found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent Shipments -->
<div class="row">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Shipments</h5>
                    <a href="couriers.php" class="btn btn-sm btn-primary">View All</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Tracking #</th>
                                <th>Sender</th>
                                <th>Recipient</th>
                                <th>Delivery User</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recent_shipments) > 0): ?>
                                <?php foreach ($recent_shipments as $shipment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($shipment['tracking_number']); ?></td>
                                    <td><?php echo htmlspecialchars($shipment['sender_name']); ?></td>
                                    <td><?php echo htmlspecialchars($shipment['recipient_name']); ?></td>
                                    <td><?php echo $shipment['delivery_user'] ? htmlspecialchars($shipment['delivery_user']) : 'Not Assigned'; ?></td>
                                    <td>
                                        <?php 
                                        $status_class = '';
                                        switch ($shipment['status']) {
                                            case 'pending':
                                                $status_class = 'bg-warning';
                                                break;
                                            case 'in_transit':
                                                $status_class = 'bg-info';
                                                break;
                                            case 'delivered':
                                                $status_class = 'bg-success';
                                                break;
                                            case 'cancelled':
                                                $status_class = 'bg-danger';
                                                break;
                                            case 'delayed':
                                                $status_class = 'bg-danger';
                                                break;
                                            case 'issue':
                                                $status_class = 'bg-danger';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $shipment['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($shipment['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="update_courier.php?id=<?php echo $shipment['id']; ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#trackingModal<?php echo $shipment['id']; ?>">
                                                <i class="fas fa-map-marker-alt"></i>
                                            </button>
                                        </div>
                                        
                                        <!-- Tracking Modal -->
                                        <div class="modal fade" id="trackingModal<?php echo $shipment['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Tracking: <?php echo htmlspecialchars($shipment['tracking_number']); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="tracking-timeline">
                                                            <!-- This would be populated with actual tracking data -->
                                                            <div class="tracking-item">
                                                                <div class="tracking-icon bg-success">
                                                                    <i class="fas fa-check"></i>
                                                                </div>
                                                                <div class="tracking-content">
                                                                    <h6>Package Registered</h6>
                                                                    <p class="text-muted"><?php echo date('M d, Y h:i A', strtotime($shipment['created_at'])); ?></p>
                                                                </div>
                                                            </div>
                                                            <!-- More tracking items would be added here -->
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
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No shipments found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>
