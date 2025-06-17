<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has super_admin role
check_role('super_admin');

// Include database connection
require_once '../config/db_connect.php';

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'Invalid user ID';
    header('Location: manage_users.php');
    exit;
}

$user_id = $_GET['id'];

// Get user data
try {
    $stmt = $pdo->prepare("SELECT id, username, full_name, email, role, status FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error_message'] = 'User not found';
        header('Location: manage_users.php');
        exit;
    }
    
    // Prevent changing own status
    if ($user['id'] == $_SESSION['user_id']) {
        $_SESSION['error_message'] = 'You cannot change your own status';
        header('Location: manage_users.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header('Location: manage_users.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get new status
    $new_status = $_POST['status'] ?? '';
    
    // Validate status
    if ($new_status !== 'active' && $new_status !== 'inactive') {
        $error_message = 'Invalid status value';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $user_id]);
            
            // Set success message and redirect
            $_SESSION['success_message'] = 'User status updated successfully!';
            header('Location: manage_users.php');
            exit;
            
        } catch (PDOException $e) {
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
            <h2><i class="fas fa-user-check me-2"></i>Change User Status</h2>
            <p class="text-muted">Update status for user: <strong><?php echo htmlspecialchars($user['username']); ?></strong></p>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="manage_users.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Users
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
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">User Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th style="width: 150px;">Username:</th>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                        </tr>
                        <tr>
                            <th>Full Name:</th>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                        </tr>
                        <tr>
                            <th>Role:</th>
                            <td>
                                <?php 
                                $role_badge = '';
                                switch ($user['role']) {
                                    case 'super_admin':
                                        $role_badge = '<span class="badge bg-danger">Super Admin</span>';
                                        break;
                                    case 'branch_admin':
                                        $role_badge = '<span class="badge bg-primary">Branch Admin</span>';
                                        break;
                                    case 'delivery_user':
                                        $role_badge = '<span class="badge bg-info">Delivery User</span>';
                                        break;
                                    default:
                                        $role_badge = '<span class="badge bg-secondary">'.ucfirst($user['role']).'</span>';
                                }
                                echo $role_badge;
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Current Status:</th>
                            <td>
                                <?php if ($user['status'] == 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Change Status</h5>
                </div>
                <div class="card-body">
                    <form action="user_status.php?id=<?php echo $user_id; ?>" method="post">
                        <div class="mb-4">
                            <label class="form-label">Select New Status</label>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="status" id="status_active" 
                                           value="active" <?php echo $user['status'] === 'active' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="status_active">
                                        <span class="badge bg-success me-2">Active</span>
                                        User can log in and access the system
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="status" id="status_inactive" 
                                           value="inactive" <?php echo $user['status'] === 'inactive' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="status_inactive">
                                        <span class="badge bg-danger me-2">Inactive</span>
                                        User cannot log in or access the system
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php if ($user['status'] === 'active'): ?>
                                Setting a user to inactive will prevent them from logging into the system. Any active sessions will be terminated.
                            <?php else: ?>
                                Setting a user to active will allow them to log into the system again.
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Status
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>
