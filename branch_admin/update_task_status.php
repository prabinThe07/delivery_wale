<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has branch_admin role
check_role('branch_admin');

// Include database connection
require_once '../config/db_connect.php';

// Get branch ID from session
$branch_id = $_SESSION['branch_id'];

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['update_task']) || !isset($_POST['task
