<?php
include '../includes/session_check.php';
include '../config/db_connect.php';

// Check if user is a branch admin
if ($_SESSION['user_type'] !== 'branch_admin') {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $delivery_user_id = $_POST['delivery_user_id'];
    $product_id = !empty($_POST['product_id']) ? $_POST['product_id'] : null;
    $location_id = $_POST['location_id'];
    $shipment_id = !empty($_POST['shipment_id']) ? $_POST['shipment_id'] : null;
    $priority = $_POST['priority'];
    $scheduled_date = $_POST['scheduled_date'];
    $instructions = $_POST['instructions'];
    $branch_id = $_SESSION['branch_id'];
    $status = 'pending';
    $created_by = $_SESSION['user_id'];
    
    // Validate that at least one of product_id or shipment_id is provided
    if (empty($product_id) && empty($shipment_id)) {
        $_SESSION['error'] = "You must select either a product or a shipment for the task.";
        header('Location: task_dashboard.php');
        exit();
    }
    
    // Check if delivery user exists and belongs to the branch
    $user_check_query = "SELECT id FROM users WHERE id = ? AND branch_id = ? AND user_type = 'delivery_user'";
    $stmt = $conn->prepare($user_check_query);
    $stmt->bind_param("ii", $delivery_user_id, $branch_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    
    if ($user_result->num_rows === 0) {
        $_SESSION['error'] = "Invalid delivery user selected.";
        header('Location: task_dashboard.php');
        exit();
    }
    $stmt->close();
    
    // Check if location exists and belongs to the branch
    $location_check_query = "SELECT id FROM locations WHERE id = ? AND branch_id = ?";
    $stmt = $conn->prepare($location_check_query);
    $stmt->bind_param("ii", $location_id, $branch_id);
    $stmt->execute();
    $location_result = $stmt->get_result();
    
    if ($location_result->num_rows === 0) {
        $_SESSION['error'] = "Invalid location selected.";
        header('Location: task_dashboard.php');
        exit();
    }
    $stmt->close();
    
    // If product_id is provided, check if it exists and belongs to the branch
    if (!empty($product_id)) {
        $product_check_query = "SELECT id FROM products WHERE id = ? AND branch_id = ?";
        $stmt = $conn->prepare($product_check_query);
        $stmt->bind_param("ii", $product_id, $branch_id);
        $stmt->execute();
        $product_result = $stmt->get_result();
        
        if ($product_result->num_rows === 0) {
            $_SESSION['error'] = "Invalid product selected.";
            header('Location: task_dashboard.php');
            exit();
        }
        $stmt->close();
    }
    
    // If shipment_id is provided, check if it exists and belongs to the branch
    if (!empty($shipment_id)) {
        $shipment_check_query = "SELECT id FROM shipments WHERE id = ? AND branch_id = ?";
        $stmt = $conn->prepare($shipment_check_query);
        $stmt->bind_param("ii", $shipment_id, $branch_id);
        $stmt->execute();
        $shipment_result = $stmt->get_result();
        
        if ($shipment_result->num_rows === 0) {
            $_SESSION['error'] = "Invalid shipment selected.";
            header('Location: task_dashboard.php');
            exit();
        }
        $stmt->close();
    }
    
    // Insert the task into the database
    $insert_query = "INSERT INTO delivery_tasks (delivery_user_id, product_id, location_id, shipment_id, priority, status, scheduled_date, instructions, branch_id, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("iiiiissiii", $delivery_user_id, $product_id, $location_id, $shipment_id, $priority, $status, $scheduled_date, $instructions, $branch_id, $created_by);
    
    if ($stmt->execute()) {
        $task_id = $stmt->insert_id;
        
        // If this task is for a shipment, update the shipment status
        if (!empty($shipment_id)) {
            $update_shipment_query = "UPDATE shipments SET status = 'assigned', updated_at = NOW() WHERE id = ?";
            $stmt_shipment = $conn->prepare($update_shipment_query);
            $stmt_shipment->bind_param("i", $shipment_id);
            $stmt_shipment->execute();
            $stmt_shipment->close();
        }
        
        // Create a notification for the delivery user
        $notification_query = "INSERT INTO notifications (user_id, message, type, reference_id, created_at) 
                              VALUES (?, 'You have been assigned a new delivery task', 'task_assignment', ?, NOW())";
        $stmt_notification = $conn->prepare($notification_query);
        $stmt_notification->bind_param("ii", $delivery_user_id, $task_id);
        $stmt_notification->execute();
        $stmt_notification->close();
        
        $_SESSION['success'] = "Task assigned successfully.";
    } else {
        $_SESSION['error'] = "Error assigning task: " . $stmt->error;
    }
    
    $stmt->close();
    header('Location: task_dashboard.php');
    exit();
} else {
    header('Location: task_dashboard.php');
    exit();
}
?>
