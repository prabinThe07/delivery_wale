<?php
session_start();
// Database connection would be here in production
// include 'config/db.php';

// For demonstration purposes
$users = [
    ['id' => 1, 'username' => 'superadmin', 'password' => password_hash('admin123', PASSWORD_DEFAULT), 'role' => 'super_admin'],
    ['id' => 2, 'username' => 'branchadmin', 'password' => password_hash('branch123', PASSWORD_DEFAULT), 'role' => 'branch_admin'],
    ['id' => 3, 'username' => 'delivery', 'password' => password_hash('delivery123', PASSWORD_DEFAULT), 'role' => 'delivery_user']
];

// Handle AJAX login request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $userType = $_POST['user_type'];
    
    $response = ['success' => false, 'message' => ''];
    
    // Validate input
    if (empty($username) || empty($password) || empty($userType)) {
        $response['message'] = 'All fields are required';
        echo json_encode($response);
        exit;
    }
    
    // In production, you would query the database
    $user = null;
    foreach ($users as $u) {
        if ($u['username'] === $username && $u['role'] === $userType) {
            $user = $u;
            break;
        }
    }
    
    if ($user && password_verify($password, $user['password'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        $response['success'] = true;
        $response['message'] = 'Login successful';
        $response['redirect'] = 'dashboard.php';
    } else {
        $response['message'] = 'Invalid username or password';
    }
    
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courier Management System - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-5">
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
                        
                        <form id="login-form" class="needs-validation" novalidate>
                            <input type="hidden" id="user-type" name="user_type" value="">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="username" name="username" required>
                                    <span class="input-group-text bg-white border-start-0">
                                        <i class="fas fa-check-circle text-success d-none" id="username-check"></i>
                                    </span>
                                </div>
                                <div class="invalid-feedback">Please enter your username</div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <label for="password" class="form-label">Password</label>
                                    <a href="#" class="text-decoration-none small text-primary">Forgot?</a>
                                </div>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="invalid-feedback">Please enter your password</div>
                            </div>
                            
                            <div class="mb-3 d-flex justify-content-between">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remember-me">
                                    <label class="form-check-label small" for="remember-me">
                                        Remember me
                                    </label>
                                </div>
                                <div class="small">
                                    New user? <a href="#" class="text-decoration-none text-primary">Signup</a>
                                </div>
                            </div>
                            
                            <div class="alert alert-danger d-none" id="login-error"></div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary py-2" id="login-btn" disabled>LOGIN</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/login.js"></script>
</body>
</html>
