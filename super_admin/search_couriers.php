<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has super_admin role
check_role('super_admin');

// Include database connection
require_once '../config/db_connect.php';

// Get all branches for filter dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM branches ORDER BY name");
    $branches = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $branches = [];
}

// Get all delivery users for filter dropdown
try {
    $stmt = $pdo->query("SELECT id, username, full_name FROM users WHERE role = 'delivery_user' ORDER BY username");
    $delivery_users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $delivery_users = [];
}

// Initialize search variables
$tracking_number = isset($_GET['tracking_number']) ? trim($_GET['tracking_number']) : '';
$sender_name = isset($_GET['sender_name']) ? trim($_GET['sender_name']) : '';
$recipient_name = isset($_GET['recipient_name']) ? trim($_GET['recipient_name']) : '';
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : '';
$delivery_user_id = isset($_GET['delivery_user_id']) ? (int)$_GET['delivery_user_id'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build the query with filters
$query = "SELECT s.*, b.name as branch_name, u.username as delivery_user 
          FROM shipments s 
          LEFT JOIN branches b ON s.branch_id = b.id 
          LEFT JOIN users u ON s.delivery_user_id = u.id 
          WHERE 1=1";
$params = [];

if (!empty($tracking_number)) {
    $query .= " AND s.tracking_number LIKE ?";
    $params[] = "%$tracking_number%";
}

if (!empty($sender_name)) {
    $query .= " AND s.sender_name LIKE ?";
    $params[] = "%$sender_name%";
}

if (!empty($recipient_name)) {
    $query .= " AND s.recipient_name LIKE ?";
    $params[] = "%$recipient_name%";
}

if (!empty($branch_id)) {
    $query .= " AND s.branch_id = ?";
    $params[] = $branch_id;
}

if (!empty($delivery_user_id)) {
    $query .= " AND s.delivery_user_id = ?";
    $params[] = $delivery_user_id;
}

if (!empty($status)) {
    $query .= " AND s.status = ?";
    $params[] = $status;
}

if (!empty($date_from)) {
    $query .= " AND DATE(s.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(s.created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY s.created_at DESC";

// Execute search if filters are applied
$shipments = [];
$search_executed = !empty($tracking_number) || !empty($sender_name) || !empty($recipient_name) || 
                  !empty($branch_id) || !empty($delivery_user_id) || !empty($status) || 
                  !empty($date_from) || !empty($date_to);

if ($search_executed) {
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $shipments = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="fas fa-search me-2"></i>Search & Filter Shipments</h2>
            <p class="text-muted">Search and filter shipments using various criteria</p>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="couriers.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to All Shipments
            </a>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo $error_message; ?>
    </div>
    <?php endif; ?>
    
    <!-- Search Form -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">Search Filters</h5>
        </div>
        <div class="card-body">
            <form action="search_couriers.php" method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="tracking_number" class="form-label">Tracking Number</label>
                    <input type="text" class="form-control" id="tracking_number" name="tracking_number" 
                           value="<?php echo htmlspecialchars($tracking_number); ?>" placeholder="Enter tracking number">
                </div>
                
                <div class="col-md-4">
                    <label for="sender_name" class="form-label">Sender Name</label>
                    <input type="text" class="form-control" id="sender_name" name="sender_name" 
                           value="<?php echo htmlspecialchars($sender_name); ?>" placeholder="Enter sender name">
                </div>
                
                <div class="col-md-4">
                    <label for="recipient_name" class="form-label">Recipient Name</label>
                    <input type="text" class="form-control" id="recipient_name" name="recipient_name" 
                           value="<?php echo htmlspecialchars($recipient_name); ?>" placeholder="Enter recipient name">
                </div>
                
                <div class="col-md-4">
                    <label for="branch_id" class="form-label">Branch</label>
                    <select class="form-select" id="branch_id" name="branch_id">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['id']; ?>" <?php echo $branch_id == $branch['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="delivery_user_id" class="form-label">Delivery User</label>
                    <select class="form-select" id="delivery_user_id" name="delivery_user_id">
                        <option value="">All Delivery Users</option>
                        <option value="null" <?php echo $delivery_user_id === 'null' ? 'selected' : ''; ?>>Not Assigned</option>
                        <?php foreach ($delivery_users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $delivery_user_id == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username'] . ' (' . $user['full_name'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="in_transit" <?php echo $status === 'in_transit' ? 'selected' : ''; ?>>In Transit</option>
                        <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="col-md-4">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <div class="col-md-4 d-flex align-items-end">
                    <div class="d-grid gap-2 d-md-flex w-100">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                        <a href="search_couriers.php" class="btn btn-secondary flex-grow-1">
                            <i class="fas fa-redo me-2"></i>Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Search Results -->
    <?php if ($search_executed): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Search Results</h5>
            <span class="badge bg-primary"><?php echo count($shipments); ?> shipments found</span>
        </div>
        <div class="card-body">
            <?php if (count($shipments) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Tracking #</th>
                            <th>Branch</th>
                            <th>Sender</th>
                            <th>Recipient</th>
                            <th>Delivery User</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shipments as $shipment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($shipment['tracking_number']); ?></td>
                            <td><?php echo htmlspecialchars($shipment['branch_name']); ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($shipment['sender_name']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($shipment['sender_phone']); ?></div>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($shipment['recipient_name']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($shipment['recipient_phone']); ?></div>
                            </td>
                            <td><?php echo $shipment['delivery_user'] ? htmlspecialchars($shipment['delivery_user']) : '<span class="text-muted">Not Assigned</span>'; ?></td>
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
                                }
                                ?>
                                <span class="badge <?php echo $status_class; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $shipment['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($shipment['created_at'])); ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="view_shipment.php?id=<?php echo $shipment['id']; ?>" class="btn btn-sm btn-outline-secondary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="update_courier.php?id=<?php echo $shipment['id']; ?>" class="btn btn-sm btn-outline-primary" title="Update Status">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-info" title="Track Shipment"
                                                data-bs-toggle="modal" data-bs-target="#trackModal<?php echo $shipment['id']; ?>">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Tracking Modal -->
                                    <div class="modal fade" id="trackModal<?php echo $shipment['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Tracking: <?php echo htmlspecialchars($shipment['tracking_number']); ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="timeline">
                                                        <!-- This would be populated with actual tracking data from the database -->
                                                        <div class="timeline-item">
                                                            <div class="timeline-point bg-success"></div>
                                                            <div class="timeline-content">
                                                                <h6 class="mb-1">Shipment Created</h6>
                                                                <p class="text-muted mb-0"><?php echo date('M d, Y H:i', strtotime($shipment['created_at'])); ?></p>
                                                                <p class="mb-0">Shipment created at <?php echo htmlspecialchars($shipment['branch_name']); ?></p>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if ($shipment['status'] != 'pending'): ?>
                                                        <div class="timeline-item">
                                                            <div class="timeline-point bg-info"></div>
                                                            <div class="timeline-content">
                                                                <h6 class="mb-1">In Transit</h6>
                                                                <p class="text-muted mb-0"><?php echo date('M d, Y H:i', strtotime($shipment['updated_at'])); ?></p>
                                                                <p class="mb-0">Shipment is in transit to destination</p>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($shipment['status'] == 'delivered'): ?>
                                                        <div class="timeline-item">
                                                            <div class="timeline-point bg-success"></div>
                                                            <div class="timeline-content">
                                                                <h6 class="mb-1">Delivered</h6>
                                                                <p class="text-muted mb-0"><?php echo date('M d, Y H:i', strtotime($shipment['updated_at'])); ?></p>
                                                                <p class="mb-0">Shipment delivered to recipient</p>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>
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
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h5>No shipments found</h5>
                <p class="text-muted">Try adjusting your search criteria</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fas fa-search fa-3x text-muted mb-3"></i>
            <h5>Enter search criteria</h5>
            <p class="text-muted">Use the filters above to search for shipments</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    /* Timeline styling */
    .timeline {
        position: relative;
        padding: 20px 0;
    }
    
    .timeline:before {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        left: 20px;
        width: 2px;
        background-color: #e9ecef;
    }
    
    .timeline-item {
        position: relative;
        padding-left: 50px;
        margin-bottom: 20px;
    }
    
    .timeline-point {
        position: absolute;
        left: 10px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
    }
    
    .timeline-content {
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 4px;
    }
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>
