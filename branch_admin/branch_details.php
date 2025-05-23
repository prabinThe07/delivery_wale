<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has branch_admin role
check_role('branch_admin');

// Include database connection
require_once '../config/db_connect.php';

// Get branch ID from session
$branch_id = $_SESSION['branch_id'];

// Get branch details
try {
    $stmt = $pdo->prepare('SELECT * FROM branches WHERE id = ?');
    $stmt->execute([$branch_id]);
    $branch = $stmt->fetch();
    
    if (!$branch) {
        $error_message = "Branch not found.";
    }
    
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
                    <li class="breadcrumb-item active" aria-current="page">Branch Details</li>
                </ol>
            </nav>
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-building me-2"></i>Branch Details</h2>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php elseif (isset($branch)): ?>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Branch Information</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Branch Name:</div>
                        <div class="col-md-8"><?php echo htmlspecialchars($branch['name']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Address:</div>
                        <div class="col-md-8"><?php echo htmlspecialchars($branch['address']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">City:</div>
                        <div class="col-md-8"><?php echo htmlspecialchars($branch['city']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">State/Province:</div>
                        <div class="col-md-8"><?php echo htmlspecialchars($branch['state']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Postal Code:</div>
                        <div class="col-md-8"><?php echo htmlspecialchars($branch['postal_code']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Country:</div>
                        <div class="col-md-8"><?php echo htmlspecialchars($branch['country']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Phone:</div>
                        <div class="col-md-8"><?php echo htmlspecialchars($branch['phone']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Email:</div>
                        <div class="col-md-8"><?php echo htmlspecialchars($branch['email']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Status:</div>
                        <div class="col-md-8">
                            <?php if ($branch['status'] == 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 fw-bold">Created At:</div>
                        <div class="col-md-8"><?php echo date('F d, Y h:i A', strtotime($branch['created_at'])); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Branch Statistics -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Branch Statistics</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get branch statistics
                    try {
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
                    } catch (PDOException $e) {
                        echo '<div class="alert alert-danger">Error fetching statistics.</div>';
                    }
                    ?>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>Total Staff:</div>
                        <div><span class="badge bg-primary"><?php echo $total_staff; ?></span></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>Total Shipments:</div>
                        <div><span class="badge bg-info"><?php echo $total_shipments; ?></span></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>Pending Shipments:</div>
                        <div><span class="badge bg-warning"><?php echo $pending_shipments; ?></span></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>Delivered Shipments:</div>
                        <div><span class="badge bg-success"><?php echo $delivered_shipments; ?></span></div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Quick Links</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="branch_users.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-users me-2"></i>Manage Branch Users
                        </a>
                        <a href="couriers.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-box me-2"></i>View All Couriers
                        </a>
                        <a href="search_couriers.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-search me-2"></i>Search & Filter Couriers
                        </a>
                        <a href="reports.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-chart-bar me-2"></i>Branch Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>
