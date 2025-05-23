<?php
// Include necessary files
include '../includes/session_check.php';
include '../config/db_connect.php';
include '../includes/header.php';

// Check if user is a branch admin
if ($_SESSION['user_type'] !== 'branch_admin') {
    header('Location: ../index.php');
    exit();
}

// Get branch ID from session
$branch_id = $_SESSION['branch_id'];

// Fetch all delivery users for this branch
$delivery_users_query = "SELECT id, username, full_name FROM users WHERE user_type = 'delivery_user' AND branch_id = ?";
$stmt = $conn->prepare($delivery_users_query);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$delivery_users_result = $stmt->get_result();
$delivery_users = [];
while ($user = $delivery_users_result->fetch_assoc()) {
    $delivery_users[$user['id']] = $user;
}
$stmt->close();

// Fetch all tasks for this branch with related information
$tasks_query = "
    SELECT 
        dt.id, 
        dt.title, 
        dt.description, 
        dt.status, 
        dt.priority,
        dt.assigned_to,
        dt.created_at,
        dt.due_date,
        dt.location_id,
        l.name as location_name,
        GROUP_CONCAT(p.name SEPARATOR ', ') as products,
        COUNT(p.id) as product_count
    FROM 
        delivery_tasks dt
    LEFT JOIN 
        locations l ON dt.location_id = l.id
    LEFT JOIN 
        task_products tp ON dt.id = tp.task_id
    LEFT JOIN 
        products p ON tp.product_id = p.id
    WHERE 
        dt.branch_id = ?
    GROUP BY 
        dt.id
    ORDER BY 
        FIELD(dt.status, 'pending', 'in_progress', 'completed', 'cancelled'),
        FIELD(dt.priority, 'high', 'medium', 'low'),
        dt.due_date ASC
";

$stmt = $conn->prepare($tasks_query);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$tasks_result = $stmt->get_result();
$stmt->close();

// Get task counts by status
$status_counts_query = "
    SELECT 
        status, 
        COUNT(*) as count 
    FROM 
        delivery_tasks 
    WHERE 
        branch_id = ? 
    GROUP BY 
        status
";
$stmt = $conn->prepare($status_counts_query);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$status_counts_result = $stmt->get_result();
$status_counts = [
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'cancelled' => 0
];
while ($row = $status_counts_result->fetch_assoc()) {
    $status_counts[$row['status']] = $row['count'];
}
$stmt->close();

// Get task counts by priority
$priority_counts_query = "
    SELECT 
        priority, 
        COUNT(*) as count 
    FROM 
        delivery_tasks 
    WHERE 
        branch_id = ? 
    GROUP BY 
        priority
";
$stmt = $conn->prepare($priority_counts_query);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$priority_counts_result = $stmt->get_result();
$priority_counts = [
    'high' => 0,
    'medium' => 0,
    'low' => 0
];
while ($row = $priority_counts_result->fetch_assoc()) {
    $priority_counts[$row['priority']] = $row['count'];
}
$stmt->close();

// Get tasks assigned to each delivery user
$user_task_counts_query = "
    SELECT 
        assigned_to, 
        COUNT(*) as count 
    FROM 
        delivery_tasks 
    WHERE 
        branch_id = ? AND 
        status IN ('pending', 'in_progress')
    GROUP BY 
        assigned_to
";
$stmt = $conn->prepare($user_task_counts_query);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$user_task_counts_result = $stmt->get_result();
$user_task_counts = [];
while ($row = $user_task_counts_result->fetch_assoc()) {
    $user_task_counts[$row['assigned_to']] = $row['count'];
}
$stmt->close();

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending':
            return 'badge-warning';
        case 'in_progress':
            return 'badge-primary';
        case 'completed':
            return 'badge-success';
        case 'cancelled':
            return 'badge-danger';
        default:
            return 'badge-secondary';
    }
}

// Function to get priority badge class
function getPriorityBadgeClass($priority) {
    switch ($priority) {
        case 'high':
            return 'badge-danger';
        case 'medium':
            return 'badge-warning';
        case 'low':
            return 'badge-info';
        default:
            return 'badge-secondary';
    }
}

