<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has super_admin role
check_role('super_admin');

// Include database connection
require_once '../config/db_connect.php';

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Get total number of shipments
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM shipments");
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $total_records = 0;
    $total_pages = 1;
}

// Get all shipments with pagination
try {
    $query = "SELECT s.*, b.name as branch_name, u.username as delivery_user 
              FROM shipments s 
              LEFT JOIN branches b ON s.branch_id = b.id 
              LEFT JOIN users u ON s.delivery_user_id = u.id 
              ORDER BY s.created_at DESC 
              LIMIT :offset, :limit";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $shipments = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $shipments = [];
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="fas fa-box me-2"></i>All Shipments</h2>
            <p class="text-muted">View and manage all shipments in the system</p>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="search_couriers.php" class="btn btn-primary me-2">
                <i class="fas fa-search me-2"></i>Search & Filter
            </a>
            <a href="#" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#exportModal">
                <i class="fas fa-file-export me-2"></i>Export
            </a>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo $error_message; ?>
    </div>
    <?php else: ?>
    
    <div class="card border-0 shadow-sm">
        <div class="card-body">
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
                        <?php if (count($shipments) > 0): ?>
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
                                                        
                                                        <?php if ($shipment['status'] == 'cancelled'): ?>
                                                        <div class="timeline-item">
                                                            <div class="timeline-point bg-danger"></div>
                                                            <div class="timeline-content">
                                                                <h6 class="mb-1">Cancelled</h6>
                                                                <p class="text-muted mb-0"><?php echo date('M d, Y H:i', strtotime($shipment['updated_at'])); ?></p>
                                                                <p class="mb-0">Shipment has been cancelled</p>
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
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-info-circle me-2"></i>No shipments found
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo ($page <= 1) ? '#' : '?page='.($page-1); ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo ($page >= $total_pages) ? '#' : '?page='.($page+1); ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Export Shipments</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="export_shipments.php" method="post">
                    <div class="mb-3">
                        <label for="export_format" class="form-label">Export Format</label>
                        <select class="form-select" id="export_format" name="export_format" required>
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date_range" class="form-label">Date Range</label>
                        <select class="form-select" id="date_range" name="date_range">
                            <option value="all">All Time</option>
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="this_week">This Week</option>
                            <option value="last_week">Last Week</option>
                            <option value="this_month">This Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                    
                    <div id="custom_date_range" class="row g-3 mb-3" style="display: none;">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date">
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status_filter" class="form-label">Status</label>
                        <select class="form-select" id="status_filter" name="status_filter">
                            <option value="all">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="in_transit">In Transit</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary">Export</button>
            </div>
        </div>
    </div>
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

<script>
    // Show/hide custom date range based on selection
    document.getElementById('date_range').addEventListener('change', function() {
        const customDateRange = document.getElementById('custom_date_range');
        if (this.value === 'custom') {
            customDateRange.style.display = 'flex';
        } else {
            customDateRange.style.display = 'none';
        }
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
