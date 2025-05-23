<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has branch_admin role
check_role('branch_admin');

// Include database connection
require_once '../config/db_connect.php';

// Get branch ID from session
$branch_id = $_SESSION['branch_id'];

// Initialize variables
$shipments = [];
$search_performed = false;

// Get all delivery users for this branch for the dropdown
try {
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE branch_id = ? AND role = "delivery_user" ORDER BY username');
    $stmt->execute([$branch_id]);
    $delivery_users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = $e->getMessage();
}

// Process search form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $search_performed = true;
    
    // Build the query
    $query = 'SELECT s.*, u.username as delivery_user 
              FROM shipments s 
              LEFT JOIN users u ON s.delivery_user_id = u.id 
              WHERE s.branch_id = ?';
    $params = [$branch_id];
    
    // Add search conditions
    if (!empty($_POST['tracking_number'])) {
        $query .= ' AND s.tracking_number LIKE ?';
        $params[] = '%' . $_POST['tracking_number'] . '%';
    }
    
    if (!empty($_POST['sender_name'])) {
        $query .= ' AND s.sender_name LIKE ?';
        $params[] = '%' . $_POST['sender_name'] . '%';
    }
    
    if (!empty($_POST['recipient_name'])) {
        $query .= ' AND s.recipient_name LIKE ?';
        $params[] = '%' . $_POST['recipient_name'] . '%';
    }
    
    if (!empty($_POST['delivery_user_id'])) {
        $query .= ' AND s.delivery_user_id = ?';
        $params[] = $_POST['delivery_user_id'];
    }
    
    if (!empty($_POST['status'])) {
        $query .= ' AND s.status = ?';
        $params[] = $_POST['status'];
    }
    
    if (!empty($_POST['date_from']) && !empty($_POST['date_to'])) {
        $query .= ' AND DATE(s.created_at) BETWEEN ? AND ?';
        $params[] = $_POST['date_from'];
        $params[] = $_POST['date_to'];
    } elseif (!empty($_POST['date_from'])) {
        $query .= ' AND DATE(s.created_at) >= ?';
        $params[] = $_POST['date_from'];
    } elseif (!empty($_POST['date_to'])) {
        $query .= ' AND DATE(s.created_at) <= ?';
        $params[] = $_POST['date_to'];
    }
    
    // Order by
    $query .= ' ORDER BY s.created_at DESC';
    
    // Execute search query
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $shipments = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error_message = $e->getMessage();
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="couriers.php">Couriers</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Search & Filter</li>
                </ol>
            </nav>
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-search me-2"></i>Search & Filter Couriers</h2>
                <a href="couriers.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to All Couriers
                </a>
            </div>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="post" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="tracking_number" class="form-label">Tracking Number</label>
                        <input type="text" class="form-control" id="tracking_number" name="tracking_number" value="<?php echo isset($_POST['tracking_number']) ? htmlspecialchars($_POST['tracking_number']) : ''; ?>">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="sender_name" class="form-label">Sender Name</label>
                        <input type="text" class="form-control" id="sender_name" name="sender_name" value="<?php echo isset($_POST['sender_name']) ? htmlspecialchars($_POST['sender_name']) : ''; ?>">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="recipient_name" class="form-label">Recipient Name</label>
                        <input type="text" class="form-control" id="recipient_name" name="recipient_name" value="<?php echo isset($_POST['recipient_name']) ? htmlspecialchars($_POST['recipient_name']) : ''; ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="delivery_user_id" class="form-label">Delivery User</label>
                        <select class="form-select" id="delivery_user_id" name="delivery_user_id">
                            <option value="">All Delivery Users</option>
                            <?php if (isset($delivery_users)): ?>
                                <?php foreach ($delivery_users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo (isset($_POST['delivery_user_id']) && $_POST['delivery_user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo (isset($_POST['status']) && $_POST['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_transit" <?php echo (isset($_POST['status']) && $_POST['status'] == 'in_transit') ? 'selected' : ''; ?>>In Transit</option>
                            <option value="delivered" <?php echo (isset($_POST['status']) && $_POST['status'] == 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                            <option value="cancelled" <?php echo (isset($_POST['status']) && $_POST['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="delayed" <?php echo (isset($_POST['status']) && $_POST['status'] == 'delayed') ? 'selected' : ''; ?>>Delayed</option>
                            <option value="issue" <?php echo (isset($_POST['status']) && $_POST['status'] == 'issue') ? 'selected' : ''; ?>>Issue</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo isset($_POST['date_from']) ? htmlspecialchars($_POST['date_from']) : ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo isset($_POST['date_to']) ? htmlspecialchars($_POST['date_to']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Search
                    </button>
                    <a href="search_couriers.php" class="btn btn-secondary ms-2">
                        <i class="fas fa-redo me-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($search_performed): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Search Results</h5>
                <span class="badge bg-primary"><?php echo count($shipments); ?> results found</span>
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
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($shipments) > 0): ?>
                            <?php foreach ($shipments as $shipment): ?>
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
                                        <a href="update_courier.php?id=<?php echo $shipment['id']; ?>" class="btn btn-outline-primary" title="Update Status">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="view_shipment.php?id=<?php echo $shipment['id']; ?>" class="btn btn-outline-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#trackingModal<?php echo $shipment['id']; ?>" title="Track Shipment">
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
                                <td colspan="7" class="text-center">No shipments found matching your search criteria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.tracking-timeline {
    position: relative;
    padding: 20px 0;
}

.tracking-item {
    position: relative;
    padding-left: 45px;
    margin-bottom: 20px;
}

.tracking-icon {
    position: absolute;
    left: 0;
    top: 0;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.tracking-content {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
}

.tracking-content h6 {
    margin-bottom: 5px;
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>
