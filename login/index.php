<?php
// Start session
session_start();

// If user is already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'super_admin':
            header('Location: ../super_admin/dashboard.php');
            break;
        case 'branch_admin':
            header('Location: ../branch_admin/dashboard.php');
            break;
        case 'delivery_user':
            header('Location: ../delivery_user/dashboard.php');
            break;
        default:
            // If role is invalid, log out
            header('Location: logout.php');
            exit;
    }
    exit;
}

// Include database connection
require_once '../config/db_connect.php';

// Get all branches for the dropdown
$branches = [];
try {
    if ($pdo) {
        $stmt = $pdo->query("SELECT id, name, city, state FROM branches WHERE status = 'active' ORDER BY name");
        $branches = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // Handle database error silently
}

// Check if there's an error message
$error_msg = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : '';
// Clear the error message
unset($_SESSION['login_error']);

// Check if there's a success message
$success_msg = isset($_SESSION['login_success']) ? $_SESSION['login_success'] : '';
// Clear the success message
unset($_SESSION['login_success']);

// Check if remember me cookie exists
$remembered_username = '';
if (isset($_COOKIE['remember_username'])) {
    $remembered_username = $_COOKIE['remember_username'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Courier Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-5">
                <div class="text-center mb-4">
                    <h2 class="text-primary"><i class="fas fa-truck-fast me-2"></i>Courier Management System</h2>
                    <p class="text-muted">Login to access your dashboard</p>
                </div>
                
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-body p-4">
                        <h4 class="text-center mb-4 text-primary">Choose Account Type</h4>
                        
                        <div class="user-type-selection mb-4">
                            <div class="row g-3">
                                <div class="col-4">
                                    <div class="user-type-option" data-type="super_admin">
                                        <div class="icon-container">
                                            <i class="fas fa-user-shield"></i>
                                        </div>
                                        <div class="label">Super Admin</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="user-type-option" data-type="branch_admin">
                                        <div class="icon-container">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <div class="label">Branch Admin</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="user-type-option" data-type="delivery_user">
                                        <div class="icon-container">
                                            <i class="fas fa-truck"></i>
                                        </div>
                                        <div class="label">Delivery User</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="selected-role-info" class="text-center mb-3 text-muted small">
                            Please select an account type to continue
                        </div>
                        
                        <?php if ($error_msg): ?>
                        <div class="alert alert-danger alert-dismissible fade show" id="login-error">
                            <?php echo htmlspecialchars($error_msg); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($success_msg): ?>
                        <div class="alert alert-success alert-dismissible fade show" id="login-success">
                            <?php echo htmlspecialchars($success_msg); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <form id="login-form" action="login_process.php" method="post" class="needs-validation" novalidate>
                            <input type="hidden" id="user-type" name="user_type" value="">
                            
                            <!-- Branch Selection (initially hidden) -->
                            <div class="mb-3 d-none" id="branch-selection-container">
                                <label for="branch_id" class="form-label">Select Branch <span class="text-danger">*</span></label>
                                <select class="form-select" id="branch_id" name="branch_id">
                                    <option value="">-- Select Branch --</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?php echo $branch['id']; ?>">
                                            <?php echo htmlspecialchars($branch['name'] . ' (' . $branch['city'] . ', ' . $branch['state'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select your branch</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($remembered_username); ?>" required>
                                    <span class="input-group-text bg-white border-start-0">
                                        <i class="fas fa-check-circle text-success d-none" id="username-check"></i>
                                    </span>
                                </div>
                                <div class="invalid-feedback">Please enter your username</div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <label for="password" class="form-label">Password</label>
                                    <a href="forgot_password.php" class="text-decoration-none small text-primary">Forgot?</a>
                                </div>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <span class="input-group-text bg-white border-start-0 password-toggle" role="button">
                                        <i class="fas fa-eye-slash"></i>
                                    </span>
                                </div>
                                <div class="invalid-feedback">Please enter your password</div>
                            </div>
                            
                            <div class="mb-3 d-flex justify-content-between">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remember-me" name="remember">
                                    <label class="form-check-label small" for="remember-me">
                                        Remember me
                                    </label>
                                </div>
                                <div class="small">
                                    New user? <a href="#" class="text-decoration-none text-primary">Signup</a>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary py-2" id="login-btn" disabled>
                                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true" id="login-spinner"></span>
                                    <span id="login-btn-text">LOGIN</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <p class="text-muted small">
                        &copy; <?php echo date('Y'); ?> Courier Management System. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/login.js"></script>
</body>
</html>
