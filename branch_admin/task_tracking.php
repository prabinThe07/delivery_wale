<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has branch_admin role
check_role('branch_admin');

// Include database connection
require_once '../config/db_connect.php';

// Get branch ID from session
$branch_id = $_SESSION['branch_id'];

// Get all active delivery tasks for this branch
try {
    $stmt = $pdo->prepare('SELECT t.*, 
                          p.name as product_name, 
                          u.username as delivery_user,
                          u.phone as delivery_user_phone,
                          l.name as location_name,
                          l.address as location_address,
                          l.latitude as location_latitude,
                          l.longitude as location_longitude
                          FROM delivery_tasks t 
                          JOIN products p ON t.product_id = p.id 
                          JOIN users u ON t.delivery_user_id = u.id 
                          LEFT JOIN locations l ON p.location_id = l.id 
                          WHERE p.branch_id = ? AND t.status IN ("assigned", "in_progress") 
                          ORDER BY 
                            CASE t.priority
                                WHEN "urgent" THEN 1
                                WHEN "high" THEN 2
                                WHEN "medium" THEN 3
                                WHEN "low" THEN 4
                            END, 
                            t.created_at DESC');
    $stmt->execute([$branch_id]);
    $active_tasks = $stmt->fetchAll();
    
    // Get all delivery users with active tasks
    $stmt = $pdo->prepare('SELECT DISTINCT u.id, u.username, u.phone, u.email,
                          (SELECT COUNT(*) FROM delivery_tasks t WHERE t.delivery_user_id = u.id AND t.status IN ("assigned", "in_progress")) as active_task_count,
                          (SELECT COUNT(*) FROM delivery_tasks t WHERE t.delivery_user_id = u.id AND t.status = "completed" AND DATE(t.completed_at) = CURDATE()) as completed_today
                          FROM users u
                          JOIN delivery_tasks t ON u.id = t.delivery_user_id
                          JOIN products p ON t.product_id = p.id
                          WHERE u.branch_id = ? AND u.role = "delivery_user" AND u.status = "active"
                          ORDER BY u.username');
    $stmt->execute([$branch_id]);
    $delivery_users = $stmt->fetchAll();
    
    // Get today's completed tasks
    $stmt = $pdo->prepare('SELECT t.*, 
                          p.name as product_name, 
                          u.username as delivery_user,
                          l.name as location_name
                          FROM delivery_tasks t 
                          JOIN products p ON t.product_id = p.id 
                          JOIN users u ON t.delivery_user_id = u.id 
                          LEFT JOIN locations l ON p.location_id = l.id 
                          WHERE p.branch_id = ? AND t.status = "completed" AND DATE(t.completed_at) = CURDATE() 
                          ORDER BY t.completed_at DESC');
    $stmt->execute([$branch_id]);
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
            <h2><i class="fas fa-map-marked-alt me-2"></i>Delivery Task Tracking</h2>
            <p class="text-muted">Track and monitor delivery tasks and delivery user locations.</p>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Delivery Users Overview -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Delivery Users Overview</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if (isset($delivery_users) && count($delivery_users) > 0): ?>
                            <?php foreach ($delivery_users as $user): ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="avatar-circle bg-primary text-white me-3">
                                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($user['username']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($user['phone']); ?></small>
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Active Tasks:</span>
                                                <span class="badge bg-primary"><?php echo $user['active_task_count']; ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span>Completed Today:</span>
                                                <span class="badge bg-success"><?php echo $user['completed_today']; ?></span>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-white border-top-0">
                                            <div class="btn-group btn-group-sm w-100">
                                                <a href="tel:<?php echo htmlspecialchars($user['phone']); ?>" class="btn btn-outline-primary">
                                                    <i class="fas fa-phone-alt"></i>
                                                </a>
                                                <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" class="btn btn-outline-primary">
                                                    <i class="fas fa-envelope"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#userTasksModal<?php echo $user['id']; ?>">
                                                    <i class="fas fa-tasks"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- User Tasks Modal -->
                                <div class="modal fade" id="userTasksModal<?php echo $user['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Tasks for <?php echo htmlspecialchars($user['username']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <h6>Active Tasks</h6>
                                                <div class="table-responsive">
                                                    <table class="table table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>Product</th>
                                                                <th>Location</th>
                                                                <th>Priority</th>
                                                                <th>Status</th>
                                                                <th>Assigned</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php 
                                                            $user_tasks = array_filter($active_tasks, function($task) use ($user) {
                                                                return $task['delivery_user_id'] == $user['id'];
                                                            });
                                                            
                                                            if (count($user_tasks) > 0):
                                                                foreach ($user_tasks as $task):
                                                            ?>
                                                                <tr>
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
                                                                        }
                                                                        ?>
                                                                        <span class="badge <?php echo $status_class; ?>">
                                                                            <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                                                        </span>
                                                                    </td>
                                                                    <td><?php echo date('M d, Y', strtotime($task['created_at'])); ?></td>
                                                                </tr>
                                                            <?php 
                                                                endforeach;
                                                            else:
                                                            ?>
                                                                <tr>
                                                                    <td colspan="5" class="text-center">No active tasks found</td>
                                                                </tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <a href="assign_task.php" class="btn btn-primary">Assign New Task</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="text-center py-4">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <p class="mb-0">No active delivery users found.</p>
                                    <p class="text-muted">Add delivery users to your branch to start assigning tasks.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Tasks -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Active Delivery Tasks</h5>
                        <a href="assign_task.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus me-1"></i> Assign New Task
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (isset($active_tasks) && count($active_tasks) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Product</th>
                                        <th>Delivery User</th>
                                        <th>Location</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Assigned</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($active_tasks as $task): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($task['id']); ?></td>
                                        <td><?php echo htmlspecialchars($task['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($task['delivery_user']); ?></td>
                                        <td><?php echo htmlspecialchars($  ?></td>
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
                                                <a href="update_task.php?id=<?php echo $task['id']; ?>" class="btn btn-outline-secondary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#locationModal<?php echo $task['id']; ?>">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                </button>
                                            </div>
                                            
                                            <!-- Location Modal -->
                                            <div class="modal fade" id="locationModal<?php echo $task['id']; ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Delivery Location</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <?php if ($task['location_name']): ?>
                                                                <h6><?php echo htmlspecialchars($task['location_name']); ?></h6>
                                                                <p><?php echo nl2br(htmlspecialchars($task['location_address'])); ?></p>
                                                                <div class="text-center">
                                                                    <a href="https://maps.google.com/?q=<?php echo urlencode($task['location_address']); ?>" target="_blank" class="btn btn-primary">
                                                                        <i class="fas fa-map-marked-alt me-1"></i> View on Google Maps
                                                                    </a>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="text-center py-4">
                                                                    <i class="fas fa-map-marker-slash fa-3x text-muted mb-3"></i>
                                                                    <p class="mb-0">No location assigned for this task.</p>
                                                                </div>
                                                            <?php endif; ?>
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
                            <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                            <p class="mb-0">No active delivery tasks found.</p>
                            <p class="text-muted">Assign tasks to delivery users to start tracking deliveries.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Completed Tasks -->
    <div class="row">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Today's Completed Tasks</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($completed_tasks) && count($completed_tasks) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Product</th>
                                        <th>Delivery User</th>
                                        <th>Location</th>
                                        <th>Completed At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($completed_tasks as $task): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($task['id']); ?></td>
                                        <td><?php echo htmlspecialchars($task['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($task['delivery_user']); ?></td>
                                        <td><?php echo htmlspecialchars($task['location_name'] ?? 'Not Assigned'); ?></td>
                                        <td><?php echo date('h:i A', strtotime($task['completed_at'])); ?></td>
                                        <td>
                                            <a href="view_task.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye me-1"></i> View Details
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-muted mb-3"></i>
                            <p class="mb-0">No tasks completed today.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>
