<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has super_admin role
check_role('super_admin');

// Include database connection
require_once '../config/db_connect.php';

// Handle branch deletion confirmation
if (isset($_GET['delete_confirmed']) && isset($_GET['id'])) {
    $branch_id = $_GET['id'];
    
    try {
        // First check if there are any users or shipments associated with this branch
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE branch_id = ?");
        $stmt->execute([$branch_id]);
        $users_count = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM shipments WHERE branch_id = ?");
        $stmt->execute([$branch_id]);
        $shipments_count = $stmt->fetchColumn();
        
        if ($users_count > 0 || $shipments_count > 0) {
            $delete_error = "Cannot delete branch because it has associated users or shipments. Reassign them first.";
        } else {
            // Delete the branch
            $stmt = $pdo->prepare("DELETE FROM branches WHERE id = ?");
            $stmt->execute([$branch_id]);
            
            $delete_success = "Branch deleted successfully!";
        }
    } catch (PDOException $e) {
        $delete_error = "Database error: " . $e->getMessage();
    }
}

// Get all branches
try {
    $stmt = $pdo->query("SELECT * FROM branches ORDER BY name ASC");
    $branches = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $branches = [];
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="fas fa-building me-2"></i>Branch Management</h2>
            <p class="text-muted">Manage all branch locations in the system</p>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="add_branch.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Add New Branch
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
                            <th>ID</th>
                            <th>Branch Name</th>
                            <th>Location</th>
                            <th>Contact</th>
                            <th>Manager</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($branches) > 0): ?>
                            <?php foreach ($branches as $branch): ?>
                            <tr>
                                <td><?php echo $branch['id']; ?></td>
                                <td>
                                    <div class="fw-medium"><?php echo htmlspecialchars($branch['name']); ?></div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($branch['address']); ?></div>
                                    <div class="text-muted small">
                                        <?php echo htmlspecialchars($branch['city'] . ', ' . $branch['state'] . ' ' . $branch['zip_code']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($branch['phone']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($branch['email']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($branch['manager_name']); ?></td>
                                <td>
                                    <?php if ($branch['status'] == 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view_branch.php?id=<?php echo $branch['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_branch.php?id=<?php echo $branch['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $branch['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Delete Confirmation Modal -->
                                    <div class="modal fade" id="deleteModal<?php echo $branch['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Confirm Delete</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Are you sure you want to delete the branch: <strong><?php echo htmlspecialchars($branch['name']); ?></strong>?</p>
                                                    <p class="text-danger">This action cannot be undone. All associated data may be lost.</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <a href="branches.php?delete_confirmed=1&id=<?php echo $branch['id']; ?>" class="btn btn-danger">Delete</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-info-circle me-2"></i>No branches found
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
