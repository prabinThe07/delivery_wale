<?php
include '../includes/session_check.php';
include '../config/db_connect.php';

// Check if user is a branch admin
if ($_SESSION['user_type'] !== 'branch_admin') {
    header('Location: ../index.php');
    exit();
}

$branch_id = $_SESSION['branch_id'];

// Check if task ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "No task ID provided.";
    header('Location: task_dashboard.php');
    exit();
}

$task_id = $_GET['id'];

// Get task details
$task_query = "SELECT * FROM delivery_tasks WHERE id = ? AND branch_id = ?";
$stmt = $conn->prepare($task_query);
$stmt->bind_param("ii", $task_id, $branch_id);
$stmt->execute();
$task_result = $stmt->get_result();

if ($task_result->num_rows === 0) {
    $_SESSION['error'] = "Task not found or you don't have permission to edit it.";
    header('Location: task_dashboard.php');
    exit();
}

$task = $task_result->fetch_assoc();
$stmt->close();

// Get all delivery users for this branch
$delivery_users_query = "SELECT id, username, full_name FROM users WHERE user_type = 'delivery_user' AND branch_id = ?";
$stmt = $conn->prepare($delivery_users_query);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$delivery_users_result = $stmt->get_result();
$delivery_users = [];
while ($user = $delivery_users_result->fetch_assoc()) {
    $delivery_users[] = $user;
}
$stmt->close();

// Get all products for this branch
$products_query = "SELECT id, name FROM products WHERE branch_id = ?";
$stmt = $conn->prepare($products_query);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$products_result = $stmt->get_result();
$products = [];
while ($product = $products_result->fetch_assoc()) {
    $products[] = $product;
}
$stmt->close();

// Get all locations for this branch
$locations_query = "SELECT id, name FROM locations WHERE branch_id = ?";
$stmt = $conn->prepare($locations_query);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$locations_result = $stmt->get_result();
$locations = [];
while ($location = $locations_result->fetch_assoc()) {
    $locations[] = $location;
}
$stmt->close();

// Get all shipments for this branch
$shipments_query = "SELECT id, tracking_number FROM shipments WHERE branch_id = ? AND (status != 'delivered' OR id = ?)";
$stmt = $conn->prepare($shipments_query);
$stmt->bind_param("ii", $branch_id, $task['shipment_id']);
$stmt->execute();
$shipments_result = $stmt->get_result();
$shipments = [];
while ($shipment = $shipments_result->fetch_assoc()) {
    $shipments[] = $shipment;
}
$stmt->close();

include '../includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mt-4">Edit Delivery Task</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="task_dashboard.php">Task Management</a></li>
        <li class="breadcrumb-item active">Edit Task</li>
    </ol>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-edit mr-1"></i>
            Edit Task #<?php echo $task_id; ?>
        </div>
        <div class="card-body">
            <form action="update_task.php" method="post">
                <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="delivery_user_id">Assign To Delivery User</label>
                            <select class="form-control" id="delivery_user_id" name="delivery_user_id" required>
                                <option value="">Select Delivery User</option>
                                <?php foreach ($delivery_users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php if ($user['id'] == $task['delivery_user_id']) echo 'selected'; ?>>
                                        <?php echo $user['full_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="product_id">Product</label>
                            <select class="form-control" id="product_id" name="product_id">
                                <option value="">Select Product (Optional)</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>" <?php if ($product['id'] == $task['product_id']) echo 'selected'; ?>>
                                        <?php echo $product['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="location_id">Delivery Location</label>
                            <select class="form-control" id="location_id" name="location_id" required>
                                <option value="">Select Location</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['id']; ?>" <?php if ($location['id'] == $task['location_id']) echo 'selected'; ?>>
                                        <?php echo $location['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select
