<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has branch_admin role
check_role('branch_admin');

// Include database connection
require_once '../config/db_connect.php';

// Get branch ID from session
$branch_id = $_SESSION['branch_id'];

// Check if ID is provided
if (!isset($_GET['id'])) {
    header('Location: task_management.php');
    exit;
}

$task_id = (int)$_GET['id'];

// Get task details
try {
    $stmt = $pdo->prepare("SELECT t.*, 
                          p.name as product_name, 
                          p.description as product_description,
                          p.category as product_category,
                          p.quantity as product_quantity,
                          s.tracking_number as shipment_tracking,
                          s.recipient_name as recipient_name,
                          s.recipient_address as recipient_address,
                          s.recipient_phone as recipient_phone,
                          u.username as delivery_user,
                          u.phone as delivery_user_phone,
                          u.email as delivery_user_email,
                          l.name as location_name,
                          l.address as location_address,
                          l.latitude as location_latitude,
                          l.longitude as location_longitude
                          FROM delivery_tasks t 
                          LEFT JOIN products p ON t.product_id = p.id 
                          LEFT JOIN shipments s ON t.shipment_id = s.id
                          LEFT JOIN users u ON t.delivery_user_id = u.id 
                          LEFT JOIN locations l ON t.location_id = l.id 
                          WHERE t.id = ? AND t.branch_id = ?");
    $stmt->execute([$task_id, $branch_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        $_SESSION['error_message'] = "Task not found or you don't have permission to view it.";
        header('Location: task_management.php');
        exit;
    }
    
    // Get task history
    $stmt = $pdo->prepare("SELECT h.*, u.username as user_name 
                          FROM task_history h 
                          LEFT JOIN users u ON h.created_by = u.id 
                          WHERE h.task_id = ? 
                          ORDER BY h.created_at DESC");
    $stmt->execute([$task_id]);
    $task_history = $stmt->fetchAll();
    
    // Get delivery user's latest location if available
    if ($task['delivery_user_id']) {
        $stmt = $pdo->prepare("SELECT * FROM delivery_locations 
                              WHERE user_id = ? 
                              ORDER BY timestamp DESC 
                              LIMIT 1");
        $stmt->execute([$task['delivery_user_id']]);
        $current_location = $stmt->fetch();
    }
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header('Location: task_management.php');
    exit;
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="task_management.php">Task Management</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Task Details</li>
                </ol>
            </nav>
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-clipboard-list me-2"></i>Task Details</h2>
                <div>
                    <a href="edit_task.php?id=<?php echo $task_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-1"></i> Edit Task
                    </a>
                    <a href="task_management.php" class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-arrow-left me-1"></i> Back to Tasks
                    </a>
                </div>
            </div>
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

    <div class="row">
        <!-- Task Details -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Task #<?php echo $task_id; ?></h5>
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
                        <span class="badge <?php echo $status_class; ?> fs-6">
                            <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="mb-3">Task Information</h6>
                            <p class="mb-1"><strong>Type:</strong> 
                                <?php echo !empty($task['shipment_tracking']) ? 'Shipment Delivery' : 'Product Delivery'; ?>
                            </p>
                            <p class="mb-1"><strong>Priority:</strong> 
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
                            </p>
                            <p class="mb-1"><strong>Scheduled Date:</strong> 
                                <?php echo date('F d, Y', strtotime($task['scheduled_date'] ?? $task['created_at'])); ?>
                            </p>
                            <p class="mb-1"><strong>Created:</strong> 
                                <?php echo date('F d, Y h:i A', strtotime($task['created_at'])); ?>
                            </p>
                            <?php if ($task['status'] === 'completed' && !empty($task['completed_at'])): ?>
                                <p class="mb-1"><strong>Completed:</strong> 
                                    <?php echo date('F d, Y h:i A', strtotime($task['completed_at'])); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-3">Delivery User</h6>
                            <?php if (!empty($task['delivery_user'])): ?>
                                <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($task['delivery_user']); ?></p>
                                <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($task['delivery_user_phone']); ?></p>
                                <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($task['delivery_user_email']); ?></p>
                                <div class="mt-2">
                                    <a href="tel:<?php echo htmlspecialchars($task['delivery_user_phone']); ?>" class="btn btn-sm btn-outline-primary me-2">
                                        <i class="fas fa-phone-alt me-1"></i> Call
                                    </a>
                                    <a href="mailto:<?php echo htmlspecialchars($task['delivery_user_email']); ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-envelope me-1"></i> Email
                                    </a>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No delivery user assigned</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <?php if (!empty($task['shipment_tracking'])): ?>
                        <!-- Shipment Details -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h6 class="mb-3">Shipment Details</h6>
                                <p class="mb-1"><strong>Tracking Number:</strong> <?php echo htmlspecialchars($task['shipment_tracking']); ?></p>
                                <p class="mb-1"><strong>Recipient:</strong> <?php echo htmlspecialchars($task['recipient_name']); ?></p>
                                <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($task['recipient_phone']); ?></p>
                                <p class="mb-1"><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($task['recipient_address'])); ?></p>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Product Details -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h6 class="mb-3">Product Details</h6>
                                <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($task['product_name']); ?></p>
                                <p class="mb-1"><strong>Category:</strong> <?php echo htmlspecialchars($task['product_category'] ?? 'N/A'); ?></p>
                                <p class="mb-1"><strong>Quantity:</strong> <?php echo htmlspecialchars($task['product_quantity']); ?></p>
                                <p class="mb-1"><strong>Description:</strong> <?php echo htmlspecialchars($task['product_description'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Delivery Location -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <h6 class="mb-3">Delivery Location</h6>
                            <?php if (!empty($task['location_name']) || !empty($task['recipient_address'])): ?>
                                <?php if (!empty($task['location_name'])): ?>
                                    <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($task['location_name']); ?></p>
                                    <p class="mb-1"><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($task['location_address'])); ?></p>
                                <?php else: ?>
                                    <p class="mb-1"><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($task['recipient_address'])); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($task['location_latitude']) && !empty($task['location_longitude'])): ?>
                                    <div class="mt-3">
                                        <div id="locationMap" style="height: 300px;"></div>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-muted">No location information available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($task['instructions'])): ?>
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h6 class="mb-3">Delivery Instructions</h6>
                                <div class="p-3 bg-light rounded">
                                    <?php echo nl2br(htmlspecialchars($task['instructions'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Task History -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Task History</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($task_history) && count($task_history) > 0): ?>
                        <div class="timeline">
                            <?php foreach ($task_history as $history): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker">
                                        <?php 
                                        $icon_class = 'fa-circle';
                                        switch ($history['status']) {
                                            case 'assigned':
                                                $icon_class = 'fa-clipboard-list text-warning';
                                                break;
                                            case 'in_progress':
                                                $icon_class = 'fa-truck text-info';
                                                break;
                                            case 'completed':
                                                $icon_class = 'fa-check-circle text-success';
                                                break;
                                            case 'cancelled':
                                                $icon_class = 'fa-times-circle text-danger';
                                                break;
                                        }
                                        ?>
                                        <i class="fas <?php echo $icon_class; ?>"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="d-flex justify-content-between">
                                            <h6 class="mb-1">Status changed to <?php echo ucfirst(str_replace('_', ' ', $history['status'])); ?></h6>
                                            <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($history['created_at'])); ?></small>
                                        </div>
                                        <?php if (!empty($history['notes'])): ?>
                                            <p class="mb-1"><?php echo htmlspecialchars($history['notes']); ?></p>
                                        <?php endif; ?>
                                        <small class="text-muted">By <?php echo htmlspecialchars($history['user_name'] ?? 'System'); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="mb-0">No history records found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Current Location -->
            <?php if (!empty($current_location)): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Current Location</h5>
                </div>
                <div class="card-body">
                    <div id="currentLocationMap" style="height: 300px;"></div>
                    <div class="mt-3">
                        <p class="mb-1"><strong>Last Updated:</strong> <?php echo date('F d, Y h:i A', strtotime($current_location['timestamp'])); ?></p>
                        <a href="https://maps.google.com/?q=<?php echo $current_location['latitude']; ?>,<?php echo $current_location['longitude']; ?>" target="_blank" class="btn btn-sm btn-primary mt-2">
                            <i class="fas fa-external-link-alt me-1"></i> Open in Google Maps
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                            <i class="fas fa-sync-alt me-1"></i> Update Status
                        </button>
                        
                        <?php if (!empty($task['delivery_user_phone'])): ?>
                            <a href="tel:<?php echo htmlspecialchars($task['delivery_user_phone']); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-phone-alt me-1"></i> Call Delivery User
                            </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($task['recipient_phone'])): ?>
                            <a href="tel:<?php echo htmlspecialchars($task['recipient_phone']); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-phone-alt me-1"></i> Call Recipient
                            </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($task['location_latitude']) && !empty($task['location_longitude'])): ?>
                            <a href="https://maps.google.com/?q=<?php echo $task['location_latitude']; ?>,<?php echo $task['location_longitude']; ?>" target="_blank" class="btn btn-outline-primary">
                                <i class="fas fa-map-marker-alt me-1"></i> Open Location in Maps
                            </a>
                        <?php elseif (!empty($task['recipient_address'])): ?>
                            <a href="https://maps.google.com/?q=<?php echo urlencode($task['recipient_address']); ?>" target="_blank" class="btn btn-outline-primary">
                                <i class="fas fa-map-marker-alt me-1"></i> Open Address in Maps
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Related Tasks -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Related Tasks</h5>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        // Get related tasks for the same delivery user
                        $stmt = $pdo->prepare("SELECT t.id, t.status, t.scheduled_date, t.priority,
                                              COALESCE(p.name, CONCAT('Shipment #', s.tracking_number)) as task_name
                                              FROM delivery_tasks t 
                                              LEFT JOIN products p ON t.product_id = p.id 
                                              LEFT JOIN shipments s ON t.shipment_id = s.id
                                              WHERE t.delivery_user_id = ? AND t.id != ? AND t.branch_id = ?
                                              ORDER BY t.scheduled_date ASC
                                              LIMIT 5");
                        $stmt->execute([$task['delivery_user_id'], $task_id, $branch_id]);
                        $related_tasks = $stmt->fetchAll();
                        
                        if (count($related_tasks) > 0):
                    ?>
                        <div class="list-group">
                            <?php foreach ($related_tasks as $related): ?>
                                <a href="view_task.php?id=<?php echo $related['id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($related['task_name']); ?></h6>
                                        <?php 
                                        $status_class = '';
                                        switch ($related['status']) {
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
                                            <?php echo ucfirst(str_replace('_', ' ', $related['status'])); ?>
                                        </span>
                                    </div>
                                    <small>
                                        <strong>Priority:</strong> <?php echo ucfirst($related['priority']); ?> | 
                                        <strong>Date:</strong> <?php echo date('M d, Y', strtotime($related['scheduled_date'])); ?>
                                    </small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="mb-0">No related tasks found.</p>
                        </div>
                    <?php 
                        endif;
                    } catch (PDOException $e) {
                        echo '<div class="alert alert-danger">Error loading related tasks.</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Task Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="update_task_status.php">
                <div class="modal-body">
                    <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Current Status</label>
                        <div>
                            <span class="badge <?php echo $status_class; ?> fs-6">
                                <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">New Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="assigned" <?php echo $task['status'] == 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                            <option value="in_progress" <?php echo $task['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $task['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $task['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
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

<!-- Include Leaflet CSS and JS for maps -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    padding-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    width: 20px;
    height: 20px;
    text-align: center;
}

.timeline:before {
    content: '';
    position: absolute;
    top: 0;
    left: -20px;
    height: 100%;
    width: 2px;
    background: #e9ecef;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($task['location_latitude']) && !empty($task['location_longitude'])): ?>
    // Initialize delivery location map
    const locationMap = L.map('locationMap').setView([<?php echo $task['location_latitude']; ?>, <?php echo $task['location_longitude']; ?>], 15);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(locationMap);
    
    L.marker([<?php echo $task['location_latitude']; ?>, <?php echo $task['location_longitude']; ?>])
        .addTo(locationMap)
        .bindPopup("<?php echo htmlspecialchars($task['location_name'] ?? 'Delivery Location'); ?>")
        .openPopup();
    <?php endif; ?>
    
    <?php if (!empty($current_location)): ?>
    // Initialize current location map
    const currentLocationMap = L.map('currentLocationMap').setView([<?php echo $current_location['latitude']; ?>, <?php echo $current_location['longitude']; ?>], 15);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(currentLocationMap);
    
    L.marker([<?php echo $current_location['latitude']; ?>, <?php echo $current_location['longitude']; ?>])
        .addTo(currentLocationMap)
        .bindPopup("<?php echo htmlspecialchars($task['delivery_user']); ?>'s Current Location")
        .openPopup();
    <?php endif; ?>
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
