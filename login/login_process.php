<?php
// Start session
session_start();

// Include database connection
require_once '../config/db_connect.php';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $user_type = $_POST['user_type'] ?? '';
    $branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validate input
    if (empty($username) || empty($password) || empty($user_type)) {
        $_SESSION['login_error'] = 'All fields are required';
        header('Location: index.php');
        exit;
    }
    
    // Validate branch selection for branch_admin and delivery_user
    if (($user_type === 'branch_admin' || $user_type === 'delivery_user') && empty($branch_id)) {
        $_SESSION['login_error'] = 'Please select your branch';
        header('Location: index.php');
        exit;
    }
    
    try {
        // In a real application, we would query the database
        // For demonstration, we'll use the database if available, otherwise fall back to hardcoded users
        
        $user = null;
        
        if ($pdo) {
            // Query the database for the user
            $stmt = $pdo->prepare("
                SELECT id, username, password, role, branch_id, full_name, email 
                FROM users 
                WHERE username = ? AND role = ? AND status = 'active'
            ");
            $stmt->execute([$username, $user_type]);
            $user = $stmt->fetch();
            
            // If user found, verify branch_id for branch_admin and delivery_user
            if ($user) {
                // For branch_admin and delivery_user, verify branch_id
                if (($user_type === 'branch_admin' || $user_type === 'delivery_user') && $user['branch_id'] != $branch_id) {
                    $_SESSION['login_error'] = 'You are not authorized for the selected branch';
                    header('Location: index.php');
                    exit;
                }
                
                // Verify password (in a real app, use password_verify)
                // For demo, we'll assume the password is correct if user is found
                // In production, replace this with proper password verification
                $password_verified = true; // Simulating password verification
                
                if ($password_verified) {
                    // Update last login time
                    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['branch_id'] = $user['branch_id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    
                    // Set remember me cookie if checked
                    if ($remember) {
                        setcookie('remember_username', $username, time() + (86400 * 30), '/');
                    }
                    
                    // Redirect to appropriate dashboard
                    redirectToDashboard($user['role']);
                } else {
                    $_SESSION['login_error'] = 'Invalid password';
                    header('Location: index.php');
                    exit;
                }
            } else {
                // Fall back to hardcoded users for demo
                useHardcodedUsers($username, $password, $user_type, $branch_id, $remember);
            }
        } else {
            // Database not available, use hardcoded users
            useHardcodedUsers($username, $password, $user_type, $branch_id, $remember);
        }
    } catch (Exception $e) {
        // General error
        $_SESSION['login_error'] = 'An error occurred. Please try again.';
        header('Location: index.php');
        exit;
    }
} else {
    // If not POST request, redirect to login page
    header('Location: index.php');
    exit;
}

// Function to use hardcoded users for demo
function useHardcodedUsers($username, $password, $user_type, $branch_id, $remember) {
    // Hardcoded users for demonstration
    $demo_users = [
        ['id' => 1, 'username' => 'superadmin', 'password' => 'admin123', 'role' => 'super_admin', 'branch_id' => null],
        ['id' => 2, 'username' => 'branchadmin1', 'password' => 'branch123', 'role' => 'branch_admin', 'branch_id' => 1],
        ['id' => 3, 'username' => 'branchadmin2', 'password' => 'branch123', 'role' => 'branch_admin', 'branch_id' => 2],
        ['id' => 4, 'username' => 'delivery1', 'password' => 'delivery123', 'role' => 'delivery_user', 'branch_id' => 1],
        ['id' => 5, 'username' => 'delivery2', 'password' => 'delivery123', 'role' => 'delivery_user', 'branch_id' => 2]
    ];
    
    $user = null;
    foreach ($demo_users as $demo_user) {
        if ($demo_user['username'] === $username && $demo_user['role'] === $user_type) {
            // For branch_admin and delivery_user, verify branch_id
            if (($user_type === 'branch_admin' || $user_type === 'delivery_user') && $demo_user['branch_id'] != $branch_id) {
                continue; // Skip this user if branch doesn't match
            }
            $user = $demo_user;
            break;
        }
    }
    
    // Verify user exists and password is correct
    if ($user && $password === $user['password']) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['branch_id'] = $user['branch_id'];
        
        // Set remember me cookie if checked
        if ($remember) {
            setcookie('remember_username', $username, time() + (86400 * 30), '/');
        }
        
        // Redirect to appropriate dashboard
        redirectToDashboard($user['role']);
    } else {
        // Invalid credentials or branch mismatch
        if ($user_type === 'branch_admin' || $user_type === 'delivery_user') {
            $_SESSION['login_error'] = 'Invalid credentials or you are not authorized for the selected branch';
        } else {
            $_SESSION['login_error'] = 'Invalid username or password';
        }
        header('Location: index.php');
        exit;
    }
}

// Function to redirect to appropriate dashboard
function redirectToDashboard($role) {
    switch ($role) {
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
            $_SESSION['login_success'] = 'Login successful! Redirecting...';
            header('Location: index.php');
    }
    exit;
}
?>
