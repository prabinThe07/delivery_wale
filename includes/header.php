<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Get user role if logged in
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';

// Define role-specific dashboard URLs
$dashboard_url = '';
switch ($user_role) {
    case 'super_admin':
        $dashboard_url = '../super_admin/dashboard.php';
        break;
    case 'branch_admin':
        $dashboard_url = '../branch_admin/dashboard.php';
        break;
    case 'delivery_user':
        $dashboard_url = '../delivery_user/dashboard.php';
        break;
    default:
        $dashboard_url = '../login/index.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courier Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php if ($user_role): ?>
    <!-- Navigation for logged-in users -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo $dashboard_url; ?>">
                <i class="fas fa-truck-fast me-2"></i>Courier Management System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" 
                           href="<?php echo $dashboard_url; ?>">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>
                    
                    <?php if ($user_role == 'super_admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'branches.php') ? 'active' : ''; ?>" 
                           href="../super_admin/branches.php">
                            <i class="fas fa-building me-1"></i> Branches
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>" 
                           href="../super_admin/users.php">
                            <i class="fas fa-users me-1"></i> Users
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($user_role == 'branch_admin' || $user_role == 'super_admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'shipments.php') ? 'active' : ''; ?>" 
                           href="<?php echo ($user_role == 'super_admin') ? '../super_admin/shipments.php' : '../branch_admin/shipments.php'; ?>">
                            <i class="fas fa-box me-1"></i> Shipments
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($user_role == 'delivery_user'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'deliveries.php') ? 'active' : ''; ?>" 
                           href="../delivery_user/deliveries.php">
                            <i class="fas fa-truck me-1"></i> My Deliveries
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" 
                           href="<?php echo str_replace('dashboard.php', 'reports.php', $dashboard_url); ?>">
                            <i class="fas fa-chart-bar me-1"></i> Reports
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($username); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo str_replace('dashboard.php', 'profile.php', $dashboard_url); ?>">
                                <i class="fas fa-user me-2"></i> Profile
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo str_replace('dashboard.php', 'settings.php', $dashboard_url); ?>">
                                <i class="fas fa-cog me-2"></i> Settings
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../login/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <div class="container mt-4">
