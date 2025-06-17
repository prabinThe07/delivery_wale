<?php
// Start session
session_start();

// Clear remember me cookie if exists
if (isset($_COOKIE['remember_token'])) {
    // Delete the cookie
    setcookie('remember_token', '', time() - 3600, '/');
}

// Store the username before clearing session if we want to keep it
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Set success message for next login
session_start();
$_SESSION['login_success'] = 'You have been successfully logged out.';

// Redirect to login page
header('Location: index.php');
exit;
?>
