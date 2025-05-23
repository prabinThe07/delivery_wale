<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has branch_admin role
check_role('branch_admin');

// Include database connection
require_once '../config/db_connect.php';

// Get branch ID from session
$branch_id = $_SESSION['branch_id'];

// Handle task status updates if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task'])) {
    $task_id = (int)$_POST['task_id'];
    $new_status = $_POST['status'];
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Update task status
        $stmt = $pdo->prepare('UPDATE delivery_tasks SET status = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$new_status, $task_id]);
        
        // If status is completed, set completed_at
        if ($new_status === 'completed') {
            $stmt = $pdo->prepare('UPDATE delivery_tasks SET completed_at = NOW() WHERE id = ?');
            $stmt->execute([$task_id]);
            
            // Get product ID from task
            $stmt = $pdo->prepare('SELECT product_id FROM delivery_tasks WHERE id = ?');
            $stmt->execute([$task_id]);
            $product_id = $stmt->fetchColumn();
            
            // Update product status
            if ($product_id) {
                $stmt = $pdo->prepare('UPDATE products SET status = "delivered", updated_at = NOW() WHERE id = ?');
                $stmt->execute([$product_id]);
            }
        }
        
        // Add task history entry
        $stmt = $pdo->prepare('INSERT INTO task_history (task_id, status, notes, created_by, created_at) 
                              VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$task_id, $new_status, $notes, $_SESSION['user_id']]);
        
        // Commit transaction
        $pdo->commit();
        
        $success_message = "Task status updated successfully.";
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$user_filter = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Build query conditions based on filters
$conditions = ['t.branch_id = ?'];
$params = [$branch_id];

if ($status_filter !== 'all') {
    $conditions[] = 't.status = ?';
    $params[] = $status_filter;
}

if ($priority_filter !== 'all') {
    $conditions[] = 't.priority = ?';
    $params[] = $priority_filter;
}

if ($user_filter > 0) {
    $conditions[] = 't.delivery_user_id = ?';
    $params[] = $user_filter;
}

if (!empty($date_filter)) {
    $conditions[] = 'DATE(t.scheduled_date) = ?';
    $params[] = $date_filter;
}

// Construct the WHERE clause
$where_clause = implode(' AND ', $conditions);

