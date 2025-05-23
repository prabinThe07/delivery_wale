<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has branch_admin role
check_role('branch_admin');

// Include database connection
require_once '../config/db_connect.php';

// Get branch ID from session
$branch_id = $_SESSION['branch_id'];

// Handle delete request
if (isset($_POST['delete_location']) && isset($_POST['location_id'])) {
    $location_id = $_POST['location_id'];
    
    try {
        // Check if location belongs to this branch
        $stmt = $pdo->prepare('SELECT * FROM locations WHERE id = ? AND branch_id = ?');
        $stmt->execute([$location_id, $branch_id]);
        $location = $stmt->fetch();
        
        if ($location) {
            // Check if location is used in any products
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE location_id = ?');
            $stmt->execute([$location_id]);
            $product_count = $stmt->fetchColumn();
            
            if ($product_count > 0) {
                $error_message = "Cannot delete location. It is associated with $product_count product(s).";
            } else {
                // Delete the location
                $stmt = $pdo->prepare('DELETE FROM locations WHERE id = ?');
                $stmt->execute([$location_id]);
                
                $success_message = "Location deleted successfully.";
            }
        } else {
            $error_message = "Location not found or you don't have permission to delete it.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Get all locations for this branch
try {
    $stmt = $pdo->prepare('SELECT l.*, 
                          (SELECT COUNT(*) FROM products WHERE location_id = l.id) as product_count 
                          FROM locations l 
                          WHERE l.branch_id = ? 
                          ORDER BY l.name');
    $stmt->execute([$branch_id]);
    $locations = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-map-marker-alt me-2"></i>Location Management</h2>
            <p class="text-muted">Manage delivery locations for your branch.</p>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Delivery Locations</h5>
                <a href="add_location.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Add New Location
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Address</th>
                            <th>Products</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($locations) && count($locations) > 0): ?>
                            <?php foreach ($locations as $location): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($location['id']); ?></td>
                                <td><?php echo htmlspecialchars($location['name']); ?></td>
                                <td><?php echo htmlspecialchars(substr($location['address'], 0, 50)) . (strlen($location['address']) > 50 ? '...' : ''); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $location['product_count']; ?></span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($location['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view_location.php?id=<?php echo $location['id']; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_location.php?id=<?php echo $location['id']; ?>" class="btn btn-outline-secondary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $location['id']; ?>" <?php echo $location['product_count'] > 0 ? 'disabled' : ''; ?>>
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteModal<?php echo $location['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Confirm Delete</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Are you sure you want to delete the location: <strong><?php echo htmlspecialchars($location['name']); ?></strong>?</p>
                                                    <p class="text-danger">This action cannot be undone.</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <form method="post">
                                                        <input type="hidden" name="location_id" value="<?php echo $location['id']; ?>">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="delete_location" class="btn btn-danger">Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No locations found</td>
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
