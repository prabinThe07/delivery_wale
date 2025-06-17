<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has super_admin role
check_role('super_admin');

// Include database connection
require_once '../config/db_connect.php';

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'Invalid branch ID';
    header('Location: branches.php');
    exit;
}

$branch_id = $_GET['id'];

// Get branch data
try {
    $stmt = $pdo->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt->execute([$branch_id]);
    $branch = $stmt->fetch();
    
    if (!$branch) {
        $_SESSION['error_message'] = 'Branch not found';
        header('Location: branches.php');
        exit;
    }
    
    // Get branch statistics
    // Total users in this branch
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE branch_id = ?");
    $stmt->execute([$branch_id]);
    $total_users = $stmt->fetchColumn();
    
    // Total shipments from this branch
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM shipments WHERE branch_id = ?");
    $stmt->execute([$branch_id]);
    $total_shipments = $stmt->fetchColumn();
    
    // Pending shipments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM shipments WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$branch_id]);
    $pending_shipments = $stmt->fetchColumn();
    
    // In transit shipments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM shipments WHERE branch_id = ? AND status = 'in_transit'");
    $stmt->execute([$branch_id]);
    $in_transit_shipments = $stmt->fetchColumn();
    
    // Delivered shipments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM shipments WHERE branch_id = ? AND status = 'delivered'");
    $stmt->execute([$branch_id]);
    $delivered_shipments = $stmt->fetchColumn();
    
    // Recent shipments
    $stmt = $pdo->prepare("
        SELECT s.*, u.username as delivery_user 
        FROM shipments s 
        LEFT JOIN users u ON s.delivery_user_id = u.id 
        WHERE s.branch_id = ? 
        ORDER BY s.created_at DESC LIMIT 5
    ");
    $stmt->execute([$branch_id]);
    $recent_shipments = $stmt->fetchAll();
    
    // Branch staff
    $stmt = $pdo->prepare("
        SELECT id, username, full_name, role, phone, email, status, last_login 
        FROM users 
        WHERE branch_id = ? 
        ORDER BY role, full_name
    ");
    $stmt->execute([$branch_id]);
    $branch_staff = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header('Location: branches.php');
    exit;
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="fas fa-building me-2"></i><?php echo htmlspecialchars($branch['name']); ?></h2>
            <p class="text-muted">Branch ID: <?php echo $branch['id']; ?></p>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="edit_branch.php?id=<?php echo $branch['id']; ?>" class="btn btn-primary me-2">
                <i class="fas fa-edit me-2"></i>Edit Branch
            </a>
            <a href="branches.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Branches
            </a>
        </div>
    </div>
    
    <!-- Branch Details Card -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Branch Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th class="ps-0" style="width: 150px;">Branch Name:</th>
                                    <td><?php echo htmlspecialchars($branch['name']); ?></td>
                                </tr>
                                <tr>
                                    <th class="ps-0">Address:</th>
                                    <td>
                                        <?php echo htmlspecialchars($branch['address']); ?><br>
                                        <?php echo htmlspecialchars($branch['city'] . ', ' . $branch['state'] . ' ' . $branch['zip_code']); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="ps-0">Branch Manager:</th>
                                    <td><?php echo htmlspecialchars($branch['manager_name'] ?: 'Not assigned'); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th class="ps-0" style="width: 150px;">Phone:</th>
                                    <td><?php echo htmlspecialchars($branch['phone']); ?></td>
                                </tr>
                                <tr>
                                    <th class="ps-0">Email:</th>
                                    <td><?php echo htmlspecialchars($branch['email']); ?></td>
                                </tr>
                                <tr>
                                    <th class="ps-0">Status:</th>
                                    <td>
                                        <?php if ($branch['status'] == 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="ps-0">Created:</th>
                                    <td><?php echo date('M d, Y', strtotime($branch['created_at'])); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h5 class="mb-3">Branch Statistics</h5>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Staff</h6>
                            <h3 class="mb-0"><?php echo $total_users; ?></h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                            <i class="fas fa-users text-primary fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100">
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
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">In Transit</h6>
                            <h3 class="mb-0"><?php echo $in_transit_shipments; ?></h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-3 rounded">
                            <i class="fas fa-truck text-warning fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Delivered</h6>
                            <h3 class="mb-0"><?php echo $delivered_shipments; ?></h3>
                        </div>
                        <div class="bg-info bg-opacity-10 p-3 rounded">
                            <i class="fas fa-check-circle text-info fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Branch Staff -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Branch Staff</h5>
                    <a href="add_user.php?branch_id=<?php echo $branch_id; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-user-plus me-1"></i> Add Staff
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($branch_staff) > 0): ?>
                                    <?php foreach ($branch_staff as $staff): ?>
                                    <tr>
                                        <td><?php echo $staff['id']; ?></td>
                                        <td>
                                            <div class="fw-medium"><?php echo htmlspecialchars($staff['full_name']); ?></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($staff['username']); ?></div>
                                        </td>
                                        <td>
                                            <?php 
                                            $role_badge = '';
                                            switch ($staff['role']) {
                                                case 'branch_admin':
                                                    $role_badge = '<span class="badge bg-primary">Branch Admin</span>';
                                                    break;
                                                case 'delivery_user':
                                                    $role_badge = '<span class="badge bg-info">Delivery User</span>';
                                                    break;
                                                default:
                                                    $role_badge = '<span class="badge bg-secondary">'.ucfirst($staff['role']).'</span>';
                                            }
                                            echo $role_badge;
                                            ?>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($staff['phone'] ?: 'N/A'); ?></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($staff['email']); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($staff['status'] == 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $staff['last_login'] ? date('M d, Y H:i', strtotime($staff['last_login'])) : 'Never'; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="view_user.php?id=<?php echo $staff['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_user.php?id=<?php echo $staff['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="fas fa-info-circle me-2"></i>No staff assigned to this branch
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Shipments -->
    <div class="row">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Shipments</h5>
                    <a href="shipments.php?branch_id=<?php echo $branch_id; ?>" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tracking #</th>
                                    <th>Sender</th>
                                    <th>Recipient</th>
                                    <th>Delivery User</th>
                                    <th>Status</th>
                                    <th>Date</th>
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
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $shipment['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($shipment['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="fas fa-info-circle me-2"></i>No shipments found for this branch
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>
