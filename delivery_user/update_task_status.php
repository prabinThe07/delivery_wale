<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has delivery_user role
check_role('delivery_user');

// Include database connection
require_once '../config/db_connect.php';

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Check if task ID and status are provided
if (!isset($_POST['task_id']) || !isset($_POST['status'])) {
    $_SESSION['error_message'] = "Missing required parameters.";
    header('Location: dashboard.php');
    exit;
}

$task_id = (int)$_POST['task_id'];
$status = $_POST['status'];

// Validate status
$valid_statuses = ['in_progress', 'completed', 'cancelled'];
if (!in_array($status, $valid_statuses)) {
    $_SESSION['error_message'] = "Invalid status.";
    header('Location: dashboard.php');
    exit;
}

try {
    // Check if task belongs to this user
    $stmt = $pdo->prepare('SELECT t.*, p.id as product_id, p.status as product_status 
                          FROM delivery_tasks t 
                          JOIN products p ON t.product_id = p.id 
                          WHERE t.id = ? AND t.delivery_user_id = ?');
    $stmt->execute([$task_id, $user_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        $_SESSION['error_message'] = "Task not found or you don't have permission to update it.";
        header('Location: dashboard.php');
        exit;
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Update task status
    $stmt = $pdo->prepare('UPDATE delivery_tasks SET status = ?, updated_at = NOW()');
    
    // If status is completed, set completed_at
    if ($status === 'completed') {
        $stmt = $pdo->prepare('UPDATE delivery_tasks SET status = ?, completed_at = NOW(), updated_at = NOW() WHERE id = ?');
        
        // Also update product status
        $stmt2 = $pdo->prepare('UPDATE products SET status = "delivered", updated_at = NOW() WHERE id = ?');
        $stmt2->execute([$task['product_id']]);
    } else {
        $stmt = $pdo->prepare('UPDATE delivery_tasks SET status = ?, updated_at = NOW() WHERE id = ?');
    }
    
    $stmt->execute([$status, $task_id]);
    
    // Commit transaction
    $pdo->commit();
    
    $_SESSION['success_message'] = "Task status updated successfully.";
    header('Location: dashboard.php');
    exit;
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header('Location: dashboard.php');
    exit;
}
?>
