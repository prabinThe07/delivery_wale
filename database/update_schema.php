<?php
// Include database connection
require_once '../config/db_connect.php';

// Create necessary tables if they don't exist
try {
    // Create locations table
    $pdo->exec("CREATE TABLE IF NOT EXISTS locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        address TEXT NOT NULL,
        city VARCHAR(50) NOT NULL,
        state VARCHAR(50) NOT NULL,
        zip_code VARCHAR(20) NOT NULL,
        latitude DECIMAL(10, 8) NULL,
        longitude DECIMAL(11, 8) NULL,
        branch_id INT NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
    )");
    
    // Create products table
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        category VARCHAR(50),
        quantity INT DEFAULT 1,
        weight DECIMAL(10, 2),
        dimensions VARCHAR(50),
        location_id INT,
        branch_id INT NOT NULL,
        status ENUM('available', 'pending', 'delivered', 'cancelled') DEFAULT 'available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
    )");
    
    // Create delivery_tasks table
    $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        delivery_user_id INT NOT NULL,
        instructions TEXT,
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        status ENUM('assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'assigned',
        start_time TIMESTAMP NULL,
        completed_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (delivery_user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Create delivery_locations table to track delivery user locations
    $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        latitude DECIMAL(10, 8) NOT NULL,
        longitude DECIMAL(11, 8) NOT NULL,
        accuracy DECIMAL(10, 2),
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Create delivery_areas table to define coverage areas
    $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_areas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        branch_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
    )");
    
    // Create delivery_area_points table to define polygon points for coverage areas
    $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_area_points (
        id INT AUTO_INCREMENT PRIMARY KEY,
        area_id INT NOT NULL,
        latitude DECIMAL(10, 8) NOT NULL,
        longitude DECIMAL(11, 8) NOT NULL,
        sequence INT NOT NULL,
        FOREIGN KEY (area_id) REFERENCES delivery_areas(id) ON DELETE CASCADE
    )");
    
    // Create delivery_user_areas table to assign users to coverage areas
    $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_user_areas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        area_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (area_id) REFERENCES delivery_areas(id) ON DELETE CASCADE
    )");
    
    // Insert sample data for locations
    $pdo->exec("INSERT INTO locations (name, address, city, state, zip_code, latitude, longitude, branch_id) VALUES
        ('Downtown Office', '123 Main St, Suite 100', 'New York', 'NY', '10001', 40.7128, -74.0060, 1),
        ('Uptown Warehouse', '456 Park Ave', 'New York', 'NY', '10022', 40.7589, -73.9851, 1),
        ('Financial District', '789 Wall St', 'New York', 'NY', '10005', 40.7074, -74.0113, 1),
        ('Mission District', '123 Valencia St', 'San Francisco', 'CA', '94103', 37.7599, -122.4212, 2),
        ('SoMa Office', '456 Folsom St', 'San Francisco', 'CA', '94105', 37.7857, -122.3964, 2),
        ('Loop Office', '123 Michigan Ave', 'Chicago', 'IL', '60601', 41.8781, -87.6298, 3)
    ");
    
    // Insert sample data for products
    $pdo->exec("INSERT INTO products (name, description, category, quantity, weight, dimensions, location_id, branch_id, status) VALUES
        ('Laptop Package', 'Dell XPS 13 laptop for delivery', 'Electronics', 1, 2.5, '12x8x2', 1, 1, 'available'),
        ('Office Supplies', 'Paper, pens, and other office supplies', 'Office', 1, 5.0, '15x12x10', 2, 1, 'available'),
        ('Medical Supplies', 'Urgent medical supplies for clinic', 'Medical', 1, 3.2, '12x10x8', 3, 1, 'available'),
        ('Food Delivery', 'Catering order for office party', 'Food', 1, 8.5, '20x15x10', 4, 2, 'available'),
        ('Legal Documents', 'Confidential legal documents', 'Documents', 1, 1.0, '12x9x1', 5, 2, 'available'),
        ('Marketing Materials', 'Brochures and promotional items', 'Marketing', 1, 4.5, '18x12x6', 6, 3, 'available')
    ");
    
    echo "Database schema updated successfully!";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>
