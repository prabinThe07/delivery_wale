<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has branch_admin role
check_role('branch_admin');

// Include database connection
require_once '../config/db_connect.php';

// Get branch ID from session
$branch_id = $_SESSION['branch_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_shipment'])) {
    // Validate and sanitize input
    $tracking_number = 'TRK' . date('YmdHis') . rand(100, 999);
    $sender_name = trim($_POST['sender_name']);
    $sender_phone = trim($_POST['sender_phone']);
    $sender_address = trim($_POST['sender_address']);
    $recipient_name = trim($_POST['recipient_name']);
    $recipient_phone = trim($_POST['recipient_phone']);
    $recipient_address = trim($_POST['recipient_address']);
    $package_type = $_POST['package_type'];
    $weight = (float)$_POST['weight'];
    $delivery_user_id = !empty($_POST['delivery_user_id']) ? (int)$_POST['delivery_user_id'] : null;
    $status = $_POST['status'];
    $notes = trim($_POST['notes']);
    
    // Validation
    $errors = [];
    
    if (empty($sender_name)) {
        $errors[] = "Sender name is required.";
    }
    
    if (empty($sender_phone)) {
        $errors[] = "Sender phone is required.";
    }
    
    if (empty($recipient_name)) {
        $errors[] = "Recipient name is required.";
    }
    
    if (empty($recipient_phone)) {
        $errors[] = "Recipient phone is required.";
    }
    
    if (empty($recipient_address)) {
        $errors[] = "Recipient address is required.";
    }
    
    if ($weight <= 0) {
        $errors[] = "Weight must be greater than zero.";
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('INSERT INTO shipments (tracking_number, sender_name, sender_phone, sender_address, 
                                  recipient_name, recipient_phone, recipient_address, package_type, weight, 
                                  delivery_user_id, status, notes, branch_id, created_at, updated_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([$tracking_number, $sender_name, $sender_phone, $sender_address, 
                          $recipient_name, $recipient_phone, $recipient_address, $package_type, $weight, 
                          $delivery_user_id, $status, $notes, $branch_id]);
            
            // Create tracking history
            $stmt = $pdo->prepare('INSERT INTO tracking_history (shipment_id, status, notes, created_at) 
                                  VALUES (LAST_INSERT_ID(), ?, "Shipment created", NOW())');
            $stmt->execute([$status]);
            
            // Redirect to shipments page with success message
            $_SESSION['success_message'] = "Shipment created successfully with tracking number: $tracking_number";
            header('Location: couriers.php');
            exit;
            
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Get all delivery users for this branch
try {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE branch_id = ? AND role = "delivery_user" AND status = "active" ORDER BY username');
    $stmt->execute([$branch_id]);
    $delivery_users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-box me-2"></i>Create New Shipment</h2>
            <p class="text-muted">Create a new shipment for delivery.</p>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Shipment Details</h5>
        </div>
        <div class="card-body">
            <form method="post" action="">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="mb-3">Sender Information</h6>
                        <div class="mb-3">
                            <label for="sender_name" class="form-label">Sender Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="sender_name" name="sender_name" value="<?php echo isset($_POST['sender_name']) ? htmlspecialchars($_POST['sender_name']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="sender_phone" class="form-label">Sender Phone <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="sender_phone" name="sender_phone" value="<?php echo isset($_POST['sender_phone']) ? htmlspecialchars($_POST['sender_phone']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="sender_address" class="form-label">Sender Address</label>
                            <textarea class="form-control" id="sender_address" name="sender_address" rows="3"><?php echo isset($_POST['sender_address']) ? htmlspecialchars($_POST['sender_address']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="mb-3">Recipient Information</h6>
                        <div class="mb-3">
                            <label for="recipient_name" class="form-label">Recipient Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="recipient_name" name="recipient_name" value="<?php echo isset($_POST['recipient_name']) ? htmlspecialchars($_POST['recipient_name']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="recipient_phone" class="form-label">Recipient Phone <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="recipient_phone" name="recipient_phone" value="<?php echo isset($_POST['recipient_phone']) ? htmlspecialchars($_POST['recipient_phone']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="recipient_address" class="form-label">Recipient Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="recipient_address" name="recipient_address" rows="3" required><?php echo isset($_POST['recipient_address']) ? htmlspecialchars($_POST['recipient_address']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="mb-3">Package Information</h6>
                        <div class="mb-3">
                            <label for="package_type" class="form-label">Package Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="package_type" name="package_type" required>
                                <option value="document" <?php echo (isset($_POST['package_type']) && $_POST['package_type'] == 'document') ? 'selected' : ''; ?>>Document</option>
                                <option value="parcel" <?php echo (isset($_POST['package_type']) && $_POST['package_type'] == 'parcel') ? 'selected' : ''; ?>>Parcel</option>
                                <option value="box" <?php echo (isset($_POST['package_type']) && $_POST['package_type'] == 'box') ? 'selected' : ''; ?>>Box</option>
                                <option value="fragile" <?php echo (isset($_POST['package_type']) && $_POST['package_type'] == 'fragile') ? 'selected' : ''; ?>>Fragile</option>
                                <option value="other" <?php echo (isset($_POST['package_type']) && $_POST['package_type'] == 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="weight" class="form-label">Weight (kg) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="weight" name="weight" step="0.01" min="0.01" value="<?php echo isset($_POST['weight']) ? htmlspecialchars($_POST['weight']) : '1.00'; ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="mb-3">Delivery Information</h6>
                        <div class="mb-3">
                            <label for="delivery_user_id" class="form-label">Assign to Delivery User</label>
                            <select class="form-select" id="delivery_user_id" name="delivery_user_id">
                                <option value="">-- Select Delivery User --</option>
                                <?php if (isset($delivery_users) && count($delivery_users) > 0): ?>
                                    <?php foreach ($delivery_users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo (isset($_POST['delivery_user_id']) && $_POST['delivery_user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div class="form-text">If no delivery user is selected, you can assign one later.</div>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="pending" <?php echo (isset($_POST['status']) && $_POST['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_transit" <?php echo (isset($_POST['status']) && $_POST['status'] == 'in_transit') ? 'selected' : ''; ?>>In Transit</option>
                                <option value="delivered" <?php echo (isset($_POST['status']) && $_POST['status'] == 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo (isset($_POST['status']) && $_POST['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                </div>
                
                <div class="d-flex justify-content-end mt-4">
                    <a href="couriers.php" class="btn btn-secondary me-2">Cancel</a>
                    <button type="submit" name="create_shipment" class="btn btn-primary">Create Shipment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>
