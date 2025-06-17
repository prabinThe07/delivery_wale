<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has super_admin role
check_role('super_admin');

// Include database connection
require_once '../config/db_connect.php';

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'Invalid shipment ID';
    header('Location: couriers.php');
    exit;
}

$shipment_id = $_GET['id'];

// Get shipment data
try {
    $stmt = $pdo->prepare("
        SELECT s.*, b.name as branch_name, u.username as delivery_user_name 
        FROM shipments s 
        LEFT JOIN branches b ON s.branch_id = b.id 
        LEFT JOIN users u ON s.delivery_user_id = u.id 
        WHERE s.id = ?
    ");
    $stmt->execute([$shipment_id]);
    $shipment = $stmt->fetch();
    
    if (!$shipment) {
        $_SESSION['error_message'] = 'Shipment not found';
        header('Location: couriers.php');
        exit;
    }
    
    // Get all delivery users for dropdown
    $stmt = $pdo->prepare("
        SELECT id, username, full_name 
        FROM users 
        WHERE role = 'delivery_user' AND branch_id = ? AND status = 'active'
        ORDER BY username
    ");
    $stmt->execute([$shipment['branch_id']]);
    $delivery_users = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header('Location: couriers.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $status = $_POST['status'] ?? '';
    $delivery_user_id = !empty($_POST['delivery_user_id']) ? (int)$_POST['delivery_user_id'] : null;
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate input
    $errors = [];
    
    if (empty($status)) {
        $errors['status'] = 'Status is required';
    }
    
    if ($status === 'in_transit' && empty($delivery_user_id)) {
        $errors['delivery_user_id'] = 'Delivery user is required for in-transit shipments';
    }
    
    // If no errors, update shipment
    if (empty($errors)) {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update shipment status
            $stmt = $pdo->prepare("
                UPDATE shipments 
                SET status = ?, delivery_user_id = ?, notes = ? 
                WHERE id = ?
            ");
            $stmt->execute([$status, $delivery_user_id, $notes, $shipment_id]);
            
            // Add tracking entry
            $stmt = $pdo->prepare("
                INSERT INTO shipment_tracking (shipment_id, status, remarks, created_by) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$shipment_id, $status, $notes, $_SESSION['user_id']]);
            
            // Commit transaction
            $pdo->commit();
            
            // Set success message and redirect
            $_SESSION['success_message'] = 'Shipment status updated successfully!';
            header('Location: couriers.php');
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="fas fa-edit me-2"></i>Update Shipment Status</h2>
            <p class="text-muted">Update status for tracking #: <strong><?php echo htmlspecialchars($shipment['tracking_number']); ?></strong></p>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="couriers.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Shipments
            </a>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Shipment Details</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th style="width: 150px;">Tracking #:</th>
                            <td><?php echo htmlspecialchars($shipment['tracking_number']); ?></td>
                        </tr>
                        <tr>
                            <th>Branch:</th>
                            <td><?php echo htmlspecialchars($shipment['branch_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Current Status:</th>
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
                        </tr>
                        <tr>
                            <th>Delivery User:</th>
                            <td><?php echo $shipment['delivery_user_name'] ? htmlspecialchars($shipment['delivery_user_name']) : '<span class="text-muted">Not Assigned</span>'; ?></td>
                        </tr>
                        <tr>
                            <th>Created:</th>
                            <td><?php echo date('M d, Y H:i', strtotime($shipment['created_at'])); ?></td>
                        </tr>
                        <tr>
                            <th>Last Updated:</th>
                            <td><?php echo date('M d, Y H:i', strtotime($shipment['updated_at'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Sender & Recipient</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h6 class="fw-bold">Sender Information</h6>
                        <p class="mb-1"><?php echo htmlspecialchars($shipment['sender_name']); ?></p>
                        <p class="mb-1"><?php echo htmlspecialchars($shipment['sender_address']); ?></p>
                        <p class="mb-1">Phone: <?php echo htmlspecialchars($shipment['sender_phone']); ?></p>
                        <?php if (!empty($shipment['sender_email'])): ?>
                        <p class="mb-0">Email: <?php echo htmlspecialchars($shipment['sender_email']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <h6 class="fw-bold">Recipient Information</h6>
                        <p class="mb-1"><?php echo htmlspecialchars($shipment['recipient_name']); ?></p>
                        <p class="mb-1"><?php echo htmlspecialchars($shipment['recipient_address']); ?></p>
                        <p class="mb-1">Phone: <?php echo htmlspecialchars($shipment['recipient_phone']); ?></p>
                        <?php if (!empty($shipment['recipient_email'])): ?>
                        <p class="mb-0">Email: <?php echo htmlspecialchars($shipment['recipient_email']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Update Status</h5>
                </div>
                <div class="card-body">
                    <form action="update_courier.php?id=<?php echo $shipment_id; ?>" method="post" class="needs-validation" novalidate>
                        <div class="mb-4">
                            <label class="form-label">Select New Status</label>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="form-check status-card">
                                        <input class="form-check-input" type="radio" name="status" id="status_pending" 
                                               value="pending" <?php echo (!isset($_POST['status']) && $shipment['status'] === 'pending') || (isset($_POST['status']) && $_POST['status'] === 'pending') ? 'checked' : ''; ?>>
                                        <label class="form-check-label status-card-label" for="status_pending">
                                            <div class="status-icon bg-warning">
                                                <i class="fas fa-clock"></i>
                                            </div>
                                            <span>Pending</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="form-check status-card">
                                        <input class="form-check-input" type="radio" name="status" id="status_in_transit" 
                                               value="in_transit" <?php echo (!isset($_POST['status']) && $shipment['status'] === 'in_transit') || (isset($_POST['status']) && $_POST['status'] === 'in_transit') ? 'checked' : ''; ?>>
                                        <label class="form-check-label status-card-label" for="status_in_transit">
                                            <div class="status-icon bg-info">
                                                <i class="fas fa-truck"></i>
                                            </div>
                                            <span>In Transit</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="form-check status-card">
                                        <input class="form-check-input" type="radio" name="status" id="status_delivered" 
                                               value="delivered" <?php echo (!isset($_POST['status']) && $shipment['status'] === 'delivered') || (isset($_POST['status']) && $_POST['status'] === 'delivered') ? 'checked' : ''; ?>>
                                        <label class="form-check-label status-card-label" for="status_delivered">
                                            <div class="status-icon bg-success">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                            <span>Delivered</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="form-check status-card">
                                        <input class="form-check-input" type="radio" name="status" id="status_cancelled" 
                                               value="cancelled" <?php echo (!isset($_POST['status']) && $shipment['status'] === 'cancelled') || (isset($_POST['status']) && $_POST['status'] === 'cancelled') ? 'checked' : ''; ?>>
                                        <label class="form-check-label status-card-label" for="status_cancelled">
                                            <div class="status-icon bg-danger">
                                                <i class="fas fa-times-circle"></i>
                                            </div>
                                            <span>Cancelled</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <?php if (isset($errors['status'])): ?>
                                <div class="text-danger mt-2"><?php echo $errors['status']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-4" id="delivery_user_section">
                            <label for="delivery_user_id" class="form-label">Assign Delivery User</label>
                            <select class="form-select <?php echo isset($errors['delivery_user_id']) ? 'is-invalid' : ''; ?>" 
                                    id="delivery_user_id" name="delivery_user_id">
                                <option value="">-- Select Delivery User --</option>
                                <?php foreach ($delivery_users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo (isset($_POST['delivery_user_id']) && $_POST['delivery_user_id'] == $user['id']) || (!isset($_POST['delivery_user_id']) && $shipment['delivery_user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['username'] . ' (' . $user['full_name'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['delivery_user_id'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['delivery_user_id']; ?></div>
                            <?php endif; ?>
                            <div class="form-text">Required for "In Transit" status. Delivery users from the same branch as the shipment.</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : htmlspecialchars($shipment['notes'] ?? ''); ?></textarea>
                            <div class="form-text">Add any additional information about this status update.</div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This update will be recorded in the shipment's tracking history.
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Status
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Tracking History -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Tracking History</h5>
                </div>
                <div class="card-body">
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
                                <h6 class="mb-1">Status Updated</h6>
                                <p class="text-muted mb-0"><?php echo date('M d, Y H:i', strtotime($shipment['updated_at'])); ?></p>
                                <p class="mb-0">Status changed to <?php echo ucfirst(str_replace('_', ' ', $shipment['status'])); ?></p>
                                <?php if (!empty($shipment['notes'])): ?>
                                <p class="mb-0 fst-italic">"<?php echo htmlspecialchars($shipment['notes']); ?>"</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Status cards styling */
    .status-card {
        margin: 0;
        padding: 0;
    }
    
    .status-card-label {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 1.5rem;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.2s;
        width: 100%;
    }
    
    .status-card-label:hover {
        border-color: #adb5bd;
        transform: translateY(-2px);
    }
    
    .status-card input:checked + .status-card-label {
        border-color: #4dabf7;
        box-shadow: 0 0 0 2px rgba(77, 171, 247, 0.25);
    }
    
    .status-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 0.75rem;
    }
    
    .status-icon i {
        font-size: 1.5rem;
        color: white;
    }
    
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
    // Show/hide delivery user section based on status selection
    document.addEventListener('DOMContentLoaded', function() {
        const statusRadios = document.querySelectorAll('input[name="status"]');
        const deliveryUserSection = document.getElementById('delivery_user_section');
        
        function toggleDeliveryUserSection() {
            const selectedStatus = document.querySelector('input[name="status"]:checked').value;
            if (selectedStatus === 'in_transit') {
                deliveryUserSection.style.display = 'block';
                document.getElementById('delivery_user_id').setAttribute('required', 'required');
            } else {
                deliveryUserSection.style.display = 'none';
                document.getElementById('delivery_user_id').removeAttribute('required');
            }
        }
        
        // Initial check
        toggleDeliveryUserSection();
        
        // Add event listeners
        statusRadios.forEach(radio => {
            radio.addEventListener('change', toggleDeliveryUserSection);
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
