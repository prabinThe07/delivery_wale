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
    header('Location: couriers.php');
    exit;
}

$shipment_id = $_GET['id'];

// Get shipment details
try {
    $stmt = $pdo->prepare('SELECT s.*, u.username as delivery_user 
                          FROM shipments s 
                          LEFT JOIN users u ON s.delivery_user_id = u.id 
                          WHERE s.id = ? AND s.branch_id = ?');
    $stmt->execute([$shipment_id, $branch_id]);
    $shipment = $stmt->fetch();
    
    if (!$shipment) {
        $error_message = "Shipment not found or does not belong to your branch.";
    }
} catch (PDOException $e) {
    $error_message = $e->getMessage();
}

// Get all delivery users for this branch
try {
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE branch_id = ? AND role = "delivery_user" AND status = "active" ORDER BY username');
    $stmt->execute([$branch_id]);
    $delivery_users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $delivery_user_id = isset($_POST['delivery_user_id']) ? $_POST['delivery_user_id'] : null;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Update shipment status
        $stmt = $pdo->prepare('UPDATE shipments SET status = ?, delivery_user_id = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$new_status, $delivery_user_id, $shipment_id]);
        
        // Add to tracking history
        $stmt = $pdo->prepare('INSERT INTO tracking_history (shipment_id, status, notes, created_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$shipment_id, $new_status, $notes]);
        
        // Commit transaction
        $pdo->commit();
        
        $success_message = "Shipment status updated successfully.";
        
        // Refresh shipment data
        $stmt = $pdo->prepare('SELECT s.*, u.username as delivery_user 
                              FROM shipments s 
                              LEFT JOIN users u ON s.delivery_user_id = u.id 
                              WHERE s.id = ? AND s.branch_id = ?');
        $stmt->execute([$shipment_id, $branch_id]);
        $shipment = $stmt->fetch();
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}

// Get tracking history
try {
    $stmt = $pdo->prepare('SELECT * FROM tracking_history WHERE shipment_id = ? ORDER BY created_at DESC');
    $stmt->execute([$shipment_id]);
    $tracking_history = $stmt->fetchAll();
} catch (PDOException $e) {
    // If table doesn't exist, create a placeholder
    $tracking_history = [];
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
                    <li class="breadcrumb-item active" aria-current="page">Update Status</li>
                </ol>
            </nav>
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-edit me-2"></i>Update Courier Status</h2>
                <a href="couriers.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Couriers
                </a>
            </div>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($success_message); ?>
    </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <?php if (isset($shipment)): ?>
    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Shipment Details</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Tracking Number:</div>
                        <div class="col-md-8"><?php echo htmlspecialchars($shipment['tracking_number']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Sender:</div>
                        <div class="col-md-8"><?php echo htmlspecialchars($shipment['sender_name']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Recipient:</div>
                        <div class="col-md-8"><?php echo htmlspecialchars($shipment['recipient_name']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Delivery Address:</div>
                        <div class="col-md-8"><?php echo htmlspecialchars($shipment['recipient_address']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Current Status:</div>
                        <div class="col-md-8">
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
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Delivery User:</div>
                        <div class="col-md-8"><?php echo $shipment['delivery_user'] ? htmlspecialchars($shipment['delivery_user']) : 'Not Assigned'; ?></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 fw-bold">Created At:</div>
                        <div class="col-md-8"><?php echo date('F d, Y h:i A', strtotime($shipment['created_at'])); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Update Status</h5>
                </div>
                <div class="card-body">
                    <form method="post" class="needs-validation" novalidate>
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <label class="form-label">Select New Status</label>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check status-card">
                                        <input class="form-check-input" type="radio" name="status" id="status_pending" value="pending" <?php echo ($shipment['status'] == 'pending') ? 'checked' : ''; ?> required>
                                        <label class="form-check-label status-card-label" for="status_pending">
                                            <div class="status-icon bg-warning">
                                                <i class="fas fa-clock"></i>
                                            </div>
                                            <div class="status-text">Pending</div>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check status-card">
                                        <input class="form-check-input" type="radio" name="status" id="status_in_transit" value="in_transit" <?php echo ($shipment['status'] == 'in_transit') ? 'checked' : ''; ?> required>
                                        <label class="form-check-label status-card-label" for="status_in_transit">
                                            <div class="status-icon bg-info">
                                                <i class="fas fa-truck"></i>
                                            </div>
                                            <div class="status-text">In Transit</div>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check status-card">
                                        <input class="form-check-input" type="radio" name="status" id="status_delivered" value="delivered" <?php echo ($shipment['status'] == 'delivered') ? 'checked' : ''; ?> required>
                                        <label class="form-check-label status-card-label" for="status_delivered">
                                            <div class="status-icon bg-success">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                            <div class="status-text">Delivered</div>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check status-card">
                                        <input class="form-check-input" type="radio" name="status" id="status_delayed" value="delayed" <?php echo ($shipment['status'] == 'delayed') ? 'checked' : ''; ?> required>
                                        <label class="form-check-label status-card-label" for="status_delayed">
                                            <div class="status-icon bg-danger">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </div>
                                            <div class="status-text">Delayed</div>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check status-card">
                                        <input class="form-check-input" type="radio" name="status" id="status_issue" value="issue" <?php echo ($shipment['status'] == 'issue') ? 'checked' : ''; ?> required>
                                        <label class="form-check-label status-card-label" for="status_issue">
                                            <div class="status-icon bg-danger">
                                                <i class="fas fa-exclamation-circle"></i>
                                            </div>
                                            <div class="status-text">Issue</div>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check status-card">
                                        <input class="form-check-input" type="radio" name="status" id="status_cancelled" value="cancelled" <?php echo ($shipment['status'] == 'cancelled') ? 'checked' : ''; ?> required>
                                        <label class="form-check-label status-card-label" for="status_cancelled">
                                            <div class="status-icon bg-danger">
                                                <i class="fas fa-ban"></i>
                                            </div>
                                            <div class="status-text">Cancelled</div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3" id="delivery_user_section">
                            <label for="delivery_user_id" class="form-label">Assign Delivery User</label>
                            <select class="form-select" id="delivery_user_id" name="delivery_user_id">
                                <option value="">Select Delivery User</option>
                                <?php if (isset($delivery_users)): ?>
                                    <?php foreach ($delivery_users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo ($shipment['delivery_user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            <div class="form-text">Add any additional information about this status update.</div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" name="update_status" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Status
                            </button>
                            <a href="couriers.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Tracking History</h5>
                </div>
                <div class="card-body p-0">
                    <div class="tracking-timeline p-3">
                        <?php if (count($tracking_history) > 0):
