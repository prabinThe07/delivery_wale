<?php
// Database configuration
$host = 'localhost';
$dbname = 'courier_system';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

// DSN (Data Source Name)
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// PDO options for error handling and fetching
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    // Create a PDO instance
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // If connection fails, don't throw an exception, just set $pdo to null
    // This allows the application to continue even if the database is not available
    $pdo = null;
    // You might want to log this error in a production environment
    // error_log("Database connection failed: " . $e->getMessage());
}
?>
