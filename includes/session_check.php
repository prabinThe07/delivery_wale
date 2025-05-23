<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Redirect to login page
    header('Location: ../login/index.php');
    exit;
}

// Function to check if user has required role
function check_role($required_roles) {
    // If $required_roles is a string, convert to array
    if (!is_array($required_roles)) {
        $required_roles = [$required_roles];
    }
    
    // Check if user's role is in the required roles array
    if (!in_array($_SESSION['role'], $required_roles)) {
        // Redirect to appropriate dashboard based on role
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
                header('Location: ../login/logout.php');
        }
        exit;
    }
    
    return true;
}
?>
