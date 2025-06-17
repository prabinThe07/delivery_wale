<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has super_admin role
check_role('super_admin');

// Include database connection
require_once '../config/db_connect.php';

// Handle user deletion confirmation
if (isset($_GET['delete_confirmed']) && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    
    try {
        // Check if user exists and is not the current user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $delete_error = "User not found.";
        } elseif ($user['id'] == $_SESSION['user_id']) {
            $delete_error = "You cannot delete your own account.";
        } else {
            // Delete the user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $delete_success = "User deleted successfully!";
        }
    } catch (PDOException $e) {
        $delete_error = "Database error: " . $e->getMessage();
    }
}

// Get filter parameters
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$branch_filter = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get all branches for filter dropdown
try {
    $stmt = $pdo->query("SELECT id, name, city, state FROM branches ORDER BY name");
    $branches = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $branches = [];
}

// Build the query with filters
$query = "SELECT u.*, b.name as branch_name 
          FROM users u 
          LEFT JOIN branches b ON u.branch_id = b.id 
          WHERE 1=1";
$params = [];

if (!empty($role_filter)) {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
}

if (!empty($branch_filter)) {
    $query .= " AND u.branch_id = ?";
    $params[] = $branch_filter;
}

if (!empty($status_filter)) {
    $query .= " AND u.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY u.id DESC";

// Get users with filters
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $users = [];
}

// Check for success message
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
unset($_SESSION['success_message']);

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="fas fa-users-cog me-2"></i>Manage Users</h2>
            <p class="text-muted">Manage all user accounts in the system</p>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="add_user.php" class="btn btn-primary">
                <i class="fas fa-user-plus me-2"></i>Add New User
            </a>
        </div>
    </div>
    
    <?php if (isset($delete_success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $delete_success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($delete_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $delete_error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo $error_message; ?>
    </div>
    <?php else: ?>
    
    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">Filter Users</h5>
        </div>
        <div class="card-body">
            <form action="manage_users.php" method="get" class="row g-3">
                <div class="col-md-3">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" id="role" name="role">
                        <option value="">All Roles</option>
                        <option value="super_admin" <?php echo $role_filter === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                        <option value="branch_admin" <?php echo $role_filter === 'branch_admin' ? 'selected' : ''; ?>>Branch Admin</option>
                        <option value="delivery_user" <?php echo $role_filter === 'delivery_user' ? 'selected' : ''; ?>>Delivery User</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="branch_id" class="form-label">Branch</label>
                    <select class="form-select" id="branch_id" name="branch_id">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['id']; ?>" <?php echo $branch_filter == $branch['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Username, name or email" value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-12 text-end">
                    <a href="manage_users.php" class="btn btn-secondary me-2">Reset</a>
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Branch</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
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
                                <td><?php echo $user['branch_name'] ? htmlspecialchars($user['branch_name']) : 'N/A'; ?></td>
                                <td>
                                    <div><?php echo htmlspecialchars($user['email']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($user['phone'] ?: 'No phone'); ?></div>
                                </td>
                                <td>
                                    <?php if ($user['status'] == 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="reset_password.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-warning" title="Reset Password">
                                            <i class="fas fa-key"></i>
                                        </a>
                                        <a href="user_status.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-info" title="Change Status">
                                            <i class="fas fa-toggle-on"></i>
                                        </a>
                                        <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete User"
                                                data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $user['id']; ?>"
                                                <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Delete Confirmation Modal -->
                                    <div class="modal fade" id="deleteModal<?php echo $user['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Confirm Delete</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Are you sure you want to delete the user: <strong><?php echo htmlspecialchars($user['username']); ?></strong>?</p>
                                                    <p class="text-danger">This action cannot be undone. All associated data may be lost.</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <a href="manage_users.php?delete_confirmed=1&id=<?php echo $user['id']; ?>" class="btn btn-danger">Delete</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-info-circle me-2"></i>No users found
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>
