<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has branch_admin role
check_role('branch_admin');

// Include database connection
require_once '../config/db_connect.php';

// Get branch ID from session
$branch_id = $_SESSION['branch_id'];

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get total number of shipments for this branch
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM shipments WHERE branch_id = ?');
    $stmt->execute([$branch_id]);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    $error_message = $e->getMessage();
}

// Get shipments for this branch with pagination
try {
    $stmt = $pdo->prepare('SELECT s.*, u.username as delivery_user 
                          FROM shipments s 
                          LEFT JOIN users u ON s.delivery_user_id = u.id 
                          WHERE s.branch_id = ? 
                          ORDER BY s.created_at DESC 
                          LIMIT ? OFFSET ?');
    $stmt->execute([$branch_id, $records_per_page, $offset]);
    $shipments = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = $e->getMessage();
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
                    <li class="breadcrumb-item active" aria-current="page">Couriers</li>
                </ol>
            </nav>
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-box me-2"></i>All Couriers</h2>
                <div>
                    <a href="search_couriers.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-search me-2"></i>Search & Filter
                    </a>
                    <a href="add_shipment.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add New Shipment
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
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
                        <?php if (isset($shipments) && count($shipments) > 0): ?>
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
                                <td colspan="7" class="text-center">No shipments found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mt-4">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
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