// Get all tasks with filters
try {
    $stmt = $pdo->prepare("SELECT t.*, 
                          p.name as product_name, 
                          p.description as product_description,
                          p.category as product_category,
                          p.quantity as product_quantity,
                          s.tracking_number as shipment_tracking,
                          s.recipient_name as recipient_name,
                          s.recipient_address as recipient_address,
                          u.username as delivery_user,
                          u.phone as delivery_user_phone,
                          l.name as location_name,
                          l.address as location_address,
                          l.latitude as location_latitude,
                          l.longitude as location_longitude
                          FROM delivery_tasks t 
                          LEFT JOIN products p ON t.product_id = p.id 
                          LEFT JOIN shipments s ON t.shipment_id = s.id
                          LEFT JOIN users u ON t.delivery_user_id = u.id 
                          LEFT JOIN locations l ON t.location_id = l.id 
                          WHERE $where_clause
                          ORDER BY 
                            CASE t.priority
                                WHEN 'urgent' THEN 1
                                WHEN 'high' THEN 2
                                WHEN 'medium' THEN 3
                                WHEN 'low' THEN 4
                            END, 
                            t.scheduled_date ASC,
                            t.created_at ASC");
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
    
    // Get all delivery users for this branch
    $stmt = $pdo->prepare('SELECT id, username, phone FROM users 
                          WHERE branch_id = ? AND role = "delivery_user" AND status = "active" 
                          ORDER BY username');
    $stmt->execute([$branch_id]);
    $delivery_users = $stmt->fetchAll();
    
    // Get task counts by status
    $stmt = $pdo->prepare('SELECT status, COUNT(*) as count FROM delivery_tasks 
                          WHERE branch_id = ? GROUP BY status');
    $stmt->execute([$branch_id]);
    $status_counts = [];
    while ($row = $stmt->fetch()) {
        $status_counts[$row['status']] = $row['count'];
    }
    
    // Get total task count
    $total_tasks = array_sum($status_counts);
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-tasks me-2"></i>Task Management</h2>
            <p class="text-muted">Manage and track all delivery tasks across your branch.</p>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Task Overview Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Tasks</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_tasks ?? 0; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Assigned</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $status_counts['assigned'] ?? 0; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                In Progress</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $status_counts['in_progress'] ?? 0; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-truck fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Completed Today</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php 
                                $completed_today = 0;
                                if (isset($tasks)) {
                                    foreach ($tasks as $task) {
                                        if ($task['status'] == 'completed' && date('Y-m-d', strtotime($task['completed_at'])) == date('Y-m-d')) {
                                            $completed_today++;
                                        }
                                    }
                                }
                                echo $completed_today;
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Actions -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <form method="get" class="row g-3">
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="assigned" <?php echo $status_filter == 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                                <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="all" <?php echo $priority_filter == 'all' ? 'selected' : ''; ?>>All Priorities</option>
                                <option value="low" <?php echo $priority_filter == 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo $priority_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo $priority_filter == 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="urgent" <?php echo $priority_filter == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="user" class="form-label">Delivery User</label>
                            <select class="form-select" id="user" name="user">
                                <option value="0" <?php echo $user_filter == 0 ? 'selected' : ''; ?>>All Users</option>
                                <?php if (isset($delivery_users) && count($delivery_users) > 0): ?>
                                    <?php foreach ($delivery_users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="date" class="form-label">Scheduled Date</label>
                            <input type="date" class="form-control" id="date" name="date" value="<?php echo $date_filter; ?>">
                        </div>
                        <div class="col-md-12 mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-1"></i> Apply Filters
                            </button>
                            <a href="task_management.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-redo me-1"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
                <div class="col-lg-4 text-end mt-3 mt-lg-0">
                    <a href="create_task.php" class="btn btn-success">
                        <i class="fas fa-plus me-1"></i> Create New Task
                    </a>
                    <a href="batch_assign.php" class="btn btn-primary ms-2">
                        <i class="fas fa-tasks me-1"></i> Batch Assignment
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Tasks Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Delivery Tasks</h5>
        </div>
        <div class="card-body">
            <?php if (isset($tasks) && count($tasks) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="tasksTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Details</th>
                                <th>Delivery User</th>
                                <th>Location</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Scheduled</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $task): ?>
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
                                        <strong><?php echo htmlspecialchars($task['product_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($task['product_category'] ?? ''); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($task['delivery_user'] ?? 'Not Assigned'); ?></td>
                                <td>
                                    <?php if (!empty($task['location_name'])): ?>
                                        <?php echo htmlspecialchars($task['location_name']); ?>
                                    <?php elseif (!empty($task['recipient_address'])): ?>
                                        <?php echo htmlspecialchars(substr($task['recipient_address'], 0, 30) . (strlen($task['recipient_address']) > 30 ? '...' : '')); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not specified</span>
                                    <?php endif; ?>
                                </td>
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
                                <td>
                                    <?php 
                                    if (!empty($task['scheduled_date'])) {
                                        echo date('M d, Y', strtotime($task['scheduled_date']));
                                    } else {
                                        echo date('M d, Y', strtotime($task['created_at']));
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view_task.php?id=<?php echo $task['id']; ?>" class="btn btn-outline-primary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_task.php?id=<?php echo $task['id']; ?>" class="btn btn-outline-secondary" title="Edit Task">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo $task['id']; ?>" title="Update Status">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                        <?php if (!empty($task['location_latitude']) && !empty($task['location_longitude'])): ?>
                                        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#mapModal<?php echo $task['id']; ?>" title="View on Map">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Status Update Modal -->
                                    <div class="modal fade" id="statusModal<?php echo $task['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Update Task Status</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="post">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Current Status</label>
                                                            <div>
                                                                <span class="badge <?php echo $status_class; ?> fs-6">
                                                                    <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="status<?php echo $task['id']; ?>" class="form-label">New Status</label>
                                                            <select class="form-select" id="status<?php echo $task['id']; ?>" name="status" required>
                                                                <option value="assigned" <?php echo $task['status'] == 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                                                                <option value="in_progress" <?php echo $task['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                                <option value="completed" <?php echo $task['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                                <option value="cancelled" <?php echo $task['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="notes<?php echo $task['id']; ?>" class="form-label">Notes</label>
                                                            <textarea class="form-control" id="notes<?php echo $task['id']; ?>" name="notes" rows="3"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="update_task" class="btn btn-primary">Update Status</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Map Modal -->
                                    <?php if (!empty($task['location_latitude']) && !empty($task['location_longitude'])): ?>
                                    <div class="modal fade" id="mapModal<?php echo $task['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Location Map</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div id="map<?php echo $task['id']; ?>" style="height: 400px;"></div>
                                                    <script>
                                                        document.addEventListener('DOMContentLoaded', function() {
                                                            const mapModal<?php echo $task['id']; ?> = document.getElementById('mapModal<?php echo $task['id']; ?>');
                                                            mapModal<?php echo $task['id']; ?>.addEventListener('shown.bs.modal', function () {
                                                                const map<?php echo $task['id']; ?> = L.map('map<?php echo $task['id']; ?>').setView([<?php echo $task['location_latitude']; ?>, <?php echo $task['location_longitude']; ?>], 15);
                                                                
                                                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                                                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                                                                }).addTo(map<?php echo $task['id']; ?>);
                                                                
                                                                L.marker([<?php echo $task['location_latitude']; ?>, <?php echo $task['location_longitude']; ?>])
                                                                    .addTo(map<?php echo $task['id']; ?>)
                                                                    .bindPopup("<?php echo htmlspecialchars($task['location_name'] ?? 'Delivery Location'); ?>")
                                                                    .openPopup();
                                                            });
                                                        });
                                                    </script>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <a href="https://maps.google.com/?q=<?php echo $task['location_latitude']; ?>,<?php echo $task['location_longitude']; ?>" target="_blank" class="btn btn-primary">
                                                        <i class="fas fa-external-link-alt me-1"></i> Open in Google Maps
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-tasks fa-4x text-muted mb-3"></i>
                    <h5>No tasks found</h5>
                    <p class="text-muted">No tasks match your current filter criteria.</p>
                    <a href="create_task.php" class="btn btn-primary mt-3">
                        <i class="fas fa-plus me-1"></i> Create New Task
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Include Leaflet CSS and JS for maps -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>

<!-- Initialize DataTables -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('tasksTable')) {
            new DataTable('#tasksTable', {
                order: [[7, 'asc'], [5, 'asc']],
                pageLength: 25,
                language: {
                    search: "Search tasks:",
                    lengthMenu: "Show _MENU_ tasks per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ tasks",
                    infoEmpty: "Showing 0 to 0 of 0 tasks",
                    infoFiltered: "(filtered from _MAX_ total tasks)"
                }
            });
        }
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
