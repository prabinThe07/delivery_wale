<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has branch_admin role
check_role('branch_admin');

// Include database connection
require_once '../config/db_connect.php';

// Get branch ID from session
$branch_id = $_SESSION['branch_id'];

// Process status change if submitted
if (isset($_POST['update_status']) && isset($_POST['user_id']) && isset($_POST['status'])) {
    $user_id = $_POST['user_id'];
    $status = $_POST['status'];
    
    try {
        // Verify user belongs to this branch AND is a delivery user (not another branch admin)
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND branch_id = ? AND role = "delivery_user"');
        $stmt->execute([$user_id, $branch_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Update user status
            $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
            $stmt->execute([$status, $user_id]);
            
            $success_message = "User status updated successfully.";
        } else {
            $error_message = "User not found, does not belong to your branch, or you don't have permission to update this user.";
        }
    } catch (PDOException $e) {
        $error_message = $e->getMessage();
    }
}

// Get all users for this branch
try {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE branch_id = ? ORDER BY role, username');
    $stmt->execute([$branch_id]);
    $users = $stmt->fetchAll();
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
                    <li class="breadcrumb-item active" aria-current="page">Branch Users</li>
                </ol>
            </nav>
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-users me-2"></i>Branch Users</h2>
                <a href="add_user.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add New User
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

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($users) && count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge <?php echo ($user['role'] == 'branch_admin') ? 'bg-primary' : 'bg-info'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo ($user['status'] == 'active') ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($user['role'] == 'delivery_user'): ?>
                                        <!-- Only show edit button for delivery users -->
                                        <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#viewUserModal<?php echo $user['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($user['role'] == 'delivery_user'): ?>
                                        <!-- Only show status toggle for delivery users -->
                                        <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo $user['id']; ?>">
                                            <i class="fas fa-toggle-on"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resetPasswordModal<?php echo $user['id']; ?>">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- View User Modal -->
                                    <div class="modal fade" id="viewUserModal<?php echo $user['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">User Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-4 fw-bold">Username:</div>
                                                        <div class="col-md-8"><?php echo htmlspecialchars($user['username']); ?></div>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-md-4 fw-bold">Full Name:</div>
                                                        <div class="col-md-8"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-md-4 fw-bold">Email:</div>
                                                        <div class="col-md-8"><?php echo htmlspecialchars($user['email']); ?></div>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-md-4 fw-bold">Phone:</div>
                                                        <div class="col-md-8"><?php echo htmlspecialchars($user['phone']); ?></div>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-md-4 fw-bold">Role:</div>
                                                        <div class="col-md-8"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></div>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-md-4 fw-bold">Status:</div>
                                                        <div class="col-md-8">
                                                            <span class="badge <?php echo ($user['status'] == 'active') ? 'bg-success' : 'bg-danger'; ?>">
                                                                <?php echo ucfirst($user['status']); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-md-4 fw-bold">Created At:</div>
                                                        <div class="col-md-8"><?php echo date('F d, Y h:i A', strtotime($user['created_at'])); ?></div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($user['role'] == 'delivery_user'): ?>
                                    <!-- Status Change Modal - Only for delivery users -->
                                    <div class="modal fade" id="statusModal<?php echo $user['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Change User Status</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="post">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <p>Current status: <strong><?php echo ucfirst($user['status']); ?></strong></p>
                                                        <div class="form-check mb-3">
                                                            <input class="form-check-input" type="radio" name="status" id="statusActive<?php echo $user['id']; ?>" value="active" <?php echo ($user['status'] == 'active') ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="statusActive<?php echo $user['id']; ?>">
                                                                Active
                                                            </label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="status" id="statusInactive<?php echo $user['id']; ?>" value="inactive" <?php echo ($user['status'] == 'inactive') ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="statusInactive<?php echo $user['id']; ?>">
                                                                Inactive
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Reset Password Modal - Only for delivery users -->
                                    <div class="modal fade" id="resetPasswordModal<?php echo $user['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Reset Password</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="reset_password.php" method="post">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <p>Are you sure you want to reset the password for <strong><?php echo htmlspecialchars($user['username']); ?></strong>?</p>
                                                        <p>A new temporary password will be generated and sent to the user's email.</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="reset_password" class="btn btn-warning">Reset Password</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No users found for this branch.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>