// Handle task filtering
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_priority = isset($_GET['priority']) ? $_GET['priority'] : '';
$filter_user = isset($_GET['user']) ? $_GET['user'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

// Build filter query
$filter_query = "
    SELECT 
        dt.id, 
        dt.title, 
        dt.description, 
        dt.status, 
        dt.priority,
        dt.assigned_to,
        dt.created_at,
        dt.due_date,
        dt.location_id,
        l.name as location_name,
        GROUP_CONCAT(p.name SEPARATOR ', ') as products,
        COUNT(p.id) as product_count
    FROM 
        delivery_tasks dt
    LEFT JOIN 
        locations l ON dt.location_id = l.id
    LEFT JOIN 
        task_products tp ON dt.id = tp.task_id
    LEFT JOIN 
        products p ON tp.product_id = p.id
    WHERE 
        dt.branch_id = ?
";

$params = [$branch_id];
$types = "i";

if (!empty($filter_status)) {
    $filter_query .= " AND dt.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if (!empty($filter_priority)) {
    $filter_query .= " AND dt.priority = ?";
    $params[] = $filter_priority;
    $types .= "s";
}

if (!empty($filter_user)) {
    $filter_query .= " AND dt.assigned_to = ?";
    $params[] = $filter_user;
    $types .= "i";
}

if (!empty($filter_date)) {
    $filter_query .= " AND DATE(dt.due_date) = ?";
    $params[] = $filter_date;
    $types .= "s";
}

$filter_query .= "
    GROUP BY 
        dt.id
    ORDER BY 
        FIELD(dt.status, 'pending', 'in_progress', 'completed', 'cancelled'),
        FIELD(dt.priority, 'high', 'medium', 'low'),
        dt.due_date ASC
";

$stmt = $conn->prepare($filter_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$filtered_tasks_result = $stmt->get_result();
$stmt->close();

// Check if we're using filtered results
$using_filters = !empty($filter_status) || !empty($filter_priority) || !empty($filter_user) || !empty($filter_date);
$tasks_to_display = $using_filters ? $filtered_tasks_result : $tasks_result;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Dashboard - Branch Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <style>
        .task-card {
            border-left: 4px solid #ccc;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .task-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .task-card.priority-high {
            border-left-color: #dc3545;
        }
        .task-card.priority-medium {
            border-left-color: #ffc107;
        }
        .task-card.priority-low {
            border-left-color: #17a2b8;
        }
        .task-card.status-pending {
            background-color: #fff8e1;
        }
        .task-card.status-in_progress {
            background-color: #e3f2fd;
        }
        .task-card.status-completed {
            background-color: #e8f5e9;
        }
        .task-card.status-cancelled {
            background-color: #ffebee;
        }
        .dashboard-stats {
            margin-bottom: 20px;
        }
        .stat-card {
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stat-card h5 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        .stat-card .stat-value {
            font-size: 24px;
            font-weight: bold;
        }
        .filter-form {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        #map-container {
            height: 400px;
            margin-bottom: 20px;
            border-radius: 5px;
            overflow: hidden;
        }
        .action-buttons .btn {
            margin-right: 5px;
        }
        .task-details {
            margin-top: 10px;
        }
        .task-products {
            margin-top: 5px;
            font-style: italic;
        }
        .user-workload {
            height: 10px;
            border-radius: 5px;
            margin-top: 5px;
        }
        .workload-light {
            background-color: #28a745;
        }
        .workload-medium {
            background-color: #ffc107;
        }
        .workload-heavy {
            background-color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <h2><i class="fas fa-tasks"></i> Task Management Dashboard</h2>
        <p>Manage and track all delivery tasks for your branch</p>
        
        <div class="row dashboard-stats">
            <div class="col-md-3">
                <div class="stat-card bg-light">
                    <h5>Task Status</h5>
                    <div class="row">
                        <div class="col-6">
                            <span class="badge badge-warning">Pending</span>
                            <div class="stat-value"><?php echo $status_counts['pending']; ?></div>
                        </div>
                        <div class="col-6">
                            <span class="badge badge-primary">In Progress</span>
                            <div class="stat-value"><?php echo $status_counts['in_progress']; ?></div>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-6">
                            <span class="badge badge-success">Completed</span>
                            <div class="stat-value"><?php echo $status_counts['completed']; ?></div>
                        </div>
                        <div class="col-6">
                            <span class="badge badge-danger">Cancelled</span>
                            <div class="stat-value"><?php echo $status_counts['cancelled']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card bg-light">
                    <h5>Task Priority</h5>
                    <div class="row">
                        <div class="col-4">
                            <span class="badge badge-danger">High</span>
                            <div class="stat-value"><?php echo $priority_counts['high']; ?></div>
                        </div>
                        <div class="col-4">
                            <span class="badge badge-warning">Medium</span>
                            <div class="stat-value"><?php echo $priority_counts['medium']; ?></div>
                        </div>
                        <div class="col-4">
                            <span class="badge badge-info">Low</span>
                            <div class="stat-value"><?php echo $priority_counts['low']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="stat-card bg-light">
                    <h5>Delivery User Workload</h5>
                    <div class="row">
                        <?php foreach ($delivery_users as $user_id => $user): ?>
                            <?php 
                                $task_count = isset($user_task_counts[$user_id]) ? $user_task_counts[$user_id] : 0;
                                $workload_class = 'workload-light';
                                if ($task_count > 10) {
                                    $workload_class = 'workload-heavy';
                                } elseif ($task_count > 5) {
                                    $workload_class = 'workload-medium';
                                }
                                $workload_percentage = min(100, $task_count * 10);
                            ?>
                            <div class="col-md-6 mb-2">
                                <div><?php echo $user['full_name']; ?> (<?php echo $task_count; ?> tasks)</div>
                                <div class="progress">
                                    <div class="progress-bar <?php echo $workload_class; ?>" role="progressbar" 
                                         style="width: <?php echo $workload_percentage; ?>%" 
                                         aria-valuenow="<?php echo $task_count; ?>" aria-valuemin="0" aria-valuemax="10">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-map-marked-alt"></i> Delivery Task Map</h5>
                    </div>
                    <div class="card-body">
                        <div id="map-container"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-clipboard-list"></i> Task List</h5>
                        <div>
                            <a href="create_task.php" class="btn btn-success btn-sm">
                                <i class="fas fa-plus"></i> Create New Task
                            </a>
                            <a href="batch_assign.php" class="btn btn-info btn-sm">
                                <i class="fas fa-tasks"></i> Batch Assign
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="filter-form">
                            <form method="GET" action="" class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="status">Status</label>
                                        <select name="status" id="status" class="form-control form-control-sm">
                                            <option value="">All Statuses</option>
                                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="priority">Priority</label>
                                        <select name="priority" id="priority" class="form-control form-control-sm">
                                            <option value="">All Priorities</option>
                                            <option value="high" <?php echo $filter_priority === 'high' ? 'selected' : ''; ?>>High</option>
                                            <option value="medium" <?php echo $filter_priority === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                            <option value="low" <?php echo $filter_priority === 'low' ? 'selected' : ''; ?>>Low</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="user">Delivery User</label>
                                        <select name="user" id="user" class="form-control form-control-sm">
                                            <option value="">All Users</option>
                                            <?php foreach ($delivery_users as $user_id => $user): ?>
                                                <option value="<?php echo $user_id; ?>" <?php echo $filter_user == $user_id ? 'selected' : ''; ?>>
                                                    <?php echo $user['full_name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="date">Due Date</label>
                                        <input type="date" name="date" id="date" class="form-control form-control-sm" value="<?php echo $filter_date; ?>">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <div>
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="fas fa-filter"></i> Filter
                                            </button>
                                            <a href="task_dashboard.php" class="btn btn-secondary btn-sm">
                                                <i class="fas fa-sync"></i> Reset
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <?php if ($using_filters): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-filter"></i> Showing filtered results. 
                                <a href="task_dashboard.php" class="alert-link">Clear all filters</a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($tasks_to_display->num_rows > 0): ?>
                            <div class="task-list">
                                <?php while ($task = $tasks_to_display->fetch_assoc()): ?>
                                    <div class="card task-card priority-<?php echo $task['priority']; ?> status-<?php echo $task['status']; ?>">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-8">
                                                    <h5 class="card-title">
                                                        <?php echo htmlspecialchars($task['title']); ?>
                                                        <span class="badge <?php echo getStatusBadgeClass($task['status']); ?>">
                                                            <?php echo ucfirst($task['status']); ?>
                                                        </span>
                                                        <span class="badge <?php echo getPriorityBadgeClass($task['priority']); ?>">
                                                            <?php echo ucfirst($task['priority']); ?> Priority
                                                        </span>
                                                    </h5>
                                                    <div class="task-details">
                                                        <p><?php echo htmlspecialchars($task['description']); ?></p>
                                                        <div><strong>Location:</strong> <?php echo htmlspecialchars($task['location_name']); ?></div>
                                                        <div><strong>Due Date:</strong> <?php echo date('M d, Y', strtotime($task['due_date'])); ?></div>
                                                        <div><strong>Assigned To:</strong> 
                                                            <?php 
                                                                echo isset($delivery_users[$task['assigned_to']]) 
                                                                    ? htmlspecialchars($delivery_users[$task['assigned_to']]['full_name']) 
                                                                    : 'Unassigned'; 
                                                            ?>
                                                        </div>
                                                        <div class="task-products">
                                                            <strong>Products (<?php echo $task['product_count']; ?>):</strong> 
                                                            <?php echo htmlspecialchars($task['products'] ?: 'None assigned'); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 text-right">
                                                    <div class="action-buttons">
                                                        <a href="view_task.php?id=<?php echo $task['id']; ?>" class="btn btn-info btn-sm">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
                                                        <a href="edit_task.php?id=<?php echo $task['id']; ?>" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                        <button type="button" class="btn btn-success btn-sm update-status-btn" 
                                                                data-toggle="modal" data-target="#updateStatusModal" 
                                                                data-task-id="<?php echo $task['id']; ?>"
                                                                data-task-title="<?php echo htmlspecialchars($task['title']); ?>"
                                                                data-current-status="<?php echo $task['status']; ?>">
                                                            <i class="fas fa-sync-alt"></i> Update Status
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> No tasks found. 
                                <a href="create_task.php" class="alert-link">Create a new task</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1" role="dialog" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateStatusModalLabel">Update Task Status</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="update_task_status.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="task_id" id="modal-task-id">
                        <p>Update status for: <strong id="modal-task-title"></strong></p>
                        <div class="form-group">
                            <label for="new_status">New Status</label>
                            <select name="new_status" id="new_status" class="form-control" required>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status_notes">Notes (Optional)</label>
                            <textarea name="status_notes" id="status_notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        // Initialize map
        var map = L.map('map-container').setView([0, 0], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        
        // Fetch delivery user locations
        function fetchDeliveryUserLocations() {
            fetch('get_delivery_user_locations.php')
                .then(response => response.json())
                .then(data => {
                    // Clear existing markers
                    map.eachLayer(function(layer) {
                        if (layer instanceof L.Marker) {
                            map.removeLayer(layer);
                        }
                    });
                    
                    // Add new markers
                    let bounds = [];
                    data.forEach(user => {
                        if (user.latitude && user.longitude) {
                            const latLng = [parseFloat(user.latitude), parseFloat(user.longitude)];
                            bounds.push(latLng);
                            
                            const marker = L.marker(latLng).addTo(map);
                            marker.bindPopup(`
                                <strong>${user.full_name}</strong><br>
                                Last updated: ${user.last_location_update}<br>
                                <a href="view_user_tasks.php?user_id=${user.id}" class="btn btn-sm btn-primary mt-2">View Tasks</a>
                            `);
                        }
                    });
                    
                    // If we have locations, fit the map to show all markers
                    if (bounds.length > 0) {
                        map.fitBounds(bounds);
                    }
                })
                .catch(error => console.error('Error fetching locations:', error));
        }
        
        // Initial fetch
        fetchDeliveryUserLocations();
        
        // Refresh every 2 minutes
        setInterval(fetchDeliveryUserLocations, 120000);
        
        // Handle update status modal
        document.querySelectorAll('.update-status-btn').forEach(button => {
            button.addEventListener('click', function() {
                const taskId = this.getAttribute('data-task-id');
                const taskTitle = this.getAttribute('data-task-title');
                const currentStatus = this.getAttribute('data-current-status');
                
                document.getElementById('modal-task-id').value = taskId;
                document.getElementById('modal-task-title').textContent = taskTitle;
                document.getElementById('new_status').value = currentStatus;
            });
        });
    </script>
</body>
</html>
<?php include '../includes/footer.php'; ?>
