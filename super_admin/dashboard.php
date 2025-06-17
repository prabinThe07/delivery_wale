<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has super_admin role
check_role('super_admin');

// Include database connection
require_once '../config/db_connect.php';

// Get statistics for dashboard
try {
    // Total branches
    $stmt = $pdo->query('SELECT COUNT(*) FROM branches');
    $total_branches = $stmt->fetchColumn();
    
    // Total users
    $stmt = $pdo->query('SELECT COUNT(*) FROM users');
    $total_users = $stmt->fetchColumn();
    
    // Total shipments
    $stmt = $pdo->query('SELECT COUNT(*) FROM shipments');
    $total_shipments = $stmt->fetchColumn();
    
    // Delivered shipments
    $stmt = $pdo->query('SELECT COUNT(*) FROM shipments WHERE status = "delivered"');
    $delivered_shipments = $stmt->fetchColumn();
    
    // Recent shipments
    $stmt = $pdo->query('SELECT s.*, b.name as branch_name, u.username as delivery_user 
                         FROM shipments s 
                         LEFT JOIN branches b ON s.branch_id = b.id 
                         LEFT JOIN users u ON s.delivery_user_id = u.id 
                         ORDER BY s.created_at DESC LIMIT 4');
    $recent_shipments = $stmt->fetchAll();
    
    // Recent user activity
    $stmt = $pdo->query('SELECT u.id, u.username, u.full_name, u.role, u.last_login 
                         FROM users u 
                         WHERE u.last_login IS NOT NULL 
                         ORDER BY u.last_login DESC LIMIT 4');
    $recent_users = $stmt->fetchAll();
    
    // Branch performance
    $stmt = $pdo->query('SELECT b.id, b.name, 
                         COUNT(s.id) as total_shipments,
                         SUM(CASE WHEN s.status = "delivered" THEN 1 ELSE 0 END) as delivered,
                         SUM(CASE WHEN s.status = "in_transit" THEN 1 ELSE 0 END) as in_transit,
                         SUM(CASE WHEN s.status = "pending" THEN 1 ELSE 0 END) as pending
                         FROM branches b
                         LEFT JOIN shipments s ON b.id = s.branch_id
                         GROUP BY b.id
                         ORDER BY total_shipments DESC
                         LIMIT 4');
    $branch_performance = $stmt->fetchAll();
    
} catch (PDOException $e) {
    // Handle database error
    $error_message = $e->getMessage();
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Minimalistic Sidebar with Dropdowns -->
        <div class="col-md-3 col-lg-2 d-md-block bg-white sidebar collapse shadow-sm" id="sidebarMenu">
            <div class="position-sticky pt-3">
                <div class="px-3 py-2 d-flex align-items-center">
                    <i class="fas fa-user-shield fs-4 text-primary me-2"></i>
                    <span class="fs-5 fw-semibold text-primary">Super Admin</span>
                </div>
                <hr class="my-2">
                
                <!-- Dashboard Link -->
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                    </li>
                </ul>
                
                <!-- Courier Management Dropdown -->
                <div class="sidebar-dropdown">
                    <a href="#courierSubmenu" data-bs-toggle="collapse" class="nav-link d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-box me-2"></i> Courier Management</span>
                        <i class="fas fa-chevron-down small"></i>
                    </a>
                    <ul class="collapse nav flex-column ms-3" id="courierSubmenu">
                        <li class="nav-item">
                            <a class="nav-link" href="couriers.php">
                                <i class="fas fa-list me-2"></i> View All Couriers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="search_couriers.php">
                                <i class="fas fa-search me-2"></i> Search & Filter
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="update_courier.php">
                                <i class="fas fa-edit me-2"></i> Update Status
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- Branch Management Dropdown -->
                <div class="sidebar-dropdown">
                    <a href="#branchSubmenu" data-bs-toggle="collapse" class="nav-link d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-building me-2"></i> Branch Management</span>
                        <i class="fas fa-chevron-down small"></i>
                    </a>
                    <ul class="collapse nav flex-column ms-3" id="branchSubmenu">
                        <li class="nav-item">
                            <a class="nav-link" href="add_branch.php">
                                <i class="fas fa-plus me-2"></i> Add New Branch
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="branches.php">
                                <i class="fas fa-cog me-2"></i> Manage Branches
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="branch_reports.php">
                                <i class="fas fa-chart-bar me-2"></i> Branch Reports
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- User Management Dropdown -->
                <div class="sidebar-dropdown">
                    <a href="#userSubmenu" data-bs-toggle="collapse" class="nav-link d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-users me-2"></i> User Management</span>
                        <i class="fas fa-chevron-down small"></i>
                    </a>
                    <ul class="collapse nav flex-column ms-3" id="userSubmenu">
                        <li class="nav-item">
                            <a class="nav-link" href="add_user.php">
                                <i class="fas fa-user-plus me-2"></i> Add New User
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">
                                <i class="fas fa-users-cog me-2"></i> Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reset_password.php">
                                <i class="fas fa-key me-2"></i> Reset Password
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="user_status.php">
                                <i class="fas fa-user-check me-2"></i> User Status
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- Reports & Analytics Dropdown -->
                <div class="sidebar-dropdown">
                    <a href="#reportsSubmenu" data-bs-toggle="collapse" class="nav-link d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-chart-line me-2"></i> Reports & Analytics</span>
                        <i class="fas fa-chevron-down small"></i>
                    </a>
                    <ul class="collapse nav flex-column ms-3" id="reportsSubmenu">
                        <li class="nav-item">
                            <a class="nav-link" href="courier_reports.php">
                                <i class="fas fa-calendar-alt me-2"></i> Periodic Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="status_breakdown.php">
                                <i class="fas fa-chart-pie me-2"></i> Status Breakdown
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="export_data.php">
                                <i class="fas fa-file-export me-2"></i> Export Data
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- Security & Logs Dropdown -->
                <div class="sidebar-dropdown">
                    <a href="#securitySubmenu" data-bs-toggle="collapse" class="nav-link d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-shield-alt me-2"></i> Security & Logs</span>
                        <i class="fas fa-chevron-down small"></i>
                    </a>
                    <ul class="collapse nav flex-column ms-3" id="securitySubmenu">
                        <li class="nav-item">
                            <a class="nav-link" href="login_history.php">
                                <i class="fas fa-history me-2"></i> Login History
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="session_logs.php">
                                <i class="fas fa-globe me-2"></i> Session Logs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="force_logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Force Logout
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- Settings Dropdown -->
                <div class="sidebar-dropdown">
                    <a href="#settingsSubmenu" data-bs-toggle="collapse" class="nav-link d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-cog me-2"></i> System Settings</span>
                        <i class="fas fa-chevron-down small"></i>
                    </a>
                    <ul class="collapse nav flex-column ms-3" id="settingsSubmenu">
                        <li class="nav-item">
                            <a class="nav-link" href="delivery_status.php">
                                <i class="fas fa-truck me-2"></i> Delivery Status
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="notification_settings.php">
                                <i class="fas fa-bell me-2"></i> Notifications
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="change_password.php">
                                <i class="fas fa-lock me-2"></i> Change Password
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- Feedback & Support Dropdown -->
                <div class="sidebar-dropdown">
                    <a href="#feedbackSubmenu" data-bs-toggle="collapse" class="nav-link d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-comment-alt me-2"></i> Feedback & Support</span>
                        <i class="fas fa-chevron-down small"></i>
                    </a>
                    <ul class="collapse nav flex-column ms-3" id="feedbackSubmenu">
                        <li class="nav-item">
                            <a class="nav-link" href="view_feedback.php">
                                <i class="fas fa-comments me-2"></i> View Feedback
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="support_messages.php">
                                <i class="fas fa-tools me-2"></i> Support Notes
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- Logout -->
                <ul class="nav flex-column mt-3">
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="../login/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <!-- Top navbar for mobile toggle -->
            <nav class="navbar navbar-light bg-white d-md-none border-bottom mb-3">
                <div class="container-fluid">
                    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <span class="navbar-brand mb-0 h1">Super Admin</span>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="../login/logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </nav>
            
            <!-- Dashboard header -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                <h1 class="h2 fw-light">Dashboard Overview</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-download me-1"></i> Export
                        </button>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-1">
                        <i class="fas fa-calendar-alt"></i>
                        This week
                    </button>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                                <i class="fas fa-building text-primary"></i>
                            </div>
                            <div>
                                <h6 class="card-title text-muted mb-0">Branches</h6>
                                <h2 class="mt-2 mb-0"><?php echo $total_branches ?? 0; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                                <i class="fas fa-users text-success"></i>
                            </div>
                            <div>
                                <h6 class="card-title text-muted mb-0">Users</h6>
                                <h2 class="mt-2 mb-0"><?php echo $total_users ?? 0; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3">
                                <i class="fas fa-box text-info"></i>
                            </div>
                            <div>
                                <h6 class="card-title text-muted mb-0">Shipments</h6>
                                <h2 class="mt-2 mb-0"><?php echo $total_shipments ?? 0; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                                <i class="fas fa-check-circle text-warning"></i>
                            </div>
                            <div>
                                <h6 class="card-title text-muted mb-0">Delivered</h6>
                                <h2 class="mt-2 mb-0"><?php echo $delivered_shipments ?? 0; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- User Management Quick Access -->
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="card-title mb-0">User Management</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <a href="add_user.php" class="card border-0 shadow-sm h-100 text-decoration-none">
                                        <div class="card-body text-center">
                                            <div class="rounded-circle bg-primary bg-opacity-10 p-3 mx-auto mb-3" style="width: fit-content;">
                                                <i class="fas fa-user-plus text-primary fa-2x"></i>
                                            </div>
                                            <h5 class="card-title">Add New User</h5>
                                            <p class="card-text text-muted small">Create new user accounts with role-based access</p>
                                        </div>
                                    </a>
                                </div>
                                
                                <div class="col-md-3">
                                    <a href="manage_users.php" class="card border-0 shadow-sm h-100 text-decoration-none">
                                        <div class="card-body text-center">
                                            <div class="rounded-circle bg-success bg-opacity-10 p-3 mx-auto mb-3" style="width: fit-content;">
                                                <i class="fas fa-users-cog text-success fa-2x"></i>
                                            </div>
                                            <h5 class="card-title">Manage Users</h5>
                                            <p class="card-text text-muted small">View, edit, and manage existing user accounts</p>
                                        </div>
                                    </a>
                                </div>
                                
                                <div class="col-md-3">
                                    <a href="reset_password.php" class="card border-0 shadow-sm h-100 text-decoration-none">
                                        <div class="card-body text-center">
                                            <div class="rounded-circle bg-warning bg-opacity-10 p-3 mx-auto mb-3" style="width: fit-content;">
                                                <i class="fas fa-key text-warning fa-2x"></i>
                                            </div>
                                            <h5 class="card-title">Reset Password</h5>
                                            <p class="card-text text-muted small">Reset passwords for user accounts</p>
                                        </div>
                                    </a>
                                </div>
                                
                                <div class="col-md-3">
                                    <a href="user_status.php" class="card border-0 shadow-sm h-100 text-decoration-none">
                                        <div class="card-body text-center">
                                            <div class="rounded-circle bg-info bg-opacity-10 p-3 mx-auto mb-3" style="width: fit-content;">
                                                <i class="fas fa-user-check text-info fa-2x"></i>
                                            </div>
                                            <h5 class="card-title">User Status</h5>
                                            <p class="card-text text-muted small">Activate or deactivate user accounts</p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="row g-3 mb-4">
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="card-title mb-0">Monthly Shipment Statistics</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="shipmentChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="card-title mb-0">Shipment Status</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="statusChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity Row -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Recent Shipments</h5>
                            <a href="couriers.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tracking #</th>
                                            <th>Branch</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (isset($recent_shipments) && count($recent_shipments) > 0): ?>
                                            <?php foreach ($recent_shipments as $shipment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($shipment['tracking_number']); ?></td>
                                                <td><?php echo htmlspecialchars($shipment['branch_name']); ?></td>
                                                <td>
                                                    <?php 
                                                    $status_class = '';
                                                    switch ($shipment['status']) {
                                                        case 'pending':
                                                            $status_class = 'bg-warning';
                                                            break;
                                                        case 'in_transit':
                                                            $status_class = 'bg-info';
                                                            break;
                                                        case 'delivered':
                                                            $status_class = 'bg-success';
                                                            break;
                                                        case 'cancelled':
                                                            $status_class = 'bg-danger';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $shipment['status'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($shipment['created_at'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-4">No recent shipments found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">User Activity</h5>
                            <a href="login_history.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php if (isset($recent_users) && count($recent_users) > 0): ?>
                                    <?php foreach ($recent_users as $user): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-3">
                                        <div>
                                            <p class="mb-0 fw-medium"><?php echo htmlspecialchars($user['full_name']); ?> (<?php echo ucfirst($user['role']); ?>)</p>
                                            <p class="mb-0 small text-muted">Logged in at <?php echo date('M d, Y H:i', strtotime($user['last_login'])); ?></p>
                                        </div>
                                        <a href="manage_users.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="list-group-item text-center py-4">No recent user activity</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Branch Performance -->
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Branch Performance</h5>
                            <a href="branch_reports.php" class="btn btn-sm btn-primary">Detailed Report</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Branch</th>
                                            <th>Total</th>
                                            <th>Delivered</th>
                                            <th>In Transit</th>
                                            <th>Pending</th>
                                            <th>Performance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (isset($branch_performance) && count($branch_performance) > 0): ?>
                                            <?php foreach ($branch_performance as $branch): ?>
                                            <?php 
                                                $total = $branch['total_shipments'] > 0 ? $branch['total_shipments'] : 1;
                                                $performance = round(($branch['delivered'] / $total) * 100);
                                                
                                                $performance_class = 'bg-success';
                                                if ($performance < 70) {
                                                    $performance_class = 'bg-warning';
                                                } elseif ($performance < 50) {
                                                    $performance_class = 'bg-danger';
                                                }
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($branch['name']); ?></td>
                                                <td><?php echo $branch['total_shipments']; ?></td>
                                                <td><?php echo $branch['delivered']; ?></td>
                                                <td><?php echo $branch['in_transit']; ?></td>
                                                <td><?php echo $branch['pending']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress flex-grow-1 me-2" style="height: 5px;">
                                                            <div class="progress-bar <?php echo $performance_class; ?>" role="progressbar" style="width: <?php echo $performance; ?>%;" aria-valuenow="<?php echo $performance; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                        </div>
                                                        <span class="small"><?php echo $performance; ?>%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4">No branch performance data available</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Shipment Chart
    const shipmentCtx = document.getElementById('shipmentChart').getContext('2d');
    const shipmentChart = new Chart(shipmentCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Total Shipments',
                data: [150, 210, 180, 250, 220, 300],
                borderColor: '#4dabf7',
                backgroundColor: 'rgba(77, 171, 247, 0.1)',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        display: true,
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    
    // Status Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Delivered', 'In Transit', 'Pending', 'Cancelled'],
            datasets: [{
                data: [65, 20, 10, 5],
                backgroundColor: [
                    '#28a745',
                    '#17a2b8',
                    '#ffc107',
                    '#dc3545'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 15
                    }
                }
            },
            cutout: '70%'
        }
    });
</script>

<!-- Custom CSS for sidebar dropdowns -->
<style>
    /* Sidebar styling */
    .sidebar {
        min-height: 100vh;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    
    .sidebar .nav-link {
        color: #6c757d;
        padding: 0.5rem 1rem;
        border-radius: 0.25rem;
        margin-bottom: 0.25rem;
        transition: all 0.2s;
    }
    
    .sidebar .nav-link:hover {
        color: #4dabf7;
        background-color: rgba(77, 171, 247, 0.05);
    }
    
    .sidebar .nav-link.active {
        color: #4dabf7;
        background-color: rgba(77, 171, 247, 0.1);
    }
    
    /* Dropdown styling */
    .sidebar-dropdown {
        margin-bottom: 0.25rem;
    }
    
    .sidebar-dropdown > a {
        color: #6c757d;
        padding: 0.5rem 1rem;
        border-radius: 0.25rem;
        transition: all 0.2s;
    }
    
    .sidebar-dropdown > a:hover {
        color: #4dabf7;
        background-color: rgba(77, 171, 247, 0.05);
        text-decoration: none;
    }
    
    .sidebar-dropdown .collapse, 
    .sidebar-dropdown .collapsing {
        margin-top: 0.25rem;
    }
    
    .sidebar-dropdown .collapse .nav-link, 
    .sidebar-dropdown .collapsing .nav-link {
        padding-left: 0.75rem;
        font-size: 0.9rem;
    }
    
    /* Card styling */
    .card {
        border-radius: 0.5rem;
        transition: transform 0.2s;
    }
    
    .card:hover {
        transform: translateY(-2px);
    }
    
    .card-header {
        border-bottom: none;
        background-color: transparent;
        padding: 1.25rem 1.25rem 0.75rem;
    }
    
    .table th {
        font-weight: 500;
    }
    
    /* Progress bar styling */
    .progress {
        background-color: #f0f0f0;
        border-radius: 1rem;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
        }
    }
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>
